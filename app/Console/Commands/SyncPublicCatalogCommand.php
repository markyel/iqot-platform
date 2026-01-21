<?php

namespace App\Console\Commands;

use App\Models\ExternalRequestItem;
use App\Models\ExternalOffer;
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

            // Получаем позиции с 3+ предложениями из external БД
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

                    // Считаем реальное количество предложений (received/processed)
                    $realOffersCount = $externalItem->offers()
                        ->whereIn('status', ['received', 'processed'])
                        ->count();

                    // Пропускаем позиции с менее чем 3 предложениями
                    if ($realOffersCount < 3) {
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
                        'offers_count' => $realOffersCount, // Используем реальный подсчет
                        'min_price' => $prices['min'],
                        'max_price' => $prices['max'],
                        'currency' => 'RUB',
                        'is_published' => true,
                        'published_at' => now(),
                        'item_created_at' => $externalItem->created_at, // Дата создания позиции
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

            // Снимаем с публикации позиции с < 3 предложениями
            $unpublished = PublicCatalogItem::where(function($query) {
                $query->where('offers_count', '<', 3)
                      ->orWhere('offers_count', null);
            })->update(['is_published' => false]);

            $this->info("✓ Синхронизация завершена:");
            $this->info("  Создано: {$created}");
            $this->info("  Обновлено: {$updated}");
            $this->info("  Снято с публикации (< 3 предложений): {$unpublished}");
            $this->info("  Пропущено (нет заявки или < 3 предложений): {$skipped}");

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
     * Рассчитать минимальную и максимальную цену из предложений (за единицу)
     */
    private function calculatePrices(ExternalRequestItem $item): array
    {
        // Загружаем предложения и вычисляем цены за единицу в рублях через accessor
        $offers = ExternalOffer::where('request_item_id', $item->id)
            ->whereIn('status', ['received', 'processed'])
            ->whereNotNull('price_per_unit')
            ->where('price_per_unit', '>', 0)
            ->get();

        if ($offers->isEmpty()) {
            return ['min' => null, 'max' => null];
        }

        // Получаем цены за единицу в рублях через accessor price_per_unit_in_rub
        $pricesInRub = $offers->map(function ($offer) {
            return $offer->price_per_unit_in_rub;
        })->filter(function ($price) {
            return $price !== null && $price > 0;
        });

        if ($pricesInRub->isEmpty()) {
            return ['min' => null, 'max' => null];
        }

        return [
            'min' => $pricesInRub->min(),
            'max' => $pricesInRub->max(),
        ];
    }
}
