<?php

namespace Tests\Unit\Questions;

use App\Jobs\ProcessSupplierQuestionJob;
use PHPUnit\Framework\TestCase;

class RequisitesAnswerTest extends TestCase
{
    private function sender(array $o = []): object
    {
        return (object) array_merge([
            'organization_name' => 'Сенсорматика', 'organization_inn' => '7806530172', 'organization_kpp' => '780201001',
            'organization_legal_address' => null, 'organization_phone' => null,
            'phone' => '+7 (812) 407-20-08', 'email' => 'anna_kalinina@prosnabz.ru', 'sender_full_name' => 'Калинина Анна Юрьевна',
        ], $o);
    }

    public function test_requisites_requests_answered_with_text(): void
    {
        foreach (['Для направления кп прошу, направить карточку предприятия с печатью', 'Пришлите, пожалуйста, Ваши реквизиты вместе с заявкой',
            'просим направить карту предприятия (ИНН) и номер телефона', 'Можно вложить карточку организации?'] as $q) {
            $a = ProcessSupplierQuestionJob::requisitesAnswer((object) ['question_text' => $q], $this->sender());
            $this->assertNotNull($a, $q);
            $this->assertStringContainsString('ИНН: 7806530172', $a);
            $this->assertStringContainsString('КПП: 780201001', $a);
            $this->assertStringNotContainsString('Юридический адрес', $a, 'пустые поля не пишем');
        }
    }

    public function test_other_questions_untouched(): void
    {
        $this->assertNull(ProcessSupplierQuestionJob::requisitesAnswer((object) ['question_text' => 'Под заказ рассматриваете?'], $this->sender()));
        $this->assertNull(ProcessSupplierQuestionJob::requisitesAnswer((object) ['question_text' => 'Пришлите реквизиты'],
            $this->sender(['organization_name' => '', 'organization_inn' => ''])));
    }
}
