<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Досыл по пулу расширения (волна 2). Письма волны 2 лежат придержанными
 * (status=pending, wave=2, scheduled_at в далёком будущем — см. CampaignPersister::HELD_UNTIL).
 *
 * Через followup_delay_days после создания батча: если по заявке мало откликов
 * (< followup_min_responses ответивших поставщиков) — «отпускаем» волну 2
 * (scheduled_at=NOW → диспетчер заберёт). Иначе отменяем (откликов достаточно).
 *
 * Идемпотентно: после отпуска/отмены строки уже не попадают под фильтр придержанных.
 */
class DispatchFollowupEmails extends Command
{
    private const CONN = 'reports';

    /** Порог, выше которого scheduled_at считается «придержанным» (HELD_UNTIL = 2037-12-31). */
    private const HELD_THRESHOLD = '2037-01-01 00:00:00';

    protected $signature = 'emails:dispatch-followup
        {--force : Запустить при выключенном флаге EMAILS_POOL_FOLLOWUP_ENABLED}';

    protected $description = 'Досыл по пулу расширения (волна 2) при малом отклике по заявке';

    public function handle(): int
    {
        if (!$this->option('force') && !config('services.email_pool.followup_enabled', true)) {
            $this->warn('emails:dispatch-followup выключен (EMAILS_POOL_FOLLOWUP_ENABLED=false).');
            return self::SUCCESS;
        }

        $delayDays = max(0, (int) config('services.email_pool.followup_delay_days', 2));
        $minResponses = max(1, (int) config('services.email_pool.followup_min_responses', 3));
        $cutoff = now()->subDays($delayDays);

        // Батчи с придержанной волной 2, созданные достаточно давно.
        $batchIds = DB::connection(self::CONN)->table('email_queue as q')
            ->join('email_batches as b', 'b.id', '=', 'q.batch_id')
            ->where('q.wave', 2)
            ->where('q.status', 'pending')
            ->where('q.scheduled_at', '>=', self::HELD_THRESHOLD)
            ->where('b.created_at', '<=', $cutoff)
            ->distinct()
            ->pluck('q.batch_id')
            ->all();

        if ($batchIds === []) {
            $this->info('Нет батчей для досыла.');
            return self::SUCCESS;
        }

        $released = 0;
        $cancelled = 0;
        $now = now();

        foreach ($batchIds as $batchId) {
            // Сколько поставщиков по батчу ответили (волна 1).
            $responses = DB::connection(self::CONN)->table('email_queue')
                ->where('batch_id', $batchId)
                ->whereIn('status', ['replied', 'reply_processed', 'in_conversation'])
                ->count();

            $held = DB::connection(self::CONN)->table('email_queue')
                ->where('batch_id', $batchId)
                ->where('wave', 2)
                ->where('status', 'pending')
                ->where('scheduled_at', '>=', self::HELD_THRESHOLD);

            if ($responses < $minResponses) {
                $n = (clone $held)->update(['scheduled_at' => $now, 'updated_at' => $now]);
                $released += $n;
                Log::info('Followup: released wave 2', ['batch_id' => $batchId, 'responses' => $responses, 'released' => $n]);
            } else {
                $n = (clone $held)->update([
                    'status' => 'cancelled',
                    'error_message' => "followup skipped: enough responses ({$responses})",
                    'updated_at' => $now,
                ]);
                $cancelled += $n;
                Log::info('Followup: cancelled wave 2 (enough responses)', ['batch_id' => $batchId, 'responses' => $responses, 'cancelled' => $n]);
            }
        }

        $this->info("Батчей: " . count($batchIds) . " | отпущено писем: {$released} | отменено: {$cancelled} (порог откликов: {$minResponses})");
        return self::SUCCESS;
    }
}
