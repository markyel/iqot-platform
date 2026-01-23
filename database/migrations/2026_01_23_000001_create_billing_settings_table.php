<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Проверяем, не существует ли уже таблица
        if (Schema::hasTable('billing_settings')) {
            return;
        }

        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название (ИП Маркелов Д.Е. / юр.лицо)
            $table->string('full_name')->nullable(); // Полное название (для ИП: Индивидуальный предприниматель...)
            $table->string('inn', 12); // ИНН
            $table->string('kpp', 9)->nullable(); // КПП (для юрлиц)
            $table->string('ogrnip', 15)->nullable(); // ОГРНИП (для ИП)
            $table->string('ogrn', 13)->nullable(); // ОГРН (для юрлиц)
            $table->text('address'); // Юридический адрес

            // Банковские реквизиты
            $table->string('bank_name'); // Название банка
            $table->string('bank_bik', 9); // БИК
            $table->string('bank_corr_account', 20); // Корреспондентский счет
            $table->string('bank_account', 20); // Расчетный счет

            // Подписанты
            $table->string('director_name')->nullable(); // ФИО руководителя (полное)
            $table->string('director_short')->nullable(); // ФИО руководителя (короткое: Иванов И.И.)
            $table->string('director_position')->nullable(); // Должность руководителя
            $table->string('accountant_name')->nullable(); // ФИО главбуха
            $table->date('registration_date')->nullable(); // Дата регистрации ИП/ООО

            // Контакты
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();

            $table->timestamps();
        });

        // Вставляем реквизиты по умолчанию из макета
        DB::table('billing_settings')->insert([
            'name' => 'ИП Маркелов Дмитрий Евгеньевич',
            'full_name' => 'Индивидуальный предприниматель Маркелов Дмитрий Евгеньевич',
            'inn' => '771512090267',
            'ogrnip' => '324774600503025',
            'address' => '127549, г. Москва, ш. Алтуфьевское, д. 62А, кв. 97',
            'bank_name' => 'АО "ТИНЬКОФФ БАНК"',
            'bank_bik' => '044525974',
            'bank_corr_account' => '30101810145250000974',
            'bank_account' => '40802810100000000000', // Нужно будет заменить на реальный
            'director_name' => 'Маркелов Дмитрий Евгеньевич',
            'director_short' => 'Маркелов Д. Е.',
            'director_position' => 'Индивидуальный предприниматель',
            'email' => 'info@iqot.ru',
            'website' => 'iqot.ru',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
