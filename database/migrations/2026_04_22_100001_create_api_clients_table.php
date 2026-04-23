<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->decimal('overdraft_percent', 5, 2)->default(20.00);
            $table->timestamps();

            $table->unique('user_id', 'uniq_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
