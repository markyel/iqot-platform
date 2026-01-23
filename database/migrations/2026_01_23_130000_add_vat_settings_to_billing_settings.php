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
        Schema::table('billing_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_settings', 'vat_enabled')) {
                $table->boolean('vat_enabled')->default(true)->after('invoice_number_current');
            }
            if (!Schema::hasColumn('billing_settings', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->default(20.00)->after('vat_enabled');
            }
        });

        // Обновляем существующую запись (если есть)
        DB::table('billing_settings')
            ->where('id', 1)
            ->update([
                'vat_enabled' => true,
                'vat_rate' => 20.00,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['vat_enabled', 'vat_rate']);
        });
    }
};
