<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Правки шаблонов писем (reports.email_templates) после 28.01.2026, потерянные вместе с базой Beget (02.11.2026).
 * Делались на проде скриптами, без миграций; база восстановлена из дампа 28.01. Здесь — то, что известно точно
 * (снимки прода в сохранившихся сессиях 04.09–23.10.2026):
 *  - темы всех 47 активных шаблонов (08.07, 20b46ed: естественные обёртки, без эмодзи и «СРОЧНО») — дословно с 16.10;
 *  - тон 5 шаблонов (между 28.01 и 04.09);
 *  - телефон в подписи только у формата full (09.07, 9254583);
 *  - скелеты блоков: 7 и 32 — дословно; 11 шаблонов «приветствие → список → просьба» — порядок с 23.10,
 *    вариант просьбы не сохранился, ставим умолчание кода kp.
 * Скелеты остальных шаблонов после 07.07 (ai_request вместо вступления/завершения, found_hint) не сохранились —
 * остаются январскими. Повторный прогон безопасен.
 */
return new class extends Migration
{
    private string $conn = 'reports';

    private const SUBJECTS = [
        'Запрос: {{item_name}} ({{tracking_token}})' => [1, 13, 49, 61],
        'Заявка {{tracking_token}} — {{item_name}}' => [2, 14, 38, 50, 62],
        '{{item_name}} ({{tracking_token}})' => [3, 39, 51, 63],
        '{{item_name}} — цена? {{tracking_token}}' => [4, 40, 52, 64],
        'Запрос цены — {{item_name}} {{tracking_token}}' => [5, 41, 53, 65],
        '{{item_name}}, заявка {{tracking_token}}' => [6, 30, 42, 54, 66],
        '{{tracking_token}} · {{item_name}}' => [7, 43, 55, 67],
        '{{item_name}} №{{tracking_token}}' => [8, 32, 44, 56],
        'Заявка на {{item_name}} ({{tracking_token}})' => [9, 45, 57],
        '{{item_name}} — запрос {{tracking_token}}' => [10, 46, 58],
        '{{item_name}} {{tracking_token}}' => [11, 47, 59],
        '{{item_name}} — {{tracking_token}}' => [12, 36, 48, 60],
    ];

    private const TONES = ['neutral' => [3, 51], 'polite' => [5, 9, 44]];

    /** Приветствие → список → просьба (CampaignBodyGenerator::requestAfterList). */
    private const REQUEST_AFTER_LIST = [3, 10, 11, 13, 32, 38, 46, 47, 51, 53, 62, 65];

    public function up(): void
    {
        $db = DB::connection($this->conn);
        $now = now();

        foreach (self::SUBJECTS as $subject => $ids) {
            $db->table('email_templates')->whereIn('id', $ids)->update(['subject_template' => $subject, 'updated_at' => $now]);
        }
        foreach (self::TONES as $tone => $ids) {
            $db->table('email_templates')->whereIn('id', $ids)->update(['ai_tone' => $tone, 'updated_at' => $now]);
        }
        if (Schema::connection($this->conn)->hasColumn('email_templates', 'signature_show_phone')) {
            $db->table('email_templates')->update(['signature_show_phone' => $db->raw("signature_format = 'full'")]);
        }

        $afterList = [['type' => 'ai_greeting'], ['type' => 'items_display'], ['type' => 'ai_request', 'variant' => 'kp'],
            ['type' => 'signature'], ['type' => 'token']];
        $db->table('email_templates')->whereIn('id', self::REQUEST_AFTER_LIST)
            ->update(['blocks' => json_encode($afterList, JSON_UNESCAPED_UNICODE), 'updated_at' => $now]);
        $db->table('email_templates')->where('id', 7)->update(['blocks' => json_encode([
            ['type' => 'static_header'], ['type' => 'ai_greeting'], ['type' => 'ai_request', 'variant' => 'kp'],
            ['type' => 'items_display'], ['type' => 'signature'], ['type' => 'company_footer'], ['type' => 'token'],
        ], JSON_UNESCAPED_UNICODE), 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Восстановление боевых данных — не откатывается.
    }
};
