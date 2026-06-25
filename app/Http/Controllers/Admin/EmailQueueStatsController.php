<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reports\EmailBatch;
use App\Models\Reports\EmailQueue;
use App\Models\Reports\Sender;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Минимальная статистика рассылки: размер очереди и успешность отправки.
 */
class EmailQueueStatsController extends Controller
{
    public function index(): View
    {
        return view('admin.emails.stats', [
            'stats' => $this->collect(),
        ]);
    }

    private function collect(): array
    {
        try {
            $byStatus = EmailQueue::select('status', DB::raw('count(*) as c'))
                ->groupBy('status')
                ->pluck('c', 'status');

            $pending = (int) ($byStatus['pending'] ?? 0);
            $sending = (int) ($byStatus['sending'] ?? 0);
            $sent = (int) ($byStatus['sent'] ?? 0);
            $error = (int) ($byStatus['error'] ?? 0);

            $sentToday = EmailQueue::where('status', 'sent')
                ->whereDate('sent_at', now()->toDateString())
                ->count();

            $errorRetryable = EmailQueue::where('status', 'error')
                ->whereColumn('retry_count', '<', 'max_retries')
                ->count();

            $processed = $sent + $error;
            $successRate = $processed > 0 ? round($sent / $processed * 100, 1) : null;

            $batches = EmailBatch::select('status', DB::raw('count(*) as c'))
                ->groupBy('status')
                ->pluck('c', 'status');

            return [
                'ok' => true,
                'pending' => $pending,
                'sending' => $sending,
                'sent' => $sent,
                'error' => $error,
                'in_queue' => $pending + $sending,
                'sent_today' => $sentToday,
                'error_retryable' => $errorRetryable,
                'success_rate' => $successRate,
                'active_senders' => Sender::where('is_active', 1)->count(),
                'blocked_senders' => Sender::whereNotNull('blocked_until')
                    ->where('blocked_until', '>', now())
                    ->count(),
                'batches_completed' => (int) ($batches['completed'] ?? 0),
                'batches_queued' => (int) ($batches['queued'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
