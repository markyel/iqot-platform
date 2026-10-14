<?php

namespace App\Console\Commands\Monitoring;

use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Отчёт пилота (Ф1, docs/monitoring-design.md §9.1): поиск вслепую против эталона
 * Content Engine.
 *  - покрытие: доля позиций программы с ценой на активном листинге;
 *  - полнота (recall): доля пар «товар × домен продавца с ценой» из CE, которые IQOT
 *    нашёл сам (активный листинг с ценой на том же домене);
 *  - новые продавцы: пары IQOT с ценой, которых нет в CE;
 *  - качество отбора: статусы найденных листингов (mismatch — «не тот товар» и т. п.);
 *  - стоимость: mp_budget_ledger.
 */
class PilotReport extends Command
{
    protected $signature = 'monitoring:pilot-report {program} {ce_csv}';

    protected $description = 'Мониторинг цен: отчёт пилота — покрытие, полнота против Content Engine, новые продавцы, стоимость';

    public function handle(): int
    {
        $programId = (int) $this->argument('program');
        $items = DB::table('program_items')->where('program_id', $programId)->where('active', true)->whereNotNull('product_id')
            ->get(['client_ref', 'product_id']);
        $productByRef = $items->pluck('product_id', 'client_ref')->all();
        $productIds = array_values(array_unique(array_map('intval', $productByRef)));

        // IQOT: найденные листинги (без стенда) — пары товар×домен.
        $listings = DB::table('mp_listings as l')->join('mp_sites as s', 's.id', '=', 'l.site_id')
            ->whereIn('l.product_id', $productIds)->where('l.bench', false)
            ->get(['l.product_id', 's.domain', 'l.status', 'l.price', 'l.found_via']);
        $iqPriced = [];
        $iqAny = [];
        $byStatus = [];
        foreach ($listings as $l) {
            $key = $l->product_id . '|' . $l->domain;
            $iqAny[$key] = true;
            if ($l->status === 'active' && $l->price !== null) {
                $iqPriced[$key] = true;
            }
            $st = $l->status === 'active' ? ($l->price !== null ? 'active_priced' : 'active_no_price') : $l->status;
            $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
        }

        // CE: пары товар×домен с ценой.
        $ce = [];
        $fh = fopen((string) $this->argument('ce_csv'), 'r');
        $h = fgetcsv($fh, 0, ',', '"', '\\');
        while (($r = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (count($r) !== count($h)) {
                continue;
            }
            $r = array_combine($h, $r);
            $pid = $productByRef[$r['lw_code']] ?? null;
            if ($pid && (float) $r['price'] > 0) {
                $ce[$pid . '|' . UrlCanonicalizer::registrableDomain($r['url'])] = true;
            }
        }
        fclose($fh);

        $ceProducts = [];
        foreach (array_keys($ce) as $k) {
            $ceProducts[explode('|', $k)[0]] = true;
        }
        $iqProducts = [];
        foreach (array_keys($iqPriced) as $k) {
            $iqProducts[explode('|', $k)[0]] = true;
        }
        $foundCe = count(array_intersect_key($ce, $iqPriced));
        $touchedCe = count(array_intersect_key($ce, $iqAny));
        $newPairs = count(array_diff_key($iqPriced, $ce));
        $searched = DB::table('mp_products')->whereIn('id', $productIds)->where('search_attempts', '>', 0)->count();
        $states = DB::table('mp_products')->whereIn('id', $productIds)->selectRaw('search_state, COUNT(*) c')->groupBy('search_state')->pluck('c', 'search_state')->all();
        $ledger = DB::table('mp_budget_ledger')->selectRaw('resource, SUM(units) u, SUM(cost_usd) usd, SUM(cost_rub) rub')->groupBy('resource')->get();

        $pct = fn (int $a, int $b) => $b > 0 ? sprintf('%d/%d (%.1f%%)', $a, $b, 100 * $a / $b) : '—';
        $this->table(['metric', 'value'], [
            ['позиций программы (сопоставлено)', count($productByRef)],
            ['товаров искали', $searched],
            ['состояния поиска', json_encode($states)],
            ['ПОКРЫТИЕ: товары с ценой (IQOT)', $pct(count($iqProducts), count($productIds))],
            ['товары с ценой в CE (эталон)', $pct(count($ceProducts), count($productIds))],
            ['ПОЛНОТА: пары CE (товар×продавец с ценой), найденные IQOT с ценой', $pct($foundCe, count($ce))],
            ['пары CE, где IQOT нашёл страницу продавца (любой статус)', $pct($touchedCe, count($ce))],
            ['НОВЫЕ: пары IQOT с ценой, которых нет в CE', $newPairs],
            ['листинги IQOT по статусу', json_encode($byStatus)],
        ]);
        $this->table(['resource', 'units', '$', '₽'], $ledger->map(fn ($r) => [$r->resource, $r->u, round((float) $r->usd, 4), round((float) $r->rub, 2)])->all());

        return self::SUCCESS;
    }
}
