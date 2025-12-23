<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_person')->nullable();
            $table->json('categories')->nullable(); // ['лифты', 'эскалаторы']
            $table->json('brands')->nullable();     // ['OTIS', 'Schindler', 'KONE']
            $table->text('description')->nullable();
            $table->decimal('rating', 3, 2)->default(0); // 0.00 - 5.00
            $table->decimal('response_rate', 5, 2)->default(0); // процент откликов
            $table->integer('avg_response_time')->nullable(); // среднее время ответа в минутах
            $table->integer('total_requests')->default(0);
            $table->integer('total_responses')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('rating');
            $table->fullText(['name', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
