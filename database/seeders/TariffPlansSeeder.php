<?php

namespace Database\Seeders;

use App\Models\TariffPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TariffPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tariffs = [
            [
                'code' => 'start',
                'name' => 'Старт',
                'description' => 'Базовый тариф без абонентской платы. Оплата за каждую обработанную позицию.',
                'monthly_price' => 0,
                'items_limit' => null, // Безлимит
                'reports_limit' => null, // Безлимит
                'price_per_item_over_limit' => 50, // Цена за позицию (используется для всех позиций)
                'price_per_report_over_limit' => 100, // Цена за открытие отчета
                'features' => [],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'basic',
                'name' => 'Базовый',
                'description' => 'Включает 15 позиций в заявках и 5 отчетов в месяц. Сверх лимита — по 40 ₽/позиция.',
                'monthly_price' => 1500,
                'items_limit' => 15,
                'reports_limit' => 5,
                'price_per_item_over_limit' => 40,
                'price_per_report_over_limit' => 80,
                'features' => [],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'extended',
                'name' => 'Расширенный',
                'description' => 'Включает 50 позиций в заявках и 15 отчетов в месяц. Сверх лимита — по 35 ₽/позиция.',
                'monthly_price' => 3500,
                'items_limit' => 50,
                'reports_limit' => 15,
                'price_per_item_over_limit' => 35,
                'price_per_report_over_limit' => 70,
                'features' => [],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'professional',
                'name' => 'Профессиональный',
                'description' => 'Включает 200 позиций в заявках и 50 отчетов в месяц. Сверх лимита — по 30 ₽/позиция.',
                'monthly_price' => 10000,
                'items_limit' => 200,
                'reports_limit' => 50,
                'price_per_item_over_limit' => 30,
                'price_per_report_over_limit' => 60,
                'features' => [],
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($tariffs as $tariff) {
            TariffPlan::updateOrCreate(
                ['code' => $tariff['code']],
                $tariff
            );
        }

        $this->command->info('Tariff plans seeded successfully!');
    }
}
