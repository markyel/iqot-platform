<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * НДС по корзине сайта (24.11.2026, решение владельца: только автомат) — разовая проба monitoring:vat-cart-probe для
 * сайтов, где НДС не виден на страницах; признак сайта = по страницам, иначе по корзине.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_sites') && !Schema::hasColumn('mp_sites', 'vat_cart')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->boolean('vat_cart')->nullable()->after('vat_default');
                $table->dateTime('vat_cart_at')->nullable()->after('vat_cart');
                $table->string('vat_cart_note', 190)->nullable()->after('vat_cart_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mp_sites') && Schema::hasColumn('mp_sites', 'vat_cart')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->dropColumn(['vat_cart', 'vat_cart_at', 'vat_cart_note']);
            });
        }
    }
};
