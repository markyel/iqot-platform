<?php

namespace App\Services\AliasExp;

use App\Services\Senders\BulkSenderImporter;
use App\Support\Senders\SenderPool;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Фабрика ящика под батч (docs/alias-mailbox-design.md, Ф2): организация из резерва (самая давно
 * использованная) → вымышленная персона (PersonaFactory: ФИО = адрес = подпись, телефон с изменённой цифрой) → ящик у поставщика домена
 * (Beget или хостинг REG.RU, alias_domains.provider — AliasProviders) с пересылкой всей почты в info@ домена (сам ящик
 * её не хранит) → отправитель reports.senders (пул alias, 1 письмо в 5 мин) → карта alias_mailboxes.
 * Любой сбой после создания ящика откатывает ящик (dropMailbox), чтобы не копить сирот.
 */
class AliasMailboxFactory
{
    public function __construct(
        private readonly AliasProviders $providers,
        private readonly OrgReserve $reserve,
        private readonly PersonaFactory $personas,
        private readonly BulkSenderImporter $importer,
    ) {
    }

    /** Срок жизни ящика: дедлайн заявки + 7 дней; без дедлайна — 21 день (решение владельца 16.10.2026). */
    public static function expiresAt(?CarbonInterface $deadline): CarbonInterface
    {
        $cfg = config('services.alias_exp');
        $byDeadline = $deadline?->copy()->endOfDay()->addDays((int) $cfg['lifetime_after_deadline_days']);

        return $byDeadline && $byDeadline->isFuture() ? $byDeadline : now()->addDays((int) $cfg['lifetime_default_days']);
    }

    /**
     * Домен пула для нового ящика: активный, с общим ящиком и катч-оллом, давнее всех использованный.
     * Домен без MX в DNS пропускаем: только что купленный (oblzakaz.ru, 23.10.2026) ещё не виден в зоне .ru,
     * письма с него получатели отклонят, а ответы не дойдут.
     */
    public function pickDomain(?string $domain = null): ?object
    {
        $rows = DB::table('alias_domains')->where('active', true)->where('catch_all', true)->whereNotNull('inbox_password')
            ->when($domain, fn ($q) => $q->where('domain', $domain))
            ->orderByRaw('last_used_at IS NOT NULL')->orderBy('last_used_at')->orderBy('id')->get();
        foreach ($rows as $row) {
            if (self::mailDnsReady((string) $row->domain)) {
                return $row;
            }
            Log::info('AliasMailboxFactory: домен без MX в DNS пропущен', ['domain' => $row->domain]);
        }

        return null;
    }

    public static function mailDnsReady(string $domain): bool
    {
        return checkdnsrr($domain, 'MX');
    }

    /**
     * @return array{mailbox_id:int, email:string, sender_id:int, organization:string, expires_at:string}
     */
    public function create(CarbonInterface $expiresAt, ?string $domain = null): array
    {
        if (!SenderPool::hasColumn()) {
            throw new RuntimeException('Нет колонки reports.senders.pool — ящик попал бы в основную рассылку. Нужна миграция.');
        }
        $dom = $this->pickDomain($domain) ?? throw new RuntimeException('Нет готового домена в пуле (alias:domain add)');
        $org = $this->reserve->pickLeastRecentlyUsed() ?? throw new RuntimeException('Резерв организаций пуст');

        $provider = $this->providers->forDomain($dom);
        // Занятые адреса: ящики домена у поставщика (включая заведённые вручную) и вся история карты.
        $taken = [];
        foreach ($provider->mailboxes($dom->domain) as $local) {
            $taken[mb_strtolower($local . '@' . $dom->domain)] = true;
        }
        foreach (DB::table('alias_mailboxes')->pluck('email') as $e) {
            $taken[mb_strtolower($e)] = true;
        }
        // Полный набор персоны сразу: одно вымышленное ФИО — в адресе, имени отправителя и подписи.
        $persona = $this->personas->make($dom->domain, $taken);
        ['email' => $email, 'local' => $local] = $persona;
        $password = $provider->password();
        $phone = PersonaFactory::legendPhone($org->phone, (string) $org->inn);

        $provider->createMailbox($dom->domain, $local, $password);
        try {
            $provider->forwardAndDelete($dom->domain, $local, $dom->inbox . '@' . $dom->domain);

            $cfg = config('services.alias_exp');
            $res = $this->importer->importBlocks([[
                'email' => $email, 'password' => $password, 'user' => $email,
                'smtp' => $dom->smtp ?? null ?: $cfg['smtp'], 'imap' => $dom->imap ?? null ?: $cfg['imap'],
                'name' => $persona['display_name'], 'fullname' => $persona['full_name'], 'phone' => $phone,
                'company' => OrgReserve::displayName((string) $org->name), 'inn' => $org->inn, 'kpp' => $org->kpp, 'ogrn' => $org->ogrn,
                'address' => $org->legal_address, 'company_phone' => $phone, 'director' => $org->director_name,
                'sender_overrides' => [
                    'pool' => SenderPool::ALIAS,
                    'send_delay_seconds' => (int) $cfg['send_delay_seconds'],
                    'daily_limit' => (int) $cfg['daily_limit'],
                ],
            ]]);
            $row = $res['rows'][0] ?? [];
            if (($row['status'] ?? '') !== 'created') {
                throw new RuntimeException('Отправитель не создан: ' . ($row['message'] ?? '?'));
            }
        } catch (\Throwable $e) {
            try {
                $provider->dropMailbox($dom->domain, $local);
            } catch (\Throwable $drop) {
                Log::warning('AliasMailboxFactory: откат ящика не удался', ['email' => $email, 'error' => $drop->getMessage()]);
            }
            throw $e;
        }

        $id = DB::table('alias_mailboxes')->insertGetId([
            'email' => $email, 'domain_id' => $dom->id, 'sender_id' => (int) $row['sender_id'],
            'client_organization_id' => $row['organization_id'] ?? null, 'org_reserve_id' => $org->id,
            'status' => 'active', 'expires_at' => $expiresAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->reserve->markUsed((int) $org->id);
        DB::table('alias_domains')->where('id', $dom->id)->update([
            'mailboxes_created' => DB::raw('mailboxes_created + 1'), 'last_used_at' => now(), 'updated_at' => now(),
        ]);
        Log::info('AliasMailboxFactory: ящик создан', ['email' => $email, 'sender_id' => $row['sender_id'], 'org' => $org->inn]);

        return ['mailbox_id' => $id, 'email' => $email, 'sender_id' => (int) $row['sender_id'],
            'organization' => (string) ($row['organization'] ?? $org->name), 'expires_at' => $expiresAt->toDateTimeString()];
    }

    /** Удалить ящик у поставщика и выключить отправителя; строка карты остаётся (поздние ответы — через катч-олл). */
    public function drop(object $mb): void
    {
        $dom = DB::table('alias_domains')->find($mb->domain_id);
        $local = strstr($mb->email, '@', true);
        $provider = $this->providers->forDomain($dom);
        try {
            $provider->dropMailbox($dom->domain, $local);
        } catch (\Throwable $e) {
            // Ящика уже нет (удалён руками) — не ошибка; остальное — пробуем снова завтра.
            if ($provider->mailboxExists($dom->domain, $local)) {
                throw $e;
            }
        }
        if ($mb->sender_id) {
            DB::connection('reports')->table('senders')->where('id', $mb->sender_id)
                ->update(['is_active' => 0, 'block_reason' => 'alias: срок жизни истёк', 'updated_at' => now()]);
        }
        DB::table('alias_mailboxes')->where('id', $mb->id)->update(['status' => 'dropped', 'dropped_at' => now(), 'updated_at' => now()]);
        if ($mb->org_reserve_id) {
            $this->reserve->release((int) $mb->org_reserve_id);
        }
    }

    /** Пароль общего ящика домена (для IMAP-приёма, Ф4). */
    public static function inboxPassword(object $dom): ?string
    {
        return $dom->inbox_password ? Crypt::decryptString($dom->inbox_password) : null;
    }
}
