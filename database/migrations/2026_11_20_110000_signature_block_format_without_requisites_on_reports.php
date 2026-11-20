<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Дополнение к 2026_11_20_100000: у январских скелетов формат подписи записан и в самом блоке
 * ({"type":"signature","format":"full",…}) — он перекрывает email_templates.signature_format, и реквизиты
 * (адрес, ИНН, email) оставались в письме (превью шаблона 45). Формат блока full / name_org_contacts →
 * name_organization; явный show_phone в стиле блока — выкл.
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        $db = DB::connection($this->conn);
        foreach ($db->table('email_templates')->where('blocks', 'like', '%"signature"%')->get(['id', 'blocks']) as $t) {
            $blocks = json_decode((string) $t->blocks, true);
            if (!is_array($blocks)) {
                continue;
            }
            $changed = false;
            foreach ($blocks as &$b) {
                if (!is_array($b) || ($b['type'] ?? '') !== 'signature') {
                    continue;
                }
                if (in_array($b['format'] ?? null, ['full', 'name_org_contacts'], true)) {
                    $b['format'] = 'name_organization';
                    $changed = true;
                }
                if (is_array($b['style'] ?? null) && !empty($b['style']['show_phone'])) {
                    $b['style']['show_phone'] = false;
                    $changed = true;
                }
            }
            unset($b);
            if ($changed) {
                $db->table('email_templates')->where('id', $t->id)
                    ->update(['blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
    }
};
