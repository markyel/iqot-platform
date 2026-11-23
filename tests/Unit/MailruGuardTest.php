<?php

namespace Tests\Unit;

use App\Console\Commands\MailruGuard;
use PHPUnit\Framework\TestCase;

class MailruGuardTest extends TestCase
{
    public function test_mailru_mx_hosts(): void
    {
        $this->assertTrue(MailruGuard::isMailruHost('mxs.mail.ru'));
        $this->assertTrue(MailruGuard::isMailruHost('emx.mail.ru.'));
        $this->assertTrue(MailruGuard::isMailruHost('MXS.MAIL.RU'));
        $this->assertFalse(MailruGuard::isMailruHost('mx.yandex.net'));
        $this->assertFalse(MailruGuard::isMailruHost('mail.ru.evil.com'));
        $this->assertFalse(MailruGuard::isMailruHost('gmail.ru'));
    }
}
