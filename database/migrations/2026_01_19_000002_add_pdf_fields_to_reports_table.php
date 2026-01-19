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
        Schema::table('reports', function (Blueprint $table) {
            // Добавляем поля для работы с n8n Report Management API
            $table->string('report_type')->default('request')->after('type'); // request | combined
            $table->string('callback_url', 500)->nullable()->after('status');
            $table->string('error_code', 50)->nullable()->after('summary');
            $table->text('error_message')->nullable()->after('error_code');
            $table->longText('pdf_content')->nullable()->after('file_path');
            $table->timestamp('pdf_expires_at')->nullable()->after('pdf_content');

            // Добавляем индекс для статуса
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'report_type',
                'callback_url',
                'error_code',
                'error_message',
                'pdf_content',
                'pdf_expires_at',
            ]);
        });
    }
};
