<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->after('balance')->constrained('promo_codes')->nullOnDelete();
            $table->timestamp('promo_code_activated_at')->nullable()->after('promo_code_id');
            $table->boolean('has_promo_priority')->default(false)->after('promo_code_activated_at');

            $table->index('promo_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['promo_code_id', 'promo_code_activated_at', 'has_promo_priority']);
        });
    }
};
