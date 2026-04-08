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
        Schema::create('parse_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_id', 100)->unique()->comment('Уникальный ID задачи для webhook');
            $table->unsignedBigInteger('user_id')->nullable()->comment('ID пользователя');
            $table->text('text')->comment('Исходный текст для парсинга');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->comment('Статус: pending, processing, completed, failed');
            $table->json('result')->nullable()->comment('Результат парсинга (items)');
            $table->text('error_message')->nullable()->comment('Сообщение об ошибке');
            $table->timestamp('started_at')->nullable()->comment('Время начала обработки');
            $table->timestamp('completed_at')->nullable()->comment('Время завершения');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parse_tasks');
    }
};
