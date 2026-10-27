<?php

namespace Tests\Unit\Dispatch;

use App\Support\Senders\SmtpServerCaps;
use Tests\TestCase;

class SmtpServerCapsTest extends TestCase
{
    public function test_parse(): void
    {
        $this->assertSame(['server269.hosting.reg.ru' => 2700, 'sm21.hosting.reg.ru' => 900],
            SmtpServerCaps::parse(' Server269.hosting.reg.ru:2700, sm21.hosting.reg.ru:900 ,bad, x:0'));
        $this->assertSame([], SmtpServerCaps::parse(''));
    }

    public function test_clip_shares_account_budget(): void
    {
        $servers = [1 => 'server269.hosting.reg.ru', 2 => 'server269.hosting.reg.ru', 3 => 'smtp.beget.com'];
        // Ящики хотят 100+50, у аккаунта осталось 30 → пропорционально 20+10; Beget не трогаем.
        $this->assertSame([1 => 20, 2 => 10, 3 => 200],
            SmtpServerCaps::clip([1 => 100, 2 => 50, 3 => 200], $servers, ['server269.hosting.reg.ru' => 30]));
        // Влезает — без изменений.
        $this->assertSame([1 => 10, 2 => 5, 3 => 200],
            SmtpServerCaps::clip([1 => 10, 2 => 5, 3 => 200], $servers, ['server269.hosting.reg.ru' => 30]));
        // Остаток от округления — ящику с большим остатком: 7·10/15 = 4 (+1), 8·10/15 = 5.
        $clipped = SmtpServerCaps::clip([1 => 7, 2 => 8], $servers, ['server269.hosting.reg.ru' => 10]);
        $this->assertSame(10, array_sum($clipped));
        // Аккаунт выбран — по нулям.
        $this->assertSame([1 => 0, 2 => 0], SmtpServerCaps::clip([1 => 7, 2 => 8], $servers, ['server269.hosting.reg.ru' => 0]));
    }
}
