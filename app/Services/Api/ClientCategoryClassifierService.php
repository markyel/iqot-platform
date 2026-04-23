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
            return $this->fullAiFallback('no_category');
        }

        $candidates = ClientCategoryCandidate::query()
            ->where('client_category_id', $clientCategoryId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('hit_count')
            ->get();

        if ($candidates->isEmpty()) {
            // §4.1 п.4: кандидатов нет — full AI, потом модерация.
            return $this->fullAiFallback('no_candidates');
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
        return $this->fullAiFallback('fallback');
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
     * Full AI-проход по позиции (§4.3). Architect-mode off: новые product_types
     * не создаём. Если модель вернула неизвестный id — product_type_id=NULL,
     * needs_review=1, trust=red.
     *
     * В MVP пока упрощённая версия: не передаём полный каталог
     * (2115 product_types), а отдаём позицию модератору как новую.
     */
    private function fullAiFallback(string $reason): array
    {
        return [
            'product_type_id' => null,
            'domain_id' => null,
            'type_confidence' => null,
            'domain_confidence' => null,
            'classification_source' => 'full_ai',
            'needs_review' => true,
            'trust_level' => 'red',
            '_fallback_reason' => $reason, // для отладки, контроллер/воркер не пишет в БД
        ];
    }

    private function recordHit(ClientCategoryCandidate $candidate): void
    {
        $candidate->increment('hit_count');
        $candidate->last_hit_at = now();
        $candidate->save();
    }
}
