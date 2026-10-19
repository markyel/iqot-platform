<?php

namespace App\Services\Monitoring\Hybrid;

use App\Models\Monitoring\MpAsk;
use App\Models\Monitoring\MpSite;
use App\Support\Monitoring\ItemScope;
use App\Support\Monitoring\MonitoringSettings;
use Illuminate\Support\Facades\DB;

/**
 * Выборка позиций на проверочный КП (Ф3, docs/monitoring-design.md §2.3) → mp_asks(planned).
 *
 * Кандидаты — листинги с ценой с сайта, обновлённой за 14 дней, по товарам программ в режиме
 * hybrid, на сайтах, сопоставленных с поставщиком (mp_sites.supplier_id), без запроса по этому
 * листингу за 60 дней. По сайту — TrustPolicy::sampleSize (уровень 0: 3–5 позиций, выше — 1 из
 * 10/50/100, не реже раза в 20 дней). Порядок позиций: цена не подтверждалась или изменилась
 * после подтверждения → дороже → прочие. Сайты: сначала уровень 0 и подошедший срок проверки.
 * Бюджет — проверочных писем в сутки (агрессивность) × позиций в письме (как в обычной рассылке), поровну между
 * программами (остаток — другим); программы с settings.verify_paused не проверяются.
 */
class VerificationPlanner
{
    public const ASK_COOLDOWN_DAYS = 60;

    public const SITE_PRICE_MAX_AGE_DAYS = 14;

    public const EXPIRES_DAYS = 14;

    /**
     * @return array{budget_items: int, planned: int, sites: int}
     */
    public function plan(bool $dryRun = false): array
    {
        $budget = MonitoringSettings::get('verify_letters_per_day') * VerificationMailer::itemsPerLetter()
            - MpAsk::query()->where('kind', 'verify')->where('created_at', '>=', now()->startOfDay())->count();
        if ($budget <= 0) {
            return ['budget_items' => 0, 'planned' => 0, 'sites' => 0];
        }

        $programs = DB::table('monitoring_programs')->where('status', 'active')->where('price_mode', 'hybrid')->get(['id', 'settings'])
            ->reject(fn ($p) => self::verifyPaused($p))->pluck('id');
        if ($programs->isEmpty()) {
            return ['budget_items' => $budget, 'planned' => 0, 'sites' => 0];
        }

        // Кандидаты: листинг × позиция hybrid-программы, которой эта страница видна (ItemScope: у позиции,
        // заявившей не все коды товара, — только страницы с её кодами); программа — первая из таких.
        $raw = DB::table('mp_listings as l')
            ->join('mp_sites as s', 's.id', '=', 'l.site_id')
            ->join('program_items as pi', 'pi.product_id', '=', 'l.product_id')
            ->whereIn('pi.program_id', $programs)->where('pi.active', true)
            ->where('l.bench', false)->where('l.status', 'active')->whereNotNull('l.price')
            ->where('l.observed_at', '>=', now()->subDays(self::SITE_PRICE_MAX_AGE_DAYS))
            ->whereNotNull('s.supplier_id')
            ->where(fn ($q) => $q->whereNull('s.blocked_until')->orWhere('s.blocked_until', '<', now()))
            ->whereNotExists(fn ($q) => $q->from('mp_asks as a')->whereColumn('a.listing_id', 'l.id')
                ->where('a.created_at', '>', now()->subDays(self::ASK_COOLDOWN_DAYS))->where('a.status', '!=', MpAsk::STATUS_CANCELLED))
            ->orderBy('pi.program_id')
            ->get(['l.id', 'l.site_id', 'l.product_id', 'l.price', 'l.currency', 'l.vat_included', 'l.availability', 'l.observed_at',
                'l.confirmed_price', 'l.matched_codes', 'l.match_method', 'l.match_evidence', 's.supplier_id', 'pi.program_id', 'pi.identity_codes']);
        $productCodes = ItemScope::productCodes($raw->pluck('product_id')->all());
        $rows = $raw->filter(fn ($r) => ItemScope::allows(ItemScope::itemCodes($r), $productCodes[(int) $r->product_id] ?? [],
            ItemScope::listingCodes($r->matched_codes, $r->match_method, $r->match_evidence)))
            ->unique(fn ($r) => $r->id . '|' . $r->program_id)->values();

        $sites = MpSite::query()->whereIn('id', $rows->pluck('site_id')->unique())->get()
            ->reject(fn (MpSite $s) => \App\Support\Monitoring\DomainPolicy::isBlocked($s->domain)) // стоп-лист (маркетплейсы и т. п.)
            ->keyBy('id');

        // Бюджет делится между программами поровну: очередь сайтов у каждой программы своя (сначала уровень 0 и
        // подошедший срок, затем больше кандидатов), программы берут по сайту по очереди; кончились сайты у одной —
        // остаток уходит другим. Раньше общая очередь сортировалась по числу кандидатов, и программа с большим
        // каталогом (Пилот, 10,5 тыс. цен) забирала все 100 проверок в день (19.10.2026).
        $queues = [];
        $siteRows = [];
        foreach ($rows->groupBy('program_id') as $pid => $programRows) {
            $bySite = $programRows->groupBy('site_id');
            $siteRows[$pid] = $bySite;
            $queues[$pid] = $bySite->keys()->filter(fn ($sid) => isset($sites[$sid]))
                ->sortBy(fn ($sid) => [
                    (int) $sites[$sid]->trust_level === 0 ? 0 : 1,
                    $sites[$sid]->next_verification_due_at === null || $sites[$sid]->next_verification_due_at->isPast() ? 0 : 1,
                    -count($bySite[$sid]),
                ])->values()->all();
        }

        $planned = 0;
        $touched = 0;
        $doneSites = [];
        $doneListings = [];
        while ($planned < $budget && $queues !== []) {
            foreach (array_keys($queues) as $pid) {
                if ($planned >= $budget) {
                    break;
                }
                $sid = array_shift($queues[$pid]);
                if ($sid === null) {
                    unset($queues[$pid]);
                    continue;
                }
                if (isset($doneSites[$sid])) {
                    continue; // сайт уже взят в этом прогоне другой программой — доверие к сайту общее
                }
                $site = $sites[$sid];
                $cands = $siteRows[$pid][$sid]->reject(fn ($r) => isset($doneListings[$r->id]))->sortBy(fn ($r) => [
                    $r->confirmed_price === null || abs((float) $r->confirmed_price - (float) $r->price) > 0.01 ? 0 : 1,
                    -(float) $r->price,
                ])->values();
                $take = min(TrustPolicy::sampleSize($site, $cands->count()), $budget - $planned);
                if ($take <= 0) {
                    continue;
                }
                $reason = (int) $site->trust_level === 0 ? 'first'
                    : ($site->next_verification_due_at === null || $site->next_verification_due_at->isPast() ? 'due' : 'sample');
                foreach ($cands->take($take) as $r) {
                    if (!$dryRun) {
                        MpAsk::create([
                            'site_id' => $site->id, 'listing_id' => $r->id, 'product_id' => $r->product_id,
                            'program_id' => $r->program_id, 'supplier_id' => $r->supplier_id, 'kind' => 'verify',
                            'reason' => $r->confirmed_price !== null && abs((float) $r->confirmed_price - (float) $r->price) > 0.01 ? 'price_changed' : $reason,
                            'site_price' => $r->price, 'site_currency' => $r->currency, 'site_vat_included' => $r->vat_included,
                            'site_availability' => $r->availability, 'site_observed_at' => $r->observed_at,
                            'status' => MpAsk::STATUS_PLANNED, 'expires_at' => now()->addDays(self::EXPIRES_DAYS),
                        ]);
                    }
                    $doneListings[$r->id] = true;
                    $planned++;
                }
                $doneSites[$sid] = true;
                $touched++;
            }
        }

        return ['budget_items' => $budget, 'planned' => $planned, 'sites' => $touched];
    }

    /** Проверки цен программы на паузе (settings.verify_paused) — решение владельца 19.10.2026 для Пилота. */
    public static function verifyPaused(object $program): bool
    {
        $s = is_array($program->settings ?? null) ? $program->settings : (json_decode((string) ($program->settings ?? ''), true) ?: []);

        return !empty($s['verify_paused']);
    }
}
