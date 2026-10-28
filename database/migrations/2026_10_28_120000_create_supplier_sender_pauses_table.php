<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Паузы по отписке на пару «поставщик × наш ящик / наш почтовый домен» (решение владельца 28.10.2026, вместо
 * паузы и недельного интервала на поставщика целиком): сигнал «не пишите» — пауза с ящика, на который ответили;
 * повторный сигнал на тот же домен — пауза на домен; каждое повторение — пауза на неделю длиннее.
 * pause_key: «m:<sender_id>» (ящик) или «d:<домен>» (домен).
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        if (Schema::connection($this->conn)->hasTable('supplier_sender_pauses')) {
            return;
        }
        Schema::connection($this->conn)->create('supplier_sender_pauses', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('supplier_id');
            $table->string('recipient_email', 255);
            $table->string('scope', 10);
            $table->string('pause_key', 255);
            $table->unsignedInteger('sender_id')->nullable();
            $table->string('sender_domain', 255);
            $table->unsignedSmallInteger('signals')->default(0);
            $table->dateTime('paused_until');
            $table->dateTime('last_signal_at');
            $table->string('last_reason', 255)->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'pause_key']);
            $table->index(['recipient_email', 'paused_until']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('supplier_sender_pauses');
    }
};
