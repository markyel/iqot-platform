<?php

namespace App\Services\Monitoring\Hybrid;

use App\Models\Monitoring\MpAsk;
use App\Services\Dispatch\RecipientChannels;
use App\Services\Generate\Batch;
use App\Services\Generate\CampaignSupplierSelector;
use App\Support\Monitoring\DomainPolicy;
use App\Support\Monitoring\ItemScope;
use App\Support\Monitoring\SiteTrust;
use App\Support\Senders\SenderPool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Запросы КП мониторинга ВОЛНАМИ до цели (решения владельца 2026-10-27; заменяет «по запросу — всегда, пул — ≤8
 * поставщиков, когда у позиции < 3 цен и раунд «по запросу» закрыт»). Мониторинг спрашивает всех потенциальных
 * поставщиков позиции; надёжная цена с сайта лишь сокращает то, что нужно добрать почтой:
 *  - цель позиции — target_prices (6) цен: свежие КП + надёжные цены сайтов (SiteTrust). Набрана — не спрашиваем,
 *    ещё не отправленные запросы позиции отменяем;
 *  - кандидаты — весь профильный пул IQOT по типу × области (CampaignSupplierSelector, как план дня) и поставщики,
 *    у которых товар найден на сайте без надёжной цены (цены нет, «по запросу» — on_request; цена есть, но сайту не
 *    верим — low_trust). Без тех, чья цена уже есть (надёжная на сайте, свежее КП), кого программа спрашивала о товаре
 *    за email_refresh_days, стоп-листа (pool_exclude) и негодных к рассылке;
 *  - волна — кандидаты по убыванию ожидаемой отдачи (доля писем с ценой за yield_window_days, сглаженная к средней:
 *    у новичка — средняя; товар на сайте — не ниже site_yield), пока ожидаемые КП не покроют дефицит, но не больше
 *    wave_max. При отдаче ~1% на письмо волна почти всегда — wave_max (замер 27.10.2026: 0,89% основного режима).
 *    Только поставщики со свободной ёмкостью на окно ожидания (предел писем адресату в день × wave_wait_days − уже
 *    ждущие отправки запросы мониторинга к нему): иначе лучшие поставщики попадали бы в каждую волну и все волны ждали
 *    бы самого загруженного адресата;
 *  - следующая волна — когда письма предыдущей ушли и прошло wave_wait_days (5: за это время приходит 93% КП),
 *    а цель не набрана. Поздний ответ всё равно засчитывается.
 * Позиции с наименьшим числом цен — первыми. Только программы price_mode=hybrid. 1 позиция = 1 письмо (VerificationMailer).
 */
class RfqPlanner
{
    public const EXPIRES_DAYS = 14;

    /** Кеш снимка состояния волн (холостой прогон по расписанию) — для страницы «Ход выполнения → Мониторинг цен». */
    public const SNAPSHOT_KEY = 'monitoring:waves:snapshot';

    private const CONN = 'reports';

    private const COUNTERS = ['positions', 'done', 'in_wave', 'no_candidates', 'waves', 'asks', 'cancelled', 'saturated'];

    private const WAITING = [MpAsk::STATUS_PLANNED, MpAsk::STATUS_QUEUED];

    /** @var array<int,int> supplier_id => запросов, назначенных в этом прогоне (выравнивание нагрузки) */
    private array $assigned = [];

    /** @var array<string,int> email адресата => свободные места на окно ожидания волны */
    private array $slots = [];

    /** @var array<int,array{0:int,1:int}> supplier_id => [писем, из них с ценой] */
    private array $history = [];

    private float $prior = 0.009;

    public static function bareDomain(string $host): string
    {
        $host = mb_strtolower(trim($host));
        $host = (string) preg_replace('#^[a-z]+://#', '', $host);
        $host = explode('/', $host)[0];

        return (string) preg_replace('/^www\./', '', $host);
    }

    /** Ожидаемая доля писем с ценой: история поставщика, подтянутая к средней $prior с весом $weight писем. */
    public static function smoothedYield(int $letters, int $priced, float $prior, int $weight): float
    {
        return ($priced + $weight * $prior) / max(1, $letters + $weight);
    }

    /**
     * Волна: кандидаты по порядку (уже отсортированы по убыванию отдачи), пока ожидаемые КП не покроют дефицит,
     * не больше $max; хотя бы один, если кандидаты есть.
     *
     * @param array<int,float> $ranked supplier_id => ожидаемая доля КП
     * @return int[]
     */
    public static function wave(array $ranked, float $need, int $max): array
    {
        $out = [];
        $expected = 0.0;
        foreach ($ranked as $sid => $p) {
            if (count($out) >= $max || ($out !== [] && $expected >= $need)) {
                break;
            }
            $out[] = (int) $sid;
            $expected += $p;
        }

        return $out;
    }

    /**
     * Итог и по программам: позиций, цель набрана, идёт волна, без кандидатов, начато волн (в холостом прогоне — готовы
     * начаться), запросов, по видам, отменено лишних, пропущено занятых поставщиков.
     *
     * @return array{positions:int, done:int, in_wave:int, no_candidates:int, waves:int, asks:int, by_reason:array<string,int>,
     *               cancelled:int, saturated:int, by_program:array<int,array<string,mixed>>, pool_by_supplier:array<int,int>}
     */
    public function plan(bool $dryRun = false): array
    {
        $cfg = (array) config('monitoring.hybrid', []);
        $stats = self::emptyStats() + ['by_program' => []];
        $this->assigned = [];
        $this->slots = [];
        $this->loadHistory(max(30, (int) ($cfg['yield_window_days'] ?? 180)));
        foreach (DB::table('monitoring_programs')->where('status', 'active')->where('price_mode', 'hybrid')->get() as $program) {
            $local = self::emptyStats();
            $this->planProgram($program, $cfg, $dryRun, $local);
            $stats['by_program'][(int) $program->id] = ['name' => (string) $program->name] + $local;
            foreach (self::COUNTERS as $k) {
                $stats[$k] += $local[$k];
            }
            foreach ($local['by_reason'] as $reason => $n) {
                $stats['by_reason'][$reason] = ($stats['by_reason'][$reason] ?? 0) + $n;
            }
        }
        arsort($this->assigned);
        $stats['pool_by_supplier'] = $this->assigned;

        return $stats;
    }

    /** @return array<string,mixed> */
    private static function emptyStats(): array
    {
        return array_fill_keys(self::COUNTERS, 0) + ['by_reason' => []];
    }

    /** @param array<string,mixed> $stats */
    private function planProgram(object $program, array $cfg, bool $dryRun, array &$stats): void
    {
        $target = max(1, (int) ($cfg['target_prices'] ?? 6));
        $countSite = (bool) ($cfg['target_counts_site'] ?? true);
        $max = max(1, (int) ($cfg['wave_max'] ?? 30));
        $wait = max(1, (int) ($cfg['wave_wait_days'] ?? 5));
        $weight = max(1, (int) ($cfg['yield_prior_weight'] ?? 20));
        $siteYield = (float) ($cfg['site_yield'] ?? 0.13);
        $poolEnabled = (bool) ($cfg['pool_enabled'] ?? false);
        $exclude = array_map('mb_strtolower', (array) ($cfg['pool_exclude'] ?? []));
        $refresh = max(14, (int) ($program->email_refresh_days ?: 60));

        $products = DB::table('program_items as pi')->join('mp_products as p', 'p.id', '=', 'pi.product_id')
            ->where('pi.program_id', $program->id)->where('pi.active', true)->where('pi.match_status', 'matched')
            ->get(['p.id', 'p.product_type_id', 'p.domain_id', 'pi.identity_codes'])->unique('id')->values();
        if ($products->isEmpty()) {
            return;
        }
        $ids = $products->pluck('id')->all();
        $productCodes = ItemScope::productCodes($ids);
        $listings = DB::table('mp_listings as l')->join('mp_sites as s', 's.id', '=', 'l.site_id')
            ->whereIn('l.product_id', $ids)->where('l.bench', false)->where('l.status', 'active')
            ->get(['l.id', 'l.product_id', 'l.site_id', 'l.price', 'l.price_kind', 'l.matched_codes', 'l.match_method', 'l.match_evidence',
                's.supplier_id', 's.domain', 's.consecutive_mismatch', 's.blocked_reason', 's.blocked_until'])->groupBy('product_id');
        $outcomes = SiteTrust::lastOutcomes($listings->flatten(1)->pluck('id')->all());
        $offers = DB::table('mp_kp_offers as k')->leftJoin('mp_asks as a', 'a.id', '=', 'k.ask_id')->whereIn('k.product_id', $ids)
            ->where('k.received_at', '>', now()->subDays($refresh))->get(['k.product_id', 'k.supplier_id', 'k.listing_id', 'a.program_id'])->groupBy('product_id');
        // Запросы программы по товарам в окне свежести: кого уже спрашивали и идёт ли волна (по программе: у разных
        // клиентов под одним товаром могут быть разные варианты).
        $asks = DB::table('mp_asks')->where('program_id', $program->id)->where('kind', 'rfq')->whereIn('product_id', $ids)
            ->where('status', '!=', MpAsk::STATUS_CANCELLED)->where('created_at', '>', now()->subDays($refresh))
            ->get(['id', 'product_id', 'supplier_id', 'status', 'sent_at'])->groupBy('product_id');
        $waveSince = now()->subDays($wait)->getTimestamp();

        $selector = new CampaignSupplierSelector();
        $pools = [];
        $plans = [];
        $candidateIds = [];
        foreach ($products as $p) {
            $stats['positions']++;
            $allowed = ($listings[$p->id] ?? collect())->filter(fn ($l) => ItemScope::allows(ItemScope::itemCodes($p), $productCodes[(int) $p->id] ?? [],
                ItemScope::listingCodes($l->matched_codes, $l->match_method, $l->match_evidence)));
            $allowedIds = $allowed->pluck('id')->flip();
            $kpSuppliers = ($offers[$p->id] ?? collect())->filter(fn ($o) => $o->listing_id ? isset($allowedIds[$o->listing_id])
                : (int) $o->program_id === (int) $program->id)->pluck('supplier_id')->map(fn ($v) => (int) $v)->unique()->flip()->all();

            $siteHave = [];
            $priceSuppliers = [];
            $priceDomains = [];
            $siteCands = [];
            foreach ($allowed as $l) {
                $paused = SiteTrust::paused($l->blocked_reason, $l->blocked_until);
                if ($l->price !== null && SiteTrust::reliable($outcomes[(int) $l->id] ?? null,
                    SiteTrust::doubtful((int) $l->consecutive_mismatch, $paused), $paused)) {
                    $siteHave[(int) $l->site_id] = true;
                    $priceDomains[self::bareDomain((string) $l->domain)] = true;
                    if ($l->supplier_id !== null) {
                        $priceSuppliers[(int) $l->supplier_id] = true;
                    }
                    continue;
                }
                if ($l->supplier_id === null || DomainPolicy::isBlocked((string) $l->domain)) {
                    continue;
                }
                // Цены нет и она «по запросу» — on_request; цена есть, но сайту не верим — low_trust (цену не называем).
                // Пустая цена без «по запросу» — сбой извлечения, не повод писать адресно (поставщик может быть в пуле).
                $reason = $l->price !== null ? 'low_trust' : ($l->price_kind === 'on_request' ? 'on_request' : null);
                if ($reason !== null && !isset($siteCands[(int) $l->supplier_id])) {
                    $siteCands[(int) $l->supplier_id] = ['reason' => $reason, 'listing_id' => (int) $l->id, 'site_id' => (int) $l->site_id];
                }
            }
            // КП поставщика, чья надёжная цена уже засчитана с его сайта, — та же цена, второй раз не считаем.
            $have = ($countSite ? count($siteHave) : 0) + count(array_diff_key($kpSuppliers, $countSite ? $priceSuppliers : []));
            $productAsks = $asks[$p->id] ?? collect();

            if ($have >= $target) {
                $stats['done']++;
                $planned = $productAsks->where('status', MpAsk::STATUS_PLANNED)->pluck('id')->all();
                if ($planned !== []) {
                    $stats['cancelled'] += count($planned);
                    if (!$dryRun) {
                        MpAsk::query()->whereIn('id', $planned)->update(['status' => MpAsk::STATUS_CANCELLED, 'updated_at' => now()]);
                    }
                }
                continue;
            }
            // Волна идёт: письма ещё не ушли или ушли меньше wave_wait_days назад — ждём ответов.
            if ($productAsks->contains(fn ($a) => in_array($a->status, self::WAITING, true)
                || ($a->status === MpAsk::STATUS_SENT && $a->sent_at !== null && strtotime((string) $a->sent_at) > $waveSince))) {
                $stats['in_wave']++;
                continue;
            }

            $cands = $siteCands;
            if ($poolEnabled && $p->product_type_id) {
                $ck = $p->product_type_id . '|' . ($p->domain_id ?? 0);
                if (!isset($pools[$ck])) {
                    try {
                        $b = new Batch();
                        $b->useNewRouting = true;
                        $b->productTypeIds = [(int) $p->product_type_id];
                        $b->domainIds = $p->domain_id ? [(int) $p->domain_id] : [];
                        $pools[$ck] = array_map(fn ($s) => (int) $s['id'], $selector->select($b));
                    } catch (\Throwable $e) {
                        Log::warning('RfqPlanner: pool select failed', ['product_type_id' => $p->product_type_id, 'error' => $e->getMessage()]);
                        $pools[$ck] = [];
                    }
                }
                foreach ($pools[$ck] as $sid) {
                    $cands[$sid] ??= ['reason' => 'pool', 'listing_id' => null, 'site_id' => null];
                }
            }
            $asked = $productAsks->pluck('supplier_id')->map(fn ($v) => (int) $v)->flip()->all();
            $cands = array_diff_key($cands, $priceSuppliers, $kpSuppliers, $asked);

            $plans[] = ['product' => $p, 'have' => $have, 'need' => $target - $have, 'cands' => $cands, 'price_domains' => $priceDomains];
            $candidateIds += array_fill_keys(array_keys($cands), true);
        }
        if ($plans === []) {
            return;
        }

        $suppliers = $this->eligible(array_keys($candidateIds));
        $this->loadSlots(array_values(array_unique(array_column($suppliers, 'email'))), $wait);

        usort($plans, fn ($a, $b) => $a['have'] <=> $b['have']);
        foreach ($plans as $plan) {
            $ranked = [];
            foreach ($plan['cands'] as $sid => $meta) {
                $sup = $suppliers[$sid] ?? null;
                if ($sup === null || in_array($sup['email'], $exclude, true)
                    || array_intersect_key($sup['domains'], array_flip($exclude)) !== []
                    || array_intersect_key($sup['domains'], $plan['price_domains']) !== []) {
                    continue; // негоден к рассылке / стоп-лист / его сайт с ценой ещё не связан с карточкой
                }
                if (($this->slots[$sup['email']] ?? 0) <= 0) {
                    $stats['saturated']++;
                    continue;
                }
                [$letters, $priced] = $this->history[$sid] ?? [0, 0];
                $y = self::smoothedYield($letters, $priced, $this->prior, $weight);
                $ranked[] = [$sid, $meta['reason'] === 'pool' ? $y : max($siteYield, $y), $this->assigned[$sid] ?? 0];
            }
            // По убыванию отдачи; при равной — менее нагруженный в этом прогоне.
            usort($ranked, fn ($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);
            $wave = self::wave(array_column($ranked, 1, 0), (float) $plan['need'], $max);
            if ($wave === []) {
                $stats['no_candidates']++;
                continue;
            }
            $stats['waves']++;
            foreach ($wave as $sid) {
                $meta = $plan['cands'][$sid];
                if (!$dryRun) {
                    MpAsk::create([
                        'site_id' => $meta['site_id'], 'listing_id' => $meta['listing_id'], 'product_id' => $plan['product']->id,
                        'program_id' => $program->id, 'supplier_id' => $sid, 'kind' => 'rfq', 'reason' => $meta['reason'],
                        'status' => MpAsk::STATUS_PLANNED, 'expires_at' => now()->addDays(self::EXPIRES_DAYS),
                    ]);
                }
                $stats['asks']++;
                $stats['by_reason'][$meta['reason']] = ($stats['by_reason'][$meta['reason']] ?? 0) + 1;
                $this->slots[$suppliers[$sid]['email']]--;
                $this->assigned[$sid] = ($this->assigned[$sid] ?? 0) + 1;
            }
        }
    }

    /** История писем и ответов с ценой по поставщикам (основной режим и мониторинг) — для ожидаемой отдачи. */
    private function loadHistory(int $days): void
    {
        $this->history = [];
        $letters = 0;
        $priced = 0;
        foreach (DB::connection(self::CONN)->select('SELECT rir.supplier_id s, COUNT(*) n, SUM(rir.price_per_unit > 0 OR rir.total_price > 0) k
            FROM request_item_responses rir JOIN email_queue eq ON eq.id = rir.email_queue_id
            WHERE eq.sent_at BETWEEN NOW() - INTERVAL ? DAY AND NOW() - INTERVAL 7 DAY GROUP BY rir.supplier_id', [$days]) as $x) {
            $this->history[(int) $x->s] = [(int) $x->n, (int) $x->k];
            $letters += (int) $x->n;
            $priced += (int) $x->k;
        }
        // Средняя по платформе (последние 7 дней не берём: ответы ещё идут); мало данных — замер 27.10.2026.
        $this->prior = $letters >= 1000 ? $priced / $letters : 0.009;
    }

    /**
     * Годные к рассылке поставщики (как VerificationMailer::eligibleSuppliers): адрес и домены почты/сайта.
     *
     * @param int[] $ids
     * @return array<int,array{email:string, domains:array<string,true>}>
     */
    private function eligible(array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::connection(self::CONN)->table('suppliers as s')->whereIn('s.id', $chunk)
                ->where('s.is_active', 1)->where('s.notify_email', 1)->whereNotNull('s.email')->where('s.email', '!=', '')
                ->where(fn ($q) => $q->whereNull('s.unsubscribe_until')->orWhere('s.unsubscribe_until', '<', now()))
                ->whereRaw("NOT EXISTS (SELECT 1 FROM blocked_domains bd WHERE bd.domain = SUBSTRING_INDEX(LOWER(s.email), '@', -1))")
                ->whereRaw('NOT EXISTS (SELECT 1 FROM recipient_mailboxes rm WHERE rm.is_blocked = 1 AND rm.email = LOWER(TRIM(s.email)))')
                ->get(['s.id', 's.email', 's.website']) as $s) {
                $email = mb_strtolower(trim((string) $s->email));
                $out[(int) $s->id] = ['email' => $email, 'domains' => array_fill_keys(array_values(array_filter([
                    ltrim((string) strrchr($email, '@'), '@'), self::bareDomain((string) $s->website)])), true)];
            }
        }

        return $out;
    }

    /**
     * Свободные места адресата на окно ожидания волны: предел писем мониторинга в день (кап канала — RecipientChannels,
     * с конкуренцией заявок; ротация — ящик пишет адресату раз в 7 дней ≈ 5 рабочих) × дней окна − уже ждущие
     * отправки запросы мониторинга к нему (все программы, все виды).
     *
     * @param string[] $emails
     */
    private function loadSlots(array $emails, int $wait): void
    {
        $emails = array_values(array_diff($emails, array_keys($this->slots)));
        if ($emails === []) {
            return;
        }
        $channels = new RecipientChannels();
        $meta = $channels->recipientMeta($emails);
        $monitoring = $channels->monitoringBatchIds();
        $busy = [];
        foreach (array_chunk($emails, 1000) as $chunk) {
            foreach (DB::connection(self::CONN)->table('email_queue')->whereIn(DB::raw('LOWER(to_email)'), $chunk)
                ->where('created_at', '>=', now()->subDays(7))
                ->whereIn('status', ['pending', 'sending', 'sent', 'opened', 'replied', 'reply_processed', 'in_conversation', 'completed'])
                ->get([DB::raw('LOWER(to_email) as e'), 'sender_id', 'batch_id']) as $q) {
                if ($q->batch_id !== null && isset($monitoring[(int) $q->batch_id])) {
                    continue;
                }
                $busy[$q->e][(int) $q->sender_id] = true; // ящик писал адресату по заявкам — до ротации не напишет
            }
        }
        $mailboxes = SenderPool::onlyMain(DB::connection(self::CONN)->table('senders'))->where('is_active', 1)
            ->where(fn ($q) => $q->whereNull('sending_disabled')->orWhere('sending_disabled', 0))->where('daily_limit', '>', 0)
            ->where(fn ($q) => $q->whereNull('is_personal')->orWhere('is_personal', 0))->count();

        $waiting = DB::table('mp_asks')->whereIn('status', self::WAITING)->selectRaw('supplier_id, COUNT(*) n')->groupBy('supplier_id')->pluck('n', 'supplier_id');
        $backlog = [];
        foreach (array_chunk($waiting->keys()->all(), 1000) as $chunk) {
            foreach (DB::connection(self::CONN)->table('suppliers')->whereIn('id', $chunk)->get(['id', 'email']) as $s) {
                $e = mb_strtolower(trim((string) $s->email));
                $backlog[$e] = ($backlog[$e] ?? 0) + (int) $waiting[$s->id];
            }
        }
        foreach ($emails as $e) {
            $n = count($busy[$e] ?? []);
            $perDay = min(RecipientChannels::cap(RecipientChannels::MONITORING, $meta[$e]['cap'], $n > 0), max(0, $mailboxes - $n) / 5);
            $this->slots[$e] = $meta[$e]['blocked'] ? 0 : (int) floor($perDay * $wait) - ($backlog[$e] ?? 0);
        }
    }
}
