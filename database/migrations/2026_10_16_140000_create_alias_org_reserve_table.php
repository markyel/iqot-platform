<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Резерв организаций для экспериментального алгоритма «ящик на батч» (docs/alias-mailbox-design.md).
 * Загружается Excel ExportBase на /manage/senders/import (вкладка «Резерв организаций»); для каждого
 * нового ящика берётся самая давно использованная организация (повторно можно — решение владельца 16.10.2026).
 * БД iqot (не reports): это только резерв, в боевые client_organizations организация попадает при создании ящика.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alias_org_reserve')) {
            return;
        }
        Schema::create('alias_org_reserve', function (Blueprint $table) {
            $table->id();
            $table->string('inn', 12)->unique();
            $table->string('kpp', 9)->nullable();
            $table->string('ogrn', 15)->nullable();
            $table->string('name', 500);
            $table->string('full_name', 1000)->nullable();
            $table->string('okved', 255)->nullable();
            $table->text('legal_address')->nullable();
            $table->string('city', 255)->nullable();
            $table->string('region', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('director_name', 255)->nullable();
            $table->string('director_position', 255)->nullable();
            $table->string('source_file', 255)->nullable();
            $table->boolean('active')->default(true);              // выключить вручную (ликвидирована и т.п.)
            $table->unsignedInteger('uses')->default(0);           // сколько ящиков на ней создано
            $table->dateTime('last_used_at')->nullable();          // для выбора «самой давно использованной»
            $table->unsignedInteger('live_mailboxes')->default(0); // ящиков на ней живо сейчас (свободные — вперёд)
            $table->timestamps();
            $table->index(['active', 'live_mailboxes', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alias_org_reserve');
    }
};
