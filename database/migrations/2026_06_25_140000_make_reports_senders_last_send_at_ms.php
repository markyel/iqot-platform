<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Миллисекундная точность senders.last_send_at для строгого «замка интервала».
 *
 * При секундной точности floor у NOW()/last_send_at давал off-by-one: слот,
 * занятый в x.999s, округлялся до x, и следующее письмо уходило уже через ~1.0s
 * вместо send_delay_seconds. TIMESTAMP(3) + сравнение с NOW(3) убирают зазор —
 * реальный интервал между письмами одного ящика >= delay (с точностью до мс).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('reports')->hasColumn('senders', 'last_send_at')) {
            DB::connection('reports')->statement(
                'ALTER TABLE senders MODIFY last_send_at TIMESTAMP(3) NULL DEFAULT NULL'
            );
        }
    }

    public function down(): void
    {
        if (Schema::connection('reports')->hasColumn('senders', 'last_send_at')) {
            DB::connection('reports')->statement(
                'ALTER TABLE senders MODIFY last_send_at TIMESTAMP NULL DEFAULT NULL'
            );
        }
    }
};
