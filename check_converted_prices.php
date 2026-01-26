<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ExternalRequestItem;
use App\Models\ExternalOffer;

$externalItemId = 402; // GBA26800PM1

$item = ExternalRequestItem::find($externalItemId);

if (!$item) {
    echo "Позиция {$externalItemId} не найдена\n";
    exit;
}

echo "=== Позиция {$externalItemId}: {$item->name} ===\n\n";

// Предложения received/processed
$validOffers = ExternalOffer::where('request_item_id', $externalItemId)
    ->whereIn('status', ['received', 'processed'])
    ->get();

echo "Предложений received/processed: {$validOffers->count()}\n\n";

echo "Анализ цен:\n";
echo str_repeat("-", 120) . "\n";
printf("%-8s %-20s %-20s %-20s %-20s\n", "ID", "total_price", "total_price_in_rub", "price_per_unit", "price_per_unit_in_rub");
echo str_repeat("-", 120) . "\n";

foreach ($validOffers as $offer) {
    printf(
        "%-8s %-20s %-20s %-20s %-20s\n",
        $offer->id,
        number_format((float)$offer->total_price, 2, '.', '') . ' ' . ($offer->currency ?? 'RUB'),
        number_format((float)$offer->total_price_in_rub, 2, '.', '') . ' RUB',
        number_format((float)$offer->price_per_unit, 2, '.', '') . ' ' . ($offer->currency ?? 'RUB'),
        number_format((float)$offer->price_per_unit_in_rub, 2, '.', '') . ' RUB'
    );
}

echo "\n";

// Цены из total_price
$totalPrices = $validOffers->where('total_price', '>', 0)->pluck('total_price');
echo "=== Используя total_price ===\n";
if ($totalPrices->isNotEmpty()) {
    echo "Min: " . number_format((float)$totalPrices->min(), 2, ',', ' ') . " ₽\n";
    echo "Max: " . number_format((float)$totalPrices->max(), 2, ',', ' ') . " ₽\n";
}

echo "\n";

// Цены из total_price_in_rub
$totalPricesInRub = $validOffers->where('total_price_in_rub', '>', 0)->pluck('total_price_in_rub');
echo "=== Используя total_price_in_rub ===\n";
if ($totalPricesInRub->isNotEmpty()) {
    echo "Min: " . number_format((float)$totalPricesInRub->min(), 2, ',', ' ') . " ₽\n";
    echo "Max: " . number_format((float)$totalPricesInRub->max(), 2, ',', ' ') . " ₽\n";
}

echo "\n";

// Цены из price_per_unit_in_rub
$pricePerUnitInRub = $validOffers->where('price_per_unit_in_rub', '>', 0)->pluck('price_per_unit_in_rub');
echo "=== Используя price_per_unit_in_rub ===\n";
if ($pricePerUnitInRub->isNotEmpty()) {
    echo "Min: " . number_format((float)$pricePerUnitInRub->min(), 2, ',', ' ') . " ₽\n";
    echo "Max: " . number_format((float)$pricePerUnitInRub->max(), 2, ',', ' ') . " ₽\n";
}
