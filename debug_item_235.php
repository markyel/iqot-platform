<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ExternalRequestItem;
use App\Models\ExternalOffer;

$itemId = 235;
$item = ExternalRequestItem::find($itemId);

if (!$item) {
    echo "Позиция {$itemId} не найдена\n";
    exit;
}

echo "=== Позиция {$itemId} ===\n";
echo "Название: {$item->name}\n\n";

// Все предложения
$allOffers = ExternalOffer::where('request_item_id', $itemId)->get();
echo "Всего предложений: {$allOffers->count()}\n";

// Группировка по статусам
$byStatus = $allOffers->groupBy('status');
foreach ($byStatus as $status => $offers) {
    echo "  {$status}: {$offers->count()}\n";
}

echo "\n";

// Предложения received/processed
$validOffers = ExternalOffer::where('request_item_id', $itemId)
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
}
