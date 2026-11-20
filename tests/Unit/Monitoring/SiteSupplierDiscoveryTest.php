<?php

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\Hybrid\SiteSupplierDiscovery;
use Tests\TestCase;

class SiteSupplierDiscoveryTest extends TestCase
{
    public function test_client_and_stop_list_domains_are_excluded(): void
    {
        $blocked = ['mylift.ru', 'myzip.ru', 'liftway.ru'];

        $this->assertTrue(SiteSupplierDiscovery::isExcluded('mylift.ru', $blocked));
        $this->assertTrue(SiteSupplierDiscovery::isExcluded('shop.myzip.ru', $blocked));
        $this->assertTrue(SiteSupplierDiscovery::isExcluded('liftway.ru', $blocked));
        $this->assertFalse(SiteSupplierDiscovery::isExcluded('revator.ru', $blocked));
        $this->assertFalse(SiteSupplierDiscovery::isExcluded('notmylift.ru', $blocked));
    }

    public function test_monitoring_stop_list_applies(): void
    {
        config(['monitoring.blocked_domains' => ['ozon.ru', 'aliexpress.']]);

        $this->assertTrue(SiteSupplierDiscovery::isExcluded('ozon.ru', []));
        $this->assertTrue(SiteSupplierDiscovery::isExcluded('aliexpress.ru', []));
        $this->assertFalse(SiteSupplierDiscovery::isExcluded('lift-fit.ru', []));
    }
}
