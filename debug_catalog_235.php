<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PublicCatalogItem;
use App\Models\ExternalRequestItem;
use App\Models\ExternalOffer;

$catalogId = 235;
$catalogItem = PublicCatalogItem::find($catalogId);

if (!$catalogItem) {
    echo "Позиция {$catalogId} не найдена в публичном каталоге\n";
    exit;
}

echo "=== Публичный каталог ID {$catalogId} ===\n";
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
    exit;
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
    echo "Min: " . number_format((float)$min, 2, ',', ' ') . " ₽\n";
    echo "Max: " . number_format((float)$max, 2, ',', ' ') . " ₽\n";

    echo "\n=== Сравнение ===\n";
    echo "Каталог Min: " . number_format((float)$catalogItem->min_price, 2, ',', ' ') . " ₽\n";
    echo "Реальный Min: " . number_format((float)$min, 2, ',', ' ') . " ₽\n";
    echo "Каталог Max: " . number_format((float)$catalogItem->max_price, 2, ',', ' ') . " ₽\n";
    echo "Реальный Max: " . number_format((float)$max, 2, ',', ' ') . " ₽\n";
}
