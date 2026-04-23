<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-миграция: переносит users.sender_id + users.client_organization_id в
 * user_senders с is_default=1.
 *
 * Оригинальные колонки users.sender_id / users.client_organization_id НЕ
 * удаляются — остаются как legacy для совместимости с существующими сервисами
 * (N8nSenderService, UserSenderController). Новое API работает через user_senders.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('users')
            ->whereNotNull('sender_id')
            ->select('id', 'sender_id', 'client_organization_id')
            ->get();

        $now = now();

        foreach ($rows as $row) {
            DB::table('user_senders')->updateOrInsert(
                [
                    'user_id' => $row->id,
                    'client_organization_id' => $row->client_organization_id,
                ],
                [
                    'external_sender_id' => $row->sender_id,
                    'is_active' => true,
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Удаляем только default-записи, созданные этой миграцией.
        DB::table('user_senders')->where('is_default', true)->delete();
    }
};
