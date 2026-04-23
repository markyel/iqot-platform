<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_staging', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_submission_id')->constrained('api_submissions')->onDelete('cascade');
            $table->enum('stage', [
                'awaiting_moderation',
                'in_moderation',
                'moderation_done',
                'awaiting_suppliers',
                'pool_ready',
                'dispatching',
                'finalised',
            ])->default('awaiting_moderation');
            $table->timestamps();

            $table->unique('api_submission_id', 'uniq_submission');
            $table->index(['stage', 'updated_at'], 'idx_stage');
        });

        Schema::create('request_items_staging', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_staging_id')->constrained('request_staging')->onDelete('cascade');
            $table->string('client_item_ref', 128)->nullable();
            $table->unsignedSmallInteger('position_number');
            $table->string('name', 500);
            $table->string('article', 255)->nullable();
            $table->string('brand', 255)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 32);
            $table->text('description')->nullable();
            $table->foreignId('client_category_id')->nullable()->constrained('client_categories');

            // Результаты классификации. product_type_id / domain_id — логические ссылки в reports.
            // Типы int signed — совместимость с reports.product_types.id / application_domains.id (int(11)).
            $table->integer('product_type_id')->nullable();
            $table->integer('domain_id')->nullable();
            $table->decimal('type_confidence', 3, 2)->nullable();
            $table->decimal('domain_confidence', 3, 2)->nullable();
            $table->enum('classification_source', [
                'manual_mapping', 'mini_classifier', 'full_ai', 'moderator',
            ])->nullable();
            $table->boolean('needs_review')->default(true);
            $table->enum('trust_level', ['green', 'yellow', 'red'])->default('red');

            $table->enum('item_status', [
                'pending',
                'classified',
                'accepted',
                'awaiting_suppliers',
                'pool_ready',
                'promoted',
                'rejected',
            ])->default('pending');
            $table->string('rejection_reason', 64)->nullable();
            $table->text('rejection_message')->nullable();

            // Биллинг.
            $table->foreignId('balance_hold_id')->nullable()->constrained('balance_holds');

            // Связь с боевой после промоушена. reports.request_items.id — логическая ссылка.
            // Тип int unsigned — совместимость с reports.request_items.id (int(10) unsigned).
            $table->unsignedInteger('promoted_request_item_id')->nullable();

            $table->timestamps();

            $table->index(['request_staging_id', 'item_status'], 'idx_staging_status');
            $table->index(['item_status', 'domain_id', 'product_type_id'], 'idx_awaiting');
            $table->index(['item_status', 'trust_level'], 'idx_classification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_items_staging');
        Schema::dropIfExists('request_staging');
    }
};
