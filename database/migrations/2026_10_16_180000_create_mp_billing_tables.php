<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Тарификация мониторинга (docs/monitoring-design.md §11a, Ф4).
 * mp_billing_days — суточное начисление программы: покрытые позиции (web / со свежим КП), включённые в план, сумма.
 * mp_billing_charges — списание с баланса за накопленные дни (еженедельно и в конце периода подписки).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mp_billing_charges')) {
            Schema::create('mp_billing_charges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id');
                $table->foreignId('program_id');
                $table->date('period_from');
                $table->date('period_to');
                $table->unsignedSmallInteger('days');
                $table->decimal('amount', 12, 2);
                $table->json('detail')->nullable();
                $table->dateTime('charged_at');
                $table->timestamps();
                $table->index(['user_id', 'charged_at']);
            });
        }
        if (!Schema::hasTable('mp_billing_days')) {
            Schema::create('mp_billing_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id');
                $table->foreignId('user_id');
                $table->date('day');
                $table->unsignedInteger('covered_web')->default(0);   // покрытые без свежего КП
                $table->unsignedInteger('covered_kp')->default(0);    // покрытые со свежим КП (ставка ×kp_multiplier)
                $table->unsignedInteger('included_web')->default(0);  // из них в абонплате плана
                $table->unsignedInteger('included_kp')->default(0);
                $table->decimal('rate', 10, 2);                       // ₽ за позицию в месяц (план)
                $table->decimal('kp_multiplier', 5, 2);
                $table->decimal('amount', 12, 2);                     // начислено за сутки
                $table->foreignId('charge_id')->nullable();           // mp_billing_charges.id после списания
                $table->timestamps();
                $table->unique(['program_id', 'day']);
                $table->index(['user_id', 'charge_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_billing_days');
        Schema::dropIfExists('mp_billing_charges');
    }
};
