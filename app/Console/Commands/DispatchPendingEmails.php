<?php

namespace App\Console\Commands;

use App\Jobs\SendQueuedEmailJob;
use App\Models\Reports\EmailQueue;
use App\Models\Reports\RecipientMailbox;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер рассылки — замена крон-триггера n8n «Send Emails v2».
 *
 * Многопоточность: раздаёт письма ЧЕСТНО по всем готовым ящикам (round-robin),
 * чтобы они слались параллельно (своя очередь `emails`, пул воркеров). На один
 * ящик за тик берётся не больше, чем он успеет отправить до следующего тика
 * (потолок = окно_тика / send_delay_seconds), с лёгким пред-разносом delay —
 * чтобы жёсткий «замок интервала» в SendQueuedEmailJob почти не срабатывал.
 *
 * Адаптивный пейсинг по ПОЛУЧАТЕЛЮ (to_email): чтобы не задолбить поставщика
 * пачкой, на каждом тике одному получателю уходит НЕ БОЛЬШЕ одного письма, и то
 * лишь если с прошлой раздачи прошёл адаптивный интервал
 * interval = clamp(остаток_рабочего_окна / pending_получателю, MIN, MAX).
 * Низкая нагрузка → MAX (≈раз в час), выше → плавно чаще (но не ниже MIN).
 * Интервал переоценивается каждый тик (диспетчер тикает раз в минуту) → объём
 * сам размазывается по дню без знания итогового числа писем. Якорь —
 * recipient_mailboxes.last_dispatched_at, ставится при клейме (см. markDispatched).
 *
 * «Claim» через status='sending': взятое в работу письмо не подхватывается
 * повторно; зависшие (упавший воркер) реклеймятся обратно в pending через 30 мин.
 */
class DispatchPendingEmails extends Command
{
    protected $signature = 'emails:dispatch-pending
        {--limit=3000 : Общий потолок писем за тик (предохранитель)}
        {--tick=60 : Окно тика в секундах — под него считается потолок на ящик}
        {--force : Запустить даже при выключенном флаге EMAILS_DISPATCH_ENABLED}';

    protected $description = 'Поставить pending/error письма из reports.email_queue в очередь на отправку (многопоточно, с паузой на ящик)';

    public function handle(): int
    {
        // Предохранитель для фазового перехода с n8n: по расписанию команда
        // молчит, пока не включён флаг. Ручной прогон — через --force.
        if (!$this->option('force') && !config('services.email_dispatch.enabled')) {
            $this->warn('emails:dispatch-pending выключен (EMAILS_DISPATCH_ENABLED=false). Используйте --force для ручного запуска.');
            return self::SUCCESS;
        }

        // 1) Реклейм застрявших в 'sending' (упавший воркер) — старше 30 минут.
        $reclaimed = EmailQueue::where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->update(['status' => 'pending']);
        if ($reclaimed) {
            $this->info("Reclaimed stale 'sending': {$reclaimed}");
        }

        $limit = max(1, (int) $this->option('limit'));
        $tick = max(5, (int) $this->option('tick'));

        // 2) Адаптивный пейсинг по получателю: какие to_email «созрели» на этот тик.
        $eligibleRecipients = $this->eligibleRecipients();

        // 3) Готовые ящики: активные, не заблокированные, у которых есть письма к отправке.
        $senders = DB::connection('reports')->table('senders as s')
            ->join('email_queue as eq', 'eq.sender_id', '=', 's.id')
            ->where('s.is_active', 1)
            ->where(function ($q) {
                $q->whereNull('s.blocked_until')->orWhereRaw('s.blocked_until <= NOW()');
            })
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->where('eq.status', 'pending')->whereRaw('eq.scheduled_at <= NOW()');
                })->orWhere(function ($q) {
                    $q->where('eq.status', 'error')
                        ->whereColumn('eq.retry_count', '<', 'eq.max_retries')
                        ->whereRaw('eq.scheduled_at <= NOW()');
                });
            })
            // Заблокированные получатели (N ошибок подряд) — сразу пропускаем, не клеймим.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('recipient_mailboxes as rm')
                    ->whereColumn('rm.email', DB::raw('LOWER(eq.to_email)'))
                    ->where('rm.is_blocked', 1);
            })
            ->groupBy('s.id', 's.send_delay_seconds', 's.daily_limit', 's.emails_sent_today', 's.last_send_date')
            ->get(['s.id', 's.send_delay_seconds', 's.daily_limit', 's.emails_sent_today', 's.last_send_date']);

        if ($senders->isEmpty()) {
            $this->info('No pending emails.');
            return self::SUCCESS;
        }

        // 4) Round-robin по ящикам: на каждый берём perSenderCap писем, ставим
        //    с накопительной задержкой по этому ящику (0, delay, 2·delay, …).
        //    Дополнительно — пейсинг по получателю: пропускаем «не созревшие»
        //    to_email и не более одного письма получателю за тик ($reserved).
        $dispatched = 0;
        $totalCapHit = 0;
        $reserved = []; // нормализованный to_email => уже отдали письмо в этом тике

        // Плоский список созревших получателей — загоняем его в SQL-выборку по
        // ящику (см. ниже), чтобы LIMIT считал уже готовых кандидатов, а не
        // «первые N писем подряд». Иначе фронт очереди, забитый остывающими
        // получателями с длинным хвостом писем, голодит созревших глубже.
        $eligibleKeys = array_keys($eligibleRecipients);
        if ($eligibleKeys === []) {
            $this->info('Dispatched: 0 (нет созревших получателей на этот тик)');
            return self::SUCCESS;
        }

        // Дневной лимит ящика (прогрев): «1 батч = 1 sender» → весь пул батча может
        // висеть на одном ящике; чтобы он не слал больше daily_limit/сутки, лимит
        // держим ЗДЕСЬ (не в генерации). Учитываем письма «в полёте» (status=sending —
        // заклеймлены, но счётчик emails_sent_today ещё не увеличен воркером).
        $today = now()->toDateString();
        $inFlight = DB::connection('reports')->table('email_queue')
            ->where('status', 'sending')
            ->selectRaw('sender_id, COUNT(*) as n')
            ->groupBy('sender_id')
            ->pluck('n', 'sender_id');
        $dailyLimitHit = 0;

        foreach ($senders as $sender) {
            if ($dispatched >= $limit) {
                break;
            }

            $delaySec = max(1, (int) ($sender->send_delay_seconds ?: 2));
            // Сколько ящик успеет отправить до следующего тика (+1 в запас от простоя).
            $perSenderCap = (int) ceil($tick / $delaySec) + 1;

            // Дневной лимит: остаток = daily_limit − отправлено_сегодня − в_полёте.
            // emails_sent_today валиден только если last_send_date = сегодня (первая
            // отправка нового дня его обнуляет в bumpSenderCounter). Ящик, выбравший
            // лимит, ПРОПУСКАЕМ (continue) — очередь НЕ встаёт; его остаток ждёт pending
            // и уйдёт на след. день (счётчик обнулится).
            $dailyLimit = (int) $sender->daily_limit;
            if ($dailyLimit > 0) {
                $sentToday = (substr((string) $sender->last_send_date, 0, 10) === $today)
                    ? (int) $sender->emails_sent_today : 0;
                $remainingDaily = $dailyLimit - $sentToday - (int) ($inFlight[$sender->id] ?? 0);
                if ($remainingDaily <= 0) {
                    $dailyLimitHit++;
                    continue;
                }
                $perSenderCap = min($perSenderCap, $remainingDaily);
            }
            // Берём по ОДНОМУ письму на созревшего получателя (GROUP BY), с буфером
            // +50 на случай, что часть получателей уже заняты другим ящиком в этом
            // тике ($reserved). Фильтр зрелости — whereIn по eligibleKeys ДО лимита.
            $fetch = $perSenderCap + 50;

            $rows = DB::connection('reports')->table('email_queue')
                ->where('sender_id', $sender->id)
                ->where(function ($q) {
                    $q->where(function ($q) {
                        $q->where('status', 'pending')->whereRaw('scheduled_at <= NOW()');
                    })->orWhere(function ($q) {
                        $q->where('status', 'error')
                            ->whereColumn('retry_count', '<', 'max_retries')
                            ->whereRaw('scheduled_at <= NOW()');
                    });
                })
                // Только СОЗРЕВШИЕ получатели (пейсинг) — фильтр ДО лимита, иначе
                // окно из первых N писем забивают остывающие и созревшие голодают.
                ->whereIn(DB::raw('LOWER(to_email)'), $eligibleKeys)
                // Не выбирать письма заблокированным получателям (см. выше).
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('recipient_mailboxes as rm')
                        ->whereColumn('rm.email', DB::raw('LOWER(email_queue.to_email)'))
                        ->where('rm.is_blocked', 1);
                })
                // По одному письму на получателя (самое приоритетное/старое из его хвоста).
                ->groupBy(DB::raw('LOWER(to_email)'))
                ->orderByRaw('MAX(priority) DESC')
                ->orderByRaw('MIN(scheduled_at) ASC')
                ->limit($fetch)
                ->get([DB::raw('MIN(id) as id'), DB::raw('LOWER(to_email) as to_email')]);

            $accum = 0;
            $sentThisSender = 0;
            foreach ($rows as $row) {
                if ($dispatched >= $limit || $sentThisSender >= $perSenderCap) {
                    break;
                }

                $recipient = mb_strtolower(trim((string) $row->to_email));
                // Получатель не созрел по интервалу ИЛИ уже получил письмо в этом тике.
                if ($recipient === '' || !isset($eligibleRecipients[$recipient]) || isset($reserved[$recipient])) {
                    continue;
                }

                $claimed = EmailQueue::where('id', $row->id)
                    ->whereIn('status', ['pending', 'error'])
                    ->update(['status' => 'sending', 'updated_at' => now()]);

                if (!$claimed) {
                    continue; // письмо уже забрал другой процесс
                }

                SendQueuedEmailJob::dispatch((int) $row->id)->delay(now()->addSeconds($accum));

                // Якорь пейсинга: фиксируем момент раздачи получателю и резервируем
                // его на остаток тика, чтобы второе письмо ему не ушло сейчас же.
                RecipientMailbox::markDispatched($recipient);
                $reserved[$recipient] = true;

                $accum += $delaySec;
                $dispatched++;
                $sentThisSender++;
            }

            if ($sentThisSender >= $perSenderCap) {
                $totalCapHit++;
            }
        }

        $this->info("Dispatched: {$dispatched} (senders: {$senders->count()}, получателей: "
            . count($reserved) . ", на тик-лимите: {$totalCapHit}, на дневном лимите: {$dailyLimitHit})");
        return self::SUCCESS;
    }

    /**
     * Множество «созревших» получателей на текущий тик (адаптивный пейсинг).
     *
     * Для каждого to_email с pending-письмами считаем
     *   interval = clamp(остаток_рабочего_окна / pending_получателю, MIN, MAX)
     * и считаем получателя готовым, если он ещё ни разу не получал письма
     * (last_dispatched_at IS NULL) или с прошлой раздачи прошло >= interval.
     *
     * Все времена — в tz приложения (UTC): last_dispatched_at пишется через now()
     * (Eloquent сохраняет UTC-строку), читаем сырое значение и парсим как UTC.
     *
     * @return array<string,bool> нормализованный to_email => true
     */
    private function eligibleRecipients(): array
    {
        $minInterval = max(1, (int) config('services.email_dispatch.recipient_interval_min_seconds', 300));
        $maxInterval = max($minInterval, (int) config('services.email_dispatch.recipient_interval_max_seconds', 3600));
        $tz = (string) config('services.email_dispatch.work_window_timezone', 'Europe/Riga');
        $endHour = (int) config('services.email_dispatch.work_window_end_hour', 20);

        // Горизонт размазывания = ДОЛЯ остатка рабочего окна (work_window_fraction,
        // дефолт 0.5 = половина). Смысл: в течение дня подъезжают НОВЫЕ заявки, и
        // «зарезервированная» вторая часть окна их впитывает — иначе утром темп искусственно
        // низкий (весь объём размазан на всё окно), а к вечеру скачок. Планируя под половину,
        // поднимаем утренний темп; к концу окна доля→0 → интервал→MIN, хвост дочищается.
        // Вне окна (ручной --force) горизонт = MAX → щадящий режим.
        $windowFraction = (float) config('services.email_dispatch.work_window_fraction', 0.5);
        if ($windowFraction <= 0 || $windowFraction > 1) {
            $windowFraction = 0.5;
        }
        $nowTz = Carbon::now($tz);
        $windowEnd = $nowTz->copy()->setTime($endHour, 0, 0);
        $remainingSec = $nowTz->lt($windowEnd)
            ? max(60, (int) round(abs($nowTz->diffInSeconds($windowEnd)) * $windowFraction))
            : $maxInterval;

        // pending по нормализованному получателю.
        $counts = DB::connection('reports')->table('email_queue')
            ->selectRaw('LOWER(to_email) as r, COUNT(*) as n')
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->where('status', 'pending')->whereRaw('scheduled_at <= NOW()');
                })->orWhere(function ($q) {
                    $q->where('status', 'error')
                        ->whereColumn('retry_count', '<', 'max_retries')
                        ->whereRaw('scheduled_at <= NOW()');
                });
            })
            ->whereRaw("TRIM(to_email) <> ''")
            ->groupBy(DB::raw('LOWER(to_email)'))
            ->pluck('n', 'r');

        if ($counts->isEmpty()) {
            return [];
        }

        $lastDispatched = DB::connection('reports')->table('recipient_mailboxes')
            ->whereIn('email', $counts->keys()->all())
            ->pluck('last_dispatched_at', 'email');

        // Личный интервал / пауза по отписке (suppliers, см. SupplierUnsubscribeEscalator):
        // override поднимает ПОЛ интервала для адреса; активная пауза (unsubscribe_until в
        // будущем) делает получателя временно НЕ eligible. Несколько поставщиков на один
        // адрес → берём самый щадящий (макс. интервал / макс. пауза).
        $overrides = [];
        $pausedUntil = [];
        $supplierMeta = DB::connection('reports')->table('suppliers')
            ->whereIn(DB::raw('LOWER(email)'), $counts->keys()->all())
            ->get([DB::raw('LOWER(email) as r'), 'unsubscribe_until', 'send_interval_override_seconds']);
        foreach ($supplierMeta as $m) {
            if ($m->send_interval_override_seconds !== null) {
                $overrides[$m->r] = max($overrides[$m->r] ?? 0, (int) $m->send_interval_override_seconds);
            }
            if ($m->unsubscribe_until !== null) {
                $pausedUntil[$m->r] = max($pausedUntil[$m->r] ?? '', (string) $m->unsubscribe_until);
            }
        }

        $now = now();
        $eligible = [];
        foreach ($counts as $recipient => $n) {
            // На паузе по отписке — пропускаем до истечения.
            if (isset($pausedUntil[$recipient]) && Carbon::parse($pausedUntil[$recipient])->isFuture()) {
                continue;
            }

            // Пол интервала — не ниже личного override; потолок поднимаем под override.
            $floor = max($minInterval, (int) ($overrides[$recipient] ?? 0));
            $ceil = max($maxInterval, $floor);
            $interval = (int) min($ceil, max($floor, intdiv($remainingSec, max(1, (int) $n))));
            $last = $lastDispatched[$recipient] ?? null;

            if ($last === null || Carbon::parse($last)->lte($now->copy()->subSeconds($interval))) {
                $eligible[$recipient] = true;
            }
        }

        return $eligible;
    }
}
