<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tariff_plan_id')->constrained()->onDelete('cascade');
            $table->timestamp('started_at'); // Дата начала действия тарифа
            $table->timestamp('expires_at')->nullable(); // Дата окончания (для ежемесячных тарифов)
            $table->integer('items_used')->default(0); // Использовано позиций за текущий период
            $table->integer('reports_used')->default(0); // Использовано отчетов за текущий период
            $table->boolean('is_active')->default(true); // Активен ли тариф
            $table->timestamp('last_charged_at')->nullable(); // Дата последнего списания
            $table->timestamps();

            // Индекс для быстрого поиска активного тарифа пользователя
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tariffs');
    }
};
