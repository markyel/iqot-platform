<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Решение владельца 20.11.2026 (превью писем после восстановления базы):
 *  - реквизиты из писем убраны: подписи full / name_org_contacts (адрес, ИНН, email, сайт) → «имя + организация»,
 *    блок company_footer снят, телефон в подписи выключен везде (в январских данных часть телефонов выдумана —
 *    +74951234567 и т.п.). Реквизиты остаются в базе — их по запросу шлёт автоответ на вопросы поставщика;
 *  - январские шаблоны 8, 2, 9 отключены (двойное приветствие и две просьбы; «приложу список» без вложения и
 *    «рад/рада»; «СРОЧНЫЙ ЗАПРОС», ❗ и капс) — их ящики пересажены на восстановленные шаблоны с одним владельцем.
 */
return new class extends Migration
{
    private string $conn = 'reports';

    /** ящик → новый шаблон (восстановленные скелеты, у каждого до этого по одному ящику) */
    private const REASSIGN = [10 => 44, 38 => 47, 23 => 13, 33 => 38, 17 => 7];

    public function up(): void
    {
        $db = DB::connection($this->conn);
        $now = now();

        $db->table('email_templates')->whereIn('signature_format', ['full', 'name_org_contacts'])
            ->update(['signature_format' => 'name_organization', 'updated_at' => $now]);
        $db->table('email_templates')->update(['signature_show_phone' => 0]);

        foreach ($db->table('email_templates')->where('blocks', 'like', '%company_footer%')->get(['id', 'blocks']) as $t) {
            $blocks = array_values(array_filter((array) json_decode((string) $t->blocks, true),
                static fn ($b) => !is_array($b) || ($b['type'] ?? '') !== 'company_footer'));
            $db->table('email_templates')->where('id', $t->id)
                ->update(['blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE), 'updated_at' => $now]);
        }

        $db->table('email_templates')->whereIn('id', [8, 2, 9])->update(['is_active' => 0, 'updated_at' => $now]);
        foreach (self::REASSIGN as $senderId => $templateId) {
            $db->table('senders')->where('id', $senderId)->whereIn('preferred_template_id', [8, 2, 9])
                ->update(['preferred_template_id' => $templateId, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
    }
};
