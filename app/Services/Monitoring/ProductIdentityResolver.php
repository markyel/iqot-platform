<?php

namespace App\Services\Monitoring;

use App\Models\Monitoring\MpIdentifier;
use App\Models\Monitoring\MpProduct;
use App\Models\Monitoring\ProgramItem;
use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\BrandNormalizer;
use App\Support\Monitoring\UnitNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Позиция программы → канонический товар (docs/monitoring-f0-spec.md §4).
 *
 *  - (артикул, бренд) найден → тот же товар;
 *  - бренд неизвестен, артикул у ровно одного товара → он;
 *  - бренд неизвестен, артикул у нескольких товаров разных брендов → ambiguous (не гадаем);
 *  - артикул у товара ДРУГОГО бренда → новый товар (один артикул у разных брендов —
 *    разные товары, урок Content Engine);
 *  - ничего → новый товар.
 * Внутренние коды учётных систем клиента и «коды серий» артикулом не считаются (уроки LazyLift).
 */
class ProductIdentityResolver
{
    /** Похоже на размер/характеристику, а не на код модели. */
    private const DIMENSION_PATTERNS = [
        '/^\d+(MM|CM|M|KG|KW|V|W|A|HZ|BAR|MPA|NM|ММ|СМ|М|КГ|КВТ|В|ВТ|А|ГЦ)$/u',
        '/^\d+(X|Х|\*)\d+/u',
        '/^[DДRØ]\d+$/u',
        '/^\d{1,4}$/',
    ];

    public function resolve(ProgramItem $item): void
    {
        $brandNorm = BrandNormalizer::normalize($item->raw_brand);
        $codes = $this->itemCodes($item, $brandNorm);
        // Коды САМОЙ позиции: по ним клиенту показываются страницы товара (ItemScope) — кросс-номера,
        // которые товару добавили другие клиенты, на эту позицию не действуют.
        $item->identity_codes = $codes;

        if ($codes === []) {
            $product = $this->findOrCreateByName($item, $brandNorm);
            $item->forceFill([
                'product_id' => $product->id,
                'match_status' => ProgramItem::MATCH_MATCHED,
                'match_note' => 'no_article',
            ])->save();

            return;
        }

        $candidates = MpIdentifier::query()
            ->whereIn('kind', [MpIdentifier::KIND_OEM, MpIdentifier::KIND_MPN])
            ->whereIn('value_norm', $codes)
            ->get(['product_id', 'brand_norm', 'value_norm']);

        $productId = null;
        $note = null;

        if ($brandNorm !== '') {
            $sameBrand = $candidates->filter(fn ($c) => $c->brand_norm === $brandNorm || !BrandNormalizer::differ($c->brand_norm, $brandNorm) && $c->brand_norm !== '');
            $unknownBrand = $candidates->filter(fn ($c) => $c->brand_norm === '');
            if ($sameBrand->isNotEmpty()) {
                $productId = (int) $sameBrand->first()->product_id;
            } elseif ($unknownBrand->pluck('product_id')->unique()->count() === 1) {
                // Товар заведён без бренда (другим клиентом) — уточняем его брендом.
                $productId = (int) $unknownBrand->first()->product_id;
                $this->attachBrand($productId, $brandNorm, $item->raw_brand);
            }
            // Иначе: код есть только у других брендов → новый товар (ниже).
        } else {
            $distinct = $candidates->pluck('product_id')->unique()->values();
            if ($distinct->count() === 1) {
                $productId = (int) $distinct->first();
            } elseif ($distinct->count() > 1) {
                $item->forceFill([
                    'product_id' => null,
                    'match_status' => ProgramItem::MATCH_AMBIGUOUS,
                    'match_note' => 'article_in_several_brands: ' . implode(',', array_slice($codes, 0, 3)),
                ])->save();

                return;
            }
        }

        if ($productId === null) {
            $productId = $this->createProduct($item, $brandNorm, $codes)->id;
        } else {
            // Тот же код у другой позиции ЭТОЙ ЖЕ программы с заметно другим названием —
            // вероятно варианты одного артикула (цвет/исполнение), склеивать молча нельзя.
            $sibling = ProgramItem::query()
                ->where('program_id', $item->program_id)->where('product_id', $productId)
                ->where('id', '!=', $item->id)->where('active', true)
                ->first(['client_ref', 'raw_name']);
            if ($sibling && $this->namesDiffer((string) $sibling->raw_name, (string) $item->raw_name)) {
                $item->forceFill([
                    'product_id' => null,
                    'match_status' => ProgramItem::MATCH_AMBIGUOUS,
                    'match_note' => mb_substr('same_article_as: ' . $sibling->client_ref, 0, 255),
                ])->save();

                return;
            }
            $this->addMissingCodes($productId, $codes, $brandNorm, $item);
        }

        $item->forceFill([
            'product_id' => $productId,
            'match_status' => ProgramItem::MATCH_MATCHED,
            'match_note' => $note,
        ])->save();
        $this->attachClientRefAlias($productId, $item, $brandNorm);
    }

    /**
     * Каталожный код клиента, похожий на код модели («LW-0002478»), — алиас товара:
     * партнёрские сайты клиента публикуют его как sku (бенч 14.10: s-mechanics.ru).
     */
    private function attachClientRefAlias(int $productId, ProgramItem $item, string $brandNorm): void
    {
        $norm = ArticleNormalizer::normalize($item->client_ref);
        if ($norm === '' || mb_strlen($norm) > 30 || !ArticleNormalizer::looksLikeModelCode($norm) || !preg_match('/[A-Z]/', $norm)) {
            return;
        }
        DB::table('mp_identifiers')->insertOrIgnore([
            'product_id' => $productId,
            'kind' => MpIdentifier::KIND_ALIAS,
            'value' => mb_substr((string) $item->client_ref, 0, 255),
            'value_norm' => $norm,
            'brand_norm' => $brandNorm,
            'source' => 'client_ref:' . $item->program_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Коды позиции: артикул + все OEM-артикулы клиента (`oem_articles`); коды из наименования —
     * только если клиент не прислал ни одного артикула.
     *
     * @return array<int,string> нормализованные коды
     */
    public function itemCodes(ProgramItem $item, string $brandNorm = ''): array
    {
        $oem = $this->oemArticles($item);
        $codes = $this->extractCodes($item->raw_article, $oem === [] ? $item->raw_name : null, $brandNorm);
        foreach ($oem as $article) {
            foreach ($this->extractCodes($article, null, $brandNorm) as $code) {
                // «147», «600», «D4» — не код модели: совпадёт с любым числом на странице. Проверку на размер
                // (как для слов из названия) не применяем: «3X02429*A» — настоящий артикул.
                if (mb_strlen($code) < 5) {
                    continue;
                }
                $codes[] = $code;
            }
        }

        return array_values(array_unique(array_map('strval', $codes)));
    }

    /** @return array<int,string> */
    private function oemArticles(ProgramItem $item): array
    {
        $raw = $item->oem_articles ?? [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        // Кросс-номера из учётных систем бывают с комментариями: «50630296 ( компл. 4 шт.+ 2 боковинки)».
        return array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) preg_replace('/\s*[(\[].*$/us', '', (string) $v)) : '', (array) $raw)));
    }

    /**
     * Коды позиции: артикул клиента (если не внутренний код) + коды моделей из
     * наименования, только когда артикула нет.
     *
     * @return array<int,string> нормализованные коды
     */
    public function extractCodes(?string $rawArticle, ?string $rawName, string $brandNorm = ''): array
    {
        $codes = [];
        $article = trim((string) $rawArticle);
        if ($article !== '' && !$this->isInternalCode($article, $brandNorm)) {
            foreach (preg_split('/\s*[;,|]\s*|\s+\/\s+/u', $article) ?: [] as $part) {
                $norm = ArticleNormalizer::normalize($part);
                if ($norm !== '' && !$this->isSeriesToken($norm)) {
                    $codes[$norm] = true;
                }
            }
        }

        if ($codes === [] && trim((string) $rawName) !== '') {
            foreach (preg_split('/[\s,;()\[\]«»"]+/u', (string) $rawName, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $norm = ArticleNormalizer::normalize($word);
                if ($norm !== '' && mb_strlen($norm) >= 5
                    && ArticleNormalizer::looksLikeModelCode($norm)
                    && !$this->isSeriesToken($norm)
                    && !$this->looksLikeDimension($norm)) {
                    $codes[$norm] = true;
                }
            }
        }

        // Числовые ключи PHP превращает в int — возвращаем строки (артикул «10283573»).
        return array_map('strval', array_keys($codes));
    }

    /** Наименования заметно различаются (не просто регистр/пробелы/пунктуация). */
    public function namesDiffer(string $a, string $b): bool
    {
        $norm = fn (string $s) => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($s)) ?? '';
        $x = $norm($a);
        $y = $norm($b);
        if ($x === '' || $y === '' || $x === $y) {
            return false;
        }
        similar_text($x, $y, $pct);

        return $pct < 95.0;
    }

    private function isInternalCode(string $article, string $brandNorm): bool
    {
        foreach ((array) config('monitoring.internal_code_patterns', []) as $re) {
            if (@preg_match($re, $article) === 1) {
                return true;
            }
        }
        // Длинный чисто числовой код (≥ 10 цифр) без бренда — типичный внутренний
        // номенклатурный номер учётной системы клиента, а не артикул производителя.
        $compact = preg_replace('/[\s\-.]/u', '', $article) ?? $article;

        return $brandNorm === '' && preg_match('/^\d{10,}$/', $compact) === 1;
    }

    private function isSeriesToken(string $norm): bool
    {
        foreach ((array) config('monitoring.series_tokens', []) as $t) {
            if (ArticleNormalizer::normalize($t) === $norm) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeDimension(string $norm): bool
    {
        foreach (self::DIMENSION_PATTERNS as $re) {
            if (preg_match($re, $norm)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int,string> $codes */
    private function createProduct(ProgramItem $item, string $brandNorm, array $codes): MpProduct
    {
        return DB::transaction(function () use ($item, $brandNorm, $codes) {
            $product = MpProduct::create([
                'name' => mb_substr((string) $item->raw_name, 0, 1000),
                'brand' => $item->raw_brand ? mb_substr($item->raw_brand, 0, 255) : null,
                'brand_norm' => $brandNorm !== '' ? $brandNorm : null,
                'unit_kind' => UnitNormalizer::normalize($item->raw_unit),
            ]);

            $inserted = $this->insertCodes($product->id, $codes, $brandNorm, $item);
            if ($inserted === 0) {
                // Гонка: параллельный импорт уже завёл товар с этими кодами — берём его.
                $existing = MpIdentifier::query()
                    ->whereIn('value_norm', $codes)->where('brand_norm', $brandNorm)
                    ->value('product_id');
                if ($existing) {
                    $product->delete();

                    return MpProduct::findOrFail($existing);
                }
            }

            return $product;
        });
    }

    /** @param array<int,string> $codes */
    private function addMissingCodes(int $productId, array $codes, string $brandNorm, ProgramItem $item): void
    {
        $this->insertCodes($productId, $codes, $brandNorm, $item);
    }

    /** @param array<int,string> $codes */
    private function insertCodes(int $productId, array $codes, string $brandNorm, ProgramItem $item): int
    {
        $rawByNorm = [];
        $rawAll = implode(';', array_merge([(string) $item->raw_article], $this->oemArticles($item)));
        foreach (preg_split('/\s*[;,|]\s*|\s+\/\s+/u', $rawAll) ?: [] as $part) {
            $n = ArticleNormalizer::normalize($part);
            if ($n !== '') {
                $rawByNorm[$n] = trim($part);
            }
        }
        $now = now();
        $rows = [];
        foreach ($codes as $code) {
            $rows[] = [
                'product_id' => $productId,
                'kind' => MpIdentifier::KIND_OEM,
                'value' => mb_substr($rawByNorm[$code] ?? $code, 0, 255),
                'value_norm' => $code,
                'brand_norm' => $brandNorm,
                'source' => 'client:' . $item->program_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows === [] ? 0 : DB::table('mp_identifiers')->insertOrIgnore($rows);
    }

    private function attachBrand(int $productId, string $brandNorm, ?string $rawBrand): void
    {
        MpProduct::query()->where('id', $productId)->whereNull('brand_norm')->update([
            'brand_norm' => $brandNorm,
            'brand' => $rawBrand ? mb_substr($rawBrand, 0, 255) : null,
        ]);
    }

    /** Позиция без артикула: товар по нормализованному наименованию (+бренд) как alias. */
    private function findOrCreateByName(ProgramItem $item, string $brandNorm): MpProduct
    {
        $alias = mb_substr(preg_replace('/\s+/u', ' ', mb_strtoupper(trim((string) $item->raw_name))) ?? '', 0, 191);
        $existing = MpIdentifier::query()
            ->where('kind', MpIdentifier::KIND_ALIAS)->where('value_norm', $alias)->where('brand_norm', $brandNorm)
            ->value('product_id');
        if ($existing) {
            return MpProduct::findOrFail($existing);
        }

        return DB::transaction(function () use ($item, $brandNorm, $alias) {
            $product = MpProduct::create([
                'name' => mb_substr((string) $item->raw_name, 0, 1000),
                'brand' => $item->raw_brand ? mb_substr($item->raw_brand, 0, 255) : null,
                'brand_norm' => $brandNorm !== '' ? $brandNorm : null,
                'unit_kind' => UnitNormalizer::normalize($item->raw_unit),
            ]);
            DB::table('mp_identifiers')->insertOrIgnore([
                'product_id' => $product->id,
                'kind' => MpIdentifier::KIND_ALIAS,
                'value' => mb_substr((string) $item->raw_name, 0, 255),
                'value_norm' => $alias,
                'brand_norm' => $brandNorm,
                'source' => 'client:' . $item->program_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $product;
        });
    }
}
