<?php

namespace App\Console\Commands;

use App\Models\ExternalRequestItem;
use App\Models\PublicCatalogItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPublicCatalogCommand extends Command
{
    protected $signature = 'catalog:sync {--full : Полная пересинхронизация}';
    protected $description = 'Синхронизация публичного каталога с позициями из external БД';

    public function handle()
    {
        $this->info('Начало синхронизации публичного каталога...');

        try {
            $fullSync = $this->option('full');

            if ($fullSync) {
                $this->info('Выполняется полная синхронизация...');
                PublicCatalogItem::truncate();
            }

            // Получаем ВСЕ позиции с предложениями из external БД (как в кабинете)
            $externalItems = ExternalRequestItem::with(['request', 'productType', 'applicationDomain'])
                ->whereHas('offers', function($q) {
                    $q->whereIn('status', ['received', 'processed']);
                })
                ->get();

            $this->info("Найдено позиций с предложениями: {$externalItems->count()}");

            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($externalItems as $externalItem) {
                try {
                    // Пропускаем, если нет связанной заявки
                    if (!$externalItem->request) {
                        $skipped++;
                        continue;
                    }

                    // Рассчитываем мин/макс цены
                    $prices = $this->calculatePrices($externalItem);

                    $data = [
                        'external_item_id' => $externalItem->id,
                        'name' => $externalItem->name,
                        'brand' => $externalItem->brand,
                        'article' => $externalItem->article,
                        'quantity' => $externalItem->quantity ?? 1,
                        'unit' => $externalItem->unit ?? 'шт.',
                        'category' => $externalItem->category,
                        'product_type_id' => $externalItem->product_type_id,
                        'product_type_name' => $externalItem->productType?->name,
                        'domain_id' => $externalItem->domain_id,
                        'domain_name' => $externalItem->applicationDomain?->name,
                        'external_request_id' => $externalItem->request_id,
                        'request_number' => $externalItem->request->request_number ?? null,
                        'offers_count' => $externalItem->offers_count,
                        'min_price' => $prices['min'],
                        'max_price' => $prices['max'],
                        'currency' => 'RUB',
                        'is_published' => true,
                        'published_at' => now(),
                    ];

                    $catalogItem = PublicCatalogItem::updateOrCreate(
                        ['external_item_id' => $externalItem->id],
                        $data
                    );

                    if ($catalogItem->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }

                } catch (\Exception $e) {
                    $this->error("Ошибка при обработке позиции {$externalItem->id}: {$e->getMessage()}");
                    Log::error("SyncPublicCatalog: Ошибка при обработке позиции {$externalItem->id}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Снимаем с публикации позиции, у которых offers_count < 3
            $unpublished = PublicCatalogItem::whereNotIn('external_item_id', $externalItems->pluck('id'))
                ->update(['is_published' => false]);

            $this->info("✓ Синхронизация завершена:");
            $this->info("  Создано: {$created}");
            $this->info("  Обновлено: {$updated}");
            $this->info("  Снято с публикации: {$unpublished}");
            $this->info("  Пропущено (нет заявки): {$skipped}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Ошибка синхронизации: ' . $e->getMessage());
            Log::error('SyncPublicCatalog failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Рассчитать минимальную и максимальную цену из предложений
     */
    private function calculatePrices(ExternalRequestItem $item): array
    {
        $offers = DB::connection('reports')
            ->table('request_item_responses')
            ->where('request_item_id', $item->id)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->select('total_price')
            ->get();

        if ($offers->isEmpty()) {
            return ['min' => null, 'max' => null];
        }

        $prices = $offers->pluck('total_price')->filter();

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
        ];
    }
}
