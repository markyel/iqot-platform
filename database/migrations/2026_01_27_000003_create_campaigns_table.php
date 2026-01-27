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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('html_template');
            $table->json('field_mapping')->nullable(); // Маппинг полей шаблона к колонкам
            $table->enum('status', ['draft', 'sending', 'completed', 'failed'])->default('draft');
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('delay_seconds')->default(2); // Задержка между письмами
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->json('data'); // Все данные для подстановки в шаблон
            $table->enum('status', ['pending', 'sent', 'failed', 'unsubscribed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
