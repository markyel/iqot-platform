<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PublicCatalogItem;
use App\Models\ExternalRequestItem;
use App\Models\ExternalOffer;

$searchName = 'GBA26800PM1';

echo "=== Поиск позиции с названием содержащим '{$searchName}' ===\n\n";

$catalogItems = PublicCatalogItem::where('name', 'like', '%'.$searchName.'%')->get();

if ($catalogItems->isEmpty()) {
    echo "Позиции не найдены в публичном каталоге\n";
    exit;
}

foreach ($catalogItems as $catalogItem) {
    echo "Публичный каталог ID: {$catalogItem->id}\n";
    echo "Название: {$catalogItem->name}\n";
    echo "External Item ID: {$catalogItem->external_item_id}\n";
    echo "Offers Count (в каталоге): {$catalogItem->offers_count}\n";
    echo "Min Price (в каталоге): " . number_format((float)$catalogItem->min_price, 2, ',', ' ') . " ₽\n";
    echo "Max Price (в каталоге): " . number_format((float)$catalogItem->max_price, 2, ',', ' ') . " ₽\n";

    echo "\n=== Проверка в External БД ===\n";

    $externalItemId = $catalogItem->external_item_id;
    $item = ExternalRequestItem::find($externalItemId);

    if (!$item) {
        echo "External позиция {$externalItemId} не найдена\n";
        continue;
    }

    // Все предложения
    $allOffers = ExternalOffer::where('request_item_id', $externalItemId)->get();
    echo "Всего предложений: {$allOffers->count()}\n";

    // Группировка по статусам
    $byStatus = $allOffers->groupBy('status');
    foreach ($byStatus as $status => $offers) {
        echo "  {$status}: {$offers->count()}\n";
    }

    echo "\n";

    // Предложения received/processed
    $validOffers = ExternalOffer::where('request_item_id', $externalItemId)
        ->whereIn('status', ['received', 'processed'])
        ->get();

    echo "Предложений received/processed: {$validOffers->count()}\n\n";

    // Цены всех валидных предложений
    echo "Цены валидных предложений:\n";
    foreach ($validOffers as $offer) {
        $price = $offer->total_price;
        $priceFormatted = number_format((float)$price, 2, ',', ' ');
        echo "  ID {$offer->id}: {$priceFormatted} ₽ (статус: {$offer->status})\n";
    }

    // Фильтрация по цене > 0
    $pricesFiltered = $validOffers->where('total_price', '>', 0)->pluck('total_price');
    echo "\nЦены > 0: {$pricesFiltered->count()} шт.\n";
    if ($pricesFiltered->isNotEmpty()) {
        $min = $pricesFiltered->min();
        $max = $pricesFiltered->max();
        echo "Реальный Min: " . number_format((float)$min, 2, ',', ' ') . " ₽\n";
        echo "Реальный Max: " . number_format((float)$max, 2, ',', ' ') . " ₽\n";

        if ((float)$catalogItem->min_price != (float)$min || (float)$catalogItem->max_price != (float)$max) {
            echo "\n⚠️ НЕСООТВЕТСТВИЕ ДАННЫХ!\n";
            echo "Каталог: от " . number_format((float)$catalogItem->min_price, 2, ',', ' ') . " ₽ до " . number_format((float)$catalogItem->max_price, 2, ',', ' ') . " ₽\n";
            echo "Реально: от " . number_format((float)$min, 2, ',', ' ') . " ₽ до " . number_format((float)$max, 2, ',', ' ') . " ₽\n";
        } else {
            echo "\n✓ Данные совпадают\n";
        }
    }

    echo "\n" . str_repeat("=", 80) . "\n\n";
}
