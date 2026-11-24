<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Рецепт карточки товара сайта (пилот профиля сайта, 24.11.2026): CSS-селекторы, доля совпадений на проверке, когда выведен.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_sites') && !Schema::hasColumn('mp_sites', 'recipe')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->json('recipe')->nullable()->after('has_structured_markup');
                $table->decimal('recipe_score', 4, 3)->nullable()->after('recipe');
                $table->dateTime('recipe_at')->nullable()->after('recipe_score');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mp_sites') && Schema::hasColumn('mp_sites', 'recipe')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->dropColumn(['recipe', 'recipe_score', 'recipe_at']);
            });
        }
    }
};
