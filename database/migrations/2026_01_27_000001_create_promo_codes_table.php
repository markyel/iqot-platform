<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->decimal('amount', 10, 2);
            $table->boolean('is_used')->default(false);
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('is_used');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
