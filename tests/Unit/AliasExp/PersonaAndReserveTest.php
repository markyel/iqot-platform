<?php

namespace Tests\Unit\AliasExp;

use App\Services\AliasExp\OrgReserve;
use App\Services\AliasExp\PersonaFactory;
use PHPUnit\Framework\TestCase;

class PersonaAndReserveTest extends TestCase
{
    public function test_persona_address_matches_name(): void
    {
        $tr = ['ё' => 'e', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'я' => 'ya'];
        for ($i = 0; $i < 200; $i++) {
            $p = (new PersonaFactory())->make('prosnabz.ru');
            [$surname] = explode(' ', $p['full_name']);
            $this->assertStringEndsWith('@prosnabz.ru', $p['email']);
            $this->assertMatchesRegularExpression('/^[a-z._0-9]+$/', $p['local']);
            $this->assertStringContainsString(strtr(mb_strtolower($surname), $tr), $p['local'], $p['full_name'] . ' / ' . $p['local']);
            $this->assertCount(3, explode(' ', $p['full_name']));
        }
    }

    public function test_persona_avoids_taken_address(): void
    {
        $taken = [];
        for ($i = 0; $i < 300; $i++) {
            $p = (new PersonaFactory())->make('x.ru', $taken);
            $this->assertArrayNotHasKey($p['email'], $taken);
            $taken[$p['email']] = true;
        }
    }

    public function test_legend_phone_changes_exactly_one_digit_stably(): void
    {
        $src = '+7 (4162) 77-65-01';
        $a = PersonaFactory::legendPhone($src, '2801177383');
        $this->assertSame($a, PersonaFactory::legendPhone($src, '2801177383'));
        $da = preg_replace('/\D/', '', $a);
        $ds = preg_replace('/\D/', '', $src);
        $this->assertSame(1, count(array_diff_assoc(str_split($da), str_split($ds))));
        $this->assertSame(substr($ds, 0, -4), substr($da, 0, -4));
        $this->assertSame(strlen($src), strlen($a));
        $multi = PersonaFactory::legendPhone('+7 (495) 123-45-67, +7 999 000-00-00', '1');
        $this->assertSame('+7 (495) 1', substr($multi, 0, 10)); // только первый номер
        $this->assertSame(strlen('+7 (495) 123-45-67'), strlen($multi));
        $this->assertNull(PersonaFactory::legendPhone('12-34', '1'));
        $this->assertNull(PersonaFactory::legendPhone(null, '1'));
    }

    public function test_public_sector_detection(): void
    {
        foreach (['Фонд капитального ремонта многоквартирных домов Амурской области', 'МУП «Водоканал»', 'ГУП ЖКХ',
            'Региональный фонд капитального ремонта, унитарная некоммерческая организация', 'ГБУЗ Городская больница №2',
            'Муниципальное казённое учреждение «УКС»', 'ФГУП Почта', 'Администрация города Перми'] as $n) {
            $this->assertTrue(OrgReserve::isPublicSector($n), $n);
        }
        foreach (['Монтаж Автоматики', 'К-электро, Компания', 'ООО «Лифтсервис»', 'Гудвин', 'Муравей', 'Фондаментстрой'] as $n) {
            $this->assertFalse(OrgReserve::isPublicSector($n), $n);
        }
        $this->assertTrue(OrgReserve::isPublicSector('Водоканал', 'Муниципальное унитарное предприятие Водоканал'));
    }
}
