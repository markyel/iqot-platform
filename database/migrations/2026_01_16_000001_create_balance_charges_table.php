<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('balance_hold_id')->constrained()->onDelete('cascade');
            $table->foreignId('request_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('external_request_item_id'); // ID позиции во внешней БД
            $table->decimal('amount', 10, 2); // Сумма списания за эту позицию
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['balance_hold_id']);
            $table->index(['request_id', 'external_request_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_charges');
    }
};
