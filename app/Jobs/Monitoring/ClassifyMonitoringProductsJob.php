<?php

namespace App\Jobs\Monitoring;

use App\Models\Monitoring\MpProduct;
use App\Services\Api\ClientCategoryClassifierService;
use App\Services\Monitoring\MonitoringGovernor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Классификация новых канонических товаров по таксономии IQOT (product_type / domain)
 * тем же классификатором, что и позиции API-заявок. Нужна для тематических пулов
 * поставщиков (Ф1/Ф3). Не блокирует импорт; квота LLM — через регулятор.
 */
class ClassifyMonitoringProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    /** @param array<int,int> $productIds */
    public function __construct(private readonly array $productIds)
    {
        $this->onQueue('monitor-import');
    }

    public function handle(ClientCategoryClassifierService $classifier, MonitoringGovernor $governor): void
    {
        foreach (array_chunk($this->productIds, 50) as $ids) {
            $products = MpProduct::query()->with('identifiers')->whereIn('id', $ids)->whereNull('product_type_id')->get();
            foreach ($products as $product) {
                if (!$governor->hasLlmBudget()) {
                    Log::info('ClassifyMonitoringProductsJob: LLM daily budget exhausted, rest postponed', ['left' => count($ids)]);
                    self::dispatch($ids)->delay(now()->addDay()->startOfDay()->addHours(3));

                    return;
                }
                try {
                    $res = $classifier->classify([
                        'name' => $product->name,
                        'brand' => $product->brand,
                        'article' => $product->articleValues()[0] ?? null,
                        'quantity' => null,
                        'unit' => null,
                        'description' => null,
                    ], null);
                    $product->forceFill([
                        'product_type_id' => $res['product_type_id'] ?? null,
                        'domain_id' => $res['domain_id'] ?? null,
                        'classification_source' => (string) ($res['classification_source'] ?? 'full_ai'),
                    ])->save();
                } catch (\Throwable $e) {
                    Log::warning('ClassifyMonitoringProductsJob: classify failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
                }
            }
        }
    }
}
