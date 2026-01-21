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
            if (!Schema::hasColumn('reports', 'report_type')) {
                $table->string('report_type')->default('request')->after('type'); // request | combined
            }
            if (!Schema::hasColumn('reports', 'callback_url')) {
                $table->string('callback_url', 500)->nullable()->after('status');
            }
            if (!Schema::hasColumn('reports', 'error_code')) {
                $table->string('error_code', 50)->nullable()->after('summary');
            }
            if (!Schema::hasColumn('reports', 'error_message')) {
                $table->text('error_message')->nullable()->after('error_code');
            }
            if (!Schema::hasColumn('reports', 'pdf_content')) {
                $table->longText('pdf_content')->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('reports', 'pdf_expires_at')) {
                $table->timestamp('pdf_expires_at')->nullable()->after('pdf_content');
            }

            // Добавляем индекс для статуса, если его нет
            if (!$this->indexExists('reports', 'reports_status_index')) {
                $table->index('status');
            }
        });
    }

    private function indexExists($table, $index)
    {
        $connection = Schema::getConnection();
        $indexes = $connection->getDoctrineSchemaManager()
            ->listTableIndexes($table);
        return array_key_exists($index, $indexes);
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
