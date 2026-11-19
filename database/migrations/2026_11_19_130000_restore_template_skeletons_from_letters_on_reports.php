<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Скелеты шаблонов писем после переделки 07.07.2026 (e195b6f: ai_request вместо вступления/завершения), не
 * сохранившиеся вместе с базой Beget. Восстановлены по нашим письмам, процитированным в ответах поставщиков
 * (ящики-отправители, письма 27.07–29.10.2026): порядок блоков — по письмам, вариант просьбы — по формулировке
 * (kp/availability/ask, примеры — CampaignBodyGenerator::requestGuide). Только шаблоны действующих ящиков,
 * по которым есть письма; 5, 41 остались январскими (так и было), 2, 63, 9, 45 — писем нет.
 * Телефон в подписи шаблона 41 — был в письме 15.10, хотя подпись не full.
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        $req = fn (string $v) => ['type' => 'ai_request', 'variant' => $v];
        $g = ['type' => 'ai_greeting'];
        $items = ['type' => 'items_display'];
        $sig = ['type' => 'signature'];
        $tok = ['type' => 'token'];

        $skeletons = [];
        // приветствие → позиции → просьба → подпись с реквизитами → номер
        foreach ([4 => 'kp', 14 => 'kp'] as $id => $v) {
            $skeletons[$id] = [$g, $items, $req($v), $sig, ['type' => 'company_footer'], $tok];
        }
        // приветствие → просьба → позиции → подпись → номер
        foreach ([42 => 'kp', 67 => 'kp', 44 => 'ask', 30 => 'ask', 6 => 'availability', 12 => 'availability'] as $id => $v) {
            $skeletons[$id] = [$g, $req($v), $items, $sig, $tok];
        }
        // приветствие → вступление → позиции → просьба → подпись → номер
        foreach ([1 => 'kp', 43 => 'kp'] as $id => $v) {
            $skeletons[$id] = [$g, ['type' => 'ai_introduction'], $items, $req($v), $sig, $tok];
        }

        $db = DB::connection($this->conn);
        foreach ($skeletons as $id => $blocks) {
            $db->table('email_templates')->where('id', $id)
                ->update(['blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
        $db->table('email_templates')->where('id', 41)->update(['signature_show_phone' => 1, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Восстановление боевых данных — не откатывается.
    }
};
