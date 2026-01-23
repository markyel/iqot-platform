<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Номер и дата
            $table->string('number')->unique(); // Номер счета (например: 611054)
            $table->date('invoice_date'); // Дата выставления счета

            // Суммы
            $table->decimal('subtotal', 10, 2); // Сумма без НДС
            $table->decimal('vat_rate', 5, 2)->default(5.00); // Ставка НДС в процентах
            $table->decimal('vat_amount', 10, 2); // Сумма НДС
            $table->decimal('total', 10, 2); // Итого к оплате

            // Статусы
            $table->enum('status', ['draft', 'sent', 'paid', 'cancelled'])->default('draft');
            $table->timestamp('paid_at')->nullable(); // Дата оплаты
            $table->timestamp('sent_at')->nullable(); // Дата отправки
            $table->timestamp('cancelled_at')->nullable(); // Дата отмены

            // Описание и примечания
            $table->text('description')->nullable(); // Назначение платежа
            $table->text('notes')->nullable(); // Внутренние примечания

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('invoice_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
