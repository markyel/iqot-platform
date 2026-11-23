<?php

namespace Tests\Unit;

use App\Services\Timeweb\TimewebMailApi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TimewebMailApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.timeweb.token' => 'tkn', 'services.timeweb.api_url' => 'https://api.timeweb.cloud']);
    }

    public function test_requests_match_api_spec(): void
    {
        Http::fake([
            'api.timeweb.cloud/api/v1/mail/domains/ex.ru?*' => Http::response(['meta' => ['total' => 2],
                'mailboxes' => [['mailbox' => 'ivan.petrov'], ['mailbox' => 'Info@ex.ru']]]),
            '*' => Http::response(['response_id' => 'x']),
        ]);
        $api = new TimewebMailApi();

        $this->assertSame(['ivan.petrov', 'info'], $api->mailboxes('ex.ru'));
        $api->createMailbox('ex.ru', 'anna', 'Pw1');
        $api->forwardAndDelete('ex.ru', 'anna', 'info@ex.ru');
        $api->setCatchAll('ex.ru', 'info@ex.ru');
        $api->dropMailbox('ex.ru', 'anna');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === 'https://api.timeweb.cloud/api/v1/mail/domains/ex.ru'
            && $r['mailbox'] === 'anna' && $r['password'] === 'Pw1' && $r->hasHeader('Authorization', 'Bearer tkn'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/mail/domains/ex.ru/mailboxes/anna')
            && $r['forwarding_incoming'] === ['is_enabled' => true, 'is_delete_messages' => true, 'incoming_list' => ['info@ex.ru']]);
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/mail/domains/ex.ru/info') && $r['email'] === 'info@ex.ru');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/mail/domains/ex.ru/mailboxes/anna'));
    }

    public function test_error_is_reported(): void
    {
        Http::fake(['*' => Http::response(['message' => 'mailbox already exists'], 409)]);

        $this->expectExceptionMessage('HTTP 409 mailbox already exists');
        (new TimewebMailApi())->createMailbox('ex.ru', 'anna', 'Pw1');
    }
}
