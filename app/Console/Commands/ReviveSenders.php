<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Пробационный возврат самоотключённых ящиков — дополняет SpamRejectGuard, чей
 * авто-возврат МЁРТВ из-за замкнутого круга: отключённый ящик ничего не шлёт →
 * не набирает «чистое окно» → ветка возврата гварда никогда не срабатывает.
 *
 * Берёт ОТСИДЕВШИЕСЯ ящики (наш спам-rate-бан, НЕ хардблок провайдера; массово не
 * слал >= rest_days, кулдаун blocked_until отбыт, revival_attempts < max_attempts)
 * и по daily_cap штук/день возвращает на ПРОБО-лимит (30) с sending_disabled=0.
 * Дальше механику доигрывают существующие процессы:
 *   - прогрев (emails:warmup-ramp) сам рампит лимит 30→cap за чистые дни;
 *   - гвард (emails:spam-reject-guard, каждые 2ч) снова отключает при спаме,
 *     инкрементя revival_attempts и удлиняя blocked_until (7→14→30д), а на 3-й
 *     провал ставит banned_once (сдаёмся — ручной разбор).
 * За флагом EMAILS_REVIVAL_ENABLED.
 */
class ReviveSenders extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:revive-senders {--dry-run : Показать, кого вернёт, ничего не меняя}';

    protected $description = 'Пробационный возврат отсидевшихся спам-отключённых ящиков (лимит 30)';

    /** Отключение провайдером (не чинится меньшей отправкой) — такие НЕ трогаем. */
    private const HARD_RE = '/sending is disabled|\b550\b|failed to send|500 -/i';

    public function handle(): int
    {
        $cfg = config('services.email_sender_revival');
        if (!(bool) ($cfg['enabled'] ?? false)) {
            $this->warn('Возврат ящиков выключен (EMAILS_REVIVAL_ENABLED=false).');
            return self::SUCCESS;
        }

        $probationLimit = max(1, (int) ($cfg['probation_limit'] ?? 30));
        $dailyCap = max(1, (int) ($cfg['daily_cap'] ?? 5));
        $restDays = max(1, (int) ($cfg['rest_days'] ?? 3));
        $maxAttempts = max(1, (int) ($cfg['max_attempts'] ?? 3));
        $dry = (bool) $this->option('dry-run');

        $now = now();
        $restCutoff = $now->copy()->subDays($restDays)->toDateTimeString();

        // Последняя МАССОВАЯ отправка по ящику (email_queue; ответы idут через
        // outgoing_replies и в «отсидку» НЕ вмешиваются).
        $lastMass = [];
        foreach (DB::connection(self::CONN)->table('email_queue')
            ->whereNotNull('sent_at')->selectRaw('sender_id, MAX(sent_at) mx')
            ->groupBy('sender_id')->get() as $r) {
            $lastMass[(int) $r->sender_id] = (string) $r->mx;
        }

        $cands = DB::connection(self::CONN)->table('senders')
            ->where('is_active', 1)
            ->where('sending_disabled', 1)
            ->where('revival_attempts', '<', $maxAttempts)
            ->where(function ($w) use ($now) {
                $w->whereNull('blocked_until')->orWhere('blocked_until', '<=', $now->toDateTimeString());
            })
            ->get(['id', 'email', 'block_reason', 'revival_attempts', 'daily_limit']);

        $eligible = [];
        foreach ($cands as $c) {
            $reason = (string) ($c->block_reason ?? '');
            if (preg_match(self::HARD_RE, $reason)) {
                continue; // хардблок провайдера — не наш случай
            }
            if ($reason === '' && (int) $c->revival_attempts === 0) {
                continue; // неясная причина и ранее не возвращали — не трогаем
            }
            $lm = $lastMass[(int) $c->id] ?? null;
            $rested = ($lm === null) || ($lm < $restCutoff);
            if (!$rested) {
                continue; // ещё слал массово недавно — не отсиделся
            }
            $c->_lastMass = $lm;
            $eligible[] = $c;
        }

        // Дольше всех отсидевшиеся — первыми (null = никогда не слал → в начало).
        usort($eligible, static function ($a, $b) {
            return strcmp((string) ($a->_lastMass ?? ''), (string) ($b->_lastMass ?? ''));
        });
        $toRevive = array_slice($eligible, 0, $dailyCap);

        $done = 0;
        foreach ($toRevive as $c) {
            $att = (int) $c->revival_attempts;
            $rested = $c->_lastMass === null ? 'никогда не слал' : ('с ' . $c->_lastMass);
            $this->line(sprintf('  ВОЗВРАТ #%d %s → лимит %d (попытка %d, массово не слал %s)',
                (int) $c->id, (string) $c->email, $probationLimit, $att + 1, $rested));
            if (!$dry) {
                DB::connection(self::CONN)->table('senders')->where('id', $c->id)->where('sending_disabled', 1)
                    ->update([
                        'sending_disabled' => 0,
                        'daily_limit' => $probationLimit,
                        'block_reason' => 'probation',
                        'banned_once' => 0,
                        'spam_reject_count' => 0,
                        'blocked_until' => null,
                        'warmup_updated_on' => null, // прогрев переоценит ящик с нуля
                        'updated_at' => now(),
                    ]);
                Log::info('ReviveSenders: sender возвращён на пробацию', ['sender_id' => (int) $c->id, 'attempt' => $att + 1]);
                $done++;
            }
        }

        $this->info(sprintf('Возврат%s: %d из %d годных на пробо-лимит %d (cap %d/день, отсидка >=%dд, лимит попыток %d).',
            $dry ? ' [dry-run]' : '', $dry ? count($toRevive) : $done, count($eligible), $probationLimit, $dailyCap, $restDays, $maxAttempts));

        return self::SUCCESS;
    }
}
