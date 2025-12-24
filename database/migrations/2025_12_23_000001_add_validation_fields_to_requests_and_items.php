<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Добавляем контактную информацию к заявкам
        Schema::table('requests', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('description');
            $table->string('company_address')->nullable()->after('company_name');
            $table->string('inn', 12)->nullable()->after('company_address');
            $table->string('kpp', 9)->nullable()->after('inn');
            $table->string('contact_person')->nullable()->after('kpp');
            $table->string('contact_phone')->nullable()->after('contact_person');
        });

        // Добавляем поля для валидации позиций
        Schema::table('request_items', function (Blueprint $table) {
            $table->enum('equipment_type', ['lift', 'escalator'])->nullable()->after('name');
            $table->string('equipment_brand')->nullable()->after('equipment_type');
            $table->string('manufacturer_article')->nullable()->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_address',
                'inn',
                'kpp',
                'contact_person',
                'contact_phone',
            ]);
        });

        Schema::table('request_items', function (Blueprint $table) {
            $table->dropColumn([
                'equipment_type',
                'equipment_brand',
                'manufacturer_article',
            ]);
        });
    }
};
