<?php

namespace Tests\Unit\Monitoring;

use App\Support\Monitoring\VatDetector;
use PHPUnit\Framework\TestCase;

class VatDetectorTest extends TestCase
{
    public function test_label_next_to_price(): void
    {
        $this->assertFalse(VatDetector::detect('Энкодер Hohner TAA27076ADY1 Цена: 28 696 ₽ (+НДС) В корзину', 28696));
        $this->assertFalse(VatDetector::detect("Цена 1\u{00A0}250 руб. без НДС", 1250));
        $this->assertTrue(VatDetector::detect('Стоимость 4 390 ₽ в т.ч. НДС 20%', 4390));
        $this->assertTrue(VatDetector::detect('4390 руб. с НДС', 4390));
        $this->assertTrue(VatDetector::detect('Цена 12.360 ₽, НДС включен', 12360));
    }

    public function test_label_of_other_price_ignored_site_phrase_used(): void
    {
        // Подпись рядом с другой ценой не решает; решает общая фраза сайта.
        $text = 'Доставка 500 ₽ без НДС. Ролик 1 250 ₽. Все цены указаны с НДС.';
        $this->assertTrue(VatDetector::detect($text, 1250));
        $this->assertFalse(VatDetector::detect('Ролик 980 ₽. Цены указаны без учета НДС.', 980));
    }

    public function test_site_default(): void
    {
        $this->assertFalse(VatDetector::siteDefault(0, 91));   // lzpro: 91 «без НДС»
        $this->assertTrue(VatDetector::siteDefault(67, 0));    // yamagi
        $this->assertTrue(VatDetector::siteDefault(19, 1));    // 95%
        $this->assertNull(VatDetector::siteDefault(8, 2));     // 80% — не уверены
        $this->assertNull(VatDetector::siteDefault(3, 0));     // мало цен
    }

    public function test_cart(): void
    {
        // lift-market: НДС внутри цены.
        $this->assertTrue(VatDetector::fromCart("Товаров на: 4 073.77 Р\nНДС: 896.23 Р\nИтого: 4 970 Р", 4970));
        // revator: подпись.
        $this->assertTrue(VatDetector::fromCart('Шкив 48 448 ₽ цена с НДС Итого: 48 448 ₽', 48448));
        // НДС сверху: итого = цена × 1,22.
        $this->assertFalse(VatDetector::fromCart("Сумма: 10 000 ₽\nНДС 22%: 2 200 ₽\nИтого: 12 200 ₽", 10000));
        $this->assertNull(VatDetector::fromCart('Итого: 4 970 ₽', 4970));
    }

    public function test_unknown(): void
    {
        $this->assertNull(VatDetector::detect('Ролик каретки 1 250 ₽ в наличии', 1250));
        $this->assertNull(VatDetector::detect('', 100));
    }
}
