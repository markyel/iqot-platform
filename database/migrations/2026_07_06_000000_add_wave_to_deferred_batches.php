<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deferred_batches (на reports): wave — сохранённая волна пина для отсрочек по капасити
 * (sender_capacity) и контейнмента бана (ban_containment). Waves-v2: холодная волна 3
 * (held-резерв) при переносе на другой ящик должна ОСТАТЬСЯ волной 3 (held), а не стать
 * немедленной волной 1. null → перегенерация ставит wave=1 (legacy-поведение).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('reports');
        $schema->table('deferred_batches', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('deferred_batches', 'wave')) {
                $table->unsignedTinyInteger('wave')->nullable()->after('only_supplier_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('reports')->table('deferred_batches', function (Blueprint $table) {
            $table->dropColumn('wave');
        });
    }
};
