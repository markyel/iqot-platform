<?php

namespace App\Services\Admin;

use App\Services\Monitoring\Web\EgressPool;
use App\Services\Senders\RelayHttpMailer;
use App\Support\Monitoring\MonitoringSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Данные админки «Ход выполнения» (/manage/emails/campaigns и вкладки): карточка заявки,
 * API-заявки, мониторинг цен, охват поставщиков, ресурсы системы. Только чтение.
 * reports — боевой почтовый пайплайн (Beget), mysql — iqot (API, мониторинг).
 */
class ProgressDashboard
{
    private const R = 'reports';

    private const SENT = "('sent','opened','replied','reply_processed','in_conversation','completed')";

    private const REPLIED = "('replied','reply_processed','in_conversation','completed')";

    private const HELD = '2037-01-01 00:00:00';

    // ─────────────────────────── Карточка заявки ───────────────────────────

    /** @return array<string,mixed>|null */
    public function request(int $id): ?array
    {
        $db = DB::connection(self::R);
        $req = $db->table('requests')->where('id', $id)->first();
        if (!$req) {
            return null;
        }
        $items = $db->table('request_items')->where('request_id', $id)->orderBy('position_number')->orderBy('id')
            ->get(['id', 'position_number', 'name', 'article', 'brand', 'quantity', 'unit', 'status', 'product_type_id', 'domain_id']);
        $ids = $items->pluck('id')->all();
        $agg = $ids === [] ? collect() : $db->table('request_item_responses as rir')
            ->leftJoin('email_queue as eq', 'eq.id', '=', 'rir.email_queue_id')
            ->whereIn('rir.request_item_id', $ids)
            ->selectRaw("rir.request_item_id iid,
                COUNT(DISTINCT rir.supplier_id) asked,
                COUNT(DISTINCT CASE WHEN eq.status IN " . self::SENT . " THEN rir.supplier_id END) sent,
                COUNT(DISTINCT CASE WHEN eq.status = 'pending' AND eq.scheduled_at < ? THEN rir.supplier_id END) pending,
                COUNT(DISTINCT CASE WHEN eq.status = 'pending' AND eq.scheduled_at >= ? THEN rir.supplier_id END) held,
                COUNT(DISTINCT CASE WHEN eq.status IN " . self::REPLIED . " THEN rir.supplier_id END) replied,
                COUNT(DISTINCT CASE WHEN rir.price_per_unit > 0 OR rir.total_price > 0 THEN rir.supplier_id END) priced,
                MIN(CASE WHEN rir.price_per_unit > 0 THEN rir.price_per_unit END) best", [self::HELD, self::HELD])
            ->groupBy('rir.request_item_id')->get()->keyBy('iid');
        $types = $db->table('product_types')->whereIn('id', $items->pluck('product_type_id')->filter()->unique())->pluck('name', 'id');

        $rows = $items->map(function ($i) use ($agg, $types) {
            $a = $agg->get($i->id);

            return [
                'pos' => $i->position_number, 'name' => $i->name, 'article' => $i->article, 'brand' => $i->brand,
                'qty' => (float) $i->quantity . ' ' . $i->unit, 'status' => $i->status,
                'type' => $types[$i->product_type_id] ?? null,
                'asked' => (int) ($a->asked ?? 0), 'sent' => (int) ($a->sent ?? 0), 'pending' => (int) ($a->pending ?? 0),
                'held' => (int) ($a->held ?? 0), 'replied' => (int) ($a->replied ?? 0), 'priced' => (int) ($a->priced ?? 0),
                'best' => $a && $a->best !== null ? (float) $a->best : null,
            ];
        })->all();

        $letters = $db->table('email_queue as eq')->join('email_batches as b', 'b.id', '=', 'eq.batch_id')
            ->whereIn('eq.id', $ids === [] ? [0] : $db->table('request_item_responses')->whereIn('request_item_id', $ids)->whereNotNull('email_queue_id')->select('email_queue_id'))
            ->selectRaw("COUNT(*) total, SUM(eq.status IN " . self::SENT . ") sent, SUM(eq.status IN " . self::REPLIED . ") replied,
                SUM(eq.status='pending' AND eq.scheduled_at < ?) pending, SUM(eq.status='pending' AND eq.scheduled_at >= ?) held,
                SUM(eq.status IN ('error','failed')) failed, MIN(eq.sent_at) first_sent, MAX(eq.sent_at) last_sent,
                COUNT(DISTINCT eq.sender_id) senders, ROUND(AVG(b.items_count),1) per_letter", [self::HELD, self::HELD])->first();

        return [
            'req' => $req,
            'items' => $rows,
            'letters' => $letters,
            'summary' => [
                'items' => count($rows),
                'priced1' => count(array_filter($rows, fn ($r) => $r['priced'] >= 1)),
                'priced3' => count(array_filter($rows, fn ($r) => $r['priced'] >= 3)),
                'no_pool' => count(array_filter($rows, fn ($r) => $r['asked'] === 0)),
            ],
        ];
    }

    /** Позиции с ≥1 и ≥3 ценами по заявкам (для списка активных). @return array<int, array{p1:int,p3:int}> */
    public function pricedByRequest(array $requestIds): array
    {
        if ($requestIds === []) {
            return [];
        }
        $rows = DB::connection(self::R)->select('SELECT a.rid, SUM(a.c >= 1) p1, SUM(a.c >= 3) p3 FROM (
                SELECT ri.request_id rid, ri.id, COUNT(DISTINCT CASE WHEN rir.price_per_unit > 0 OR rir.total_price > 0 THEN rir.supplier_id END) c
                FROM request_items ri LEFT JOIN request_item_responses rir ON rir.request_item_id = ri.id
                WHERE ri.request_id IN (' . implode(',', array_map('intval', $requestIds)) . ') GROUP BY ri.request_id, ri.id) a GROUP BY a.rid');
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->rid] = ['p1' => (int) $r->p1, 'p3' => (int) $r->p3];
        }

        return $out;
    }

    // ─────────────────────────── API-заявки ───────────────────────────

    /** @return array<string,mixed> */
    public function api(int $days = 30): array
    {
        $since = now()->subDays($days);
        $clients = DB::table('api_clients as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->get(['c.id', 'c.is_active', 'u.name', 'u.email', 'u.balance']);
        $subs = DB::table('api_submissions')->where('created_at', '>=', $since)
            ->selectRaw('api_client_id, status, COUNT(*) c, SUM(items_total) items, SUM(items_accepted) acc, SUM(items_rejected) rej')
            ->groupBy('api_client_id', 'status')->get()->groupBy('api_client_id');
        $items = DB::table('request_items_staging as i')->join('request_staging as s', 's.id', '=', 'i.request_staging_id')
            ->join('api_submissions as a', 'a.id', '=', 's.api_submission_id')->where('a.created_at', '>=', $since)
            ->selectRaw('a.api_client_id, i.item_status st, COUNT(*) c')->groupBy('a.api_client_id', 'i.item_status')->get()->groupBy('api_client_id');
        $reasons = DB::table('request_items_staging as i')->join('request_staging as s', 's.id', '=', 'i.request_staging_id')
            ->join('api_submissions as a', 'a.id', '=', 's.api_submission_id')->where('a.created_at', '>=', $since)
            ->whereNotNull('i.rejection_reason')->selectRaw('i.rejection_reason r, COUNT(*) c')->groupBy('i.rejection_reason')
            ->orderByDesc('c')->pluck('c', 'r')->all();
        $holds = DB::table('balance_holds')->whereNotNull('api_submission_id')
            ->selectRaw("status, COUNT(*) c, SUM(amount) amount")->groupBy('status')->get()->keyBy('status');
        $readyMin = DB::table('api_submissions')->where('created_at', '>=', $since)->whereNotNull('ready_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, ready_at)) m')->value('m');
        $recent = DB::table('api_submissions as a')->leftJoin('api_clients as c', 'c.id', '=', 'a.api_client_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')->orderByDesc('a.id')->limit(30)
            ->get(['a.id', 'a.external_id', 'a.client_ref', 'a.status', 'a.stage', 'a.items_total', 'a.items_accepted', 'a.items_rejected',
                'a.internal_request_id', 'a.created_at', 'a.ready_at', 'a.deadline_at', 'u.name as client']);

        $rows = $clients->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name ?: $c->email, 'active' => (bool) $c->is_active, 'balance' => (float) $c->balance,
            'by_status' => collect($subs[$c->id] ?? [])->pluck('c', 'status')->all(),
            'subs' => (int) collect($subs[$c->id] ?? [])->sum('c'),
            'items' => (int) collect($subs[$c->id] ?? [])->sum('items'),
            'items_by_status' => collect($items[$c->id] ?? [])->pluck('c', 'st')->all(),
        ])->sortByDesc('subs')->values()->all();

        return ['days' => $days, 'clients' => $rows, 'reasons' => $reasons, 'recent' => $recent,
            'holds' => $holds, 'ready_minutes' => $readyMin !== null ? (int) round($readyMin) : null];
    }

    // ─────────────────────────── Мониторинг ───────────────────────────

    /** @return array<string,mixed> */
    public function monitoring(): array
    {
        $programs = [];
        $perProductPerDay = max(1, intdiv(MonitoringSettings::get('yandex_per_day'), max(1, MonitoringSettings::get('search_queries'))));
        foreach (DB::table('monitoring_programs')->whereIn('status', ['active', 'paused', 'draft'])->orderBy('id')->get() as $p) {
            $it = DB::selectOne("SELECT COUNT(*) total, SUM(active) active, SUM(match_status='matched') matched, SUM(match_status='ambiguous') amb,
                SUM(active=1 AND last_priced_at >= NOW() - INTERVAL ? DAY) covered, SUM(active=1 AND price_source='kp') kp_only
                FROM program_items WHERE program_id = ?", [$p->target_freshness_days, $p->id]);
            $st = DB::table('program_items as pi')->join('mp_products as m', 'm.id', '=', 'pi.product_id')
                ->where('pi.program_id', $p->id)->where('pi.active', true)
                ->selectRaw("SUM(m.last_search_at IS NULL) never, SUM(m.search_state IN ('queued','searching')) inwork,
                    SUM(m.search_state='found') found, SUM(m.search_state='not_found') notfound, SUM(m.listings_priced = 0) noprice")->first();
            $programs[] = [
                'p' => $p, 'it' => $it, 'st' => $st,
                'eta_days' => (int) ($st->never ?? 0) > 0 ? round(((int) $st->never) / $perProductPerDay, 1) : 0,
            ];
        }
        $listings = DB::table('mp_listings')->where('bench', false)->selectRaw('status, COUNT(*) c, SUM(price IS NOT NULL) priced')
            ->groupBy('status')->orderByDesc('c')->get();
        $checks = DB::table('mp_price_observations')->where('observed_at', '>=', now()->subDay())
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->orderByDesc('c')->pluck('c', 'status')->all();
        $fails = DB::select("SELECT CASE WHEN note LIKE '%HTTP 503%' OR note LIKE '%HTTP 429%' THEN 'ИИ-прокси перегружен'
                WHEN note LIKE 'llm:%' THEN 'ИИ: прочее' WHEN note LIKE '%robots%' THEN 'robots.txt запрещает'
                WHEN note LIKE '%timed out%' OR note LIKE '%cURL error 28%' THEN 'таймаут' WHEN note LIKE '%cURL error 6%' THEN 'DNS'
                WHEN note LIKE 'HTTP 4%' THEN CONCAT('HTTP ', SUBSTRING(note, 6, 3)) WHEN note LIKE 'HTTP 5%' THEN 'сайт 5xx'
                WHEN note LIKE '%not html%' THEN 'не HTML' ELSE 'прочее' END reason, COUNT(*) c
            FROM mp_price_observations WHERE observed_at >= NOW() - INTERVAL 24 HOUR AND status = 'failed' GROUP BY reason ORDER BY c DESC");
        $asks = DB::table('mp_asks')->selectRaw("kind, COALESCE(reason, '-') reason, status, COUNT(*) c")
            ->groupBy('kind', 'reason', 'status')->get();
        $askMatrix = [];
        foreach ($asks as $a) {
            $k = $a->kind === 'verify' ? 'Проверка цены сайта' : ($a->reason === 'pool' ? 'КП: пул IQOT' : 'КП: «цена по запросу»');
            $askMatrix[$k][$a->status] = ($askMatrix[$k][$a->status] ?? 0) + (int) $a->c;
        }
        $lettersToday = DB::table('mp_asks')->where('queued_at', '>=', now()->startOfDay())
            ->selectRaw("CASE WHEN kind='verify' THEN 'Проверка цены сайта' WHEN reason='pool' THEN 'КП: пул IQOT' ELSE 'КП: «цена по запросу»' END k, COUNT(DISTINCT batch_id) c")
            ->groupBy('k')->pluck('c', 'k')->all();
        $budget = DB::table('mp_budget_ledger')->where('day', now()->toDateString())
            ->selectRaw('resource, SUM(units) u, SUM(cost_usd) usd, SUM(cost_rub) rub')->groupBy('resource')->get()->keyBy('resource');
        $limits = [
            'yandex_query' => MonitoringSettings::get('yandex_per_day'),
            'fetch_http' => MonitoringSettings::get('fetch_per_day'),
            'fetch_headless' => MonitoringSettings::get('headless_per_day'),
            'fetch_index' => MonitoringSettings::get('index_per_day'),
            'llm_call' => null,
            'email' => MonitoringSettings::get('verify_letters_per_day'),
        ];

        return [
            'programs' => $programs, 'listings' => $listings, 'checks' => $checks, 'fails' => $fails,
            'asks' => $askMatrix, 'letters_today' => $lettersToday,
            'kp' => DB::table('mp_kp_offers')->selectRaw('source, COUNT(*) c')->groupBy('source')->pluck('c', 'source')->all(),
            'verifications' => DB::table('mp_verifications')->selectRaw('outcome, COUNT(*) c')->groupBy('outcome')->pluck('c', 'outcome')->all(),
            'trust' => DB::table('mp_sites')->selectRaw('trust_level, COUNT(*) c')->groupBy('trust_level')->pluck('c', 'trust_level')->all(),
            'budget' => $budget, 'limits' => $limits, 'llm_usd_limit' => MonitoringSettings::get('llm_usd_per_day'),
            'per_product_per_day' => $perProductPerDay,
            'index' => DB::selectOne('SELECT COUNT(*) pages, SUM(fetched_at IS NOT NULL) done, COUNT(DISTINCT site_id) sites FROM mp_site_pages'),
            'sites_priced' => (int) DB::table('mp_listings')->where('bench', false)->where('status', 'active')->whereNotNull('price')->distinct()->count('site_id'),
        ];
    }

    // ─────────────────────────── Охват поставщиков ───────────────────────────

    /** Письма по обычным заявкам (без тех. заявок мониторинга) за $days. @return array<string,mixed> */
    public function coverage(int $days = 30): array
    {
        $db = DB::connection(self::R);
        $db->statement('CREATE TEMPORARY TABLE IF NOT EXISTS adm_mon_ri AS SELECT ri.id FROM request_items ri JOIN requests rq ON rq.id = ri.request_id WHERE rq.source = \'monitoring\'');
        $db->statement('DROP TEMPORARY TABLE IF EXISTS adm_eq');
        $db->statement("CREATE TEMPORARY TABLE adm_eq AS
            SELECT q.supplier_id, LOWER(q.to_email) e, DATE(q.sent_at) d, b.items_count k, q.status IN " . self::REPLIED . " rep,
                   EXISTS(SELECT 1 FROM request_item_responses x WHERE x.batch_id = q.batch_id AND x.supplier_id = q.supplier_id AND (x.price_per_unit > 0 OR x.total_price > 0)) kp
            FROM email_queue q JOIN email_batches b ON b.id = q.batch_id
            WHERE q.sent_at >= NOW() - INTERVAL {$days} DAY AND q.status IN " . self::SENT . "
              AND NOT EXISTS (SELECT 1 FROM adm_mon_ri m WHERE m.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(b.request_items, '$[0]')) AS UNSIGNED))");
        $t = $db->selectOne('SELECT COUNT(*) letters, COUNT(DISTINCT supplier_id) sups, SUM(rep) rep, SUM(kp) kp, COUNT(DISTINCT d) days, ROUND(AVG(k), 2) per_letter FROM adm_eq');
        $base = (int) $db->table('suppliers')->where('is_active', 1)->where('notify_email', 1)->count();
        $buckets = $db->select("SELECT CASE WHEN c=1 THEN '1' WHEN c<=5 THEN '2–5' WHEN c<=20 THEN '6–20' WHEN c<=100 THEN '21–100' WHEN c<=300 THEN '101–300' ELSE '300+' END grp,
                COUNT(*) sups, SUM(c) letters, SUM(rep) rep, SUM(kp) kp, MIN(c) mn
            FROM (SELECT supplier_id, COUNT(*) c, SUM(rep) rep, SUM(kp) kp FROM adm_eq GROUP BY supplier_id) a GROUP BY grp ORDER BY mn");
        $top = $db->select('SELECT a.supplier_id, s.name, s.email, s.scope_product_types scope, a.c, a.rep, a.kp, ROUND(a.c / GREATEST(1, a.days), 1) per_day
            FROM (SELECT supplier_id, COUNT(*) c, SUM(rep) rep, SUM(kp) kp, COUNT(DISTINCT d) days FROM adm_eq GROUP BY supplier_id) a
            JOIN suppliers s ON s.id = a.supplier_id ORDER BY a.c DESC LIMIT 25');
        $byK = $db->select('SELECT k, COUNT(*) letters, SUM(rep) rep, SUM(kp) kp FROM adm_eq GROUP BY k ORDER BY k');
        $byDay = $db->select('SELECT d, COUNT(*) letters, COUNT(DISTINCT supplier_id) sups, ROUND(AVG(k), 2) per_letter FROM adm_eq GROUP BY d ORDER BY d DESC LIMIT 14');
        $p = $db->selectOne("SELECT COUNT(*) items,
                AVG((SELECT COUNT(*) FROM request_item_responses x WHERE x.request_item_id = ri.id)) asked,
                SUM((SELECT COUNT(*) FROM request_item_responses x WHERE x.request_item_id = ri.id AND (x.price_per_unit > 0 OR x.total_price > 0)) >= 1) ge1,
                SUM((SELECT COUNT(*) FROM request_item_responses x WHERE x.request_item_id = ri.id AND (x.price_per_unit > 0 OR x.total_price > 0)) >= 3) ge3
            FROM request_items ri JOIN requests rq ON rq.id = ri.request_id
            WHERE rq.source <> 'monitoring' AND ri.created_at >= NOW() - INTERVAL {$days} DAY");

        return ['days' => $days, 't' => $t, 'base' => $base, 'buckets' => $buckets, 'top' => $top, 'by_k' => $byK, 'by_day' => $byDay, 'positions' => $p];
    }

    // ─────────────────────────── Ресурсы системы ───────────────────────────

    /** @return array<string,mixed> */
    public function resources(): array
    {
        $db = DB::connection(self::R);
        $dayStart = now('Europe/Moscow')->startOfDay()->utc();
        // Отдельные запросы по индексам sent_at / status: OR по двум колонкам и сводка по всей
        // email_queue давали полный скан (~5 с каждый).
        $sentBySender = $db->table('email_queue')->where('sent_at', '>=', $dayStart)
            ->whereRaw('status IN ' . self::SENT)->groupBy('sender_id')->selectRaw('sender_id, COUNT(*) c')->pluck('c', 'sender_id');
        $errBySender = $db->table('email_queue')->where('status', 'error')->where('updated_at', '>=', now()->subDay())
            ->groupBy('sender_id')->selectRaw('sender_id, COUNT(*) c')->pluck('c', 'sender_id');
        $senderRows = $db->table('senders')->get(['id', 'email', 'smtp_server', 'is_active', 'sending_disabled', 'is_personal', 'daily_limit', 'blocked_until']);
        $groups = [];
        foreach ($senderRows as $sr) {
            $key = mb_strtolower(substr(strrchr((string) $sr->email, '@') ?: '@?', 1)) . '|' . $sr->smtp_server;
            $g = $groups[$key] ?? ['domain' => explode('|', $key)[0], 'smtp_server' => $sr->smtp_server, 'total' => 0, 'active' => 0, 'usable' => 0, 'disabled' => 0,
                'blocked_now' => 0, 'inactive' => 0, 'personal' => 0, 'daily_limit' => 0, 'sent_today' => 0, 'errors_24h' => 0];
            $g['total']++;
            // Годный ящик: активен И отправка не отключена (sending_disabled — отключение провайдером/вручную;
            // 15.10.2026 так выключены целиком postserve.ru, mailnex.ru, fastmailr.ru и др. при is_active=1).
            $active = (int) $sr->is_active === 1;
            $disabled = $active && (int) $sr->sending_disabled === 1;
            $g['active'] += $active ? 1 : 0;
            $g['disabled'] += $disabled ? 1 : 0;
            $g['usable'] += $active && !$disabled ? 1 : 0;
            $g['inactive'] += $active ? 0 : 1;
            $g['blocked_now'] += $active && !$disabled && $sr->blocked_until && \Illuminate\Support\Carbon::parse($sr->blocked_until)->isFuture() ? 1 : 0;
            $g['personal'] += (int) $sr->is_personal === 1 ? 1 : 0;
            $g['daily_limit'] += (int) $sr->daily_limit;
            $g['sent_today'] += (int) ($sentBySender[$sr->id] ?? 0);
            $g['errors_24h'] += (int) ($errBySender[$sr->id] ?? 0);
            $groups[$key] = $g;
        }
        usort($groups, fn ($a, $b) => [$b['usable'], $b['total']] <=> [$a['usable'], $a['total']]);
        $senders = array_map(fn ($g) => (object) $g, $groups);
        $sTot = $db->selectOne("SELECT COUNT(*) total, SUM(is_active = 1) active,
                SUM(is_active = 1 AND COALESCE(sending_disabled, 0) = 0) usable,
                SUM(is_active = 1 AND sending_disabled = 1) disabled,
                SUM(is_active = 1 AND COALESCE(sending_disabled, 0) = 0 AND blocked_until > NOW()) blocked_now,
                SUM(is_active = 1 AND COALESCE(sending_disabled, 0) = 0 AND (blocked_until IS NULL OR blocked_until <= NOW())) ready,
                COUNT(DISTINCT CASE WHEN is_active = 1 AND COALESCE(sending_disabled, 0) = 0 THEN SUBSTRING_INDEX(email, '@', -1) END) live_domains,
                COUNT(DISTINCT SUBSTRING_INDEX(email, '@', -1)) domains,
                SUM(is_active = 0 AND last_block_at >= NOW() - INTERVAL 7 DAY) deactivated_7d, SUM(is_active = 0) inactive_total
            FROM senders");
        $blockedList = $db->table('senders')->where('is_active', 1)->where(fn ($q) => $q->whereNull('sending_disabled')->orWhere('sending_disabled', 0))->where('blocked_until', '>', now())
            ->orderByDesc('blocked_until')->limit(15)->get(['id', 'email', 'blocked_until', 'block_reason', 'block_count']);
        $capacity = null;
        try {
            $capacity = app(\App\Services\Generate\SenderDailyCapacity::class)->poolRemainingTotal();
        } catch (\Throwable) {
        }
        $mail = (object) [
            'sent_today' => array_sum($sentBySender->all()),
            'pending' => $db->table('email_queue')->where('status', 'pending')->where('scheduled_at', '<', self::HELD)->count(),
            'sending' => $db->table('email_queue')->where('status', 'sending')->count(),
            'errors_24h' => array_sum($errBySender->all()),
            'stale_pending' => $db->table('email_queue')->where('status', 'pending')->where('scheduled_at', '<', now()->subDays(7))->count(),
        ];

        // Релеи: живая проба /health (3 с) + метка «down» предохранителя RelayHttpMailer.
        $relayMailer = app(RelayHttpMailer::class);
        $relays = [];
        foreach ($relayMailer->endpoints() as $ep) {
            $t = microtime(true);
            $health = null;
            $err = null;
            try {
                $res = Http::timeout(4)->connectTimeout(3)->get(rtrim($ep['url'], '/') . '/health');
                $health = $res->successful() ? (string) ($res->json('status') ?? 'ok') : 'HTTP ' . $res->status();
            } catch (\Throwable $e) {
                $err = mb_substr($e->getMessage(), 0, 80);
            }
            $relays[] = ['url' => $ep['url'], 'weight' => $ep['weight'], 'health' => $health, 'error' => $err,
                'ms' => (int) round((microtime(true) - $t) * 1000), 'down_mark' => $relayMailer->isDown($ep['url'])];
        }

        $egress = [];
        try {
            $pool = EgressPool::make();
            $assigned = DB::table('mp_sites')->selectRaw("COALESCE(egress, 'direct') e, COUNT(*) c")->groupBy('e')->pluck('c', 'e');
            foreach ($pool->names() as $name) {
                $c = $pool->get($name);
                $egress[] = ['name' => $name, 'proxy' => $c['proxy'] ?? null, 'fallback' => (bool) ($c['fallback'] ?? false),
                    'down' => $pool->isDown($name), 'sites' => (int) ($assigned[$name] ?? 0)];
            }
        } catch (\Throwable) {
        }

        $ai = ['url' => (string) config('services.openai_classifier.base_url'), 'health' => null, 'ms' => null];
        try {
            $t = microtime(true);
            $base = preg_replace('~/v1/?$~', '', $ai['url']);
            $res = Http::timeout(4)->connectTimeout(3)->get($base . '/health');
            $ai['health'] = $res->successful() ? 'ok' : 'HTTP ' . $res->status();
            $ai['ms'] = (int) round((microtime(true) - $t) * 1000);
        } catch (\Throwable $e) {
            $ai['health'] = 'нет ответа';
        }
        $ai['errors_1h'] = (int) DB::table('mp_price_observations')->where('observed_at', '>=', now()->subHour())
            ->where(fn ($q) => $q->where('note', 'like', '%HTTP 503%')->orWhere('note', 'like', '%HTTP 429%'))->count();
        $ai['calls_1h'] = (int) DB::table('mp_price_observations')->where('observed_at', '>=', now()->subHour())->count();

        $queues = DB::table('jobs')->selectRaw('queue, COUNT(*) c, SUM(reserved_at IS NOT NULL) running, MIN(created_at) oldest')
            ->groupBy('queue')->orderByDesc('c')->get();
        $free = (float) @disk_free_space('/');
        $total = (float) @disk_total_space('/');

        return [
            'senders' => $senders, 's_tot' => $sTot, 'blocked' => $blockedList, 'capacity_left' => $capacity, 'mail' => $mail,
            'relays' => $relays, 'egress' => $egress, 'ai' => $ai, 'queues' => $queues,
            'failed_jobs_24h' => (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count(),
            'disk' => ['free_gb' => round($free / 1e9, 1), 'total_gb' => round($total / 1e9, 1), 'pct' => $total > 0 ? round(100 * $free / $total) : null],
        ];
    }
}
