<?php

namespace App\Console\Commands;

use App\Services\AliasExp\AliasMailboxFactory;
use App\Services\AliasExp\BegetMailApi;
use App\Services\AliasExp\OrgReserve;
use App\Support\Senders\SenderPool;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Управление алгоритмом «ящик на батч» (docs/alias-mailbox-design.md).
 *   status                                  — резерв, домены, ящики
 *   api-check {домен}                       — живость ключей Beget (только чтение: список ящиков)
 *   domain-add {домен} [--inbox=info] [--inbox-password=] [--reset-inbox-password]
 *                                           — домен в пул: общий ящик (создаётся, если нет) + катч-олл на него
 *   mailbox-create [--domain=] [--deadline=Y-m-d]
 *                                           — ручной ящик под батч (в Ф3 их создаёт план дня)
 *   drop-expired [--dry-run]                — удалить ящики с истёкшим сроком
 *   mailbox-drop {email}                    — досрочно удалить ящик
 *   watch [batch_id]                        — ход рассылки батчей ветки: отправлено, отказы, ответы
 *   release {batch_id}                      — отпустить удержанные (--hold) письма батча по рабочим слотам
 *   reserve-filter                          — выключить госсектор среди загруженных организаций
 */
class AliasExpCommand extends Command
{
    protected $signature = 'alias-exp {action} {domain?} {--inbox=info} {--inbox-password=} {--reset-inbox-password} {--domain=} {--deadline=} {--dry-run}';

    protected $description = 'Алгоритм «ящик на батч»: домены, ящики, проверка API Beget';

    public function handle(BegetMailApi $beget, OrgReserve $reserve, AliasMailboxFactory $factory): int
    {
        return match ($this->argument('action')) {
            'status' => $this->status($reserve),
            'api-check' => $this->apiCheck($beget),
            'domain-add' => $this->domainAdd($beget),
            'mailbox-create' => $this->mailboxCreate($factory),
            'drop-expired' => $this->dropExpired($factory),
            'reserve-filter' => $this->reserveFilter($reserve),
            'mailbox-drop' => $this->mailboxDrop($factory),
            'release' => $this->release(),
            'watch' => $this->watch(),
            default => $this->unknown(),
        };
    }

    private function status(OrgReserve $reserve): int
    {
        $r = $reserve->stats();
        $this->line("Резерв организаций: {$r['total']} (активных {$r['active']}, не использованных {$r['never_used']}, с живым ящиком {$r['live']})");
        $this->line('Колонка senders.pool: ' . (SenderPool::hasColumn() ? 'есть' : 'НЕТ — ящики не создаются'));
        $this->line('API Beget: ' . (app(BegetMailApi::class)->isConfigured() ? 'ключи заданы' : 'ключей нет'));
        $this->table(['домен', 'общий ящик', 'катч-олл', 'активен', 'MX в DNS', 'ящиков создано', 'последний'],
            DB::table('alias_domains')->orderBy('domain')->get()->map(fn ($d) => [
                $d->domain, $d->inbox . '@' . ($d->inbox_password ? '' : ' (нет пароля)'), $d->catch_all ? 'да' : 'нет',
                $d->active ? 'да' : 'нет', AliasMailboxFactory::mailDnsReady($d->domain) ? 'да' : 'НЕТ — не выдаётся', $d->mailboxes_created, $d->last_used_at,
            ])->all());
        $this->table(['статус', 'ящиков'], DB::table('alias_mailboxes')->selectRaw('status, COUNT(*) n')->groupBy('status')
            ->get()->map(fn ($x) => [$x->status, $x->n])->all());

        return self::SUCCESS;
    }

    private function apiCheck(BegetMailApi $beget): int
    {
        $domain = (string) $this->argument('domain');
        if ($domain === '') {
            $this->error('Укажите домен: alias-exp api-check example.ru');

            return self::FAILURE;
        }
        $list = $beget->listMailboxes($domain);
        $this->info("Ключи Beget работают: на {$domain} ящиков " . count($list));
        foreach ($list as $m) {
            $this->line("  {$m['mailbox']}@{$domain}  пересылка: " . ($m['forward_mail_status'] ?? '?'));
        }

        return self::SUCCESS;
    }

    private function domainAdd(BegetMailApi $beget): int
    {
        $domain = mb_strtolower(trim((string) $this->argument('domain')));
        $inbox = mb_strtolower(trim((string) $this->option('inbox')));
        if ($domain === '' || $inbox === '') {
            $this->error('Укажите домен: alias-exp domain-add example.ru [--inbox=info] [--inbox-password=…]');

            return self::FAILURE;
        }
        $password = (string) $this->option('inbox-password');
        if ($beget->mailboxExists($domain, $inbox) && $this->option('reset-inbox-password')) {
            // Только для своего, только что созданного ящика: владелец им не пользуется.
            $password = BegetMailApi::password();
            $beget->changePassword($domain, $inbox, $password);
            $this->info("Пароль {$inbox}@{$domain} сброшен и сохранён зашифрованным");
        } elseif ($beget->mailboxExists($domain, $inbox)) {
            if ($password === '') {
                // Чужой пароль не меняем: им может пользоваться владелец.
                $this->error("{$inbox}@{$domain} уже есть — передайте его пароль: --inbox-password=…");

                return self::FAILURE;
            }
            $this->line("{$inbox}@{$domain} уже есть — используем");
        } else {
            $password = $password !== '' ? $password : BegetMailApi::password();
            $beget->createMailbox($domain, $inbox, $password);
            $this->info("Создан общий ящик {$inbox}@{$domain} (пароль сохранён зашифрованным)");
        }
        $beget->setCatchAll($domain, $inbox);
        $this->info("Катч-олл {$domain} → {$inbox}@{$domain}");

        DB::table('alias_domains')->updateOrInsert(['domain' => $domain], [
            'inbox' => $inbox, 'inbox_password' => Crypt::encryptString($password), 'catch_all' => true,
            'active' => true, 'updated_at' => now(), 'created_at' => now(),
        ]);

        return self::SUCCESS;
    }

    private function mailboxCreate(AliasMailboxFactory $factory): int
    {
        $deadline = $this->option('deadline') ? Carbon::parse((string) $this->option('deadline')) : null;
        $r = $factory->create(AliasMailboxFactory::expiresAt($deadline), $this->option('domain') ?: null);
        $this->info("Ящик {$r['email']} → sender {$r['sender_id']}, организация «{$r['organization']}», живёт до {$r['expires_at']}");

        return self::SUCCESS;
    }

    private function dropExpired(AliasMailboxFactory $factory): int
    {
        $due = DB::table('alias_mailboxes')->where('status', 'active')->where('expires_at', '<=', now())->get();
        $this->line('К удалению: ' . $due->count());
        foreach ($due as $mb) {
            if ($this->option('dry-run')) {
                $this->line("  {$mb->email} (срок {$mb->expires_at})");
                continue;
            }
            try {
                $factory->drop($mb);
                $this->line("  удалён {$mb->email}");
            } catch (\Throwable $e) {
                DB::table('alias_mailboxes')->where('id', $mb->id)->update(['error' => mb_substr($e->getMessage(), 0, 500), 'updated_at' => now()]);
                $this->warn("  {$mb->email}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function reserveFilter(OrgReserve $reserve): int
    {
        $this->info('Выключено организаций госсектора: ' . $reserve->excludePublicSector());

        return self::SUCCESS;
    }

    /** Досрочно удалить ящик: alias-exp mailbox-drop {email} */
    private function mailboxDrop(AliasMailboxFactory $factory): int
    {
        $mb = DB::table('alias_mailboxes')->where('email', (string) $this->argument('domain'))->where('status', 'active')->first();
        if (!$mb) {
            $this->error('Живой ящик не найден: alias-exp mailbox-drop {email}');

            return self::FAILURE;
        }
        $factory->drop($mb);
        $this->info("Удалён {$mb->email}, отправитель {$mb->sender_id} выключен");

        return self::SUCCESS;
    }

    /** Удержанные письма батча ветки (scheduled_at = 2037, plan-day --hold) → слоты рабочих часов с текущего момента. */
    private function release(): int
    {
        $batchId = (int) $this->argument('domain');
        $senderId = (int) DB::connection('reports')->table('email_batches')->where('id', $batchId)->value('sender_id');
        if (!$senderId || !isset(SenderPool::aliasIds()[$senderId])) {
            $this->error('Батч не найден или не из ветки «ящик на батч»: alias-exp release {batch_id}');

            return self::FAILURE;
        }
        $ids = DB::connection('reports')->table('email_queue')->where('batch_id', $batchId)->where('status', 'pending')
            ->where('scheduled_at', '>=', '2037-01-01')->orderBy('id')->pluck('id')->all();
        $slots = \App\Services\AliasExp\AliasWindow::slots(count($ids));
        foreach ($ids as $i => $id) {
            DB::connection('reports')->table('email_queue')->where('id', $id)->update(['scheduled_at' => $slots[$i]->toDateTimeString()]);
        }
        if ($ids !== []) {
            $this->info(sprintf('Отпущено %d писем: с %s по %s МСК', count($ids),
                $slots[0]->setTimezone('Europe/Moscow')->format('d.m H:i'), end($slots)->setTimezone('Europe/Moscow')->format('d.m H:i')));
        } else {
            $this->line('Удержанных писем нет.');
        }

        return self::SUCCESS;
    }

    /** Ход батчей ветки (все живые ящики или один батч). */
    private function watch(): int
    {
        $r = DB::connection('reports');
        $q = DB::table('alias_mailboxes')->whereNotNull('batch_ids');
        if ($this->argument('domain')) {
            $q->where('batch_ids', 'like', '%' . (int) $this->argument('domain') . '%');
        } else {
            $q->where('status', 'active');
        }
        foreach ($q->get() as $mb) {
            foreach ((array) json_decode((string) $mb->batch_ids, true) as $b) {
                $x = $r->selectOne("SELECT COUNT(*) total, SUM(status IN ('sent','opened','replied','reply_processed','in_conversation','completed')) sent,
                    SUM(status='pending') pending, SUM(status='sending') sending, SUM(status='error') err, SUM(status='cancelled') cancelled,
                    SUM(status='replied') replied, MAX(sent_at) last_sent, MIN(CASE WHEN status='pending' THEN scheduled_at END) next_at
                    FROM email_queue WHERE batch_id = ?", [$b]);
                $s = $r->table('senders')->find($mb->sender_id);
                $conv = $r->selectOne("SELECT COUNT(DISTINCT ec.id) conv, COUNT(m.id) msgs, SUM(m.ai_processed=1) analyzed
                    FROM email_conversations ec LEFT JOIN email_messages m ON m.conversation_id = ec.id AND m.direction = 'incoming' WHERE ec.batch_id = ?", [$b]);
                $bounce = $r->selectOne("SELECT COUNT(*) n, SUM(bounce_reason LIKE '%spam%') spam FROM unidentified_emails WHERE reason = 'bounce' AND sender_id = ?", [$mb->sender_id]);
                $unident = $r->table('unidentified_emails')->where('sender_id', $mb->sender_id)->where('reason', '!=', 'bounce')->count();
                $prices = $r->table('request_item_responses')->where('batch_id', $b)->where(fn ($w) => $w->whereNotNull('price_per_unit')->orWhereNotNull('total_price'))->count();
                $tz = fn ($t) => $t ? \Carbon\Carbon::parse($t, 'UTC')->setTimezone('Europe/Moscow')->format('d.m H:i') : '—';
                $this->line(sprintf('batch %d · %s · ящик %s%s', $b, $mb->email,
                    $s && $s->sending_disabled ? 'ОТКЛЮЧЁН (' . $s->block_reason . ')' : ($s && $s->blocked_until && $s->blocked_until > now() ? 'блок до ' . $s->blocked_until : 'в работе'),
                    $s ? ", отправлено сегодня {$s->emails_sent_today}" : ''));
                $this->line(sprintf('  писем %d: отправлено %d, ждут %d, в полёте %d, ошибок %d, отменено %d · последнее %s МСК, следующее %s МСК',
                    $x->total, $x->sent, $x->pending, $x->sending, $x->err, $x->cancelled, $tz($x->last_sent), $tz($x->next_at)));
                $this->line(sprintf('  ответы: бесед %d, входящих %d (разобрано ИИ %d), replied %d, цен %d · отбойников %d (спам %d), неопознанных %d',
                    $conv->conv, $conv->msgs, (int) $conv->analyzed, $x->replied, $prices, $bounce->n, (int) $bounce->spam, $unident));
            }
        }

        return self::SUCCESS;
    }

    private function unknown(): int
    {
        $this->error('Действия: status | api-check {домен} | domain-add {домен} | mailbox-create | mailbox-drop {email} | release {batch_id} | drop-expired | reserve-filter');

        return self::FAILURE;
    }
}
