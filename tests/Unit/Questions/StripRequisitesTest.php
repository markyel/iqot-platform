<?php

namespace Tests\Unit\Questions;

use App\Services\Questions\QuestionAutoAnswerClassifier as C;
use PHPUnit\Framework\TestCase;

class StripRequisitesTest extends TestCase
{
    public function test_strips_placeholders(): void
    {
        $a = "Здравствуйте,\n\nНаименование: К-электро\nИНН: 9718221644\nАдрес: Москва, ул. Примерная, д. 1\nТелефон: +7 (4012) 37-45-70";
        $this->assertSame("Здравствуйте,\n\nНаименование: К-электро\nИНН: 9718221644\nТелефон: +7 (4012) 37-45-70", C::stripUnknownRequisites($a));
        $b = 'Мы - Вятка реконструкция. ИНН: 4345491861, КПП: 434501001, Адрес: не указан, Телефон: +7 (833) 242-15-42';
        $this->assertSame('Мы - Вятка реконструкция. ИНН: 4345491861, КПП: 434501001, Телефон: +7 (833) 242-15-42', C::stripUnknownRequisites($b));
        $this->assertSame('Адрес: Москва, Угрешская улица, 2, стр. 54', C::stripUnknownRequisites('Адрес: Москва, Угрешская улица, 2, стр. 54'));
    }

    public function test_repeat_detection_similarity(): void
    {
        similar_text('для регистрации запроса прошу направить карточку предприятия с печатью', 'для регистрации запроса прошу направить карточку предприятия с печатью контактный номер для связи', $pct);
        $this->assertGreaterThanOrEqual(75, $pct);
    }
}
