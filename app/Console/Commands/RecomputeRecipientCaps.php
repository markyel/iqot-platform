<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Адаптивный дневной cap получателя (recipient_mailboxes.daily_cap) по вовлечённости.
 *
 * База (services.email_dispatch.recipient_daily_cap, дефолт 10). Двигаем ПОСТЕПЕННО
 * (шаг/сутки) к цели:
 *   - ответил / прислал оффер, без баунсов → к MAX (15) — тёплый лид, хочет нашу почту;
 *   - есть баунсы ЛИБО много писем без ответа (cold_sends) → к MIN (5) — риск FBL;
 *   - иначе → к БАЗЕ (10).
 * Заблокированные (is_blocked) пропускаем — они и так не получают. Раз в сутки (МСК).
 */
class RecomputeRecipientCaps extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:recompute-recipient-caps {--dry-run : Показать, не меняя} {--limit=0 : Ограничить число адресов (0=все)}';

    protected $description = 'Пересчёт адаптивного дневного cap получателей по вовлечённости (ответы/баунсы)';

    public function handle(): int
    {
        $base = max(1, (int) config('services.email_dispatch.recipient_daily_cap', 10));
        $capMax = max($base, (int) config('services.email_dispatch.recipient_cap_max', 15));
        $capMin = max(1, min($base, (int) config('services.email_dispatch.recipient_cap_min', 5)));
        $step = max(1, (int) config('services.email_dispatch.recipient_cap_step', 2));
        $coldSends = max(1, (int) config('services.email_dispatch.recipient_cap_cold_sends', 12));
        $dry = (bool) $this->option('dry-run');

        // Окно активности — последние 60 дней (не тянем древность).
        $since = now()->subDays(60)->toDateTimeString();

        // Ответившие адреса (позитив).
        $replied = DB::connection(self::CONN)->table('email_queue')
            ->whereIn('status', ['replied', 'reply_processed', 'in_conversation'])
            ->where('updated_at', '>=', $since)
            ->distinct()->pluck(DB::raw('LOWER(to_email)'))->flip();

        // Сколько РЕАЛЬНО отправлено адресу (для «холодных» без ответа).
        $sentCount = DB::connection(self::CONN)->table('email_queue')
            ->where('status', 'sent')->where('sent_at', '>=', $since)
            ->selectRaw('LOWER(to_email) r, COUNT(*) c')
            ->groupBy(DB::raw('LOWER(to_email)'))->pluck('c', 'r');

        $up = $down = $toBase = $unchanged = 0;
        $q = DB::connection(self::CONN)->table('recipient_mailboxes')
            ->select(['id', 'email', 'bounce_count', 'is_blocked', 'daily_cap']);
        if (($lim = (int) $this->option('limit')) > 0) {
            $q->limit($lim);
        }

        foreach ($q->orderBy('id')->cursor() as $r) {
            if ((int) $r->is_blocked === 1) {
                continue; // заблокирован — cap не важен
            }
            $cur = $r->daily_cap !== null ? (int) $r->daily_cap : $base;

            // Цель по вовлечённости.
            if ((int) $r->bounce_count > 0) {
                $target = $capMin;
            } elseif (isset($replied[$r->email])) {
                $target = $capMax;
            } elseif ((int) ($sentCount[$r->email] ?? 0) >= $coldSends) {
                $target = $capMin; // много слали, 0 ответа → холодный, снижаем
            } else {
                $target = $base;
            }

            // Постепенно (шаг/сутки), с зажимом.
            if ($cur < $target) {
                $new = min($target, $cur + $step);
            } elseif ($cur > $target) {
                $new = max($target, $cur - $step);
            } else {
                $new = $cur;
            }
            $new = max($capMin, min($capMax, $new));

            if ($new === $cur && $r->daily_cap !== null) {
                $unchanged++;
                continue;
            }
            if ($new > $cur) {
                $up++;
            } elseif ($new < $cur) {
                $down++;
            } else {
                $toBase++;
            }

            if (!$dry) {
                DB::connection(self::CONN)->table('recipient_mailboxes')
                    ->where('id', $r->id)->update(['daily_cap' => $new, 'updated_at' => now()]);
            }
        }

        $this->info("Cap пересчитан: вверх {$up}, вниз {$down}, зафиксировано-к-базе {$toBase}, без изменений {$unchanged}"
            . " (база {$base}, диапазон {$capMin}..{$capMax}, шаг {$step})" . ($dry ? ' [dry-run]' : ''));
        return self::SUCCESS;
    }
}
