<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'reports';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Добавляем поля модерации в product_types
        Schema::connection('reports')->table('product_types', function (Blueprint $table) {
            $table->enum('status', ['active', 'pending'])->default('active')->after('is_active');
            $table->enum('source', ['manual', 'ai_generated'])->default('manual')->after('status');
            $table->boolean('is_verified')->default(true)->after('source');

            $table->index('status');
            $table->index('source');
        });

        // Добавляем поля модерации в application_domains
        Schema::connection('reports')->table('application_domains', function (Blueprint $table) {
            $table->enum('status', ['active', 'pending'])->default('active')->after('is_active');
            $table->enum('source', ['manual', 'ai_generated'])->default('manual')->after('status');
            $table->boolean('is_verified')->default(true)->after('source');

            $table->index('status');
            $table->index('source');
        });

        // Обновляем существующие записи
        DB::connection('reports')->table('product_types')->update([
            'status' => 'active',
            'source' => 'manual',
            'is_verified' => true
        ]);

        DB::connection('reports')->table('application_domains')->update([
            'status' => 'active',
            'source' => 'manual',
            'is_verified' => true
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('reports')->table('product_types', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['source']);
            $table->dropColumn(['status', 'source', 'is_verified']);
        });

        Schema::connection('reports')->table('application_domains', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['source']);
            $table->dropColumn(['status', 'source', 'is_verified']);
        });
    }
};
