<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен (Ф0): атомарный лимит частоты запросов к домену (вежливый обход).
 * Резерв слота — CAS-UPDATE по next_allowed_at (как reserveSlot у ящиков-отправителей),
 * вместо usleep + Cache в Content Engine (неатомарно и блокирует воркер).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_domain_slots')) {
            return;
        }

        Schema::create('mp_domain_slots', function (Blueprint $table) {
            $table->string('domain', 191)->primary();
            $table->dateTime('next_allowed_at', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_domain_slots');
    }
};
