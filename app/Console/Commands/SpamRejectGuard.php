<?php

namespace App\Console\Commands;

use App\Services\Senders\SenderBanContainment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Гвард спам-реджекта отправителей: отключение по ДОЛЕ (а не по абсолюту).
 *
 * Раньше отключение жило в IncomingEmailRouter::penalizeSenderForSpam по абсолютному
 * порогу spam_reject_count>=5 (кумулятивный, без учёта объёма) И с мис-атрибуцией
 * (findSenderOfBounced гадал по токену/«свежайшему письму»). Это гасило здоровые
 * объёмные ящики за 5 фоновых реджектов и вешало счётчик не на тот ящик.
 *
 * Теперь атрибуция корректна (spam-реджект вешается на ящик, на чей IMAP пришёл NDR —
 * реальный отправитель), а решение об отключении принимает ЭТА команда по окну:
 *   доля = spam-реджекты(окно) / отправлено(окно), только если отправлено >= min_sent.
 *   доля >= disable_pct  → sending_disabled=1 (+ контейнмент);
 *   отключённый с долей < reenable_pct → sending_disabled=0 (авто-восстановление,
 *   гистерезис) — возвращаем ошибочно/временно отключённые.
 * Приём (is_active=1) не трогаем. За флагом EMAILS_SPAM_GUARD_ENABLED.
 *
 * Классификация спама: предпочитаем сохранённый bounce_reason (считался на приёме с
 * вложением-DSN — точно); для старых строк без него — фолбэк по телу.
 */
class SpamRejectGuard extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:spam-reject-guard {--dry-run : Показать решения, не меняя}';

    protected $description = 'Отключение/возврат отправителей по ДОЛЕ спам-реджекта за окно';

    private const SPAM_RE = '/spam|blacklist|black ?list|listed|reputation|policy reasons|abuse|blocked using|\brbl\b|dnsbl|554[ -].*(reject|spam|policy)|спам|репутац|заблокирован/ui';

    public function handle(): int
    {
        $cfg = config('services.email_spam_guard');
        if (!(bool) ($cfg['enabled'] ?? false)) {
            $this->warn('Гвард спам-реджекта выключен (EMAILS_SPAM_GUARD_ENABLED=false).');
            return self::SUCCESS;
        }

        $windowDays = max(1, (int) ($cfg['window_days'] ?? 3));
        $minSent = max(1, (int) ($cfg['min_sent'] ?? 30));
        $disablePct = (float) ($cfg['disable_rate_pct'] ?? 15);
        $reenablePct = (float) ($cfg['reenable_rate_pct'] ?? 8);
        $dry = (bool) $this->option('dry-run');
        $since = now()->subDays($windowDays);

        // Отправлено за окно по ящику.
        $sent = [];
        foreach (DB::connection(self::CONN)->table('email_queue')
            ->whereNotNull('sent_at')->where('sent_at', '>=', $since)
            ->selectRaw('sender_id, COUNT(*) n')->groupBy('sender_id')->get() as $r) {
            $sent[(int) $r->sender_id] = (int) $r->n;
        }

        // Спам-реджекты за окно по ящику (bounce_reason='spam' точно, иначе фолбэк по телу).
        $spam = $this->spamBySender($since);

        // Кандидаты — активные на приём ящики, реально слаавшие в окне.
        $senders = DB::connection(self::CONN)->table('senders')
            ->where('is_active', 1)
            ->get(['id', 'email', 'sending_disabled']);

        $disabled = 0; $reenabled = 0; $skipped = 0;
        foreach ($senders as $s) {
            $sid = (int) $s->id;
            $sw = $sent[$sid] ?? 0;
            if ($sw < $minSent) {
                $skipped++;
                continue; // мало данных — не судим (оставляем как есть)
            }
            $sp = $spam[$sid] ?? 0;
            $rate = 100 * $sp / $sw;

            if ((int) $s->sending_disabled === 0 && $rate >= $disablePct) {
                $this->line(sprintf('  ОТКЛЮЧИТЬ #%d %s: %.1f%% (%d/%d)', $sid, $s->email, $rate, $sp, $sw));
                if (!$dry) {
                    $flipped = DB::connection(self::CONN)->table('senders')->where('id', $sid)->where('sending_disabled', 0)
                        ->update(['sending_disabled' => 1, 'last_block_at' => now(), 'block_reason' => sprintf('spam-rate %.1f%% (%d/%d)', $rate, $sp, $sw), 'updated_at' => now()]);
                    if ($flipped) {
                        SenderBanContainment::contain($sid, 'spam_rate');
                        Log::warning('SpamRejectGuard: sender отключён по доле спама', ['sender_id' => $sid, 'rate' => round($rate, 1), 'spam' => $sp, 'sent' => $sw]);
                    }
                }
                $disabled++;
            } elseif ((int) $s->sending_disabled === 1 && $rate < $reenablePct) {
                $this->line(sprintf('  ВЕРНУТЬ  #%d %s: %.1f%% (%d/%d)', $sid, $s->email, $rate, $sp, $sw));
                if (!$dry) {
                    DB::connection(self::CONN)->table('senders')->where('id', $sid)->where('sending_disabled', 1)
                        ->update(['sending_disabled' => 0, 'spam_reject_count' => 0, 'updated_at' => now()]);
                    Log::info('SpamRejectGuard: sender возвращён (доля упала)', ['sender_id' => $sid, 'rate' => round($rate, 1)]);
                }
                $reenabled++;
            }
        }

        $this->info(sprintf('Гвард%s: отключено %d, возвращено %d, пропущено(мало данных) %d. Окно=%dд, min_sent=%d, off>=%.0f%%, on<%.0f%%.',
            $dry ? ' [dry-run]' : '', $disabled, $reenabled, $skipped, $windowDays, $minSent, $disablePct, $reenablePct));
        return self::SUCCESS;
    }

    /**
     * Спам-реджекты за окно, сгруппированные по ящику-отправителю (sender_id бунса).
     * bounce_reason='spam' — точная метка (с приёма); NULL (старые строки) —
     * классифицируем по телу как фолбэк.
     *
     * @return array<int,int> sender_id => кол-во спам-реджектов
     */
    private function spamBySender(\DateTimeInterface $since): array
    {
        $out = [];
        // 1) Точные — по сохранённой метке.
        foreach (DB::connection(self::CONN)->table('unidentified_emails')
            ->where('reason', 'bounce')->where('bounce_reason', 'spam')->where('created_at', '>=', $since)
            ->selectRaw('sender_id, COUNT(*) n')->groupBy('sender_id')->get() as $r) {
            $out[(int) $r->sender_id] = (int) $r->n;
        }
        // 2) Фолбэк — строки без метки (bounce_reason IS NULL): классифицируем по телу.
        $rows = DB::connection(self::CONN)->table('unidentified_emails')
            ->where('reason', 'bounce')->whereNull('bounce_reason')->where('created_at', '>=', $since)
            ->get(['sender_id', 'subject', 'body_text', 'body_html']);
        foreach ($rows as $r) {
            $h = mb_strtolower((string) $r->subject . "\n" . mb_substr((string) $r->body_text, 0, 4000) . "\n" . mb_substr((string) $r->body_html, 0, 4000));
            if (preg_match(self::SPAM_RE, $h)) {
                $sid = (int) $r->sender_id;
                $out[$sid] = ($out[$sid] ?? 0) + 1;
            }
        }
        return $out;
    }
}
