<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_category_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_category_id')->constrained('client_categories')->onDelete('cascade');
            // product_type_id / domain_id — логические ссылки в reports.product_types / application_domains.
            // Cross-DB FK невозможны в MySQL, валидация на уровне приложения (спека §3.3).
            // Тип int signed — совместимость с реальной схемой reports (product_types.id int(11), application_domains.id int(11)).
            $table->integer('product_type_id');
            $table->integer('domain_id')->nullable();
            $table->unsignedTinyInteger('priority')->default(1);
            $table->decimal('confidence', 3, 2)->default(0.80);
            $table->enum('source', ['manual', 'ai_suggested', 'learned']);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->unique(['client_category_id', 'product_type_id', 'domain_id'], 'uniq_candidate');
            $table->index(['client_category_id', 'is_active'], 'idx_category_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_category_candidates');
    }
};
