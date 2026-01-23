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
            // Изображения подписи и печати
            if (!Schema::hasColumn('billing_settings', 'signature_image')) {
                $table->string('signature_image')->nullable()->after('website');
            }
            if (!Schema::hasColumn('billing_settings', 'stamp_image')) {
                $table->string('stamp_image')->nullable()->after('signature_image');
            }

            // Нумерация счетов
            if (!Schema::hasColumn('billing_settings', 'invoice_number_mask')) {
                $table->string('invoice_number_mask')->default('{NUMBER}')->after('stamp_image');
            }
            if (!Schema::hasColumn('billing_settings', 'invoice_number_start')) {
                $table->unsignedInteger('invoice_number_start')->default(1)->after('invoice_number_mask');
            }
            if (!Schema::hasColumn('billing_settings', 'invoice_number_current')) {
                $table->unsignedInteger('invoice_number_current')->default(0)->after('invoice_number_start');
            }
        });

        // Обновляем существующую запись настроек (если есть)
        DB::table('billing_settings')
            ->where('id', 1)
            ->update([
                'invoice_number_mask' => '{NUMBER}',
                'invoice_number_start' => 611054,
                'invoice_number_current' => 611053,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn([
                'signature_image',
                'stamp_image',
                'invoice_number_mask',
                'invoice_number_start',
                'invoice_number_current',
            ]);
        });
    }
};
