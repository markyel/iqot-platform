<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * reports.requests.source += 'monitoring' — техническая заявка программы мониторинга (Ф3
 * «Гибрид», решение владельца 2026-10-15): её позиции — товары на проверочный КП, письма
 * адресные (конкретному поставщику, с ценой его сайта). Исключена из пулов плана дня,
 * интентов, генерации v1, публичного каталога и кабинета (фильтр source != 'monitoring').
 * Схема reports создана вне Laravel — правим ENUM условно, только добавляя значение.
 */
return new class extends Migration
{
    protected $connection = 'reports';

    public function up(): void
    {
        if (!Schema::connection('reports')->hasColumn('requests', 'source')) {
            return;
        }
        $col = DB::connection('reports')->selectOne("SHOW COLUMNS FROM requests WHERE Field = 'source'");
        if ($col && !str_contains((string) $col->Type, "'monitoring'")) {
            DB::connection('reports')->statement("ALTER TABLE requests MODIFY source ENUM('web','admin','api','monitoring') NOT NULL DEFAULT 'web'");
        }
    }

    public function down(): void
    {
        $col = DB::connection('reports')->selectOne("SHOW COLUMNS FROM requests WHERE Field = 'source'");
        if ($col && str_contains((string) $col->Type, "'monitoring'")
            && !DB::connection('reports')->table('requests')->where('source', 'monitoring')->exists()) {
            DB::connection('reports')->statement("ALTER TABLE requests MODIFY source ENUM('web','admin','api') NOT NULL DEFAULT 'web'");
        }
    }
};
