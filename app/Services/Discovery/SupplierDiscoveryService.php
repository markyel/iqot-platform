<?php

namespace App\Services\Discovery;

use App\Models\ApplicationDomain;
use App\Models\ProductType;
use App\Services\Api\OpenAIClassifierClient;
use App\Services\Api\SupplierCoverageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Полный pipeline поиска новых поставщиков для пары (domain, product_type).
 * Спека §7.2–7.3.
 *
 * Итерация §7.3:
 *   1. Генерируем поисковые запросы AI (подача стратегии итерации).
 *   2. Multi-search через YandexSearchClient.
 *   3. Fetch страниц + strip HTML → текст.
 *   4. AI-экстрактор контактов (name/email/phone/website).
 *   5. AI-валидатор: действительно ли это B2B-поставщик данного pt?
 *   6. INSERT reports.suppliers + supplier_product_types + supplier_domains.
 *
 * Возвращает число найденных и сохранённых поставщиков.
 */
class SupplierDiscoveryService
{
    public const MAX_ITERATIONS = 5;
    public const MAX_PAGES_PER_ITERATION = 15;

    public function __construct(
        private readonly YandexSearchClient $search,
        private readonly SupplierPageParser $parser,
        private readonly OpenAIClassifierClient $ai,
        private readonly SupplierCoverageService $coverage,
    ) {
    }

    /**
     * Выполнить одну итерацию discovery.
     *
     * @return array{new_suppliers:int, urls_scanned:int, queries:array<string>}
     */
    public function runIteration(
        int $iteration,
        int $productTypeId,
        ?int $domainId,
    ): array {
        $productType = ProductType::find($productTypeId);
        if (!$productType) {
            throw new \RuntimeException('product_type not found: ' . $productTypeId);
        }
        $domain = $domainId ? ApplicationDomain::find($domainId) : null;

        // 1. Генерация запросов
        $queries = $this->generateQueries($iteration, $productType, $domain);
        if (empty($queries)) {
            return ['new_suppliers' => 0, 'urls_scanned' => 0, 'queries' => []];
        }

        // 2. Yandex multi-search
        $results = $this->search->multiSearch($queries);
        if (empty($results)) {
            return ['new_suppliers' => 0, 'urls_scanned' => 0, 'queries' => $queries];
        }

        // Сортируем по приоритету: уникальные домены вперёд; ограничиваем MAX_PAGES.
        $byDomain = [];
        foreach ($results as $r) {
            $d = $r['domain'] ?: parse_url($r['url'], PHP_URL_HOST);
            if ($d && !isset($byDomain[$d])) {
                $byDomain[$d] = $r;
            }
        }
        $results = array_slice(array_values($byDomain), 0, self::MAX_PAGES_PER_ITERATION);

        // Построим индекс существующих поставщиков по домену (website + email host).
        $existingByDomain = $this->loadExistingSupplierMap(
            array_map(fn ($r) => $r['domain'] ?: parse_url($r['url'], PHP_URL_HOST), $results)
        );

        $scanned = 0;
        $saved = 0;
        $extended = 0;

        foreach ($results as $r) {
            $siteDomain = $r['domain'] ?: parse_url($r['url'], PHP_URL_HOST);
            $normDomain = $siteDomain ? mb_strtolower(preg_replace('/^www\./i', '', (string) $siteDomain)) : null;
            $existingSupplierId = $normDomain ? ($existingByDomain[$normDomain] ?? null) : null;

            // 3. Fetch страницы (с извлечением ссылок-кандидатов на «Контакты»).
            $fetched = $this->parser->fetchWithContactLinks($r['url']);
            if ($fetched === null || mb_strlen($fetched['text']) < 200) {
                $scanned++;
                continue;
            }
            $pageText = $fetched['text'];
            $contactLinks = $fetched['contact_links'];

            // 4+5. AI — экстрактор + валидатор в одном промпте.
            try {
                $info = $this->extractAndValidate($productType, $domain, $r, $pageText);
            } catch (\Throwable $e) {
                Log::warning('SupplierDiscovery: ai extract failed', ['url' => $r['url'], 'error' => $e->getMessage()]);
                $scanned++;
                continue;
            }
            $scanned++;

            if (!$info['is_supplier'] || !$info['confidence'] || $info['confidence'] < 0.6) {
                continue;
            }

            // Если email не найден и есть ссылки на страницу контактов — догружаем и переспрашиваем.
            if (empty($info['email']) && !empty($contactLinks)) {
                $info = $this->enrichWithContactPage($productType, $domain, $r, $pageText, $contactLinks, $info);
            }

            if (empty($info['email']) && empty($info['phone'])) {
                continue;
            }

            // Дополнительная попытка матча по email/phone если по домену не нашли.
            if ($existingSupplierId === null) {
                $existingSupplierId = $this->findExistingSupplierByContacts($info['email'], $info['phone']);
            }

            if ($existingSupplierId !== null) {
                // Существующий поставщик — расширяем scope, не создаём дубль.
                if ($this->extendExistingSupplier($existingSupplierId, $info, $productTypeId, $domainId)) {
                    $extended++;
                }
                continue;
            }

            // 6. INSERT нового supplier + pivot.
            $this->persistSupplier($info, $productTypeId, $domainId, $siteDomain, $r['url']);
            $saved++;
        }

        Log::info('SupplierDiscovery: iteration done', [
            'iteration' => $iteration,
            'product_type_id' => $productTypeId,
            'domain_id' => $domainId,
            'urls_scanned' => $scanned,
            'new_suppliers' => $saved,
            'existing_extended' => $extended,
        ]);

        return [
            'new_suppliers' => $saved,
            'urls_scanned' => $scanned,
            'queries' => $queries,
            'existing_extended' => $extended,
        ];
    }

    /**
     * Полный цикл до MAX_ITERATIONS или достижения coverage.
     *
     * @return array{total_new:int, iterations_used:int, status:'success_covered'|'success_partial'|'exhausted'}
     */
    public function runFullDiscovery(int $productTypeId, ?int $domainId): array
    {
        $totalNew = 0;
        $iterationsUsed = 0;

        for ($i = 1; $i <= self::MAX_ITERATIONS; $i++) {
            $iterationsUsed = $i;

            $summary = $this->runIteration($i, $productTypeId, $domainId);
            $totalNew += $summary['new_suppliers'];

            // Проверка coverage — если достигли threshold, выходим.
            $cov = $this->coverage->checkCoverage($domainId, $productTypeId);
            if ($cov['is_sufficient']) {
                return ['total_new' => $totalNew, 'iterations_used' => $iterationsUsed, 'status' => 'success_covered'];
            }
        }

        return [
            'total_new' => $totalNew,
            'iterations_used' => $iterationsUsed,
            'status' => $totalNew > 0 ? 'success_partial' : 'exhausted',
        ];
    }

    /**
     * Генерация поисковых запросов через AI. Стратегия зависит от номера итерации (§7.3).
     *
     * @return array<string>
     */
    private function generateQueries(int $iteration, ProductType $productType, ?ApplicationDomain $domain): array
    {
        if (!$this->ai->isConfigured()) {
            // Бэкап — простые keyword-запросы без AI.
            $core = $productType->name;
            if ($domain) {
                $core .= ' ' . $domain->name;
            }
            return [
                'поставщик ' . $core . ' оптом',
                'производитель ' . $core . ' Россия',
                $core . ' купить B2B',
            ];
        }

        $strategies = [
            1 => 'Прямой поиск: keywords product_type + domain + "поставщик/производитель/оптом". Локализация — Россия/СНГ. Язык русский.',
            2 => 'Расширение: синонимы product_type, смежные термины. Узкие технические названия, сокращения. Добавь английские варианты брендов.',
            3 => 'Конкуренты: найди каталоги и маркетплейсы где перечислены поставщики этой категории. "лучшие поставщики", "рейтинг", "топ".',
            4 => 'Бренды: крупные производители этого product_type + "дилер", "дистрибутор", "сервисный центр".',
            5 => 'Отраслевые каталоги: B2B-порталы, Pulscen, ТИУ, Allbiz, exportersindia — по этому сегменту.',
        ];
        $strategy = $strategies[$iteration] ?? $strategies[1];

        $systemPrompt = 'Ты — специалист по закупкам. Сгенерируй 6–10 поисковых запросов '
            . 'для Яндекса чтобы найти B2B-поставщиков указанного product_type в указанной области применения. '
            . 'Стратегия итерации: ' . $strategy . ' '
            . 'Отвечай строго JSON: {"queries": ["...", "..."]}. '
            . 'Запросы — короткие (3-7 слов), без кавычек, без спец-операторов. Русский язык.';

        $userPrompt = "product_type: {$productType->name} (slug={$productType->slug})\n";
        if ($domain) {
            $userPrompt .= "domain: {$domain->name} (slug={$domain->slug})\n";
        } else {
            $userPrompt .= "domain: универсальный (любая отрасль)\n";
        }
        if (!empty($productType->keywords)) {
            $keywords = is_array($productType->keywords) ? $productType->keywords : json_decode($productType->keywords, true);
            if (is_array($keywords)) {
                $userPrompt .= 'keywords: ' . implode(', ', array_slice($keywords, 0, 10)) . "\n";
            }
        }

        try {
            $result = $this->ai->jsonCompletion($this->ai->modelMini(), $systemPrompt, $userPrompt, 500);
            $queries = $result['queries'] ?? [];
            return is_array($queries) ? array_slice(array_filter(array_map('strval', $queries)), 0, 10) : [];
        } catch (\Throwable $e) {
            Log::warning('SupplierDiscovery: queries gen failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * AI-промпт: одновременно экстракт контактов + валидация того что это B2B-поставщик.
     *
     * @return array{is_supplier:bool, confidence:float, name:string|null, email:string|null, phone:string|null, website:string|null, categories:array<string>, reason:string}
     */
    private function extractAndValidate(ProductType $pt, ?ApplicationDomain $domain, array $searchResult, string $pageText): array
    {
        $systemPrompt = 'Ты — извлекатель контактов и валидатор поставщиков. '
            . 'Тебе дан текст web-страницы компании. '
            . 'Определи: '
            . '(1) является ли эта компания B2B-поставщиком товара/оборудования данной категории (не интернет-магазин для физлиц, не новостной сайт, не агрегатор); '
            . '(2) извлеки контакты: название компании, основной email (общий), основной телефон, website (главный домен). '
            . 'Отвечай строго JSON: '
            . '{"is_supplier": bool, "confidence": float (0..1), "name": string|null, "email": string|null, "phone": string|null, "website": string|null, "categories": string[], "reason": string}. '
            . 'Если нет уверенности — is_supplier=false. Если контакты не нашлись — null. '
            . 'categories — список 1-5 категорий товаров которыми занимается компания (в свободной форме).';

        $contextHint = "product_type который интересует: {$pt->name}\n";
        if ($domain) {
            $contextHint .= "область: {$domain->name}\n";
        }

        $userPrompt = $contextHint
            . "\nURL: {$searchResult['url']}\n"
            . "Заголовок: {$searchResult['title']}\n"
            . "Snippet: {$searchResult['content']}\n\n"
            . "Текст страницы (обрезан):\n" . $pageText;

        $result = $this->ai->jsonCompletion($this->ai->modelMini(), $systemPrompt, $userPrompt, 700);

        return [
            'is_supplier' => (bool) ($result['is_supplier'] ?? false),
            'confidence' => (float) ($result['confidence'] ?? 0),
            'name' => $this->s($result['name'] ?? null, 255),
            'email' => $this->validEmail($result['email'] ?? null),
            'phone' => $this->s($result['phone'] ?? null, 50),
            'website' => $this->s($result['website'] ?? null, 255),
            'categories' => is_array($result['categories'] ?? null) ? array_slice($result['categories'], 0, 5) : [],
            'reason' => $this->s($result['reason'] ?? null, 500) ?? '',
        ];
    }

    /**
     * Если первая попытка извлечения не дала email — тянем страницы-кандидаты
     * «Контакты» (до 2), склеиваем текст с главной и повторяем AI-экстракцию.
     * Возвращает обновлённый $info (email/phone/website могут быть дополнены).
     *
     * @param array<string> $contactLinks
     */
    private function enrichWithContactPage(
        ProductType $pt,
        ?ApplicationDomain $domain,
        array $searchResult,
        string $mainText,
        array $contactLinks,
        array $info,
    ): array {
        $extraTexts = [];
        foreach (array_slice($contactLinks, 0, 2) as $link) {
            $txt = $this->parser->fetch($link);
            if ($txt !== null && mb_strlen($txt) >= 80) {
                $extraTexts[] = "=== Страница: {$link} ===\n" . $txt;
            }
        }
        if (empty($extraTexts)) {
            return $info;
        }

        // Ограничим объём, чтобы промпт не раздулся: 6000 главная + до 5000 контакты.
        $mainTrimmed = mb_substr($mainText, 0, 6000, 'UTF-8');
        $contactsJoined = mb_substr(implode("\n\n", $extraTexts), 0, 5000, 'UTF-8');
        $combined = $mainTrimmed . "\n\n" . $contactsJoined;

        try {
            $enriched = $this->extractAndValidate($pt, $domain, $searchResult, $combined);
        } catch (\Throwable $e) {
            Log::info('SupplierDiscovery: contact page extract failed', [
                'url' => $searchResult['url'],
                'error' => $e->getMessage(),
            ]);
            return $info;
        }

        // Обновим только пустые поля — не перезатираем то что уже нашли.
        $merged = $info;
        foreach (['email', 'phone', 'website', 'name'] as $f) {
            if (empty($merged[$f]) && !empty($enriched[$f])) {
                $merged[$f] = $enriched[$f];
            }
        }
        // confidence не уменьшаем, is_supplier сохраняем true.
        if (!empty($enriched['confidence']) && $enriched['confidence'] > ($merged['confidence'] ?? 0)) {
            $merged['confidence'] = $enriched['confidence'];
        }
        if (!empty($enriched['categories']) && empty($merged['categories'])) {
            $merged['categories'] = $enriched['categories'];
        }
        if (!empty($merged['email'])) {
            Log::info('SupplierDiscovery: email found on contact page', [
                'base_url' => $searchResult['url'],
                'contact_links' => array_slice($contactLinks, 0, 2),
                'email' => $merged['email'],
            ]);
        }
        return $merged;
    }

    /**
     * Map существующих поставщиков: normalized_domain → supplier_id.
     *
     * @param array<string> $candidateDomains
     * @return array<string, int>
     */
    private function loadExistingSupplierMap(array $candidateDomains): array
    {
        $normalized = [];
        foreach ($candidateDomains as $d) {
            if (!$d) {
                continue;
            }
            $d = mb_strtolower(preg_replace('/^www\./i', '', (string) $d));
            if ($d) {
                $normalized[$d] = true;
            }
        }
        if (empty($normalized)) {
            return [];
        }

        $existing = DB::connection('reports')->table('suppliers')
            ->where(function ($q) use ($normalized) {
                foreach (array_keys($normalized) as $d) {
                    $q->orWhere('website', 'LIKE', '%' . $d . '%');
                    $q->orWhere('email', 'LIKE', '%@' . $d);
                }
            })
            ->select('id', 'website', 'email')
            ->get();

        $map = [];
        foreach ($existing as $row) {
            if ($row->website) {
                $host = parse_url($row->website, PHP_URL_HOST) ?: $row->website;
                $key = mb_strtolower(preg_replace('/^www\./i', '', (string) $host));
                if ($key) {
                    $map[$key] = (int) $row->id;
                }
            }
            if ($row->email && preg_match('/@([\w.-]+)$/', (string) $row->email, $m)) {
                $map[mb_strtolower($m[1])] = (int) $row->id;
            }
        }
        return $map;
    }

    /**
     * Попытка найти поставщика по контактам (email целиком или нормализованному phone).
     */
    private function findExistingSupplierByContacts(?string $email, ?string $phone): ?int
    {
        $q = DB::connection('reports')->table('suppliers');
        $applied = false;

        if ($email) {
            $q->where('email', mb_strtolower($email));
            $applied = true;
        } elseif ($phone) {
            $digits = preg_replace('/\D+/', '', $phone);
            if ($digits && mb_strlen($digits) >= 10) {
                // Берём последние 10 цифр (российские номера).
                $tail = mb_substr($digits, -10);
                $q->where(DB::raw("REGEXP_REPLACE(COALESCE(phone, ''), '[^0-9]', '')"), 'LIKE', '%' . $tail);
                $applied = true;
            }
        }

        if (!$applied) {
            return null;
        }
        $row = $q->select('id')->first();
        return $row ? (int) $row->id : null;
    }

    /**
     * Добавляет связи supplier_product_types и supplier_domains для существующего
     * поставщика. Возвращает true если хотя бы одна связь была добавлена/обновлена.
     */
    private function extendExistingSupplier(int $supplierId, array $info, int $productTypeId, ?int $domainId): bool
    {
        $now = now();
        $changed = false;

        // supplier_product_types (supplier_id + product_type_id — composite PK).
        $hasPt = DB::connection('reports')->table('supplier_product_types')
            ->where('supplier_id', $supplierId)
            ->where('product_type_id', $productTypeId)
            ->exists();
        if (!$hasPt) {
            DB::connection('reports')->table('supplier_product_types')->insert([
                'supplier_id' => $supplierId,
                'product_type_id' => $productTypeId,
                'is_included' => 1,
                'source' => 'ai_inferred',
                'confidence' => (float) $info['confidence'],
                'is_manual' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $changed = true;
        }

        if ($domainId) {
            $hasDom = DB::connection('reports')->table('supplier_domains')
                ->where('supplier_id', $supplierId)
                ->where('domain_id', $domainId)
                ->exists();
            if (!$hasDom) {
                DB::connection('reports')->table('supplier_domains')->insert([
                    'supplier_id' => $supplierId,
                    'domain_id' => $domainId,
                    'is_included' => 1,
                    'source' => 'ai_inferred',
                    'confidence' => (float) $info['confidence'],
                    'is_manual' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $changed = true;
            }
        }

        if ($changed) {
            // Если расширили scope — обновим profile_updated_at; confidence поднимаем
            // только если новое значение выше.
            $supplier = DB::connection('reports')->table('suppliers')
                ->where('id', $supplierId)
                ->select('profile_confidence')
                ->first();
            $newConfidence = max((float) ($supplier->profile_confidence ?? 0), (float) $info['confidence']);
            DB::connection('reports')->table('suppliers')
                ->where('id', $supplierId)
                ->update([
                    'profile_confidence' => $newConfidence,
                    'profile_updated_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        return $changed;
    }

    private function persistSupplier(array $info, int $productTypeId, ?int $domainId, string $siteDomain, string $url): void
    {
        DB::connection('reports')->transaction(function () use ($info, $productTypeId, $domainId, $siteDomain, $url) {
            $website = $info['website'] ?: $url;
            $now = now();

            $supplierId = DB::connection('reports')->table('suppliers')->insertGetId([
                'name' => $info['name'] ?: $siteDomain ?: 'Unknown',
                'email' => $info['email'],
                'phone' => $info['phone'],
                'website' => $website,
                'notify_email' => !empty($info['email']) ? 1 : 0,
                'notify_telegram' => 0,
                'notify_sms' => 0,
                'is_active' => 1,
                'scope_product_types' => 'specific',
                'scope_domains' => $domainId ? 'specific' : 'all',
                'profile_source' => 'auto_refined',
                'profile_confidence' => $info['confidence'],
                'profile_updated_at' => $now,
                'categories' => !empty($info['categories']) ? json_encode($info['categories'], JSON_UNESCAPED_UNICODE) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Pivot с product_type
            DB::connection('reports')->table('supplier_product_types')->insertOrIgnore([
                'supplier_id' => $supplierId,
                'product_type_id' => $productTypeId,
                'is_included' => 1,
                'source' => 'ai_inferred',
                'confidence' => $info['confidence'],
                'is_manual' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($domainId) {
                DB::connection('reports')->table('supplier_domains')->insertOrIgnore([
                    'supplier_id' => $supplierId,
                    'domain_id' => $domainId,
                    'is_included' => 1,
                    'source' => 'ai_inferred',
                    'confidence' => $info['confidence'],
                    'is_manual' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    private function s(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        if (mb_strlen($trimmed) > $max) {
            $trimmed = mb_substr($trimmed, 0, $max);
        }
        return $trimmed;
    }

    private function validEmail(?string $email): ?string
    {
        $email = $this->s($email, 255);
        if ($email === null) {
            return null;
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
    }
}
