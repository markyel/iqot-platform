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
            default => $this->unknown(),
        };
    }

    private function status(OrgReserve $reserve): int
    {
        $r = $reserve->stats();
        $this->line("Резерв организаций: {$r['total']} (активных {$r['active']}, не использованных {$r['never_used']}, с живым ящиком {$r['live']})");
        $this->line('Колонка senders.pool: ' . (SenderPool::hasColumn() ? 'есть' : 'НЕТ — ящики не создаются'));
        $this->line('API Beget: ' . (app(BegetMailApi::class)->isConfigured() ? 'ключи заданы' : 'ключей нет'));
        $this->table(['домен', 'общий ящик', 'катч-олл', 'активен', 'ящиков создано', 'последний'],
            DB::table('alias_domains')->orderBy('domain')->get()->map(fn ($d) => [
                $d->domain, $d->inbox . '@' . ($d->inbox_password ? '' : ' (нет пароля)'), $d->catch_all ? 'да' : 'нет',
                $d->active ? 'да' : 'нет', $d->mailboxes_created, $d->last_used_at,
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

    private function unknown(): int
    {
        $this->error('Действия: status | api-check {домен} | domain-add {домен} | mailbox-create | mailbox-drop {email} | drop-expired | reserve-filter');

        return self::FAILURE;
    }
}
