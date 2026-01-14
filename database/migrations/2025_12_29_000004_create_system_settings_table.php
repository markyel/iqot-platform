<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->text('setting_value')->nullable();
            $table->enum('setting_type', ['string', 'integer', 'decimal', 'boolean', 'json'])->default('string');
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        // Seed начальных настроек
        $settings = [
            [
                'setting_key' => 'price_per_item',
                'setting_value' => '50.00',
                'setting_type' => 'decimal',
                'description' => 'Стоимость мониторинга одной позиции (руб.)',
            ],
            [
                'setting_key' => 'pricing_monitoring',
                'setting_value' => '396.00',
                'setting_type' => 'decimal',
                'description' => 'Стоимость мониторинга позиции для разовых операций (лендинг)',
            ],
            [
                'setting_key' => 'pricing_report_unlock',
                'setting_value' => '99.00',
                'setting_type' => 'decimal',
                'description' => 'Стоимость разблокировки отчета для разовых операций (лендинг)',
            ],
            [
                'setting_key' => 'subscription_basic_price',
                'setting_value' => '5000.00',
                'setting_type' => 'decimal',
                'description' => 'Базовый тариф: стоимость подписки в месяц',
            ],
            [
                'setting_key' => 'subscription_basic_positions',
                'setting_value' => '15',
                'setting_type' => 'integer',
                'description' => 'Базовый тариф: количество позиций в месяц',
            ],
            [
                'setting_key' => 'subscription_basic_reports',
                'setting_value' => '5',
                'setting_type' => 'integer',
                'description' => 'Базовый тариф: количество отчетов в месяц',
            ],
            [
                'setting_key' => 'subscription_basic_overlimit_position',
                'setting_value' => '300.00',
                'setting_type' => 'decimal',
                'description' => 'Базовый тариф: стоимость позиции сверх лимита',
            ],
            [
                'setting_key' => 'subscription_basic_overlimit_report',
                'setting_value' => '89.00',
                'setting_type' => 'decimal',
                'description' => 'Базовый тариф: стоимость отчета сверх лимита',
            ],
            [
                'setting_key' => 'subscription_advanced_price',
                'setting_value' => '15000.00',
                'setting_type' => 'decimal',
                'description' => 'Расширенный тариф: стоимость подписки в месяц',
            ],
            [
                'setting_key' => 'subscription_advanced_positions',
                'setting_value' => '50',
                'setting_type' => 'integer',
                'description' => 'Расширенный тариф: количество позиций в месяц',
            ],
            [
                'setting_key' => 'subscription_advanced_reports',
                'setting_value' => '15',
                'setting_type' => 'integer',
                'description' => 'Расширенный тариф: количество отчетов в месяц',
            ],
            [
                'setting_key' => 'subscription_advanced_overlimit_position',
                'setting_value' => '270.00',
                'setting_type' => 'decimal',
                'description' => 'Расширенный тариф: стоимость позиции сверх лимита',
            ],
            [
                'setting_key' => 'subscription_advanced_overlimit_report',
                'setting_value' => '79.00',
                'setting_type' => 'decimal',
                'description' => 'Расширенный тариф: стоимость отчета сверх лимита',
            ],
            [
                'setting_key' => 'subscription_pro_price',
                'setting_value' => '50000.00',
                'setting_type' => 'decimal',
                'description' => 'Профессиональный тариф: стоимость подписки в месяц',
            ],
            [
                'setting_key' => 'subscription_pro_positions',
                'setting_value' => '200',
                'setting_type' => 'integer',
                'description' => 'Профессиональный тариф: количество позиций в месяц',
            ],
            [
                'setting_key' => 'subscription_pro_reports',
                'setting_value' => '50',
                'setting_type' => 'integer',
                'description' => 'Профессиональный тариф: количество отчетов в месяц',
            ],
            [
                'setting_key' => 'subscription_pro_overlimit_position',
                'setting_value' => '240.00',
                'setting_type' => 'decimal',
                'description' => 'Профессиональный тариф: стоимость позиции сверх лимита',
            ],
            [
                'setting_key' => 'subscription_pro_overlimit_report',
                'setting_value' => '69.00',
                'setting_type' => 'decimal',
                'description' => 'Профессиональный тариф: стоимость отчета сверх лимита',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->insert([
                'setting_key' => $setting['setting_key'],
                'setting_value' => $setting['setting_value'],
                'setting_type' => $setting['setting_type'],
                'description' => $setting['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
