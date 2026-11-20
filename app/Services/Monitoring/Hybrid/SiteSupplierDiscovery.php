<?php

namespace App\Services\Monitoring\Hybrid;

use App\Jobs\Monitoring\DiscoverSiteSupplierJob;
use App\Support\Monitoring\DomainPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Сайты мониторинга → кандидаты в поставщики IQOT (решение владельца 20.11.2026).
 * Сайт, где нашлась цена на нашу позицию, но нет поставщика в reports.suppliers (SiteSupplierLinker не сопоставил),
 * проверяется тем же путём, что и находки предрассылочного таргетинга: SupplierDiscoveryService::discoverFromUrl
 * (страница + ИИ: B2B-поставщик, контакты; тип — по товару листинга, область — гейт domain_match/hits).
 * Найденный/расширенный поставщик сразу привязывается к сайту (mp_sites.supplier_id) → Гибрид может ему писать.
 * Не берём: маркетплейсы/агрегаторы, стоп-лист мониторинга, reports.blocked_domains (домены клиентов),
 * стоп-лист пула Гибрида. Итог проверки — mp_sites.supplier_discovery_*; сбой загрузки/ИИ — повтор через 7 дней.
 */
class SiteSupplierDiscovery
{
    /** Исходы, после которых сайт не перепроверяем. */
    public const FINAL = ['created', 'extended', 'rejected', 'excluded'];

    public const RETRY_DAYS = 7;

    /**
     * @return array{candidates: int, dispatched: int, excluded: int}
     */
    public function dispatch(int $limit, bool $dryRun = false): array
    {
        $blocked = array_merge(
            DB::connection('reports')->table('blocked_domains')->pluck('domain')->all(),
            (array) config('monitoring.hybrid.pool_exclude', []),
        );

        // Сайт без поставщика + листинг с ценой на товар с известным типом; по сайту — самый свежий такой листинг.
        $rows = DB::table('mp_sites as s')
            ->join('mp_listings as l', 'l.site_id', '=', 's.id')
            ->join('mp_products as p', 'p.id', '=', 'l.product_id')
            ->whereNull('s.supplier_id')
            ->where(fn ($q) => $q->whereNull('s.blocked_until')->orWhere('s.blocked_until', '<', now()))
            ->whereNotIn('s.kind', ['marketplace', 'aggregator'])
            ->where(fn ($q) => $q->whereNull('s.supplier_discovery_at')
                ->orWhere(fn ($q) => $q->whereNotIn('s.supplier_discovery_result', self::FINAL)
                    ->where('s.supplier_discovery_at', '<', now()->subDays(self::RETRY_DAYS))))
            ->where('l.status', 'active')
            ->where('l.price', '>', 0)
            ->whereNotNull('p.product_type_id')
            ->orderByDesc('l.observed_at')
            ->get(['s.id as site_id', 's.domain', 'l.url', 'p.product_type_id', 'p.domain_id']);

        $sites = [];
        foreach ($rows as $r) {
            $sites[$r->site_id] ??= $r;
        }

        $dispatched = 0;
        $excluded = 0;
        foreach ($sites as $site) {
            if (self::isExcluded($site->domain, $blocked)) {
                $excluded++;
                if (!$dryRun) {
                    $this->mark($site->site_id, 'excluded');
                }
                continue;
            }
            if ($dispatched >= $limit) {
                continue;
            }
            $dispatched++;
            if ($dryRun) {
                continue;
            }
            // Отметка до постановки в очередь — следующий тик сайт не возьмёт повторно.
            $this->mark($site->site_id, 'queued');
            DiscoverSiteSupplierJob::dispatch((int) $site->site_id, (string) $site->url, (int) $site->product_type_id,
                $site->domain_id !== null ? (int) $site->domain_id : null);
        }

        return ['candidates' => count($sites), 'dispatched' => $dispatched, 'excluded' => $excluded];
    }

    /** @param array<int,string> $blocked */
    public static function isExcluded(string $domain, array $blocked): bool
    {
        return DomainPolicy::isBlocked($domain) || DomainPolicy::isBlocked($domain, $blocked);
    }

    private function mark(int $siteId, string $result): void
    {
        DB::table('mp_sites')->where('id', $siteId)->update([
            'supplier_discovery_at' => now(),
            'supplier_discovery_result' => $result,
            'updated_at' => now(),
        ]);
    }
}
