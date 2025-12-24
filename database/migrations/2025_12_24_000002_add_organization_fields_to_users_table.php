<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'organization')) {
                $table->string('organization')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'inn')) {
                $table->string('inn', 12)->nullable()->after('organization');
            }
            if (!Schema::hasColumn('users', 'kpp')) {
                $table->string('kpp', 9)->nullable()->after('inn');
            }
            // phone уже существует в таблице, не добавляем
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'organization')) {
                $table->dropColumn('organization');
            }
            if (Schema::hasColumn('users', 'inn')) {
                $table->dropColumn('inn');
            }
            if (Schema::hasColumn('users', 'kpp')) {
                $table->dropColumn('kpp');
            }
            // phone не удаляем, т.к. был добавлен не этой миграцией
        });
    }
};
