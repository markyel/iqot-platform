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
        Schema::table('public_catalog_items', function (Blueprint $table) {
            $table->timestamp('item_created_at')->nullable()->after('published_at');
            $table->index('item_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_catalog_items', function (Blueprint $table) {
            $table->dropIndex(['item_created_at']);
            $table->dropColumn('item_created_at');
        });
    }
};
