<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique(); // REQ-20251216-0001
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', [
                'draft',      // Черновик
                'pending',    // Ожидает отправки
                'sending',    // Отправка запросов
                'collecting', // Сбор ответов
                'completed',  // Завершена
                'cancelled',  // Отменена
            ])->default('draft');
            $table->integer('items_count')->default(0);
            $table->integer('suppliers_count')->default(0);
            $table->integer('offers_count')->default(0);
            $table->timestamp('collection_started_at')->nullable();
            $table->timestamp('collection_ended_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('code');
            $table->index(['user_id', 'status']);
        });

        // Связь заявок с поставщиками
        Schema::create('request_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->enum('status', [
                'pending',   // Ожидает отправки
                'sent',      // Отправлено
                'delivered', // Доставлено
                'opened',    // Открыто
                'responded', // Получен ответ
                'bounced',   // Ошибка доставки
            ])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            
            $table->unique(['request_id', 'supplier_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_suppliers');
        Schema::dropIfExists('requests');
    }
};
