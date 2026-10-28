<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Пауза между письмами одного ящика по умолчанию 2 → 20 с (решение владельца 28.10.2026: без всплесков скорости
 * с одного ящика/домена). Новые ящики пула (BulkSenderImporter не задаёт паузу) получают 20 с из DEFAULT колонки.
 * Существующие 2-секундные переведены на 20 разово на проде (бэкап storage/app/backups/senders-delay2-*.json).
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        DB::connection($this->conn)->statement('ALTER TABLE senders ALTER COLUMN send_delay_seconds SET DEFAULT 20');
    }

    public function down(): void
    {
        DB::connection($this->conn)->statement('ALTER TABLE senders ALTER COLUMN send_delay_seconds SET DEFAULT 2');
    }
};
