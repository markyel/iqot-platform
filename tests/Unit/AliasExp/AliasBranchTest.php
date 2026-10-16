<?php

namespace Tests\Unit\AliasExp;

use App\Services\AliasExp\AliasBranch;
use App\Services\AliasExp\AliasWindow;
use App\Services\Generate\CampaignEmailBuilder;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AliasBranchTest extends TestCase
{
    public function test_slots_stay_in_window_and_keep_pause(): void
    {
        config(['services.alias_exp.window_start_hour' => 9, 'services.alias_exp.window_end_hour' => 18,
            'services.alias_exp.window_timezone' => 'Europe/Moscow', 'services.alias_exp.send_delay_seconds' => 300]);
        // Пятница 17:50 МСК: часть писем — в пятницу, остальное — с понедельника 9:00.
        $from = CarbonImmutable::parse('2026-10-16 17:50:00', 'Europe/Moscow');
        $slots = AliasWindow::slots(300, $from);
        $this->assertCount(300, $slots);
        $prev = null;
        foreach ($slots as $s) {
            $m = $s->setTimezone('Europe/Moscow');
            $this->assertTrue($m->isWeekday(), (string) $m);
            $this->assertGreaterThanOrEqual(9, $m->hour);
            $this->assertLessThan(18, $m->hour);
            if ($prev) {
                $this->assertGreaterThanOrEqual(300, $prev->diffInSeconds($s, false));
            }
            $prev = $s;
        }
        $this->assertSame('2026-10-19', $slots[5]->setTimezone('Europe/Moscow')->toDateString()); // после выходных
        $this->assertFalse(AliasWindow::isOpen(CarbonImmutable::parse('2026-10-17 12:00', 'Europe/Moscow')));
        $this->assertTrue(AliasWindow::isOpen(CarbonImmutable::parse('2026-10-16 09:00', 'Europe/Moscow')));
        $this->assertFalse(AliasWindow::isOpen(CarbonImmutable::parse('2026-10-16 18:00', 'Europe/Moscow')));
    }

    public function test_share_is_stable_and_close_to_config(): void
    {
        config(['services.alias_exp.share' => 0.2]);
        $in = 0;
        for ($i = 1; $i <= 10000; $i++) {
            $in += (int) AliasBranch::inShare($i);
        }
        $this->assertEqualsWithDelta(2000, $in, 150);
        $this->assertSame(AliasBranch::inShare(4242), AliasBranch::inShare(4242));
        config(['services.alias_exp.share' => 0]);
        $this->assertFalse(AliasBranch::inShare(4242));
    }

    public function test_groups_pair_same_type_homogeneous_first(): void
    {
        $rows = [
            1 => ['product_type_id' => 10, 'domain_id' => 1, 'brand' => 'OTIS', 'name' => 'Кнопка вызова 1 этаж'],
            2 => ['product_type_id' => 10, 'domain_id' => 1, 'brand' => 'KONE', 'name' => 'Плата управления'],
            3 => ['product_type_id' => 10, 'domain_id' => 1, 'brand' => 'OTIS', 'name' => 'Кнопка вызова 2 этаж'],
            4 => ['product_type_id' => 20, 'domain_id' => 1, 'brand' => '', 'name' => 'Ролик'],
            5 => ['product_type_id' => 0, 'domain_id' => 1, 'brand' => '', 'name' => 'Без типа'],
            6 => ['product_type_id' => 10, 'domain_id' => 2, 'brand' => '', 'name' => 'Другая область'],
        ];
        $g = AliasBranch::group([1, 2, 3, 4, 5, 6], $rows, 2);
        $this->assertSame([[1, 3], [2], [4], [5], [6]], $g);
        $this->assertSame([[1], [2], [3]], AliasBranch::group([1, 2, 3], $rows, 1));
        // Одна деталь из двух заявок — не пара.
        $dup = [7 => ['product_type_id' => 405, 'domain_id' => 1, 'article' => 'MP431TWB14A', 'name' => 'КОНТАКТ ТОРМОЗА MP431TWB14A'],
            8 => ['product_type_id' => 405, 'domain_id' => 1, 'article' => 'MP431TWB14A', 'name' => 'КОНТАКТ ТОРМОЗА MP431TWB14A']];
        $this->assertSame([[7], [8]], AliasBranch::group([7, 8], $dup, 2));
    }

    public function test_no_double_period(): void
    {
        $this->assertSame('Ролик — 1 шт.', CampaignEmailBuilder::withSuffix('Ролик — 1 шт.', '.'));
        $this->assertSame('Ролик — <strong>1 шт.</strong>', CampaignEmailBuilder::withSuffix('Ролик — <strong>1 шт.</strong>', '.'));
        $this->assertSame('Ролик — 1 м.', CampaignEmailBuilder::withSuffix('Ролик — 1 м', '.'));
        $this->assertSame('Ролик — 1 шт.!', CampaignEmailBuilder::withSuffix('Ролик — 1 шт.', '!'));
    }

    public function test_tagless_subject(): void
    {
        $this->assertSame('Запрос: Ролик', CampaignEmailBuilder::cleanTaglessSubject('Запрос: Ролик ()'));
        $this->assertSame('Ролик', CampaignEmailBuilder::cleanTaglessSubject('Заявка — Ролик'));
        $this->assertSame('Ролик', CampaignEmailBuilder::cleanTaglessSubject('Ролик, заявка'));
        $this->assertSame('Ролик', CampaignEmailBuilder::cleanTaglessSubject('· Ролик'));
        $this->assertSame('Ролик', CampaignEmailBuilder::cleanTaglessSubject('Ролик №'));
        $this->assertSame('Ролик — цена?', CampaignEmailBuilder::cleanTaglessSubject('Ролик — цена?'));
        $this->assertSame('Ремень D4*600 KM-2', CampaignEmailBuilder::cleanTaglessSubject('Ремень D4*600 KM-2 —'));
    }
}
