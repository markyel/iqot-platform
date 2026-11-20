<?php

namespace Tests\Unit;

use App\Support\Mail\TestMode;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailTestModeTest extends TestCase
{
    public function test_off_by_default(): void
    {
        config(['services.email_test.recipients' => []]);
        $this->assertFalse(TestMode::active());
        $this->assertTrue(TestMode::allows('supplier@example.com'));
    }

    public function test_only_listed_recipients(): void
    {
        config(['services.email_test.recipients' => ['MarkYellow@yandex.ru', ' info@liftway.ru ']]);
        $this->assertTrue(TestMode::active());
        $this->assertTrue(TestMode::allows('markyellow@yandex.ru'));
        $this->assertTrue(TestMode::allows(' INFO@liftway.ru'));
        $this->assertFalse(TestMode::allows('sales@supplier.ru'));
        $this->assertFalse(TestMode::allows(null));

        $this->expectException(\RuntimeException::class);
        TestMode::assertAllowed('sales@supplier.ru');
    }

    public function test_laravel_mail_is_cancelled_for_other_recipients(): void
    {
        config(['services.email_test.recipients' => ['boag77@gmail.com']]);
        $send = fn (string $to) => Event::until(new MessageSending((new Email())->to($to)->subject('t')->text('t'), []));

        $this->assertFalse($send('client@example.com'));
        $this->assertNotFalse($send('boag77@gmail.com'));
    }
}
