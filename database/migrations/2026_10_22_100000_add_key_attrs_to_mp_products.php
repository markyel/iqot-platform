<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ключевые признаки товара из наименования (сторона, типоразмер, номер этажа, модель/серия, бренд…) —
 * для проверки, что страница поставщика про тот же вариант (M02384: «правая» отводка получала цену «левой»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            if (!Schema::hasColumn('mp_products', 'key_attrs')) {
                $table->json('key_attrs')->nullable()->after('brand_norm');
            }
            if (!Schema::hasColumn('mp_products', 'key_attrs_at')) {
                $table->timestamp('key_attrs_at')->nullable()->after('key_attrs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            foreach (['key_attrs_at', 'key_attrs'] as $col) {
                if (Schema::hasColumn('mp_products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
