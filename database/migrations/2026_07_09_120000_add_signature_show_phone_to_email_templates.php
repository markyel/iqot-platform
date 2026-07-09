<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Пер-шаблонный флаг «показывать телефон в подписи» (email_templates на reports).
 * По умолчанию true (обратная совместимость), но у большинства шаблонов будет
 * выключен: телефон убран из подписи по решению — оставляем в меньшинстве (full).
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasColumn('email_templates', 'signature_show_phone')) {
            Schema::connection($this->conn)->table('email_templates', function (Blueprint $table) {
                $table->boolean('signature_show_phone')->default(true)->after('signature_format');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->conn)->hasColumn('email_templates', 'signature_show_phone')) {
            Schema::connection($this->conn)->table('email_templates', function (Blueprint $table) {
                $table->dropColumn('signature_show_phone');
            });
        }
    }
};
