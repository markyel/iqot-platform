<?php

namespace App\Services\Api;

use App\Models\Api\ClientCategory;
use App\Models\Api\ClientCategoryCandidate;
use App\Models\ApplicationDomain;
use App\Models\ProductType;
use Illuminate\Support\Facades\Log;

/**
 * Классификатор позиций API-submission по стратегиям §4.1 спеки.
 *
 * Принимает одну позицию из payload и контекст (api_client_id, client_category_id),
 * возвращает результат классификации для записи в request_items_staging.
 *
 * Возвращаемая структура:
 *   [
 *     'product_type_id' => ?int,
 *     'domain_id' => ?int,
 *     'type_confidence' => ?float,
 *     'domain_confidence' => ?float,
 *     'classification_source' => 'manual_mapping'|'mini_classifier'|'full_ai',
 *     'needs_review' => bool,
 *     'trust_level' => 'green'|'yellow'|'red',
 *   ]
 */
class ClientCategoryClassifierService
{
    public function __construct(
        private readonly OpenAIClassifierClient $ai,
    ) {
    }

    /**
     * @param array<string,mixed> $item позиция из payload
     * @param int|null $clientCategoryId id из iqot.client_categories (уже upsertнутый)
     * @return array<string,mixed>
     */
    public function classify(array $item, ?int $clientCategoryId): array
    {
        // Если нет client_category — сразу full AI без маппинга (§4.1 п.5).
        if ($clientCategoryId === null) {
            return $this->fullAiClassify($item, null);
        }

        $candidates = ClientCategoryCandidate::query()
            ->where('client_category_id', $clientCategoryId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('hit_count')
            ->get();

        if ($candidates->isEmpty()) {
            // §4.1 п.4: кандидатов нет — полный AI-проход + создание ai_suggested candidate.
            return $this->fullAiClassify($item, $clientCategoryId);
        }

        $manualCandidates = $candidates->where('source', 'manual');
        $learnedCandidates = $candidates->whereIn('source', ['learned', 'ai_suggested'])
            ->filter(fn ($c) => $c->hit_count >= 10);

        // §4.1 п.1: ровно один manual → используем напрямую.
        if ($manualCandidates->count() === 1 && $learnedCandidates->isEmpty()) {
            /** @var ClientCategoryCandidate $c */
            $c = $manualCandidates->first();
            $this->recordHit($c);
            return [
                'product_type_id' => $c->product_type_id,
                'domain_id' => $c->domain_id,
                'type_confidence' => (float) $c->confidence,
                'domain_confidence' => $c->domain_id ? (float) $c->confidence : null,
                'classification_source' => 'manual_mapping',
                'needs_review' => false,
                'trust_level' => 'green',
            ];
        }

        // §4.1 п.2/п.3: несколько manual ИЛИ manual + learned → mini-classifier.
        $pool = $candidates->filter(function (ClientCategoryCandidate $c) {
            return $c->source === 'manual' || ($c->hit_count >= 10);
        })->values();

        if ($pool->isNotEmpty() && $this->ai->isConfigured()) {
            try {
                return $this->miniClassify($item, $clientCategoryId, $pool->all());
            } catch (\Throwable $e) {
                Log::warning('ClientCategoryClassifier: mini-classifier failed, fallback', [
                    'error' => $e->getMessage(),
                ]);
                // Падаем в fallback ниже.
            }
        }

        // §4.1 п.4/фоллбэк: полный AI.
        return $this->fullAiClassify($item, $clientCategoryId);
    }

    /**
     * Вызывает mini-classifier: модель выбирает один из candidates.
     *
     * @param array<string,mixed> $item
     * @param int $clientCategoryId
     * @param array<ClientCategoryCandidate> $candidates
     * @return array<string,mixed>
     */
    private function miniClassify(array $item, int $clientCategoryId, array $candidates): array
    {
        $category = ClientCategory::query()->find($clientCategoryId);
        $categoryPath = $category?->full_path ?: '';

        // Подгружаем имена product_types/domains кандидатов (в reports).
        $productTypeIds = array_values(array_unique(array_map(fn ($c) => (int) $c->product_type_id, $candidates)));
        $domainIds = array_values(array_unique(array_filter(array_map(fn ($c) => $c->domain_id, $candidates))));

        $productTypes = ProductType::query()->whereIn('id', $productTypeIds)->get()->keyBy('id');
        $domains = ApplicationDomain::query()->whereIn('id', $domainIds)->get()->keyBy('id');

        $optionsText = '';
        foreach ($candidates as $i => $c) {
            $ptName = $productTypes[$c->product_type_id]->name ?? ('product_type #' . $c->product_type_id);
            $domainName = $c->domain_id ? ($domains[$c->domain_id]->name ?? ('domain #' . $c->domain_id)) : 'любой';
            $optionsText .= sprintf(
                "  [%d] product_type=\"%s\" (id=%d), domain=\"%s\", priority=%d, source=%s, hits=%d\n",
                $i,
                $ptName,
                $c->product_type_id,
                $domainName,
                $c->priority,
                $c->source,
                $c->hit_count,
            );
        }

        $systemPrompt = 'Ты классификатор B2B-товаров в закупочной системе. '
            . 'Тебе дана позиция заявки и список кандидатов (product_type + domain). '
            . 'Выбери один кандидат, который точнее всего соответствует позиции. '
            . 'Отвечай строго JSON: {"chosen_candidate_index": int, "confidence": float (0..1), "reasoning": string}. '
            . 'Если ни один кандидат не подходит — chosen_candidate_index=-1.';

        $userPrompt = "Клиентская категория: {$categoryPath}\n"
            . 'Позиция:' . "\n"
            . '  name: ' . ($item['name'] ?? '') . "\n"
            . '  article: ' . ($item['article'] ?? '—') . "\n"
            . '  brand: ' . ($item['brand'] ?? '—') . "\n"
            . '  quantity: ' . ($item['quantity'] ?? '') . ' ' . ($item['unit'] ?? '') . "\n"
            . '  description: ' . ($item['description'] ?? '—') . "\n\n"
            . "Кандидаты:\n" . $optionsText;

        $result = $this->ai->jsonCompletion($this->ai->modelMini(), $systemPrompt, $userPrompt, 400);

        $idx = $result['chosen_candidate_index'] ?? -1;
        $conf = (float) ($result['confidence'] ?? 0);

        if (!is_int($idx) || $idx < 0 || $idx >= count($candidates)) {
            return $this->fullAiFallback('mini_no_match');
        }

        /** @var ClientCategoryCandidate $chosen */
        $chosen = $candidates[$idx];
        $this->recordHit($chosen);

        // Trust level: manual → green, ai_suggested <20 hits → yellow, ≥20 → green.
        $trust = 'yellow';
        if ($chosen->source === 'manual') {
            $trust = 'green';
        } elseif ($chosen->hit_count >= 20) {
            $trust = 'green';
        }

        return [
            'product_type_id' => $chosen->product_type_id,
            'domain_id' => $chosen->domain_id,
            'type_confidence' => $conf,
            'domain_confidence' => $chosen->domain_id ? $conf : null,
            'classification_source' => 'mini_classifier',
            'needs_review' => $trust !== 'green',
            'trust_level' => $trust,
        ];
    }

    /**
     * Full AI-проход по позиции (§4.3).
     *
     * Pipeline:
     *   1. Prefilter product_types в reports: SQL по name/slug/keywords + токенам
     *      из имени/бренда/артикула позиции. Возвращаем до 50 кандидатов.
     *   2. Загружаем активные domains (их немного, ~100).
     *   3. AI (gpt-4o) выбирает product_type_id + domain_id + confidence.
     *   4. Hallucination guard: если id не из предложенного списка → null/red.
     *   5. Если confidence >= 0.75 и есть client_category — создаём ai_suggested
     *      candidate, чтобы следующие submission'ы шли через mini_classifier.
     *
     * Если AI не сконфигурирован или упал — возвращаем raw-fallback
     * (product_type=null, trust=red, needs_review=1 — на модерацию).
     */
    private function fullAiClassify(array $item, ?int $clientCategoryId): array
    {
        if (!$this->ai->isConfigured()) {
            return $this->rawFallback('ai_not_configured');
        }

        try {
            $productTypes = $this->prefilterProductTypes($item);
            $prefilterEmpty = $productTypes->isEmpty();
            if ($prefilterEmpty) {
                // Не нашли по токенам — всё равно зовём AI, передаём полный список
                // активных листьев. Их ~200, для промпта нормально. AI либо выберет
                // что-то осмысленное, либо хотя бы проставит domain.
                $productTypes = ProductType::query()
                    ->where('is_active', 1)
                    ->where('status', 'active')
                    ->where('is_leaf', 1)
                    ->orderBy('name')
                    ->limit(250)
                    ->get(['id', 'slug', 'name', 'keywords']);

                if ($productTypes->isEmpty()) {
                    // В таксономии вообще ничего нет — совсем крайний случай.
                    return $this->rawFallback('no_candidates_prefilter');
                }
            }

            $domains = ApplicationDomain::query()
                ->where('is_active', 1)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'slug', 'name']);

            $category = $clientCategoryId ? ClientCategory::query()->find($clientCategoryId) : null;

            $result = $this->callFullAi($item, $category, $productTypes, $domains);

            // Hallucination guard: AI должен вернуть id из предложенного списка.
            $allowedPtIds = $productTypes->pluck('id')->all();
            $allowedDomainIds = array_merge([null, 0], $domains->pluck('id')->all());

            $ptId = $result['product_type_id'] ?? null;
            $domainId = $result['domain_id'] ?? null;
            $confidence = (float) ($result['confidence'] ?? 0);

            if (!in_array($ptId, $allowedPtIds, true)) {
                $ptId = null;
                $confidence = 0;
            }
            if ($domainId !== null && !in_array((int) $domainId, $allowedDomainIds, true)) {
                $domainId = null;
            }

            if ($ptId === null) {
                // Даже если product_type не удалось определить, domain может быть
                // полезен — сохраняем его и отправляем позицию на ручную модерацию.
                if ($domainId !== null) {
                    Log::info('ClientCategoryClassifier: domain-only fallback', [
                        'item_name' => $item['name'] ?? null,
                        'domain_id' => $domainId,
                        'prefilter_empty' => $prefilterEmpty,
                    ]);
                    return [
                        'product_type_id' => null,
                        'domain_id' => (int) $domainId,
                        'type_confidence' => 0.0,
                        'domain_confidence' => $confidence > 0 ? $confidence : null,
                        'classification_source' => 'full_ai',
                        'needs_review' => true,
                        'trust_level' => 'red',
                        '_fallback_reason' => 'pt_null_domain_only',
                    ];
                }
                return $this->rawFallback('ai_no_valid_match');
            }

            // Trust-level политика для full_ai §5.1: confidence≥0.9 → yellow, иначе red.
            $trust = $confidence >= 0.9 ? 'yellow' : 'red';
            $needsReview = $trust !== 'green'; // full_ai всегда нуждается в ревью изначально

            // §4.1 п.4: если confidence приемлемый — создаём ai_suggested candidate
            // для этой client_category, чтобы ускорить будущие submissions.
            if ($clientCategoryId !== null && $confidence >= 0.75) {
                $this->createAiSuggestedCandidate(
                    $clientCategoryId,
                    (int) $ptId,
                    $domainId !== null ? (int) $domainId : null,
                    $confidence
                );
            }

            return [
                'product_type_id' => (int) $ptId,
                'domain_id' => $domainId !== null ? (int) $domainId : null,
                'type_confidence' => $confidence,
                'domain_confidence' => $domainId !== null ? $confidence : null,
                'classification_source' => 'full_ai',
                'needs_review' => $needsReview,
                'trust_level' => $trust,
            ];
        } catch (\Throwable $e) {
            Log::warning('ClientCategoryClassifier: full AI failed', [
                'error' => $e->getMessage(),
                'item_name' => $item['name'] ?? null,
            ]);
            return $this->rawFallback('ai_exception');
        }
    }

    /**
     * Prefilter: SQL-поиск product_types по токенам из позиции.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\ProductType>
     */
    private function prefilterProductTypes(array $item): \Illuminate\Support\Collection
    {
        $terms = $this->extractSearchTerms($item);
        if (empty($terms)) {
            // Нет полезных токенов — возвращаем пустой набор (модератор получит позицию как есть).
            return collect();
        }

        $query = ProductType::query()
            ->where('is_active', 1)
            ->where('status', 'active')
            ->where('is_leaf', 1);

        $query->where(function ($q) use ($terms) {
            foreach ($terms as $t) {
                $like = '%' . $t . '%';
                $q->orWhere('name', 'LIKE', $like)
                    ->orWhere('slug', 'LIKE', $like)
                    ->orWhereRaw(
                        "JSON_SEARCH(LOWER(CAST(keywords AS CHAR)), 'one', ?) IS NOT NULL",
                        ['%' . mb_strtolower($t) . '%']
                    );
            }
        });

        return $query->limit(50)->get(['id', 'slug', 'name', 'keywords']);
    }

    /**
     * Извлечение токенов из name/article/brand. Фильтруем слишком короткие и служебные.
     *
     * @return array<string>
     */
    private function extractSearchTerms(array $item): array
    {
        $raw = trim(
            (string) ($item['name'] ?? '') . ' ' .
            (string) ($item['brand'] ?? '') . ' ' .
            (string) ($item['article'] ?? '')
        );
        if ($raw === '') {
            return [];
        }
        // Разбиваем по non-letter-digit символам.
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $raw) ?: [];
        $clean = [];
        foreach ($tokens as $t) {
            $t = trim($t);
            if (mb_strlen($t) < 3) {
                continue; // слишком короткое
            }
            $clean[mb_strtolower($t)] = true; // уникальность
        }
        return array_keys($clean);
    }

    /**
     * Вызов AI (gpt-4o) для полной классификации.
     *
     * @return array<string,mixed> {product_type_id, domain_id, confidence, reasoning}
     */
    private function callFullAi(
        array $item,
        ?ClientCategory $category,
        \Illuminate\Support\Collection $productTypes,
        \Illuminate\Support\Collection $domains,
    ): array {
        $ptList = $productTypes->map(fn ($pt) => sprintf('  id=%d  name="%s"  slug=%s', $pt->id, $pt->name, $pt->slug))->implode("\n");
        $domainList = $domains->map(fn ($d) => sprintf('  id=%d  name="%s"  slug=%s', $d->id, $d->name, $d->slug))->implode("\n");

        $systemPrompt = 'Ты — классификатор B2B-запчастей и оборудования. '
            . 'Тебе дана позиция из заявки и список возможных product_types + domains. '
            . 'Выбери ОДИН product_type_id, который лучше всего соответствует позиции. '
            . 'Также выбери наиболее подходящий domain_id — область промышленного применения. '
            . 'Используй ТОЛЬКО id из списков — не придумывай свои. '
            . 'ПРАВИЛА ДЛЯ DOMAIN: '
            . '— если товар предназначен для конкретной отрасли (лифты, эскалаторы, станки, HVAC, '
            . 'сельхозтехника, строительство, медоборудование и т.п.), обязательно укажи domain_id; '
            . 'подсказки: бренды и артикулы часто намекают на отрасль (OTIS/Thyssen/Sodimas → лифты; '
            . 'Danfoss/Siemens для VFD в промышленности чаще лифты/HVAC/станки; SKF подшипники — '
            . 'смотри по названию и client_category); '
            . '— NULL для domain используй ТОЛЬКО если товар действительно универсальный: крепёж, '
            . 'смазки, общий ручной инструмент, расходники без отраслевой специфики; '
            . '— если сомневаешься — лучше выбрать наиболее вероятный domain, чем null. '
            . 'Ответ строго JSON: '
            . '{"product_type_id": int|null, "domain_id": int|null, "confidence": float (0..1), "reasoning": string}. '
            . 'Если ни один product_type не подходит — product_type_id=null, confidence=0.';

        $userPrompt = "Позиция:\n"
            . '  name: ' . ($item['name'] ?? '') . "\n"
            . '  brand: ' . ($item['brand'] ?? '—') . "\n"
            . '  article: ' . ($item['article'] ?? '—') . "\n"
            . '  description: ' . ($item['description'] ?? '—') . "\n"
            . '  quantity: ' . ($item['quantity'] ?? '—') . ' ' . ($item['unit'] ?? '') . "\n"
            . ($category ? '  client_category: ' . $category->full_path . "\n" : '')
            . "\nДоступные product_types:\n" . $ptList
            . "\n\nДоступные domains:\n" . $domainList;

        return $this->ai->jsonCompletion(
            $this->ai->modelFull(),
            $systemPrompt,
            $userPrompt,
            600
        );
    }

    private function createAiSuggestedCandidate(int $clientCategoryId, int $productTypeId, ?int $domainId, float $confidence): void
    {
        try {
            ClientCategoryCandidate::query()->updateOrCreate(
                [
                    'client_category_id' => $clientCategoryId,
                    'product_type_id' => $productTypeId,
                    'domain_id' => $domainId,
                ],
                [
                    'priority' => 2,
                    'confidence' => round($confidence, 2),
                    'source' => 'ai_suggested',
                    'is_active' => true,
                    'hit_count' => 1,
                    'last_hit_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('ClientCategoryClassifier: failed to create ai_suggested candidate', [
                'error' => $e->getMessage(),
                'client_category_id' => $clientCategoryId,
                'product_type_id' => $productTypeId,
            ]);
        }
    }

    /**
     * Сырой fallback — AI упал/не настроен/не нашёл совпадение. Модератор разберёт.
     */
    private function rawFallback(string $reason): array
    {
        return [
            'product_type_id' => null,
            'domain_id' => null,
            'type_confidence' => null,
            'domain_confidence' => null,
            'classification_source' => 'full_ai',
            'needs_review' => true,
            'trust_level' => 'red',
            '_fallback_reason' => $reason,
        ];
    }

    private function recordHit(ClientCategoryCandidate $candidate): void
    {
        $candidate->increment('hit_count');
        $candidate->last_hit_at = now();
        $candidate->save();
    }
}
