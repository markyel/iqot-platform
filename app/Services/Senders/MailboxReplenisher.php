<?php

namespace App\Services\Senders;

use App\Services\AliasExp\OrgReserve;
use App\Services\AliasExp\PersonaFactory;
use App\Services\Senders\Mailboxes\BegetMailboxProvider;
use App\Services\Senders\Mailboxes\MailboxProvider;
use App\Services\Senders\Mailboxes\RegruMailboxProvider;
use App\Support\Senders\SenderPool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Пополнение основного пула ящиков (решение владельца 27.10.2026). Держим target рабочих ящиков: сначала возвращаются
 * отключённые на живых доменах (emails:revive-senders, живые домены первыми), новые создаём только на недостающее —
 * дефицит = цель − рабочие − ждущие возврата на живых доменах.
 *
 * Ящики создают поставщики (Mailboxes\MailboxProvider): Beget и хостинг REG.RU (ISPmanager). Новые — на живых доменах
 * (DomainHealth: за health_days ≥ min_sent отправок и спам-отказов ≤ max_spam_pct) с MX, которые обслуживает один из
 * поставщиков, не домены ветки «ящик на батч»; не больше per_day в сутки и не больше per_domain_max ящиков на домен
 * (рабочие + ждущие возврата): горит именно домен, а всплеск объёма с домена — главная причина ожогов (июль 2026).
 * Засев: домен поставщика, по которому ещё не набралось min_sent отправок и спам не выше порога (новый домен REG.RU),
 * получает до seed_per_domain ящиков — дальше домен судит DomainHealth по их отправкам. Место на аккаунте
 * (лимит отправки REG.RU 3000/сутки на аккаунт) — MailboxProvider::roomLeft, общее для доменов одного аккаунта.
 * Домены — по свободному месту, затем по чистоте.
 *
 * Ящик — как у ветки «ящик на батч» (вымышленная персона, организация из резерва — давнее всех использованная), но
 * обычный входящий: без пересылки, ответы читает приём по IMAP; пул main, стартовый лимит прогрева (рампу ведёт
 * emails:warmup-ramp). Сбой после создания ящика у поставщика — ящик удаляем.
 */
class MailboxReplenisher
{
    /** @var MailboxProvider[] */
    private array $providers;

    public function __construct(
        BegetMailboxProvider $beget,
        RegruMailboxProvider $regru,
        private readonly OrgReserve $reserve,
        private readonly PersonaFactory $personas,
        private readonly BulkSenderImporter $importer,
    ) {
        $this->providers = [$beget, $regru];
    }

    /**
     * Сколько ящиков создать на каких доменах: по одному на домен с наименьшим занятым местом (при равном — чище),
     * пока не закрыт дефицит, не исчерпан дневной лимит, есть место под потолком домена (свой max — у засеваемого)
     * и на аккаунте домена ($accountRoom; аккаунта нет в списке — без ограничения).
     *
     * @param array<string,array{occupied:int, spam_pct:float, max?:int, account?:string}> $domains
     * @param array<string,int> $accountRoom
     * @return array<string,int>
     */
    public static function allocate(int $deficit, array $domains, int $perDay, int $perDomainMax, array $accountRoom = []): array
    {
        $left = min(max(0, $deficit), max(0, $perDay));
        $out = [];
        $taken = [];
        while ($left > 0) {
            $best = null;
            $bestKey = null;
            foreach ($domains as $d => $x) {
                $occupied = $x['occupied'] + ($out[$d] ?? 0);
                if ($occupied >= ($x['max'] ?? $perDomainMax)) {
                    continue;
                }
                $acc = $x['account'] ?? null;
                if ($acc !== null && isset($accountRoom[$acc]) && ($taken[$acc] ?? 0) >= $accountRoom[$acc]) {
                    continue;
                }
                $key = [$occupied, $x['spam_pct']];
                if ($bestKey === null || $key < $bestKey) {
                    $best = $d;
                    $bestKey = $key;
                }
            }
            if ($best === null) {
                break;
            }
            $out[$best] = ($out[$best] ?? 0) + 1;
            if (($acc = $domains[$best]['account'] ?? null) !== null) {
                $taken[$acc] = ($taken[$acc] ?? 0) + 1;
            }
            $left--;
        }

        return $out;
    }

    /**
     * @return array{target:int, working:int, revivable:int, deficit:int, live:array<string,float>, seed:string[],
     *               providers:array<string,string>, plan:array<string,int>, created:string[], errors:string[]}
     */
    public function run(bool $dryRun = false): array
    {
        $cfg = (array) config('services.mailbox_replenish', []);
        $target = max(0, (int) ($cfg['target'] ?? 90));
        $perDay = max(0, (int) ($cfg['per_day'] ?? 2));
        $perDomainMax = max(1, (int) ($cfg['per_domain_max'] ?? 8));
        $seedMax = max(0, min($perDomainMax, (int) ($cfg['seed_per_domain'] ?? 2)));
        $maxSpam = (float) ($cfg['max_spam_pct'] ?? 3);
        $minSent = max(1, (int) ($cfg['min_sent'] ?? 50));

        $health = DomainHealth::byDomain(max(1, (int) ($cfg['health_days'] ?? 7)));
        try {
            $aliasDomains = DB::table('alias_domains')->pluck('domain')->map(fn ($d) => mb_strtolower((string) $d))->flip()->all();
        } catch (\Throwable) {
            $aliasDomains = [];
        }
        $live = array_filter($health, fn ($h, $d) => !isset($aliasDomains[$d]) && DomainHealth::isLive($h, $maxSpam, $minSent), ARRAY_FILTER_USE_BOTH);
        $working = array_sum(array_column($health, 'working'));
        $revivable = array_sum(array_column($live, 'disabled'));
        $res = ['target' => $target, 'working' => $working, 'revivable' => $revivable, 'deficit' => $target - $working - $revivable,
            'live' => array_map(fn ($h) => $h['spam_pct'], $live), 'seed' => [], 'providers' => [], 'plan' => [], 'created' => [], 'errors' => []];
        $providers = array_values(array_filter($this->providers, fn (MailboxProvider $p) => $p->isConfigured()));
        if ($res['deficit'] <= 0 || $providers === []) {
            return $res;
        }

        // Кандидаты: живые домены поставщиков + засев новых доменов поставщиков.
        $candidates = [];
        foreach ($live as $d => $h) {
            $candidates[$d] = ['h' => $h, 'max' => $perDomainMax];
        }
        foreach ($providers as $p) {
            foreach ($p->seedDomains() as $d) {
                $h = $health[$d] ?? ['sent' => 0, 'spam_pct' => 0.0, 'working' => 0, 'disabled' => 0];
                if (isset($candidates[$d]) || isset($aliasDomains[$d]) || $h['sent'] >= $minSent || $h['spam_pct'] > $maxSpam) {
                    continue;
                }
                $candidates[$d] = ['h' => $h, 'max' => $seedMax];
                $res['seed'][] = $d;
            }
        }

        $domains = [];
        $owner = [];
        $accountRoom = [];
        foreach ($candidates as $d => $c) {
            if (!checkdnsrr($d, 'MX')) {
                continue;
            }
            $p = null;
            foreach ($providers as $candidate) {
                if ($candidate->manages($d)) {
                    $p = $candidate;
                    break;
                }
            }
            if ($p === null) {
                continue;
            }
            $acc = $p->account($d);
            if (!array_key_exists($acc, $accountRoom) && ($room = $p->roomLeft($d)) !== null) {
                $accountRoom[$acc] = $room;
            }
            $owner[$d] = $p;
            $res['providers'][$d] = $p->name();
            $domains[$d] = ['occupied' => $c['h']['working'] + $c['h']['disabled'], 'spam_pct' => $c['h']['spam_pct'], 'max' => $c['max'], 'account' => $acc];
        }
        $res['plan'] = self::allocate($res['deficit'], $domains, $perDay, $perDomainMax, $accountRoom);
        if ($dryRun) {
            return $res;
        }
        foreach ($res['plan'] as $d => $n) {
            for ($i = 0; $i < $n; $i++) {
                try {
                    $res['created'][] = $this->create($owner[$d], $d);
                } catch (\Throwable $e) {
                    $res['errors'][] = "{$d}: " . $e->getMessage();
                    Log::error('MailboxReplenisher: ящик не создан', ['domain' => $d, 'provider' => $owner[$d]->name(), 'error' => $e->getMessage()]);
                }
            }
        }

        return $res;
    }

    private function create(MailboxProvider $provider, string $domain): string
    {
        if (!SenderPool::hasColumn()) {
            throw new RuntimeException('Нет колонки reports.senders.pool — нужна миграция.');
        }
        $org = $this->reserve->pickLeastRecentlyUsed() ?? throw new RuntimeException('Резерв организаций пуст');

        // Занятые адреса: ящики домена у поставщика (в т.ч. заведённые вручную) и отправители на домене.
        $taken = [];
        foreach ($provider->mailboxes($domain) as $local) {
            $taken[mb_strtolower($local . '@' . $domain)] = true;
        }
        foreach (DB::connection('reports')->table('senders')->where('email', 'like', '%@' . $domain)->pluck('email') as $e) {
            $taken[mb_strtolower((string) $e)] = true;
        }
        $persona = $this->personas->make($domain, $taken);
        $password = $provider->password();
        $phone = PersonaFactory::legendPhone($org->phone, (string) $org->inn);

        $provider->createMailbox($domain, $persona['local'], $password);
        try {
            $res = $this->importer->importBlocks([[
                'email' => $persona['email'], 'password' => $password, 'user' => $persona['email'],
                'smtp' => $provider->smtp($domain), 'imap' => $provider->imap($domain),
                'name' => $persona['display_name'], 'fullname' => $persona['full_name'], 'phone' => $phone,
                'company' => OrgReserve::displayName((string) $org->name), 'inn' => $org->inn, 'kpp' => $org->kpp, 'ogrn' => $org->ogrn,
                'address' => $org->legal_address, 'company_phone' => $phone, 'director' => $org->director_name,
                'sender_overrides' => ['pool' => SenderPool::MAIN, 'daily_limit' => max(1, (int) config('services.email_warmup.start', 30))],
            ]]);
            $row = $res['rows'][0] ?? [];
            if (($row['status'] ?? '') !== 'created') {
                throw new RuntimeException('Отправитель не создан: ' . ($row['message'] ?? '?'));
            }
        } catch (\Throwable $e) {
            try {
                $provider->dropMailbox($domain, $persona['local']);
            } catch (\Throwable $drop) {
                Log::warning('MailboxReplenisher: откат ящика не удался', ['email' => $persona['email'], 'error' => $drop->getMessage()]);
            }
            throw $e;
        }
        $this->reserve->markUsed((int) $org->id);
        Log::info('MailboxReplenisher: ящик создан', ['email' => $persona['email'], 'provider' => $provider->name(),
            'sender_id' => $row['sender_id'] ?? null, 'org' => $org->inn]);

        return $persona['email'];
    }
}
