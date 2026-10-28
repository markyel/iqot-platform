<?php

namespace App\Console\Commands;

use App\Jobs\SendQueuedEmailJob;
use App\Models\Reports\EmailQueue;
use App\Models\Reports\RecipientMailbox;
use App\Services\Dispatch\RecipientChannels;
use App\Support\Senders\SmtpServerCaps;
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

        // 1) Реклейм застрявших в 'sending' (упавший воркер). Порог 40 мин > retryUntil
        // job'а (30 мин): живой job к этому моменту уже либо отработал, либо истёк.
        $reclaimed = EmailQueue::where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(40))
            ->update(['status' => 'pending']);
        if ($reclaimed) {
            $this->info("Reclaimed stale 'sending': {$reclaimed}");
        }

        $limit = max(1, (int) $this->option('limit'));
        $tick = max(5, (int) $this->option('tick'));

        // 2) Каналы и их лимиты на получателя (заявки / эксперимент / мониторинг изолированы, RecipientChannels).
        //    Ветка «ящик на батч»: письма ящика расписаны по дням, но внутри дня ящику доступны все письма на сегодня.
        $channels = new RecipientChannels();
        $aliasIds = $channels->aliasIds();
        $aliasUntil = Carbon::now('Europe/Moscow')->endOfDay()->utc()->toDateTimeString();
        $aliasIdList = array_keys($aliasIds) ?: [0];
        $monitoringBatches = array_keys($channels->monitoringBatchIds()) ?: [0];

        // Адаптивный пейсинг: какие to_email «созрели» на этот тик — отдельно в каждом канале.
        $eligible = $this->eligibleByChannel($channels, $aliasUntil);
        if ($eligible === []) {
            $this->info('Dispatched: 0 (нет созревших получателей на этот тик)');
            return self::SUCCESS;
        }

        // 3) Готовые ящики: активные, не заблокированные, у которых есть письма к отправке.
        $senders = DB::connection('reports')->table('senders as s')
            ->join('email_queue as eq', 'eq.sender_id', '=', 's.id')
            ->where('s.is_active', 1)
            // Отключённый от массовой рассылки ящик (спам-гвард / повторный бан прогрева)
            // не шлёт, даже когда blocked_until истёк (ответы в беседах — отдельный путь).
            ->where(function ($q) {
                $q->whereNull('s.sending_disabled')->orWhere('s.sending_disabled', 0);
            })
            ->where(function ($q) {
                $q->whereNull('s.blocked_until')->orWhereRaw('s.blocked_until <= NOW()');
            })
            ->where(function ($q) use ($aliasIdList, $aliasUntil) {
                $q->where(function ($q) {
                    $q->where('eq.status', 'pending')->whereRaw('eq.scheduled_at <= NOW()');
                })->orWhere(function ($q) {
                    $q->where('eq.status', 'error')
                        ->whereColumn('eq.retry_count', '<', 'eq.max_retries')
                        ->whereRaw('eq.scheduled_at <= NOW()');
                })->orWhere(function ($q) use ($aliasIdList, $aliasUntil) {
                    $q->where('eq.status', 'pending')->whereIn('eq.sender_id', $aliasIdList)->where('eq.scheduled_at', '<=', $aliasUntil);
                });
            })
            // Заблокированные получатели (N ошибок подряд) — сразу пропускаем, не клеймим.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('recipient_mailboxes as rm')
                    ->whereColumn('rm.email', DB::raw('LOWER(eq.to_email)'))
                    ->where('rm.is_blocked', 1);
            })
            ->groupBy('s.id', 's.email', 's.send_delay_seconds', 's.daily_limit', 's.emails_sent_today', 's.last_send_date', 's.smtp_server')
            ->get(['s.id', 's.email', 's.send_delay_seconds', 's.daily_limit', 's.emails_sent_today', 's.last_send_date', 's.smtp_server']);

        if ($senders->isEmpty()) {
            $this->info('No pending emails.');
            return self::SUCCESS;
        }
        // Ящики ветки «ящик на батч» — первыми в обходе (решение владельца 28.10.2026): получателю за тик уходит одно
        // письмо, и классические ящики (меньшие id) занимали общих с веткой получателей раньше — у ветки с паузой 300 с
        // и окном 9–18 МСК письма мониторинга стояли весь день (28.10: 8 из 300). Дневные капы получателей те же.
        $senders = $senders->sortBy(fn ($s) => isset($aliasIds[(int) $s->id]) ? 0 : 1)->values();

        // 4) Round-robin по ящикам: на каждый берём perSenderCap писем, ставим
        //    с накопительной задержкой по этому ящику (0, delay, 2·delay, …).
        //    Не более одного письма получателю за тик ($reserved) — во всех каналах вместе.
        $dispatched = 0;
        $totalCapHit = 0;
        $reserved = []; // нормализованный to_email => уже отдали письмо в этом тике
        $byChannel = [];

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
        // Суточный потолок сервера = аккаунта провайдера (REG.RU: 3000 писем за 24 ч на аккаунт), SmtpServerCaps.
        $serverLeft = SmtpServerCaps::remaining();
        $serverCapHit = 0;
        // Ящики ветки «ящик на батч» шлют только в своё окно (будни 9–18 МСК), уже общего окна тика.
        $aliasClosed = !\App\Services\AliasExp\AliasWindow::isOpen() ? $aliasIds : [];
        // Паузы по отписке (ящик / наш домен × получатель, SenderPauses): такие письма ждут в очереди конца паузы.
        $eligibleRecipients = [];
        foreach ($eligible as $byRecipient) {
            $eligibleRecipients += $byRecipient;
        }
        $pauses = (new \App\Services\Senders\SenderPauses())->active(array_keys($eligibleRecipients));
        $pausedHeld = 0;

        foreach ($senders as $sender) {
            if ($dispatched >= $limit) {
                break;
            }
            if (isset($aliasClosed[(int) $sender->id])) {
                continue;
            }
            $isAlias = isset($aliasIds[(int) $sender->id]);

            $delaySec = max(1, (int) ($sender->send_delay_seconds ?: 20));
            // Сколько ящик успеет отправить до следующего тика (+1 в запас от простоя).
            $perSenderCap = (int) ceil($tick / $delaySec) + 1;
            // Уже взятые в работу и ещё не отправленные письма ящика занимают его ближайшие слоты: не берём сверх
            // того, что ящик успеет до следующего тика (у ящиков эксперимента пауза 300 с — иначе копились десятки
            // писем «в полёте», часами ждали слота и уже числились в дневном лимите получателей, 19.10.2026).
            $perSenderCap -= (int) ($inFlight[$sender->id] ?? 0);
            if ($perSenderCap <= 0) {
                $totalCapHit++;
                continue;
            }

            // Дневной лимит: остаток = daily_limit − отправлено_сегодня − в_полёте.
            // emails_sent_today валиден только если last_send_date = сегодня (первая
            // отправка нового дня его обнуляет триггер БД before_sender_update). Ящик, выбравший
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
            $server = mb_strtolower((string) $sender->smtp_server);
            if (isset($serverLeft[$server])) {
                if ($serverLeft[$server] <= 0) {
                    $serverCapHit++;
                    continue;
                }
                $perSenderCap = min($perSenderCap, $serverLeft[$server]);
            }
            // Берём по ОДНОМУ письму на созревшего получателя (GROUP BY), с буфером
            // +50 на случай, что часть получателей уже заняты другим ящиком в этом
            // тике ($reserved). Фильтр зрелости — whereIn по созревшим своего канала ДО лимита.
            $fetch = $perSenderCap + 50;
            $fetchRows = function (string $channel) use ($sender, $isAlias, $aliasUntil, $monitoringBatches, $eligible, $fetch) {
                $keys = array_keys($eligible[$channel] ?? []);
                if ($keys === []) {
                    return collect();
                }
                $q = DB::connection('reports')->table('email_queue')
                    ->where('sender_id', $sender->id)
                    ->where(function ($q) use ($isAlias, $aliasUntil) {
                        $q->where(function ($q) use ($isAlias, $aliasUntil) {
                            $isAlias
                                ? $q->where('status', 'pending')->where('scheduled_at', '<=', $aliasUntil)
                                : $q->where('status', 'pending')->whereRaw('scheduled_at <= NOW()');
                        })->orWhere(function ($q) {
                            $q->where('status', 'error')
                                ->whereColumn('retry_count', '<', 'max_retries')
                                ->whereRaw('scheduled_at <= NOW()');
                        });
                    })
                    // Только СОЗРЕВШИЕ в этом канале получатели (пейсинг) — фильтр ДО лимита.
                    ->whereIn(DB::raw('LOWER(to_email)'), $keys)
                    ->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('recipient_mailboxes as rm')
                            ->whereColumn('rm.email', DB::raw('LOWER(email_queue.to_email)'))
                            ->where('rm.is_blocked', 1);
                    })
                    // Домен в стоп-листе (blocked_domains: жалобы, домены клиентов) — не шлём, даже если письмо
                    // попало в очередь в обход фильтров генерации (28.10.2026: myzip.ru / mylift.ru — ящики клиента).
                    ->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('blocked_domains as bd')
                            ->whereColumn('bd.domain', DB::raw("SUBSTRING_INDEX(LOWER(email_queue.to_email), '@', -1)"));
                    });
                // Письма мониторинга идут и с обычных ящиков, и с ящиков ветки «ящик на батч» — разделяем по батчу.
                if ($channel === RecipientChannels::MONITORING) {
                    $q->whereIn('batch_id', $monitoringBatches);
                } else {
                    $q->where(fn ($q) => $q->whereNull('batch_id')->orWhereNotIn('batch_id', $monitoringBatches));
                }

                // По одному письму на получателя (самое приоритетное/старое из его хвоста).
                return $q->groupBy(DB::raw('LOWER(to_email)'))
                    ->orderByRaw('MAX(priority) DESC')
                    ->orderByRaw('MIN(scheduled_at) ASC')
                    ->limit($fetch)
                    ->get([DB::raw('MIN(id) as id'), DB::raw('LOWER(to_email) as to_email')])
                    ->each(fn ($r) => $r->channel = $channel);
            };
            $rows = $isAlias
                ? $fetchRows(RecipientChannels::ALIAS)->concat($fetchRows(RecipientChannels::MONITORING))
                : $fetchRows(RecipientChannels::MONITORING)->concat($fetchRows(RecipientChannels::MAIN));

            $accum = 0;
            $sentThisSender = 0;
            foreach ($rows as $row) {
                if ($dispatched >= $limit || $sentThisSender >= $perSenderCap) {
                    break;
                }

                $recipient = mb_strtolower(trim((string) $row->to_email));
                // Получатель не созрел по интервалу ИЛИ уже получил письмо в этом тике.
                if ($recipient === '' || !isset($eligible[$row->channel][$recipient]) || isset($reserved[$recipient])) {
                    continue;
                }
                if ($pauses !== [] && \App\Services\Senders\SenderPauses::blocks($pauses, $recipient, (int) $sender->id, (string) $sender->email)) {
                    $pausedHeld++;
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
                $byChannel[$row->channel] = ($byChannel[$row->channel] ?? 0) + 1;
                $sentThisSender++;
            }

            if ($sentThisSender >= $perSenderCap) {
                $totalCapHit++;
            }
            if (isset($serverLeft[$server])) {
                $serverLeft[$server] -= $sentThisSender;
            }
        }

        $this->info("Dispatched: {$dispatched} (senders: {$senders->count()}, получателей: "
            . count($reserved) . ", на тик-лимите: {$totalCapHit}, на дневном лимите: {$dailyLimitHit}, на лимите аккаунта: {$serverCapHit}, на паузе по отписке: {$pausedHeld}) по каналам: " . json_encode($byChannel));
        return self::SUCCESS;
    }

    /**
     * «Созревшие» на текущий тик получатели — отдельно по каналам (заявки / эксперимент / мониторинг).
     *
     * У каждого канала свой дневной кап на адресата (RecipientChannels::cap) и свой адаптивный интервал
     *   interval = max(пол, горизонт / pending_канала_у_адресата), от последнего письма этого канала сегодня.
     * Общий для всех каналов — только пол: между любыми двумя письмами адресату не меньше
     * recipient_interval_min_seconds (или личного override), по recipient_mailboxes.last_dispatched_at.
     * Горизонт = доля остатка рабочего окна (work_window_fraction): утром темп выше, к вечеру хвост дочищается.
     *
     * Все времена — в tz приложения (UTC): last_dispatched_at пишется через now(), читаем сырое значение как UTC.
     *
     * @return array<string,array<string,bool>> канал => [нормализованный to_email => true]
     */
    private function eligibleByChannel(RecipientChannels $channels, string $aliasUntil): array
    {
        $minInterval = max(1, (int) config('services.email_dispatch.recipient_interval_min_seconds', 300));
        $maxInterval = max($minInterval, (int) config('services.email_dispatch.recipient_interval_max_seconds', 3600));
        $tz = (string) config('services.email_dispatch.work_window_timezone', 'Europe/Riga');
        $endHour = (int) config('services.email_dispatch.work_window_end_hour', 20);
        $windowFraction = (float) config('services.email_dispatch.work_window_fraction', 0.5);
        if ($windowFraction <= 0 || $windowFraction > 1) {
            $windowFraction = 0.5;
        }
        $nowTz = Carbon::now($tz);
        $windowEnd = $nowTz->copy()->setTime($endHour, 0, 0);
        $remainingSec = $nowTz->lt($windowEnd)
            ? max(60, (int) round(abs($nowTz->diffInSeconds($windowEnd)) * $windowFraction))
            : $maxInterval;

        $pending = $channels->pending($aliasUntil);
        if ($pending === []) {
            return [];
        }
        $emails = array_keys($pending);
        $meta = $channels->recipientMeta($emails);
        $usage = $channels->usageToday($emails);

        // Личный интервал / пауза по отписке (suppliers, см. SupplierUnsubscribeEscalator):
        // override поднимает ПОЛ интервала для адреса; активная пауза (unsubscribe_until в
        // будущем) делает получателя временно НЕ eligible. Несколько поставщиков на один
        // адрес → берём самый щадящий (макс. интервал / макс. пауза).
        $overrides = [];
        $pausedUntil = [];
        foreach (array_chunk($emails, 1000) as $chunk) {
            foreach (DB::connection('reports')->table('suppliers')->whereIn(DB::raw('LOWER(email)'), $chunk)
                ->get([DB::raw('LOWER(email) as r'), 'unsubscribe_until', 'send_interval_override_seconds']) as $m) {
                if ($m->send_interval_override_seconds !== null) {
                    $overrides[$m->r] = max($overrides[$m->r] ?? 0, (int) $m->send_interval_override_seconds);
                }
                if ($m->unsubscribe_until !== null) {
                    $pausedUntil[$m->r] = max($pausedUntil[$m->r] ?? '', (string) $m->unsubscribe_until);
                }
            }
        }

        $now = now();
        $eligible = [];
        foreach ($pending as $recipient => $byChannel) {
            $m = $meta[$recipient];
            if ($m['blocked'] || (isset($pausedUntil[$recipient]) && Carbon::parse($pausedUntil[$recipient])->isFuture())) {
                continue;
            }
            // Общий пол между любыми письмами адресату (все каналы).
            $floor = max($minInterval, (int) ($overrides[$recipient] ?? 0));
            if ($m['last'] !== null && Carbon::parse($m['last'])->gt($now->copy()->subSeconds($floor))) {
                continue;
            }
            foreach ($byChannel as $channel => $n) {
                $cap = RecipientChannels::cap($channel, $m['cap'], RecipientChannels::contended($usage, $pending, $recipient));
                $used = $usage[$recipient][$channel] ?? ['n' => 0, 'last' => null];
                if ($cap <= 0 || $used['n'] >= $cap) {
                    continue;
                }
                $interval = max($floor, intdiv($remainingSec, max(1, (int) $n)));
                if ($used['last'] === null || Carbon::parse($used['last'])->lte($now->copy()->subSeconds($interval))) {
                    $eligible[$channel][$recipient] = true;
                }
            }
        }

        return $eligible;
    }
}
