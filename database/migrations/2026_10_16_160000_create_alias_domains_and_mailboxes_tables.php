<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Алгоритм «ящик на батч» (docs/alias-mailbox-design.md, Ф2), БД iqot.
 * alias_domains — пул доменов эксперимента: общий ящик (info@), куда пересылается почта всех ящиков домена
 *   и катч-олл (письма на удалённые ящики); его IMAP-пароль — зашифрован (Crypt).
 * alias_mailboxes — карта «адрес → батч»: адрес ящика и есть метка рассылки; строка остаётся после удаления
 *   ящика, чтобы поздние ответы (через катч-олл) опознавались.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('alias_domains')) {
            Schema::create('alias_domains', function (Blueprint $table) {
                $table->id();
                $table->string('domain', 255)->unique();
                $table->string('inbox', 64)->default('info');       // общий ящик домена (local part)
                $table->text('inbox_password')->nullable();          // encrypted
                $table->boolean('catch_all')->default(false);
                $table->boolean('active')->default(true);
                $table->unsignedInteger('mailboxes_created')->default(0);
                $table->dateTime('last_used_at')->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('alias_mailboxes')) {
            Schema::create('alias_mailboxes', function (Blueprint $table) {
                $table->id();
                $table->string('email', 255)->unique();
                $table->foreignId('domain_id');
                $table->unsignedInteger('sender_id')->nullable();          // reports.senders.id
                $table->unsignedInteger('client_organization_id')->nullable(); // reports.client_organizations.id
                $table->foreignId('org_reserve_id')->nullable();           // alias_org_reserve.id
                $table->json('batch_ids')->nullable();                     // reports.email_batches.id
                $table->json('request_item_ids')->nullable();
                $table->string('status', 16)->default('active');           // active | expired | dropped | failed
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('dropped_at')->nullable();
                $table->string('error', 500)->nullable();
                $table->timestamps();
                $table->index(['status', 'expires_at']);
                $table->index('sender_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alias_mailboxes');
        Schema::dropIfExists('alias_domains');
    }
};
