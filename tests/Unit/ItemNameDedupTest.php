<?php

namespace Tests\Unit;

use App\Services\Generate\CampaignEmailBuilder;
use Tests\TestCase;

class ItemNameDedupTest extends TestCase
{
    public function test_brand_or_article_already_in_name(): void
    {
        $name = 'Качалка отводки верхняя 3 оси с подшипниками FAA307G1 Otis';
        $this->assertTrue(CampaignEmailBuilder::mentions($name, 'Otis'));
        $this->assertTrue(CampaignEmailBuilder::mentions($name, 'OTIS'), 'без учёта регистра');
        $this->assertTrue(CampaignEmailBuilder::mentions($name, 'FAA307G1'));
        $this->assertFalse(CampaignEmailBuilder::mentions($name, 'Schindler'));
    }

    public function test_whole_word_only(): void
    {
        // короткий бренд не находится внутри артикула или слова
        $this->assertFalse(CampaignEmailBuilder::mentions('Плата KM804342G02', 'KM'));
        $this->assertFalse(CampaignEmailBuilder::mentions('Лифтмаш-сервис', 'Лифт'));
        $this->assertTrue(CampaignEmailBuilder::mentions('Выключатель (Diro) 601.6369.028', 'Diro'));
        $this->assertTrue(CampaignEmailBuilder::mentions('Выключатель 601.6369.028', '601.6369.028'), 'точки — буквально');
    }

    public function test_empty_values(): void
    {
        $this->assertFalse(CampaignEmailBuilder::mentions('Ролик', ''));
        $this->assertFalse(CampaignEmailBuilder::mentions('', 'Otis'));
    }
}
