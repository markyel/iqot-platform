<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Флаг авто-приёма green-классифицированных позиций для API-клиента.
 *
 * Когда включён: после inbox-классификации все позиции с trust_level=green
 * сразу переводятся в accepted без участия модератора. Yellow/red остаются
 * в очереди на ручной разбор.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('api_clients', 'auto_approve_green')) {
                $table->boolean('auto_approve_green')->default(false)->after('overdraft_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            if (Schema::hasColumn('api_clients', 'auto_approve_green')) {
                $table->dropColumn('auto_approve_green');
            }
        });
    }
};
