<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ветка «ящик на батч» не только на Beget (20.11.2026): поставщик ящиков домена и его серверы SMTP/IMAP
 * (host:port). Пусто — как раньше: Beget, services.alias_exp.smtp/imap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alias_domains', function (Blueprint $table) {
            if (!Schema::hasColumn('alias_domains', 'provider')) {
                $table->string('provider', 16)->default('beget')->after('domain');
            }
            if (!Schema::hasColumn('alias_domains', 'smtp')) {
                $table->string('smtp', 120)->nullable()->after('inbox_password');
            }
            if (!Schema::hasColumn('alias_domains', 'imap')) {
                $table->string('imap', 120)->nullable()->after('smtp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('alias_domains', function (Blueprint $table) {
            $table->dropColumn(['provider', 'smtp', 'imap']);
        });
    }
};
