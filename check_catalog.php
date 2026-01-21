<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PublicCatalogItem;

echo "Всего в каталоге: " . PublicCatalogItem::count() . "\n";
echo "С offers_count = 0: " . PublicCatalogItem::where('offers_count', 0)->count() . "\n";
echo "С offers_count > 0: " . PublicCatalogItem::where('offers_count', '>', 0)->count() . "\n";

echo "\nПримеры позиций:\n";
$items = PublicCatalogItem::limit(5)->get();
foreach ($items as $item) {
    echo "ID: {$item->id}, Name: {$item->name}, Offers: {$item->offers_count}\n";
}
