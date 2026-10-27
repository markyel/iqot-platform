<?php

namespace Tests\Unit;

use App\Jobs\ReceiveSenderEmailsJob;
use Tests\TestCase;

class ReceiveAuthFailureTest extends TestCase
{
    public function test_auth_failure_detection(): void
    {
        // Реальные ответы из лога прода (27.10.2026).
        $this->assertTrue(ReceiveSenderEmailsJob::isAuthFailure('NO [AUTHENTICATIONFAILED] Authentication failed.'));
        $this->assertTrue(ReceiveSenderEmailsJob::isAuthFailure('NO [AUTHENTICATIONFAILED] NEOBHODIM parol prilozheniya https://help.mail.ru/mail'));
        $this->assertTrue(ReceiveSenderEmailsJob::isAuthFailure('failed to authenticate'));
        // Сеть и временные сбои — не повод снимать ящик.
        $this->assertFalse(ReceiveSenderEmailsJob::isAuthFailure('connection failed'));
        $this->assertFalse(ReceiveSenderEmailsJob::isAuthFailure('NO Service temporarily unavailable'));
    }

    public function test_deactivate_after_series(): void
    {
        $t0 = 1_800_000_000;
        $this->assertFalse(ReceiveSenderEmailsJob::shouldDeactivate(null, $t0, 24));            // серии нет
        $this->assertFalse(ReceiveSenderEmailsJob::shouldDeactivate($t0, $t0 + 23 * 3600, 24)); // ещё рано
        $this->assertTrue(ReceiveSenderEmailsJob::shouldDeactivate($t0, $t0 + 24 * 3600, 24));
        $this->assertFalse(ReceiveSenderEmailsJob::shouldDeactivate($t0, $t0 + 100 * 3600, 0)); // выключено
    }
}
