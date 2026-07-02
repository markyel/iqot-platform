<?php

namespace App\Console\Commands;

use App\Services\Senders\SenderBanContainment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Прогрев отправителей (Phase 3): раз в сутки пересчитывает senders.daily_limit.
 *   - успешный вчера (были отправки И не было бана) → daily_limit ×(1+step_pct%), до cap;
 *   - бан вчера (senders.last_block_at во вчерашних сутках) → сброс лимита в start;
 *     если это ПОВТОРНЫЙ бан (banned_once=1) → sending_disabled=1 (блок, приём остаётся);
 *   - простой (0 отправок, без бана) → лимит без изменений.
 * Идемпотентно за день (warmup_updated_on). Только для ящиков в пуле генерации
 * (is_active=1, sending_disabled=0). За флагом EMAILS_WARMUP_ENABLED.
 */
class ProcessSenderWarmup extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:warmup-ramp {--dry-run : Показать, не меняя}';

    protected $description = 'Прогрев отправителей: суточный пересчёт daily_limit (рампа/сброс/блок)';

    public function handle(): int
    {
        $cfg = config('services.email_warmup');
        if (!(bool) ($cfg['enabled'] ?? false)) {
            $this->warn('Прогрев выключен (EMAILS_WARMUP_ENABLED=false).');
            return self::SUCCESS;
        }

        $start = max(1, (int) ($cfg['start'] ?? 30));
        $stepPct = max(0, (int) ($cfg['step_pct'] ?? 20));
        $cap = max($start, (int) ($cfg['cap'] ?? 500));
        $dry = (bool) $this->option('dry-run');

        $tz = 'Europe/Moscow';
        $yStart = now($tz)->subDay()->startOfDay()->utc();
        $yEnd = now($tz)->subDay()->endOfDay()->utc();
        $today = now($tz)->toDateString();

        $senders = DB::connection(self::CONN)->table('senders')
            ->where('is_active', 1)->where('sending_disabled', 0)
            ->get(['id', 'email', 'daily_limit', 'warmup_updated_on', 'banned_once', 'last_block_at']);

        $ramped = 0; $reset = 0; $blocked = 0; $idle = 0;

        foreach ($senders as $s) {
            if ((string) $s->warmup_updated_on === $today) {
                continue; // уже считали сегодня
            }
            $limit = max($start, (int) $s->daily_limit);

            $bannedYesterday = $s->last_block_at !== null
                && $s->last_block_at >= (string) $yStart && $s->last_block_at <= (string) $yEnd;

            $sentYesterday = DB::connection(self::CONN)->table('email_queue')
                ->where('sender_id', $s->id)->whereNotNull('sent_at')
                ->whereBetween('sent_at', [$yStart, $yEnd])->count();

            $update = ['warmup_updated_on' => $today, 'updated_at' => now()];
            $action = 'idle';

            if ($bannedYesterday) {
                $update['daily_limit'] = $start;
                if ((int) $s->banned_once === 1) {
                    $update['sending_disabled'] = 1; // повторный бан → блок генерации
                    $action = 'block';
                    $blocked++;
                } else {
                    $update['banned_once'] = 1;
                    $action = 'reset';
                    $reset++;
                }
            } elseif ($sentYesterday > 0) {
                $newLimit = min($cap, (int) ceil($limit * (1 + $stepPct / 100)));
                if ($newLimit > $limit) {
                    $update['daily_limit'] = $newLimit;
                    $action = "ramp {$limit}→{$newLimit}";
                    $ramped++;
                } else {
                    $action = "cap ({$limit})";
                    $idle++;
                }
            } else {
                $idle++;
            }

            if ($dry) {
                $this->line(sprintf("  sender#%d %s: %s (вчера отправлено %d)", $s->id, $s->email, $action, $sentYesterday));
            } else {
                DB::connection(self::CONN)->table('senders')->where('id', $s->id)->update($update);
                if ($action === 'block') {
                    // Повторный бан → блок генерации: снять его pending-письма и
                    // отложить на переброс другими отправителями (Phase 3b).
                    SenderBanContainment::contain((int) $s->id, 'repeat_ban');
                }
            }
        }

        $this->info("Прогрев: рампа {$ramped}, сброс {$reset}, блок {$blocked}, без изменений {$idle}" . ($dry ? ' [dry-run]' : ''));
        return self::SUCCESS;
    }
}
