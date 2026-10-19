<?php

namespace Tests\Unit\Dispatch;

use App\Services\Dispatch\RecipientChannels;
use Tests\TestCase;

class RecipientChannelsTest extends TestCase
{
    public function test_isolated_caps(): void
    {
        config(['services.email_dispatch.channels' => ['alias_share' => 0.2, 'monitoring_cap' => 2]]);
        $this->assertSame(10, RecipientChannels::cap(RecipientChannels::MAIN, 10));
        $this->assertSame(2, RecipientChannels::cap(RecipientChannels::ALIAS, 10));
        $this->assertSame(3, RecipientChannels::cap(RecipientChannels::ALIAS, 15));
        $this->assertSame(1, RecipientChannels::cap(RecipientChannels::ALIAS, 5));
        $this->assertSame(1, RecipientChannels::cap(RecipientChannels::ALIAS, 1));
        $this->assertSame(2, RecipientChannels::cap(RecipientChannels::MONITORING, 15));
        $this->assertSame(2, RecipientChannels::cap(RecipientChannels::MONITORING, 5));
    }
}
