<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acts')) {
            return;
        }

        Schema::create('acts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Номер и период
            $table->string('number')->unique(); // Номер акта (совпадает с УПД)
            $table->date('act_date'); // Дата акта
            $table->integer('period_year'); // Год отчетного периода
            $table->integer('period_month'); // Месяц отчетного периода (1-12)

            // Суммы
            $table->decimal('subtotal', 10, 2); // Сумма без НДС
            $table->decimal('vat_rate', 5, 2)->default(5.00); // Ставка НДС
            $table->decimal('vat_amount', 10, 2); // Сумма НДС
            $table->decimal('total', 10, 2); // Итого

            // Статус
            $table->enum('status', ['draft', 'generated', 'sent', 'signed'])->default('draft');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();

            // Примечания
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'period_year', 'period_month']);
            $table->index('act_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acts');
    }
};
