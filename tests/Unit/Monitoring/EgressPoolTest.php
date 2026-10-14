<?php

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\Web\EgressPool;
use Tests\TestCase;

class EgressPoolTest extends TestCase
{
    private function pool(): EgressPool
    {
        return new EgressPool([
            ['name' => 'relay2', 'proxy' => 'http://155.212.185.101:3128'],
            ['name' => 'direct'],
            ['name' => 'relay1', 'proxy' => 'http://45.146.167.20:3128', 'fallback' => true],
            ['name' => 'liftpages', 'proxy' => 'http://45.12.239.216:3128', 'fallback' => true],
        ]);
    }

    private function names(array $channels): array
    {
        return array_column($channels, 'name');
    }

    public function test_empty_config_means_direct_only(): void
    {
        $pool = new EgressPool([]);
        $this->assertSame(['direct'], $pool->names());
        $this->assertNull($pool->pick('lift-snab.ru')['proxy']);
    }

    public function test_choice_is_stable_and_fallbacks_go_last(): void
    {
        $pool = $this->pool();
        $a = $this->names($pool->candidates('lift-snab.ru'));
        $this->assertSame($a, $this->names($pool->candidates('LIFT-SNAB.ru')));
        $this->assertSame(['relay1', 'liftpages'], array_slice($a, 2));
        $this->assertEqualsCanonicalizing(['relay2', 'direct'], array_slice($a, 0, 2));
    }

    public function test_domains_spread_over_primary_channels(): void
    {
        $pool = $this->pool();
        $first = [];
        foreach (range(1, 40) as $i) {
            $first[$pool->pick("site{$i}.ru")['name']] = true;
        }
        $this->assertEqualsCanonicalizing(['relay2', 'direct'], array_keys($first));
    }

    public function test_preferred_channel_goes_first(): void
    {
        $this->assertSame('liftpages', $this->pool()->pick('lift-snab.ru', 'liftpages')['name']);
        $this->assertNotNull($this->pool()->pick('lift-snab.ru', 'unknown'));
    }

    public function test_banned_channel_is_skipped_for_that_domain_only(): void
    {
        $pool = $this->pool();
        $pool->markBanned('direct', 'lift-snab.ru', 'captcha');
        $pool->markBanned('relay2', 'lift-snab.ru', 'HTTP 403');
        $this->assertSame('relay1', $pool->pick('lift-snab.ru', 'direct')['name']);
        $this->assertContains('direct', $this->names($pool->candidates('s-mechanics.ru')));

        $pool->markBanned('relay1', 'lift-snab.ru', 'captcha');
        $pool->markBanned('liftpages', 'lift-snab.ru', 'captcha');
        $this->assertNull($pool->pick('lift-snab.ru'));
    }

    public function test_site_banning_two_channels_is_exhausted(): void
    {
        $pool = $this->pool();
        $pool->markBanned('relay2', 'exvator.ru', 'captcha');
        $this->assertFalse($pool->exhaustedFor('exvator.ru'));
        $pool->markBanned('direct', 'exvator.ru', 'captcha');
        $this->assertSame(2, $pool->bannedCount('exvator.ru'));
        $this->assertTrue($pool->exhaustedFor('exvator.ru'));
        $this->assertFalse($pool->exhaustedFor('lift-snab.ru'));
    }

    public function test_down_channel_moves_to_tail_but_stays_available(): void
    {
        $pool = $this->pool();
        $pool->markDown('relay2', 'Failed to connect');
        $names = $this->names($pool->candidates('lift-snab.ru', 'relay2'));
        $this->assertSame('relay2', end($names));
        $this->assertSame('direct', $names[0]);

        // direct «упасть» не может — это сам сервер.
        $pool->markDown('direct', 'x');
        $this->assertFalse($pool->isDown('direct'));
    }

    public function test_proxy_failure_detection(): void
    {
        $proxy = 'http://45.146.167.20:3128';
        $this->assertTrue(EgressPool::isProxyFailure('cURL error 7: Failed to connect to 45.146.167.20 port 3128 after 8001 ms', $proxy));
        $this->assertTrue(EgressPool::isProxyFailure('cURL error 56: CONNECT tunnel failed, response 403', $proxy));
        // 5xx на CONNECT — прокси не достучался до сайта: сбой сайта, не канала.
        $this->assertFalse(EgressPool::isProxyFailure('cURL error 56: CONNECT tunnel failed, response 500', $proxy));
        $this->assertFalse(EgressPool::isProxyFailure('cURL error 28: Operation timed out after 20001 milliseconds', $proxy));
        $this->assertFalse(EgressPool::isProxyFailure('cURL error 7: Failed to connect to lift-snab.ru port 443', $proxy));
        $this->assertFalse(EgressPool::isProxyFailure('CONNECT tunnel failed', null));
    }
}
