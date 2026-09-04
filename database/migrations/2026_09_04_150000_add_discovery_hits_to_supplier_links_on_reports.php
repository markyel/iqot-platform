<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Счётчик попаданий дискавери на связях поставщик↔домен и поставщик↔тип продукции
 * (connection=reports): discovery_hits + last_discovery_at.
 *
 * Зачем ОТДЕЛЬНОЕ поле: positive_signals/negative_signals — карма по ОТВЕТАМ
 * поставщика (пишет сбор сигналов из входящих; гейт пула в CampaignSupplierSelector
 * считает строку «доверенной» по positive_signals>0). Складывать туда находки
 * дискавери нельзя — мусорный сайт, всплывший трижды в Яндексе, стал бы «доверенным».
 *
 * Использование (SupplierDiscoveryService): связь с доменом включается в пул либо по
 * AI-подтверждению принадлежности домену (domain_match, confidence ≥ порога), либо
 * когда discovery_hits достигает services.supplier_discovery.domain_hits_to_include.
 * До этого строка живёт с is_included=0 как кандидат.
 */
return new class extends Migration
{
    private const TABLES = ['supplier_domains', 'supplier_product_types'];

    public function up(): void
    {
        $reports = Schema::connection('reports');

        foreach (self::TABLES as $table) {
            if (!$reports->hasTable($table)) {
                continue;
            }
            if (!$reports->hasColumn($table, 'discovery_hits')) {
                $reports->table($table, function (Blueprint $t) {
                    $t->unsignedInteger('discovery_hits')->default(0)->after('negative_signals');
                });
            }
            if (!$reports->hasColumn($table, 'last_discovery_at')) {
                $reports->table($table, function (Blueprint $t) {
                    $t->timestamp('last_discovery_at')->nullable()->after('discovery_hits');
                });
            }
        }
    }

    public function down(): void
    {
        $reports = Schema::connection('reports');

        foreach (self::TABLES as $table) {
            if (!$reports->hasTable($table)) {
                continue;
            }
            foreach (['last_discovery_at', 'discovery_hits'] as $col) {
                if ($reports->hasColumn($table, $col)) {
                    $reports->table($table, function (Blueprint $t) use ($col) {
                        $t->dropColumn($col);
                    });
                }
            }
        }
    }
};
