<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reports\RecipientMailbox;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Статистика приёма входящих писем (Laravel-пайплайн App\Services\Senders\
 * IncomingEmailRouter): поток входящих, беседы по статусам, неопознанные письма,
 * вложения. Зеркало EmailQueueStatsController, но для входящего направления.
 *
 * Часть данных историческая (эпоха n8n) — поэтому отдельно считаем срез «сегодня»
 * (по московскому дню), он показывает активность именно нового пайплайна.
 */
class ReceiveEmailStatsController extends Controller
{
    public function index(): View
    {
        return view('admin.emails.receive-stats', [
            'stats' => $this->collect(),
        ]);
    }

    private function collect(): array
    {
        try {
            // «Сегодня» — по московскому календарному дню; received_at в БД хранится
            // в UTC, поэтому границы МСК-суток переводим в UTC (как в рассылке).
            $tz = 'Europe/Moscow';
            $dayStart = now($tz)->startOfDay()->utc();
            $dayEnd = now($tz)->endOfDay()->utc();

            $incomingToday = DB::connection('reports')->table('email_messages')
                ->where('direction', 'incoming')
                ->whereBetween('received_at', [$dayStart, $dayEnd])
                ->count();

            $incomingTotal = DB::connection('reports')->table('email_messages')
                ->where('direction', 'incoming')
                ->count();

            // «Неопознанные» считаем без отбойников (reason='bounce') — это письма
            // о недоставке, для них отдельный счётчик ниже.
            $unidentToday = DB::connection('reports')->table('unidentified_emails')
                ->where('reason', '!=', 'bounce')
                ->whereBetween('received_at', [$dayStart, $dayEnd])
                ->count();

            $unidentTotal = DB::connection('reports')->table('unidentified_emails')
                ->where('reason', '!=', 'bounce')
                ->count();

            $bounceToday = DB::connection('reports')->table('unidentified_emails')
                ->where('reason', 'bounce')
                ->whereBetween('received_at', [$dayStart, $dayEnd])
                ->count();

            $bounceTotal = DB::connection('reports')->table('unidentified_emails')
                ->where('reason', 'bounce')
                ->count();

            $convByStatus = DB::connection('reports')->table('email_conversations')
                ->select('status', DB::raw('count(*) as c'))
                ->groupBy('status')
                ->pluck('c', 'status');

            $convHasOffers = DB::connection('reports')->table('email_conversations')
                ->where('has_offers', 1)
                ->count();

            $unidentManualReview = DB::connection('reports')->table('unidentified_emails')
                ->where('status', 'manual_review')
                ->count();

            $unidentNoToken = DB::connection('reports')->table('unidentified_emails')
                ->where('reason', 'no_token')
                ->count();

            $attachments = DB::connection('reports')->table('email_attachments')->count()
                + DB::connection('reports')->table('unidentified_email_attachments')->count();

            $blockedMailboxes = RecipientMailbox::where('is_blocked', 1)->count();

            // «Последнее входящее» считаем по created_at (момент записи в БД, всегда
            // корректный UTC), а не по received_at — заголовок Date письма может нести
            // чужой пояс/кривое значение и искажать «последнюю активность».
            $lastReceived = DB::connection('reports')->table('email_messages')
                ->where('direction', 'incoming')
                ->max('created_at');

            return [
                'ok' => true,
                'enabled' => (bool) config('services.email_receive.enabled', false),
                'last_received_at' => $lastReceived
                    ? Carbon::parse($lastReceived, 'UTC')->setTimezone($tz)->format('d.m.Y H:i')
                    : null,
                'incoming_today' => $incomingToday,
                'incoming_total' => $incomingTotal,
                'unident_today' => $unidentToday,
                'unident_total' => $unidentTotal,
                'bounce_today' => $bounceToday,
                'bounce_total' => $bounceTotal,
                'conv_waiting' => (int) ($convByStatus['waiting'] ?? 0),
                'conv_complete' => (int) ($convByStatus['complete'] ?? 0),
                'conv_needs_clarification' => (int) ($convByStatus['needs_clarification'] ?? 0),
                'conv_has_offers' => $convHasOffers,
                'unident_manual_review' => $unidentManualReview,
                'unident_no_token' => $unidentNoToken,
                'attachments_total' => $attachments,
                'blocked_mailboxes' => $blockedMailboxes,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
