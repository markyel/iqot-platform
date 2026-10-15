<?php

namespace App\Jobs\Monitoring;

use App\Models\Api\ClientCategory;
use App\Models\Api\ClientCategoryCandidate;
use App\Services\Api\ClientCategoryClassifierService;
use App\Services\Monitoring\MonitoringGovernor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Классификация товаров мониторинга по таксономии IQOT ЧЕРЕЗ КАТЕГОРИЮ ЗАКАЗЧИКА (решение владельца
 * 2026-10-15): соответствие «категория заказчика → product_type/domain» берётся из
 * client_category_candidates (ручное или выученное — общее с заявками этого клиента); если его нет —
 * ОДИН вызов AI на категорию (название категории + примеры товаров), результат запоминается как
 * кандидат и применяется ко всем товарам категории. Вместо поштучного gpt-4o: 30 тыс. позиций →
 * не больше числа категорий (~100) вызовов. Позиции без категории не классифицируются.
 */
class ClassifyMonitoringCategoriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    /** Не больше категорий за один запуск (остальные — следующим запуском цепочкой). */
    private const PER_RUN = 20;

    public function __construct(private readonly int $programId)
    {
        $this->onQueue('monitor-import');
    }

    public function handle(ClientCategoryClassifierService $classifier, MonitoringGovernor $governor): void
    {
        $categoryIds = DB::table('program_items as pi')->join('mp_products as p', 'p.id', '=', 'pi.product_id')
            ->where('pi.program_id', $this->programId)->where('pi.active', true)
            ->whereNotNull('pi.client_category_id')->whereNull('p.product_type_id')
            ->distinct()->pluck('pi.client_category_id')->all();
        if ($categoryIds === []) {
            return;
        }

        $done = 0;
        foreach (array_slice($categoryIds, 0, self::PER_RUN) as $categoryId) {
            $category = ClientCategory::find($categoryId);
            if (!$category) {
                continue;
            }
            $mapping = $this->mapping((int) $categoryId);
            if ($mapping === null) {
                if (!$governor->hasLlmBudget()) {
                    Log::info('ClassifyMonitoringCategoriesJob: LLM budget exhausted, postponed', ['program_id' => $this->programId]);
                    self::dispatch($this->programId)->delay(now()->addDay()->startOfDay()->addHours(3));

                    return;
                }
                $mapping = $this->classifyCategory($classifier, $category);
                $governor->record('llm_call', 1, 0.0);
            }
            if ($mapping === null || empty($mapping['product_type_id'])) {
                continue;
            }
            DB::table('mp_products as p')->join('program_items as pi', 'pi.product_id', '=', 'p.id')
                ->where('pi.program_id', $this->programId)->where('pi.client_category_id', $categoryId)
                ->whereNull('p.product_type_id')
                ->update([
                    'p.product_type_id' => $mapping['product_type_id'],
                    'p.domain_id' => $mapping['domain_id'] ?? null,
                    'p.classification_source' => 'client_category',
                    'p.updated_at' => now(),
                ]);
            $done++;
        }

        if (count($categoryIds) > self::PER_RUN) {
            self::dispatch($this->programId);
        }
        Log::info('ClassifyMonitoringCategoriesJob', ['program_id' => $this->programId, 'categories' => count($categoryIds), 'mapped' => $done]);
    }

    /** Известное соответствие категории: ручное в приоритете, затем выученное с наибольшим числом попаданий. */
    private function mapping(int $categoryId): ?array
    {
        $c = ClientCategoryCandidate::query()->where('client_category_id', $categoryId)->where('is_active', true)
            ->orderByRaw("source = 'manual' DESC")->orderByDesc('priority')->orderByDesc('hit_count')->first();

        return $c ? ['product_type_id' => $c->product_type_id, 'domain_id' => $c->domain_id] : null;
    }

    /** Один AI-вызов на категорию: название/путь категории + до 5 примеров товаров. */
    private function classifyCategory(ClientCategoryClassifierService $classifier, ClientCategory $category): ?array
    {
        $examples = DB::table('program_items')->where('program_id', $this->programId)->where('client_category_id', $category->id)
            ->limit(5)->pluck('raw_name')->map(fn ($n) => mb_substr((string) $n, 0, 120))->all();
        try {
            $res = $classifier->classify([
                'name' => mb_substr(($category->full_path ?: $category->leaf_name) . ' — категория товаров', 0, 500),
                'brand' => null,
                'article' => null,
                'quantity' => null,
                'unit' => null,
                'description' => 'Примеры товаров категории: ' . implode('; ', $examples),
            ], (int) $category->id);
        } catch (\Throwable $e) {
            Log::warning('ClassifyMonitoringCategoriesJob: classify failed', ['category_id' => $category->id, 'error' => $e->getMessage()]);

            return null;
        }

        return ['product_type_id' => $res['product_type_id'] ?? null, 'domain_id' => $res['domain_id'] ?? null];
    }
}
