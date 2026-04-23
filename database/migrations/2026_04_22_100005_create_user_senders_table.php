<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Создаёт таблицу user_senders для поддержки multi-sender (§3.12 / §9.3).
 *
 * Рациональное расширение спеки: поле external_sender_id хранит ID отправителя
 * во внешней системе n8n (текущая реализация N8nSenderService). Это необходимо
 * для совместимости с существующим SMTP-пайплайном.
 *
 * Инвариант: у одного user_id максимум один sender с is_default=1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_senders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // client_organization_id — логическая ссылка в reports.client_organizations (cross-DB).
            // Тип int unsigned выбран для совместимости с users.client_organization_id.
            $table->unsignedInteger('client_organization_id')->nullable();
            // external_sender_id — ID отправителя в n8n (legacy integration, матчится с users.sender_id).
            $table->unsignedInteger('external_sender_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Уникальность: на пару (user_id, client_organization_id) только один sender.
            // Для записей без организации (client_organization_id=NULL) уникальность не гарантируется;
            // контролируется приложением.
            $table->unique(['user_id', 'client_organization_id'], 'uniq_user_org');
            $table->index(['user_id', 'is_default'], 'idx_user_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_senders');
    }
};
