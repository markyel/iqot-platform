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
        Schema::table('requests', function (Blueprint $table) {
            $table->boolean('synced_to_main_db')->default(false)->after('status');
            $table->unsignedBigInteger('main_db_request_id')->nullable()->after('synced_to_main_db');
            $table->timestamp('synced_at')->nullable()->after('main_db_request_id');

            $table->index('synced_to_main_db');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex(['synced_to_main_db']);
            $table->dropColumn(['synced_to_main_db', 'main_db_request_id', 'synced_at']);
        });
    }
};
