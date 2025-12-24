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
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('organization');
            $table->string('inn', 12);
            $table->string('kpp', 9)->nullable();
            $table->string('email');
            $table->string('phone');
            $table->text('items_list'); // Список товаров для запроса КП
            $table->boolean('terms_accepted')->default(false);
            $table->enum('status', ['new', 'processing', 'contacted', 'completed'])->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
