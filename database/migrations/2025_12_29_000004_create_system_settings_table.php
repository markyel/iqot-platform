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

        // Seed для стоимости позиции
        DB::table('system_settings')->insert([
            'setting_key' => 'price_per_item',
            'setting_value' => '50.00',
            'setting_type' => 'decimal',
            'description' => 'Стоимость мониторинга одной позиции (руб.)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
