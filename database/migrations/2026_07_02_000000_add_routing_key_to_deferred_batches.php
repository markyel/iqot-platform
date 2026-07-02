<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deferred_batches (на reports) получает product_type_id/domain_id — ключ маршрутизации
 * отложенного анонимного батча. Нужен для накопительной отсрочки по загрузке получателей
 * (reason='recipient_load', status='accumulating'): команда emails:process-load-deferred
 * группирует накопители по (product_type_id, domain_id) и выпускает, когда набралось
 * TARGET однородных позиций / пул разгрузился / истёк макс-срок.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('reports')->table('deferred_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('product_type_id')->nullable()->after('sender_id');
            $table->unsignedBigInteger('domain_id')->nullable()->after('product_type_id');
            $table->index(['reason', 'status', 'product_type_id', 'domain_id'], 'idx_deferred_load_group');
        });
    }

    public function down(): void
    {
        Schema::connection('reports')->table('deferred_batches', function (Blueprint $table) {
            $table->dropIndex('idx_deferred_load_group');
            $table->dropColumn(['product_type_id', 'domain_id']);
        });
    }
};
