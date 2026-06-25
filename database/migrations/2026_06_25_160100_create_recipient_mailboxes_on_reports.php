<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Таблица recipient_mailboxes на connection=reports — учёт ошибок отправки по
 * ящику получателя (ключ — нормализованный to_email).
 *
 * Логика (см. App\Models\Reports\RecipientMailbox, App\Jobs\SendQueuedEmailJob):
 * каждая неуспешная (не ratelimit) отправка инкрементит consecutive_errors;
 * успешная — сбрасывает в 0. При достижении порога (services.email_dispatch.
 * recipient_error_threshold, дефолт 3) ящик помечается is_blocked=1, и диспетчер
 * перестаёт ставить ему письма (сразу пропускает). Разблокировка — ручная.
 */
return new class extends Migration
{
    public function up(): void
    {
        $reports = Schema::connection('reports');

        if ($reports->hasTable('recipient_mailboxes')) {
            return;
        }

        $reports->create('recipient_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();                  // нормализованный lowercase to_email
            $table->unsignedInteger('consecutive_errors')->default(0);
            $table->boolean('is_blocked')->default(false)->index();
            $table->string('last_error_message', 255)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('reports')->dropIfExists('recipient_mailboxes');
    }
};
