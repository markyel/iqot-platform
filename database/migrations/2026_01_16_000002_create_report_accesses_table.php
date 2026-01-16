<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('request_id')->constrained()->onDelete('cascade');
            $table->string('report_number', 50); // Номер отчета из внешней БД
            $table->decimal('price', 10, 2)->default(0); // Стоимость доступа
            $table->timestamp('accessed_at'); // Время первого доступа
            $table->timestamps();

            $table->index(['user_id', 'accessed_at']);
            $table->unique(['user_id', 'request_id']); // Один пользователь - одна заявка = один доступ
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_accesses');
    }
};
