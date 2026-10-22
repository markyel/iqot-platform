<?php

namespace Tests\Unit\Monitoring;

use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\ListCards;
use Tests\TestCase;

class ListCardsTest extends TestCase
{
    /** Каталог как у liftcomplete: карточки с повторяющимися строками свойств, у нашей цены нет. */
    private function catalog(): string
    {
        $card = fn (string $href, string $title, string $code, string $buy) => '<div class="card itemCard"><div class="card__main"><div class="card__title"><a href="' . $href . '">' . $title . '</a></div>'
            . '<ul class="card__list"><li class="card__item"><div>Артикул:</div><div>0001</div></li><li class="card__item"><div>Доп. артикул:</div><div>' . $code . '</div></li>'
            . '<li class="card__item"><div>Вес:</div><div>2</div></li></ul></div><div class="card__buy">' . $buy . '</div></div>';

        return '<html><body><div class="menu"><a href="/a">A</a><a href="/b">B</a><a href="/c">C</a></div><div class="category__list">'
            . $card('/p/prima', 'Отводка привода ДК, OTIS, Prima, 3100RII, правая', 'FAA24390D1', 'В наличии 7 шт. 9&nbsp;738₽ <a href="/p/prima?buy=1">Купить</a>')
            . $card('/p/mech-left', 'Отводка привода ДК, OTIS, L - 510 мм, механическая, левая', 'FEA24390C1', '<button>Заказать</button>')
            . $card('/p/techna-right', 'Отводка привода ДК, OTIS, TECHNA/DO2000, L - 510 мм, правая', '04027', '<button>Заказать</button>')
            . $card('/p/pax', 'Отводка привода ДК, OTIS, PAX, L - 243 мм, правая', 'FAA24390H1', '6 176₽')
            . '</div></body></html>';
    }

    public function test_price_from_neighbour_card_is_detected(): void
    {
        $h = $this->catalog();
        $this->assertFalse(ListCards::priceInOwnCard($h, [ArticleNormalizer::normalize('FEA24390C1')], 'Отводка привода ДК, OTIS, TECHNA/DO2000, L - 510 мм, правая', 9738));
        $this->assertFalse(ListCards::priceInOwnCard($h, [ArticleNormalizer::normalize('FEA24390C1')], '', 9738));
    }

    public function test_price_in_own_card_is_accepted(): void
    {
        $h = $this->catalog();
        $this->assertTrue(ListCards::priceInOwnCard($h, [ArticleNormalizer::normalize('FAA24390D1')], 'Отводка привода ДК, OTIS, Prima, 3100RII, правая', 9738));
        $this->assertTrue(ListCards::priceInOwnCard($h, [], 'Отводка привода ДК, OTIS, PAX, L - 243 мм, правая', 6176));
    }

    public function test_page_without_repeating_cards_is_undetermined(): void
    {
        $h = '<html><body><h1>Отводка правая Techna FEA24390C1</h1><div class="price">34 707 ₽</div></body></html>';
        $this->assertNull(ListCards::priceInOwnCard($h, [ArticleNormalizer::normalize('FEA24390C1')], 'Отводка правая Techna', 34707));
    }
}
