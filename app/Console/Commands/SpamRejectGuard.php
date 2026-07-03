<?php

namespace App\Console\Commands;

use App\Services\Senders\SenderBanContainment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Гвард отправителей: отключение по ДОЛЕ проблемных бунсов за окно (а не по абсолюту).
 *
 * Две метрики (каждая — самостоятельный триггер отключения):
 *   1) spam-реджект   — наше письмо забраковано как спам (репутация From-адреса);
 *   2) мёртвые адреса — permanent-бунс (user unknown): письма «в никуда». Высокая доля
 *      хард-баунсов — отдельный критерий, по которому провайдер (beget) режет ящик, и
 *      признак протухшего списка. Страховочная метрика (обычно у нас ~0.5%).
 * Доля = проблемные_бунсы(окно) / отправлено(окно), только если отправлено >= min_sent.
 *   spam-доля >= disable_pct  ИЛИ  dead-доля >= dead_pct  → sending_disabled=1 (+ контейнмент).
 *   отключённый, у кого ОБЕ доли ниже порогов возврата → sending_disabled=0 (гистерезис).
 *
 * Атрибуция бунса — по ящику, на чей IMAP пришёл NDR (реальный отправитель; фикс
 * IncomingEmailRouter). Классификация — сохранённый bounce_reason (точно, с приёма);
 * для старых строк без метки — фолбэк по телу. Приём (is_active=1) не трогаем.
 * За флагом EMAILS_SPAM_GUARD_ENABLED.
 */
class SpamRejectGuard extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:spam-reject-guard {--dry-run : Показать решения, не меняя}';

    protected $description = 'Отключение/возврат отправителей по доле спам-реджекта и мёртвых адресов';

    private const SPAM_RE = '/spam|blacklist|black ?list|listed|reputation|policy reasons|abuse|blocked using|\brbl\b|dnsbl|554[ -].*(reject|spam|policy)|спам|репутац|заблокирован/ui';
    private const PERM_RE = '/user unknown|no such (user|mailbox)|does not exist|mailbox unavailable|invalid recipient|recipient address rejected|user not found|550 5\.1\.1|нет такого|не существует|адрес не найден|неизвестн/ui';

    public function handle(): int
    {
        $cfg = config('services.email_spam_guard');
        if (!(bool) ($cfg['enabled'] ?? false)) {
            $this->warn('Гвард выключен (EMAILS_SPAM_GUARD_ENABLED=false).');
            return self::SUCCESS;
        }

        $windowDays = max(1, (int) ($cfg['window_days'] ?? 3));
        $minSent = max(1, (int) ($cfg['min_sent'] ?? 30));
        // Возврат — низкорисковая операция (если ящик всё ещё плохой, следующий прогон
        // снова отключит), поэтому судим по мягкому порогу объёма.
        $reenableMinSent = max(1, (int) ($cfg['reenable_min_sent'] ?? 10));
        $disablePct = (float) ($cfg['disable_rate_pct'] ?? 15);
        $reenablePct = (float) ($cfg['reenable_rate_pct'] ?? 8);
        $deadPct = (float) ($cfg['dead_rate_pct'] ?? 10);
        $dry = (bool) $this->option('dry-run');
        $since = now()->subDays($windowDays);

        // Отправлено за окно по ящику.
        $sent = [];
        foreach (DB::connection(self::CONN)->table('email_queue')
            ->whereNotNull('sent_at')->where('sent_at', '>=', $since)
            ->selectRaw('sender_id, COUNT(*) n')->groupBy('sender_id')->get() as $r) {
            $sent[(int) $r->sender_id] = (int) $r->n;
        }

        // Проблемные бунсы за окно по ящику: спам + мёртвые адреса.
        [$spam, $dead] = $this->bounceStatsBySender($since);

        // Кандидаты — активные на приём ящики.
        $senders = DB::connection(self::CONN)->table('senders')
            ->where('is_active', 1)
            ->get(['id', 'email', 'sending_disabled']);

        $disabled = 0; $reenabled = 0; $skipped = 0;
        foreach ($senders as $s) {
            $sid = (int) $s->id;
            $sw = $sent[$sid] ?? 0;
            $isDisabled = (int) $s->sending_disabled === 1;
            $minForThis = $isDisabled ? $reenableMinSent : $minSent;
            if ($sw < $minForThis) {
                $skipped++;
                continue; // мало данных — не судим
            }
            $sp = $spam[$sid] ?? 0;
            $pm = $dead[$sid] ?? 0;
            $spamRate = 100 * $sp / $sw;
            $deadRate = 100 * $pm / $sw;

            $overSpam = $spamRate >= $disablePct;
            $overDead = $deadRate >= $deadPct;

            if (!$isDisabled && ($overSpam || $overDead)) {
                $why = [];
                if ($overSpam) $why[] = sprintf('spam %.1f%% (%d/%d)', $spamRate, $sp, $sw);
                if ($overDead) $why[] = sprintf('dead %.1f%% (%d/%d)', $deadRate, $pm, $sw);
                $reason = implode(', ', $why);
                $this->line(sprintf('  ОТКЛЮЧИТЬ #%d %s: %s', $sid, $s->email, $reason));
                if (!$dry) {
                    $flipped = DB::connection(self::CONN)->table('senders')->where('id', $sid)->where('sending_disabled', 0)
                        ->update(['sending_disabled' => 1, 'last_block_at' => now(), 'block_reason' => mb_substr($reason, 0, 255), 'updated_at' => now()]);
                    if ($flipped) {
                        SenderBanContainment::contain($sid, $overDead && !$overSpam ? 'dead_rate' : 'spam_rate');
                        Log::warning('SpamRejectGuard: sender отключён', ['sender_id' => $sid, 'spam_rate' => round($spamRate, 1), 'dead_rate' => round($deadRate, 1), 'sent' => $sw]);
                    }
                }
                $disabled++;
            } elseif ($isDisabled && $spamRate < $reenablePct && $deadRate < $deadPct) {
                $this->line(sprintf('  ВЕРНУТЬ  #%d %s: spam %.1f%%, dead %.1f%% (%d)', $sid, $s->email, $spamRate, $deadRate, $sw));
                if (!$dry) {
                    DB::connection(self::CONN)->table('senders')->where('id', $sid)->where('sending_disabled', 1)
                        ->update(['sending_disabled' => 0, 'spam_reject_count' => 0, 'updated_at' => now()]);
                    Log::info('SpamRejectGuard: sender возвращён', ['sender_id' => $sid, 'spam_rate' => round($spamRate, 1), 'dead_rate' => round($deadRate, 1)]);
                }
                $reenabled++;
            }
        }

        $this->info(sprintf('Гвард%s: отключено %d, возвращено %d, пропущено %d. Окно=%dд, min_sent=%d, spam>=%.0f%%, dead>=%.0f%%, возврат<%.0f%%.',
            $dry ? ' [dry-run]' : '', $disabled, $reenabled, $skipped, $windowDays, $minSent, $disablePct, $deadPct, $reenablePct));
        return self::SUCCESS;
    }

    /**
     * Проблемные бунсы за окно по ящику-отправителю (sender_id бунса): спам и мёртвые.
     * bounce_reason (spam/permanent) — точная метка с приёма; NULL (старые строки) —
     * фолбэк по телу (permanent проверяем ПЕРЕД spam — постоянная ошибка перекрывает).
     *
     * @return array{0:array<int,int>,1:array<int,int>} [spamBySender, deadBySender]
     */
    private function bounceStatsBySender(\DateTimeInterface $since): array
    {
        $spam = []; $dead = [];

        // 1) Точные — по сохранённой метке.
        foreach (DB::connection(self::CONN)->table('unidentified_emails')
            ->where('reason', 'bounce')->whereIn('bounce_reason', ['spam', 'permanent'])->where('created_at', '>=', $since)
            ->selectRaw('sender_id, bounce_reason, COUNT(*) n')->groupBy('sender_id', 'bounce_reason')->get() as $r) {
            $sid = (int) $r->sender_id;
            if ($r->bounce_reason === 'spam') $spam[$sid] = ($spam[$sid] ?? 0) + (int) $r->n;
            else $dead[$sid] = ($dead[$sid] ?? 0) + (int) $r->n;
        }

        // 2) Фолбэк — строки без метки (bounce_reason IS NULL): классифицируем по телу.
        $rows = DB::connection(self::CONN)->table('unidentified_emails')
            ->where('reason', 'bounce')->whereNull('bounce_reason')->where('created_at', '>=', $since)
            ->get(['sender_id', 'subject', 'body_text', 'body_html']);
        foreach ($rows as $r) {
            $h = mb_strtolower((string) $r->subject . "\n" . mb_substr((string) $r->body_text, 0, 4000) . "\n" . mb_substr((string) $r->body_html, 0, 4000));
            $sid = (int) $r->sender_id;
            if (preg_match(self::PERM_RE, $h)) {
                $dead[$sid] = ($dead[$sid] ?? 0) + 1;
            } elseif (preg_match(self::SPAM_RE, $h)) {
                $spam[$sid] = ($spam[$sid] ?? 0) + 1;
            }
        }

        return [$spam, $dead];
    }
}
