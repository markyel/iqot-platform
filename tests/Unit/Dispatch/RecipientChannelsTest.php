<?php

namespace Tests\Unit\Dispatch;

use App\Services\Dispatch\RecipientChannels;
use Tests\TestCase;

class RecipientChannelsTest extends TestCase
{
    private function channels(): void
    {
        config(['services.email_dispatch.channels' => ['alias_share' => 0.2, 'monitoring_cap' => 2,
            'monitoring_share_contended' => 0.5, 'monitoring_share_free' => 1.0]]);
    }

    public function test_isolated_caps(): void
    {
        $this->channels();
        $this->assertSame(10, RecipientChannels::cap(RecipientChannels::MAIN, 10));
        $this->assertSame(2, RecipientChannels::cap(RecipientChannels::ALIAS, 10));
        $this->assertSame(3, RecipientChannels::cap(RecipientChannels::ALIAS, 15));
        $this->assertSame(1, RecipientChannels::cap(RecipientChannels::ALIAS, 5));
        $this->assertSame(1, RecipientChannels::cap(RecipientChannels::ALIAS, 1));
    }

    public function test_monitoring_cap_share_of_recipient_cap(): void
    {
        // Решение 27.10.2026: при конкуренции с заявками — до половины капа адресата, без неё — весь кап;
        // не меньше прежнего фиксированного (monitoring_cap).
        $this->channels();
        $this->assertSame(5, RecipientChannels::cap(RecipientChannels::MONITORING, 10, true));
        $this->assertSame(10, RecipientChannels::cap(RecipientChannels::MONITORING, 10, false));
        $this->assertSame(8, RecipientChannels::cap(RecipientChannels::MONITORING, 15, true));
        $this->assertSame(3, RecipientChannels::cap(RecipientChannels::MONITORING, 5, true));
        $this->assertSame(2, RecipientChannels::cap(RecipientChannels::MONITORING, 2, true)); // пол
        $this->assertSame(5, RecipientChannels::cap(RecipientChannels::MONITORING, 10));       // по умолчанию — конкуренция
    }

    public function test_contended_by_main_letters_today(): void
    {
        $usage = ['a@x.ru' => [RecipientChannels::MAIN => ['n' => 1, 'last' => null]],
            'b@x.ru' => [RecipientChannels::MONITORING => ['n' => 3, 'last' => null]]];
        $pending = ['c@x.ru' => [RecipientChannels::MAIN => 2], 'b@x.ru' => [RecipientChannels::ALIAS => 1]];
        $this->assertTrue(RecipientChannels::contended($usage, $pending, 'a@x.ru'));   // заявкам уже отправлено
        $this->assertTrue(RecipientChannels::contended($usage, $pending, 'c@x.ru'));   // заявки ждут отправки
        $this->assertFalse(RecipientChannels::contended($usage, $pending, 'b@x.ru'));  // только мониторинг и эксперимент
        $this->assertFalse(RecipientChannels::contended($usage, $pending, 'd@x.ru'));
    }
}
