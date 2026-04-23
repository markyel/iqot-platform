<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients');
            $table->char('external_id', 26);                               // "sub_01HXYZ..." (ULID-like)
            $table->string('idempotency_key', 128);                        // NOT NULL — сервер генерирует если клиент не прислал
            $table->string('client_ref', 128)->nullable();
            // client_organization_id — логическая ссылка в reports.client_organizations (cross-DB, без FK).
            // Тип int unsigned — совместимость с users.client_organization_id.
            $table->unsignedInteger('client_organization_id')->nullable();
            $table->foreignId('sender_id')->nullable()->constrained('user_senders');
            $table->timestamp('deadline_at')->nullable();

            $table->enum('status', [
                'accepted', 'processing', 'ready',
                'ready_minimum', 'completed', 'cancelled',
            ])->default('accepted');
            $table->string('stage', 40)->default('inbox_buffered');
            $table->timestamp('status_changed_at')->useCurrent();

            // internal_request_id — логическая ссылка на reports.requests.id.
            // Тип int unsigned — совместимость со схемой reports (requests.id int(10) unsigned).
            $table->unsignedInteger('internal_request_id')->nullable();
            $table->timestamp('promoted_at')->nullable();

            $table->unsignedSmallInteger('items_total')->default(0);
            $table->unsignedSmallInteger('items_accepted')->default(0);
            $table->unsignedSmallInteger('items_rejected')->default(0);
            $table->json('rejected_summary')->nullable();

            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();

            $table->timestamps();

            $table->unique('external_id', 'uniq_external');
            $table->unique(['api_client_id', 'idempotency_key'], 'uniq_idempotency');
            $table->index(['status', 'updated_at'], 'idx_status');
            $table->index('internal_request_id', 'idx_internal_request');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_submissions');
    }
};
