<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Номер запроса (блок token) — в каждом активном шаблоне. Без него в письме нет номера с суффиксом поставщика,
 * и ответ не привязывается к письму (IncomingEmailRouter::matchQueue). На проде 16.10.2026 блок был у всех
 * используемых шаблонов; в дампе 28.01 его нет у шаблона 2, который после восстановления стоит у двух ящиков.
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        $db = DB::connection($this->conn);
        foreach ($db->table('email_templates')->where('is_active', 1)->get(['id', 'blocks']) as $t) {
            $blocks = json_decode((string) $t->blocks, true);
            if (!is_array($blocks)) {
                continue;
            }
            foreach ($blocks as $b) {
                if (is_array($b) && ($b['type'] ?? '') === 'token') {
                    continue 2;
                }
            }
            $blocks[] = ['type' => 'token'];
            $db->table('email_templates')->where('id', $t->id)
                ->update(['blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
    }
};
