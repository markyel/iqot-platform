<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Наблюдение за ходом выполнения заявок: активные заявки, волны рассылки (waves-v2:
 * В1 горячие / В2 тёплые / В3 холодные) и статистика ответов/отказов поставщиков.
 * Всё на коннекте reports (боевой почтовый пайплайн).
 */
class EmailCampaignStatsController extends Controller
{
    private const CONN = 'reports';
    private const HELD = '2037-01-01 00:00:00';

    /** Заявки «в работе» (не терминальные). */
    private const ACTIVE_STATUSES = ['queued_for_sending', 'emails_sent', 'responses_received', 'new', 'active', 'draft'];

    public function index(): View
    {
        try {
            // Кэш 60с: сводки/волны — тяжёлые агрегаты по большой email_queue, страница
            // наблюдательная (не realtime). Список активных заявок обновляется тем же тактом.
            $data = Cache::remember('admin:email_campaigns', 60, fn () => [
                'ok' => true,
                'cards' => $this->cards(),
                'waves' => $this->waves(),
                'replyTypes' => $this->replyTypes(),
                'requests' => $this->activeRequests(),
            ]);
        } catch (\Throwable $e) {
            $data = ['ok' => false, 'error' => $e->getMessage()];
        }

        return view('admin.emails.campaigns', ['data' => $data]);
    }

    /** Верхние карточки-сводка. */
    private function cards(): array
    {
        $db = DB::connection(self::CONN);
        $tz = 'Europe/Moscow';
        $todayFrom = now($tz)->startOfDay()->utc();
        $todayTo = now($tz)->endOfDay()->utc();

        $activeRequests = $db->table('requests')->whereIn('status', ['emails_sent', 'responses_received'])->count();

        $offersTotal = $db->table('request_item_responses')
            ->where('status', 'received')
            ->distinct()->count('supplier_id');
        $offersToday = $db->table('request_item_responses')
            ->where('status', 'received')
            ->whereBetween('updated_at', [$todayFrom, $todayTo])
            ->distinct()->count('supplier_id');

        // Живые беседы (пришёл хоть один ответ поставщика).
        $conversations = $db->table('email_conversations')
            ->whereIn('status', ['complete', 'partial', 'needs_clarification', 'waiting'])
            ->count();

        // Отказы за 90 дн (email_type=rejection в ai_classification — LIKE, без JSON-парса).
        $rejections = $db->table('email_messages')
            ->where('direction', 'incoming')
            ->where('received_at', '>=', now()->subDays(90))
            ->where('ai_classification', 'like', '%"email_type": "rejection"%')
            ->count();

        $held = $db->table('email_queue')->where('status', 'pending')->where('scheduled_at', '>=', self::HELD)->count();
        $pendingLive = $db->table('email_queue')->where('status', 'pending')->where('scheduled_at', '<', self::HELD)->count();

        return [
            'active_requests' => $activeRequests,
            'offers_total' => $offersTotal,
            'offers_today' => $offersToday,
            'conversations' => $conversations,
            'rejections' => $rejections,
            'held' => $held,
            'pending_live' => $pendingLive,
        ];
    }

    /** Разбивка по волнам (waves-v2): всего / отправлено / ждут / held / ответили. */
    private function waves(): array
    {
        $rows = DB::connection(self::CONN)->table('email_queue')
            ->selectRaw(
                "wave,
                 count(*) total,
                 sum(status='sent') sent,
                 sum(status in ('replied','reply_processed','in_conversation')) replied,
                 sum(status='pending' and scheduled_at < ?) pending,
                 sum(status='pending' and scheduled_at >= ?) held,
                 sum(status in ('error','failed')) failed,
                 sum(status='cancelled') cancelled",
                [self::HELD, self::HELD]
            )
            ->groupBy('wave')
            ->orderBy('wave')
            ->get();

        $labels = [1 => 'В1 — горячие (Яндекс-матч)', 2 => 'В2 — тёплые (добор пула)', 3 => 'В3 — холодные (резерв)'];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'wave' => (int) $r->wave,
                'label' => $labels[(int) $r->wave] ?? ('Волна ' . $r->wave),
                'total' => (int) $r->total,
                'sent' => (int) $r->sent,
                'replied' => (int) $r->replied,
                'pending' => (int) $r->pending,
                'held' => (int) $r->held,
                'failed' => (int) $r->failed,
                'cancelled' => (int) $r->cancelled,
            ];
        }

        return $out;
    }

    /** Типы ответов поставщиков за 90 дней (КП/вопрос/отказ/автоответ/пустое). */
    private function replyTypes(): array
    {
        $r = DB::connection(self::CONN)->table('email_messages')
            ->where('direction', 'incoming')
            ->where('ai_processed', 1)
            ->where('received_at', '>=', now()->subDays(90))
            ->selectRaw(
                "sum(ai_classification like '%\"has_offers\": true%') offers,
                 sum(ai_classification like '%\"email_type\": \"question\"%') questions,
                 sum(ai_classification like '%\"email_type\": \"rejection\"%') rejections,
                 sum(ai_classification like '%\"email_type\": \"auto_reply\"%') auto_reply,
                 sum(ai_classification like '%\"email_type\": \"empty_reply\"%') empty_reply,
                 count(*) total"
            )
            ->first();

        return [
            'offers' => (int) ($r->offers ?? 0),
            'questions' => (int) ($r->questions ?? 0),
            'rejections' => (int) ($r->rejections ?? 0),
            'auto_reply' => (int) ($r->auto_reply ?? 0),
            'empty_reply' => (int) ($r->empty_reply ?? 0),
            'total' => (int) ($r->total ?? 0),
        ];
    }

    /**
     * Активные заявки с их прогрессом: позиций, охвачено поставщиков, КП, отказов.
     * Прогресс считаем по request_item_responses (леджер кампании: позиция×поставщик).
     */
    private function activeRequests(): array
    {
        $db = DB::connection(self::CONN);

        $requests = $db->table('requests')
            ->whereIn('status', ['emails_sent', 'responses_received'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'request_number', 'title', 'status', 'total_items', 'items_with_offers', 'updated_at']);

        if ($requests->isEmpty()) {
            return [];
        }

        $reqIds = $requests->pluck('id')->all();

        // Леджер по заявкам: охвачено поставщиков + КП (received). Связь через request_items.
        $agg = $db->table('request_items as ri')
            ->join('request_item_responses as rir', 'rir.request_item_id', '=', 'ri.id')
            ->whereIn('ri.request_id', $reqIds)
            ->selectRaw(
                'ri.request_id rid,
                 count(distinct rir.supplier_id) suppliers,
                 count(distinct case when rir.status = ? then rir.supplier_id end) offers',
                ['received']
            )
            ->groupBy('ri.request_id')
            ->get()
            ->keyBy('rid');

        // Разосланных писем по волнам на заявку: distinct email_queue (одно письмо покрывает
        // несколько позиций) через request_item_responses.email_queue_id → email_queue.wave.
        // Отменённые (cancelled) не считаем — это не разосланные.
        $waveRows = $db->table('request_items as ri')
            ->join('request_item_responses as rir', 'rir.request_item_id', '=', 'ri.id')
            ->join('email_queue as eq', 'eq.id', '=', 'rir.email_queue_id')
            ->whereIn('ri.request_id', $reqIds)
            ->where('eq.status', '<>', 'cancelled')
            ->selectRaw('ri.request_id rid, eq.wave, count(distinct eq.id) cnt')
            ->groupBy('ri.request_id', 'eq.wave')
            ->get();
        $waveByReq = [];
        foreach ($waveRows as $wr) {
            $waveByReq[(int) $wr->rid][(int) $wr->wave] = (int) $wr->cnt;
        }

        $out = [];
        foreach ($requests as $r) {
            $a = $agg->get($r->id);
            $wc = $waveByReq[(int) $r->id] ?? [];
            $out[] = [
                'id' => (int) $r->id,
                'number' => $r->request_number ?: ('#' . $r->id),
                'title' => $r->title,
                'status' => $r->status,
                'items' => (int) $r->total_items,
                'items_with_offers' => (int) $r->items_with_offers,
                'suppliers' => (int) ($a->suppliers ?? 0),
                'offers' => (int) ($a->offers ?? 0),
                'wave1' => (int) ($wc[1] ?? 0),
                'wave2' => (int) ($wc[2] ?? 0),
                'wave3' => (int) ($wc[3] ?? 0),
                'updated_at' => $r->updated_at,
            ];
        }

        return $out;
    }
}
