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
        Schema::table('tariff_plans', function (Blueprint $table) {
            $table->boolean('pdf_reports_enabled')->default(false)->after('features');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tariff_plans', function (Blueprint $table) {
            $table->dropColumn('pdf_reports_enabled');
        });
    }
};
