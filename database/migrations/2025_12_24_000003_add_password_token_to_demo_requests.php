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
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->string('password_setup_token')->nullable()->after('terms_accepted');
            $table->timestamp('password_setup_token_expires_at')->nullable()->after('password_setup_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->dropColumn(['password_setup_token', 'password_setup_token_expires_at']);
        });
    }
};
