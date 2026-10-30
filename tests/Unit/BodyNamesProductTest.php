<?php

namespace Tests\Unit;

use App\Services\Generate\CampaignBodyGenerator;
use PHPUnit\Framework\TestCase;

class BodyNamesProductTest extends TestCase
{
    public function test_text_naming_own_item_is_not_reusable(): void
    {
        $rope = [['name' => 'Канат PAWO F7S, d=8,0 mm, 8х19W-IWRC', 'brand' => 'PAWO']];
        $this->assertTrue(CampaignBodyGenerator::namesProduct(
            ['introduction' => 'Запрашиваем КП по канатам ниже — интересуют ваши предложения.'], $rope, 'Лифты'));

        $button = [['name' => 'Кнопка приказа прямоугольная ASY этаж "15" с Брайлем', 'brand' => null]];
        $this->assertTrue(CampaignBodyGenerator::namesProduct(
            ['request' => 'Будем признательны за коммерческое предложение по кнопкам лифтов.'], $button, 'Лифты'));

        // Брендом позиции текст тоже привязан к товару.
        $axle = [['name' => 'Ось паллеты Отис длинная L=181,5 мм']];
        $this->assertTrue(CampaignBodyGenerator::namesProduct(['closing' => 'Нужны цены по запчастям Отис.'], $axle, 'Эскалаторы'));
    }

    public function test_known_part_name_from_another_item_is_caught(): void
    {
        // Текст про трансформаторы, позиция — шунт (так текст ящика доставался чужой позиции).
        $shunt = [['name' => 'Шунт системы позиционирования L=250мм']];
        $this->assertTrue(CampaignBodyGenerator::namesProduct(['introduction' => 'Нужны цены по трансформаторам.'], $shunt, 'Лифты'));
    }

    public function test_neutral_text_is_reusable(): void
    {
        $guide = [['name' => 'Масленка направляющей кабины лифта ETN 100']];
        $body = [
            'greeting' => 'Добрый день,',
            'introduction' => 'Направьте предложение в ответ на это письмо, информацию по наличию тоже.',
            'request' => 'Подготовьте, пожалуйста, КП по этой позиции для обслуживания лифтов: стоимость, срок и условия оплаты.',
        ];
        $this->assertFalse(CampaignBodyGenerator::namesProduct($body, $guide, 'Лифты'));

        $board = [['name' => 'Плата SPBC сервисная (плата интерфейса пользователя)']];
        $this->assertFalse(CampaignBodyGenerator::namesProduct(
            ['request' => 'Подтвердите актуальность цен и наличия по интересующей позиции и пришлите КП или счёт. Платёж — по счёту.'], $board, 'Лифты'));

        $comb = [['name' => 'Гребенка алюминиевая эскалатора SIGMA SCE (22 зуба)']];
        $this->assertFalse(CampaignBodyGenerator::namesProduct(
            ['request' => 'Можете помочь с позициями для эскалаторов? Интересуют цена и сроки.'], $comb, 'Эскалаторы'));
    }
}
