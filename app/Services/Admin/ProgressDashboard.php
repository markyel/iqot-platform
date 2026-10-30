<?php

namespace App\Services\Admin;

use App\Services\Monitoring\Web\EgressPool;
use App\Services\Senders\DomainHealth;
use App\Services\Senders\RelayHttpMailer;
use App\Support\Monitoring\MonitoringSettings;
use App\Support\Senders\SenderPool;
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

    // ─────────────────────── Эксперимент «ящик на батч» ───────────────────────

    /**
     * Ветка «ящик на батч» (docs/alias-mailbox-design.md): настройки, резерв, домены, ход каждого батча
     * и сравнение с основной рассылкой за тот же период (письма, отправленные с начала эксперимента).
     *
     * @return array<string,mixed>
     */
    public function aliasExp(): array
    {
        $db = DB::connection(self::R);
        $cfg = config('services.alias_exp');
        $mailboxes = DB::table('alias_mailboxes as m')->join('alias_domains as d', 'd.id', '=', 'm.domain_id')
            ->orderByDesc('m.id')->get(['m.*', 'd.domain']);

        $batchIds = [];
        foreach ($mailboxes as $mb) {
            foreach ((array) json_decode((string) $mb->batch_ids, true) as $b) {
                $batchIds[(int) $b] = true;
            }
        }
        $stats = [];
        if ($batchIds !== []) {
            $in = implode(',', array_keys($batchIds));
            foreach ($db->select("SELECT batch_id, COUNT(*) total, SUM(status IN " . self::SENT . ") sent, SUM(status='pending') pending,
                    SUM(status='error') err, SUM(status='error' AND error_message LIKE '%spam%') spam,
                    MIN(sent_at) first_sent, MAX(sent_at) last_sent, MIN(CASE WHEN status='pending' THEN scheduled_at END) next_at
                FROM email_queue WHERE batch_id IN ($in) GROUP BY batch_id") as $r) {
                $stats[(int) $r->batch_id] = (array) $r;
            }
            foreach ($db->select("SELECT ec.batch_id, COUNT(DISTINCT ec.supplier_id) replied FROM email_conversations ec
                    WHERE ec.batch_id IN ($in) AND EXISTS (SELECT 1 FROM email_messages m WHERE m.conversation_id = ec.id AND m.direction = 'incoming')
                    GROUP BY ec.batch_id") as $r) {
                $stats[(int) $r->batch_id]['replied'] = (int) $r->replied;
            }
            foreach ($db->select("SELECT batch_id, COUNT(DISTINCT supplier_id) kp FROM request_item_responses
                    WHERE batch_id IN ($in) AND (price_per_unit IS NOT NULL OR total_price IS NOT NULL) GROUP BY batch_id") as $r) {
                $stats[(int) $r->batch_id]['kp'] = (int) $r->kp;
            }
        }
        $senderIds = $mailboxes->pluck('sender_id')->filter()->all();
        $senders = $senderIds === [] ? collect() : $db->table('senders as s')->leftJoin('client_organizations as o', 'o.id', '=', 's.client_organization_id')
            ->whereIn('s.id', $senderIds)->get(['s.id', 's.sender_full_name', 's.is_active', 's.sending_disabled', 's.block_reason', 's.blocked_until', 'o.name as org'])->keyBy('id');
        $bounces = $senderIds === [] ? collect() : $db->table('unidentified_emails')->whereIn('sender_id', $senderIds)->where('reason', 'bounce')
            ->selectRaw("sender_id, COUNT(*) n, SUM(bounce_reason = 'spam') spam")->groupBy('sender_id')->get()->keyBy('sender_id');
        $itemIds = [];
        foreach ($mailboxes as $mb) {
            $itemIds = array_merge($itemIds, (array) json_decode((string) $mb->request_item_ids, true));
        }
        $items = $itemIds === [] ? collect() : $db->table('request_items as ri')->join('requests as r', 'r.id', '=', 'ri.request_id')
            ->whereIn('ri.id', $itemIds)->get(['ri.id', 'ri.name', 'ri.article', 'r.id as request_id', 'r.request_number', 'r.source'])->keyBy('id');

        // Ящики мониторинга (пул КП Гибрида через ветку, с 27.10.2026) — отдельно: другая аудитория и отдача,
        // в сравнение веток эксперимента по заявкам их не берём.
        $rows = [];
        $monitoringRows = [];
        $start = null;
        $monStart = null;
        foreach ($mailboxes as $mb) {
            $bs = array_map('intval', (array) json_decode((string) $mb->batch_ids, true));
            $agg = ['total' => 0, 'sent' => 0, 'pending' => 0, 'err' => 0, 'spam' => 0, 'replied' => 0, 'kp' => 0, 'last_sent' => null, 'next_at' => null];
            foreach ($bs as $b) {
                foreach (['total', 'sent', 'pending', 'err', 'spam', 'replied', 'kp'] as $k) {
                    $agg[$k] += (int) ($stats[$b][$k] ?? 0);
                }
                $agg['last_sent'] = max($agg['last_sent'], $stats[$b]['last_sent'] ?? null);
                $agg['next_at'] = $stats[$b]['next_at'] ?? $agg['next_at'];
            }
            $s = $senders[$mb->sender_id] ?? null;
            $bn = $bounces[$mb->sender_id] ?? null;
            $mbItems = array_values(array_filter(array_map(fn ($id) => $items[(int) $id] ?? null, (array) json_decode((string) $mb->request_item_ids, true))));
            $isMonitoring = collect($mbItems)->contains(fn ($it) => $it->source === 'monitoring');
            foreach ($bs as $b) {
                $fs = $stats[$b]['first_sent'] ?? null;
                if ($isMonitoring) {
                    $monStart = $fs && ($monStart === null || $fs < $monStart) ? $fs : $monStart;
                } else {
                    $start = $fs && ($start === null || $fs < $start) ? $fs : $start;
                }
            }
            $row = $agg + [
                'mailbox' => $mb, 'batch_ids' => $bs, 'persona' => $s->sender_full_name ?? null, 'org' => $s->org ?? null,
                'state' => !$s ? '—' : ($mb->status !== 'active' ? 'удалён' : ($s->sending_disabled ? 'отключён: ' . $s->block_reason
                    : ($s->blocked_until && $s->blocked_until > now() ? 'блок до ' . $s->blocked_until : 'в работе'))),
                'bounces' => (int) ($bn->n ?? 0), 'bounce_spam' => (int) ($bn->spam ?? 0),
                'items' => $mbItems,
            ];
            if ($isMonitoring) {
                $monitoringRows[] = $row;
            } else {
                $rows[] = $row;
            }
        }

        // Сравнение веток — только по ОБЩИМ адресатам (поставщики, которым за период писали обе ветки): ветка пишет всему
        // пулу, основная — по капам и релевантности, состав адресатов разный. Заявки: без именных заявок клиентов
        // (персональный ящик от реальной организации, ~23% ответов — ветке такие не достаются), без мониторинга.
        // Мониторинг: ветка против писем основной по тем же запросам КП пулу (mp_asks kind=rfq, reason=pool);
        // проверки цен с сайта и «по запросу» отдают заметно больше цен и ветке не достаются.
        $cmp = null;
        if ($start !== null) {
            $cmp = $this->branchCompare($start, "COALESCE(rq.source, '') <> 'monitoring' AND COALESCE(b.is_customer_request, 0) = 0");
        }
        $cmpMonitoring = null;
        if ($monStart !== null) {
            $poolAskIds = [];
            foreach (DB::table('mp_asks')->where('kind', 'rfq')->where('reason', 'pool')->whereNotNull('email_queue_id')
                ->where('created_at', '>=', \Illuminate\Support\Carbon::parse($monStart)->subDays(3))->pluck('email_queue_id') as $id) {
                $poolAskIds[(int) $id] = true;
            }
            $cmpMonitoring = $this->branchCompare($monStart, "rq.source = 'monitoring'", $poolAskIds);
        }

        return [
            'enabled' => (bool) $cfg['enabled'], 'share' => (float) $cfg['share'], 'per_batch' => (int) $cfg['items_per_batch'],
            'window' => sprintf('будни %d:00–%d:00 МСК', $cfg['window_start_hour'], $cfg['window_end_hour']),
            'window_open' => \App\Services\AliasExp\AliasWindow::isOpen(), 'delay' => (int) $cfg['send_delay_seconds'],
            'budget_left' => \App\Services\AliasExp\AliasBranch::budgetToday(), 'budget' => (int) $cfg['mailboxes_per_day'],
            'reserve' => app(\App\Services\AliasExp\OrgReserve::class)->stats(),
            'domains' => DB::table('alias_domains')->orderBy('domain')->get(),
            'rows' => $rows, 'monitoring_rows' => $monitoringRows, 'cmp' => $cmp, 'cmp_monitoring' => $cmpMonitoring,
        ];
    }

    /**
     * Ветка «ящик на батч» против основной по общим адресатам за период с $since. Ответ — входящее в беседе
     * (батч, поставщик), КП — цена в request_item_responses по той же паре, отказ «спам» — ошибка отправки с 554.
     * Заявка письма — по первой позиции батча (поиск по первичным ключам).
     *
     * @param string $cond доп. условие на письмо (алиасы eq, s, b, rq)
     * @param array<int,true>|null $mainIds письма основной ветки только из этого набора (null — все)
     * @return array{since:string, recipients:int, alias:array<string,int>, main:array<string,int>, alias_all_recipients:int}
     */
    private function branchCompare(string $since, string $cond, ?array $mainIds = null): array
    {
        $db = DB::connection(self::R);
        $letters = fn (string $poolCond, string $extra = '', array $bind = []) => $db->select("SELECT eq.id, LOWER(TRIM(eq.to_email)) rcpt,
                eq.status = 'error' spam,
                EXISTS (SELECT 1 FROM email_conversations ec JOIN email_messages m ON m.conversation_id = ec.id AND m.direction = 'incoming'
                    WHERE ec.batch_id = eq.batch_id AND ec.supplier_id = eq.supplier_id) replied,
                EXISTS (SELECT 1 FROM request_item_responses rir WHERE rir.batch_id = eq.batch_id AND rir.supplier_id = eq.supplier_id
                    AND (rir.price_per_unit IS NOT NULL OR rir.total_price IS NOT NULL)) kp
            FROM email_queue eq JOIN senders s ON s.id = eq.sender_id
            LEFT JOIN email_batches b ON b.id = eq.batch_id
            LEFT JOIN request_items ri ON ri.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(b.request_items, '$[0]')) AS UNSIGNED)
            LEFT JOIN requests rq ON rq.id = ri.request_id
            WHERE ((eq.status IN " . self::SENT . " AND eq.sent_at >= ?) OR (eq.status = 'error' AND eq.error_message LIKE '%spam%' AND eq.updated_at >= ?))
                AND {$poolCond} AND {$cond} {$extra}", array_merge([$since, $since], $bind));

        $alias = $letters("s.pool = 'alias'");
        $main = [];
        $aliasRcpts = array_values(array_unique(array_map(fn ($r) => (string) $r->rcpt, $alias)));
        foreach (array_chunk($aliasRcpts, 300) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            foreach ($letters("(s.pool IS NULL OR s.pool = 'main')", "AND LOWER(TRIM(eq.to_email)) IN ({$in})", $chunk) as $r) {
                if ($mainIds === null || isset($mainIds[(int) $r->id])) {
                    $main[] = $r;
                }
            }
        }
        $common = array_flip(array_intersect($aliasRcpts, array_map(fn ($r) => (string) $r->rcpt, $main)));

        $agg = function (array $rows) use ($common): array {
            $a = ['letters' => 0, 'replied' => 0, 'kp' => 0, 'spam' => 0];
            foreach ($rows as $r) {
                if (!isset($common[$r->rcpt])) {
                    continue;
                }
                if ($r->spam) {
                    $a['spam']++;
                    continue;
                }
                $a['letters']++;
                $a['replied'] += (int) $r->replied;
                $a['kp'] += (int) $r->kp;
            }

            return $a;
        };

        return ['since' => $since, 'recipients' => count($common), 'alias' => $agg($alias), 'main' => $agg($main),
            'alias_all_recipients' => count($aliasRcpts)];
    }

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
        $askLabel = static fn (string $kind, ?string $reason): string => $kind === 'verify' ? 'Проверка цены сайта'
            : (['pool' => 'КП: пул IQOT', 'low_trust' => 'КП: недоверенный сайт'][$reason] ?? 'КП: «цена по запросу»');
        $asks = DB::table('mp_asks')->selectRaw("kind, COALESCE(reason, '-') reason, status, COUNT(*) c")
            ->groupBy('kind', 'reason', 'status')->get();
        $askMatrix = [];
        foreach ($asks as $a) {
            $k = $askLabel($a->kind, $a->reason);
            $askMatrix[$k][$a->status] = ($askMatrix[$k][$a->status] ?? 0) + (int) $a->c;
        }
        // Письма сегодня (МСК) — по письмам, а не батчам: у ветки «ящик на батч» в батче — товар и все его поставщики.
        $todayAsks = DB::table('mp_asks')->where('queued_at', '>=', now('Europe/Moscow')->startOfDay()->utc())->whereNotNull('email_queue_id')
            ->get(['kind', 'reason', 'email_queue_id']);
        $aliasLetters = [];
        foreach ($todayAsks->pluck('email_queue_id')->unique()->chunk(1000) as $chunk) {
            $aliasLetters += DB::connection(self::R)->table('email_queue as eq')->join('senders as s', 's.id', '=', 'eq.sender_id')
                ->whereIn('eq.id', $chunk->all())->where('s.pool', 'alias')->pluck('eq.id')->flip()->all();
        }
        $lettersToday = [];
        $lettersTodayAlias = [];
        foreach ($todayAsks->unique('email_queue_id') as $a) {
            $k = $askLabel($a->kind, $a->reason);
            $lettersToday[$k] = ($lettersToday[$k] ?? 0) + 1;
            if (isset($aliasLetters[$a->email_queue_id])) {
                $lettersTodayAlias[$k] = ($lettersTodayAlias[$k] ?? 0) + 1;
            }
        }
        $budget = DB::table('mp_budget_ledger')->where('day', now()->toDateString())
            ->selectRaw('resource, SUM(units) u, SUM(cost_usd) usd, SUM(cost_rub) rub')->groupBy('resource')->get()->keyBy('resource');
        // Лимит «проверочных писем в сутки» — только проверки цены сайта; в журнале «email» — все письма мониторинга.
        $budget->put('verify_letters', (object) ['u' => $lettersToday['Проверка цены сайта'] ?? 0, 'usd' => 0, 'rub' => 0]);
        $limits = [
            'yandex_query' => MonitoringSettings::get('yandex_per_day'),
            'fetch_http' => MonitoringSettings::get('fetch_per_day'),
            'fetch_headless' => MonitoringSettings::get('headless_per_day'),
            'fetch_index' => MonitoringSettings::get('index_per_day'),
            'llm_call' => null,
            'verify_letters' => MonitoringSettings::get('verify_letters_per_day'),
            'email' => null,
        ];
        $hybrid = (array) config('monitoring.hybrid', []);

        return [
            'programs' => $programs, 'listings' => $listings, 'checks' => $checks, 'fails' => $fails,
            'asks' => $askMatrix, 'letters_today' => $lettersToday, 'letters_today_alias' => $lettersTodayAlias,
            // Волны запросов КП (RfqPlanner): снимок холостого прогона по расписанию (monitoring:hybrid sync, 30 мин).
            'waves' => \Illuminate\Support\Facades\Cache::get(\App\Services\Monitoring\Hybrid\RfqPlanner::SNAPSHOT_KEY),
            'wave_cfg' => ['target' => (int) ($hybrid['target_prices'] ?? 6), 'site' => (bool) ($hybrid['target_counts_site'] ?? true),
                'max' => (int) ($hybrid['wave_max'] ?? 30), 'wait' => (int) ($hybrid['wave_wait_days'] ?? 5)],
            'kp' => DB::table('mp_kp_offers')->selectRaw('source, COUNT(*) c')->groupBy('source')->pluck('c', 'source')->all(),
            'verifications' => DB::table('mp_verifications')->selectRaw('outcome, COUNT(*) c')->groupBy('outcome')->pluck('c', 'outcome')->all(),
            'trust' => DB::table('mp_sites')->selectRaw('trust_level, COUNT(*) c')->groupBy('trust_level')->pluck('c', 'trust_level')->all(),
            'budget' => $budget, 'limits' => $limits, 'llm_usd_limit' => MonitoringSettings::get('llm_usd_per_day'),
            'per_product_per_day' => $perProductPerDay,
            // Подтверждение цен сайтов КП (Гибрид): по программам — письма-проверки, исходы сверок, подтверждённые цены, КП.
            'confirm' => \App\Models\Monitoring\MonitoringProgram::query()->orderBy('id')->get()->map(function ($mp) {
                $prods = DB::table('program_items')->where('program_id', $mp->id)->whereNotNull('product_id')->distinct()->pluck('product_id')->all();
                $asks = DB::table('mp_asks')->where('program_id', $mp->id)->where('kind', 'verify')
                    ->selectRaw("SUM(status IN ('sent','answered','expired')) sent, SUM(status = 'answered') answered, SUM(status IN ('planned','queued')) waiting")->first();
                $out = $prods === [] ? collect() : DB::table('mp_verifications')->whereIn('product_id', $prods)
                    ->selectRaw('source, outcome, COUNT(*) n')->groupBy('source', 'outcome')->get();

                return [
                    'p' => $mp, 'asks' => $asks,
                    'outcomes' => $out->groupBy('outcome')->map(fn ($g) => (int) $g->sum('n'))->all(),
                    'by_source' => $out->groupBy('source')->map(fn ($g) => (int) $g->sum('n'))->all(),
                    'confirmed' => $prods === [] ? 0 : DB::table('mp_listings')->whereIn('product_id', $prods)->where('bench', false)->whereNotNull('confirmed_at')->count(),
                    'priced' => $prods === [] ? 0 : DB::table('mp_listings')->whereIn('product_id', $prods)->where('bench', false)->where('status', 'active')->whereNotNull('price')->count(),
                    'kp' => DB::table('mp_kp_offers as k')->join('mp_asks as a', 'a.id', '=', 'k.ask_id')->where('a.program_id', $mp->id)
                        ->selectRaw('COUNT(*) offers, COUNT(DISTINCT k.product_id) products')->first(),
                ];
            })->all(),
            // Тарификация (§11a): последние сутки начисления, не списано, последнее списание, предупреждение.
            'billing' => \App\Models\Monitoring\MonitoringProgram::query()->orderBy('id')->get()->map(fn ($mp) => [
                'p' => $mp,
                'day' => DB::table('mp_billing_days')->where('program_id', $mp->id)->orderByDesc('day')->first(),
                'sum' => app(\App\Services\Monitoring\Billing\MonitoringBilling::class)->programSummary($mp),
            ])->all(),
            'index' => DB::selectOne('SELECT COUNT(*) pages, SUM(fetched_at IS NOT NULL) done, COUNT(DISTINCT site_id) sites FROM mp_site_pages'),
            'sites_priced' => (int) DB::table('mp_listings')->where('bench', false)->where('status', 'active')->whereNotNull('price')->distinct()->count('site_id'),
        ];
    }

    // ─────────────────────────── Охват поставщиков ───────────────────────────

    /** Письма по обычным заявкам (без тех. заявок мониторинга) за $days. @return array<string,mixed> */
    public function coverage(int $days = 30): array
    {
        $db = DB::connection(self::R);
        $db->statement('DROP TEMPORARY TABLE IF EXISTS adm_eq');
        // Без писем мониторинга (тех. заявка, source=monitoring): заявка письма — по первой позиции батча, поиск по
        // первичным ключам. Раньше — временная таблица всех позиций тех. заявок без индекса и перебор её на каждое
        // письмо; с волнами (27.10.2026) мониторинг заводит ~4 000 позиций в день — вкладка упёрлась бы в таймаут.
        $db->statement("CREATE TEMPORARY TABLE adm_eq AS
            SELECT q.supplier_id, LOWER(q.to_email) e, DATE(q.sent_at) d, b.items_count k, q.status IN " . self::REPLIED . " rep,
                   EXISTS(SELECT 1 FROM request_item_responses x WHERE x.batch_id = q.batch_id AND x.supplier_id = q.supplier_id AND (x.price_per_unit > 0 OR x.total_price > 0)) kp
            FROM email_queue q JOIN email_batches b ON b.id = q.batch_id
            LEFT JOIN request_items ri ON ri.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(b.request_items, '$[0]')) AS UNSIGNED)
            LEFT JOIN requests rq ON rq.id = ri.request_id
            WHERE q.sent_at >= NOW() - INTERVAL {$days} DAY AND q.status IN " . self::SENT . " AND COALESCE(rq.source, '') <> 'monitoring'");
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
        // Пул ящиков и здоровье доменов (DomainHealth — как у пополнения и возврата, решение владельца 27.10.2026).
        $rcfg = (array) config('services.mailbox_replenish', []);
        $healthDays = max(1, (int) ($rcfg['health_days'] ?? 7));
        $maxSpam = (float) ($rcfg['max_spam_pct'] ?? 3);
        $minSent = max(1, (int) ($rcfg['min_sent'] ?? 50));
        $health = \Illuminate\Support\Facades\Cache::remember('progress:domain-health', 600, fn () => DomainHealth::byDomain($healthDays));
        $live = array_filter($health, fn ($h) => DomainHealth::isLive($h, $maxSpam, $minSent));
        $mailboxPool = [
            'enabled' => (bool) ($rcfg['enabled'] ?? false), 'target' => (int) ($rcfg['target'] ?? 90),
            'working' => array_sum(array_column($health, 'working')), 'revivable' => array_sum(array_column($live, 'disabled')),
            'live_domains' => count($live), 'per_day' => (int) ($rcfg['per_day'] ?? 2), 'per_domain_max' => (int) ($rcfg['per_domain_max'] ?? 8),
            'max_spam' => $maxSpam, 'min_sent' => $minSent, 'days' => $healthDays,
            'created_7d' => (int) SenderPool::onlyMain($db->table('senders'))->where(fn ($q) => $q->whereNull('is_personal')->orWhere('is_personal', 0))
                ->where('created_at', '>=', now()->subDays(7))->count(),
        ];
        $mailboxPool['deficit'] = max(0, $mailboxPool['target'] - $mailboxPool['working'] - $mailboxPool['revivable']);
        $groups = [];
        foreach ($senderRows as $sr) {
            $key = mb_strtolower(substr(strrchr((string) $sr->email, '@') ?: '@?', 1)) . '|' . $sr->smtp_server;
            $dom = explode('|', $key)[0];
            $h = $health[$dom] ?? null;
            $g = $groups[$key] ?? ['domain' => $dom, 'smtp_server' => $sr->smtp_server, 'total' => 0, 'active' => 0, 'usable' => 0, 'disabled' => 0,
                'blocked_now' => 0, 'inactive' => 0, 'personal' => 0, 'daily_limit' => 0, 'sent_today' => 0, 'errors_24h' => 0,
                'sent_window' => $h['sent'] ?? 0, 'spam_pct' => $h['spam_pct'] ?? null,
                'health' => $h === null ? null : (DomainHealth::isLive($h, $maxSpam, $minSent) ? 'live' : ($h['sent'] >= $minSent ? 'burning' : 'unknown'))];
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
            'senders' => $senders, 's_tot' => $sTot, 'blocked' => $blockedList, 'capacity_left' => $capacity, 'mail' => $mail, 'pool' => $mailboxPool,
            'relays' => $relays, 'egress' => $egress, 'ai' => $ai, 'queues' => $queues,
            'failed_jobs_24h' => (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count(),
            'disk' => ['free_gb' => round($free / 1e9, 1), 'total_gb' => round($total / 1e9, 1), 'pct' => $total > 0 ? round(100 * $free / $total) : null],
        ];
    }
}
