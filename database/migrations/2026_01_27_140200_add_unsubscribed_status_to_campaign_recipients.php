<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Для MySQL нужно изменить ENUM через ALTER TABLE
        DB::statement("ALTER TABLE campaign_recipients MODIFY COLUMN status ENUM('pending', 'sent', 'failed', 'unsubscribed') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE campaign_recipients MODIFY COLUMN status ENUM('pending', 'sent', 'failed') DEFAULT 'pending'");
    }
};
