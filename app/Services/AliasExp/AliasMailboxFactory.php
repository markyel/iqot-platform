<?php

namespace App\Services\AliasExp;

use App\Services\Senders\BulkSenderImporter;
use App\Services\Senders\SenderAddressGenerator;
use App\Support\Senders\SenderPool;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Фабрика ящика под батч (docs/alias-mailbox-design.md, Ф2): организация из резерва (самая давно
 * использованная) → адрес на домене пула → ящик в Beget с пересылкой всей почты в info@ домена (сам ящик
 * её не хранит) → отправитель reports.senders (пул alias, 1 письмо в 5 мин) → карта alias_mailboxes.
 * Любой сбой после создания ящика в Beget откатывает ящик (dropMailbox), чтобы не копить сирот.
 */
class AliasMailboxFactory
{
    public function __construct(
        private readonly BegetMailApi $beget,
        private readonly OrgReserve $reserve,
        private readonly SenderAddressGenerator $addresses,
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

    /** Домен пула для нового ящика: активный, с общим ящиком и катч-оллом, давнее всех использованный. */
    public function pickDomain(?string $domain = null): ?object
    {
        return DB::table('alias_domains')->where('active', true)->where('catch_all', true)->whereNotNull('inbox_password')
            ->when($domain, fn ($q) => $q->where('domain', $domain))
            ->orderByRaw('last_used_at IS NOT NULL')->orderBy('last_used_at')->orderBy('id')->first();
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

        // Занятые адреса: ящики домена в Beget (включая заведённые вручную) и вся история карты.
        $taken = [];
        foreach ($this->beget->listMailboxes($dom->domain) as $m) {
            $taken[mb_strtolower($m['mailbox'] . '@' . $dom->domain)] = true;
        }
        foreach (DB::table('alias_mailboxes')->pluck('email') as $e) {
            $taken[mb_strtolower($e)] = true;
        }
        $orgArr = (array) $org;
        ['email' => $email, 'password' => $password] = $this->addresses->addressFor($orgArr, $dom->domain, $taken);
        $local = strstr($email, '@', true);

        $this->beget->createMailbox($dom->domain, $local, $password);
        try {
            $this->beget->forwardAndDelete($dom->domain, $local, $dom->inbox . '@' . $dom->domain);

            $cfg = config('services.alias_exp');
            $res = $this->importer->importBlocks([[
                'email' => $email, 'password' => $password, 'user' => $email,
                'smtp' => $cfg['smtp'], 'imap' => $cfg['imap'],
                'company' => $org->name, 'inn' => $org->inn, 'kpp' => $org->kpp, 'ogrn' => $org->ogrn,
                'address' => $org->legal_address, 'company_phone' => $org->phone, 'director' => $org->director_name,
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
                $this->beget->dropMailbox($dom->domain, $local);
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

    /** Удалить ящик в Beget и выключить отправителя; строка карты остаётся (поздние ответы — через катч-олл). */
    public function drop(object $mb): void
    {
        $dom = DB::table('alias_domains')->find($mb->domain_id);
        $local = strstr($mb->email, '@', true);
        try {
            $this->beget->dropMailbox($dom->domain, $local);
        } catch (\Throwable $e) {
            // Ящика уже нет (удалён руками) — не ошибка; остальное — пробуем снова завтра.
            if ($this->beget->mailboxExists($dom->domain, $local)) {
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
