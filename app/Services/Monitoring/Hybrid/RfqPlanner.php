<?php

namespace App\Services\Monitoring\Hybrid;

use App\Models\Monitoring\MpAsk;
use App\Services\Generate\Batch;
use App\Services\Generate\CampaignSupplierSelector;
use App\Support\Monitoring\DomainPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Запросы КП у поставщиков без цены на сайте (Ф3+ «Гибрид», решения владельца 2026-10-15):
 *  1. «По запросу» — ВСЕГДА: каждому поставщику, у которого на сайте найдена страница товара без
 *     цены (товар у него точно есть), независимо от числа цен с других сайтов;
 *  2. Пул — потом: если после раунда «по запросу» (нет открытых запросов по товару) у позиции
 *     меньше POOL_TARGET_PRICES цен (сайты + КП), — профильному пулу IQOT по типу/области товара
 *     (CampaignSupplierSelector — карма, стоп-листы, отписки как в основном режиме).
 * Только программы price_mode=hybrid. Повтор по паре товар × поставщик — не чаще email_refresh_days
 * программы (по умолчанию 60). Отдельного лимита писем нет — общие лимиты ящиков и капы получателей.
 */
class RfqPlanner
{
    public const POOL_TARGET_PRICES = 3;

    public const POOL_SUPPLIERS_PER_ROUND = 8;

    public const EXPIRES_DAYS = 14;

    /** @return array{on_request: int, pool: int} */
    public function plan(bool $dryRun = false): array
    {
        $stats = ['on_request' => 0, 'pool' => 0];
        foreach (DB::table('monitoring_programs')->where('status', 'active')->where('price_mode', 'hybrid')->get() as $program) {
            $refresh = max(14, (int) ($program->email_refresh_days ?: 60));
            $stats['on_request'] += $this->onRequest($program, $refresh, $dryRun);
            $stats['pool'] += $this->pool($program, $refresh, $dryRun);
        }

        return $stats;
    }

    private function onRequest(object $program, int $refresh, bool $dryRun): int
    {
        $rows = DB::table('mp_listings as l')->join('mp_sites as s', 's.id', '=', 'l.site_id')
            ->join('program_items as pi', 'pi.product_id', '=', 'l.product_id')
            ->where('pi.program_id', $program->id)->where('pi.active', true)
            ->where('l.bench', false)->where('l.status', 'active')->whereNull('l.price')
            ->whereNotNull('s.supplier_id')
            ->where(fn ($q) => $q->whereNull('s.blocked_until')->orWhere('s.blocked_until', '<', now()))
            ->whereNotExists(fn ($q) => $q->from('mp_asks as a')->whereColumn('a.product_id', 'l.product_id')
                ->whereColumn('a.supplier_id', 's.supplier_id')->where('a.kind', 'rfq')
                ->where('a.created_at', '>', now()->subDays($refresh))->where('a.status', '!=', MpAsk::STATUS_CANCELLED))
            ->whereNotExists(fn ($q) => $q->from('mp_kp_offers as k')->whereColumn('k.product_id', 'l.product_id')
                ->whereColumn('k.supplier_id', 's.supplier_id')->where('k.received_at', '>', now()->subDays($refresh)))
            ->get(['l.id', 'l.site_id', 'l.product_id', 's.supplier_id', 's.domain']);

        $made = 0;
        $seen = [];
        foreach ($rows as $r) {
            $key = $r->product_id . '|' . $r->supplier_id;
            if (isset($seen[$key]) || DomainPolicy::isBlocked($r->domain)) {
                continue;
            }
            $seen[$key] = true;
            if (!$dryRun) {
                MpAsk::create([
                    'site_id' => $r->site_id, 'listing_id' => $r->id, 'product_id' => $r->product_id, 'program_id' => $program->id,
                    'supplier_id' => $r->supplier_id, 'kind' => 'rfq', 'reason' => 'on_request',
                    'status' => MpAsk::STATUS_PLANNED, 'expires_at' => now()->addDays(self::EXPIRES_DAYS),
                ]);
            }
            $made++;
        }

        return $made;
    }

    private function pool(object $program, int $refresh, bool $dryRun): int
    {
        $products = DB::table('program_items as pi')->join('mp_products as p', 'p.id', '=', 'pi.product_id')
            ->where('pi.program_id', $program->id)->where('pi.active', true)->where('pi.match_status', 'matched')
            ->whereNotNull('p.product_type_id')
            ->distinct()->get(['p.id', 'p.product_type_id', 'p.domain_id']);
        if ($products->isEmpty()) {
            return 0;
        }
        $ids = $products->pluck('id')->all();

        // Сколько цен у товара: сайты (активные с ценой) + свежие КП (разные поставщики).
        $sitePriced = DB::table('mp_listings')->whereIn('product_id', $ids)->where('bench', false)->where('status', 'active')
            ->whereNotNull('price')->groupBy('product_id')->selectRaw('product_id, COUNT(DISTINCT site_id) c')->pluck('c', 'product_id');
        $kp = DB::table('mp_kp_offers')->whereIn('product_id', $ids)->where('received_at', '>', now()->subDays($refresh))
            ->groupBy('product_id')->selectRaw('product_id, COUNT(DISTINCT supplier_id) c')->pluck('c', 'product_id');
        // Раунд «по запросу» ещё идёт — пул позже; пул по товару уже спрашивали в окне — не повторяем.
        $open = DB::table('mp_asks')->whereIn('product_id', $ids)->where('kind', 'rfq')
            ->whereIn('status', [MpAsk::STATUS_PLANNED, MpAsk::STATUS_QUEUED, MpAsk::STATUS_SENT])->distinct()->pluck('product_id')->flip();
        $poolDone = DB::table('mp_asks')->whereIn('product_id', $ids)->where('kind', 'rfq')->where('reason', 'pool')
            ->where('created_at', '>', now()->subDays($refresh))->where('status', '!=', MpAsk::STATUS_CANCELLED)
            ->distinct()->pluck('product_id')->flip();

        $selector = new CampaignSupplierSelector();
        $poolCache = [];
        $made = 0;
        foreach ($products as $p) {
            $have = (int) ($sitePriced[$p->id] ?? 0) + (int) ($kp[$p->id] ?? 0);
            if ($have >= self::POOL_TARGET_PRICES || isset($open[$p->id]) || isset($poolDone[$p->id])) {
                continue;
            }
            $ck = $p->product_type_id . '|' . ($p->domain_id ?? 0);
            if (!isset($poolCache[$ck])) {
                try {
                    $b = new Batch();
                    $b->useNewRouting = true;
                    $b->productTypeIds = [(int) $p->product_type_id];
                    $b->domainIds = $p->domain_id ? [(int) $p->domain_id] : [];
                    $poolCache[$ck] = array_map(fn ($s) => (int) $s['id'], $selector->select($b));
                } catch (\Throwable $e) {
                    Log::warning('RfqPlanner: pool select failed', ['product_type_id' => $p->product_type_id, 'error' => $e->getMessage()]);
                    $poolCache[$ck] = [];
                }
            }
            if ($poolCache[$ck] === []) {
                continue;
            }
            // Исключить: уже спрошенных по товару в окне (любой запрос) и тех, у кого на сайте есть цена.
            $asked = DB::table('mp_asks')->where('product_id', $p->id)->where('created_at', '>', now()->subDays($refresh))
                ->where('status', '!=', MpAsk::STATUS_CANCELLED)->pluck('supplier_id')->flip();
            $withPrice = DB::table('mp_listings as l')->join('mp_sites as s', 's.id', '=', 'l.site_id')
                ->where('l.product_id', $p->id)->where('l.status', 'active')->whereNotNull('l.price')->whereNotNull('s.supplier_id')
                ->pluck('s.supplier_id')->flip();
            $take = 0;
            foreach ($poolCache[$ck] as $sid) {
                if ($take >= self::POOL_SUPPLIERS_PER_ROUND) {
                    break;
                }
                if (isset($asked[$sid]) || isset($withPrice[$sid])) {
                    continue;
                }
                if (!$dryRun) {
                    MpAsk::create([
                        'site_id' => null, 'listing_id' => null, 'product_id' => $p->id, 'program_id' => $program->id,
                        'supplier_id' => $sid, 'kind' => 'rfq', 'reason' => 'pool',
                        'status' => MpAsk::STATUS_PLANNED, 'expires_at' => now()->addDays(self::EXPIRES_DAYS),
                    ]);
                }
                $take++;
                $made++;
            }
        }

        return $made;
    }
}
