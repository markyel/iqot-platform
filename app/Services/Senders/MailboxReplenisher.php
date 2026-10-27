<?php

namespace App\Services\Senders;

use App\Services\AliasExp\BegetMailApi;
use App\Services\AliasExp\OrgReserve;
use App\Services\AliasExp\PersonaFactory;
use App\Support\Senders\SenderPool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Пополнение основного пула ящиков (решение владельца 27.10.2026). Держим target рабочих ящиков: сначала возвращаются
 * отключённые на живых доменах (emails:revive-senders, живые домены первыми), новые создаём только на недостающее —
 * дефицит = цель − рабочие − ждущие возврата на живых доменах.
 *
 * Новые — только на живых доменах (DomainHealth: за health_days ≥ min_sent отправок и спам-отказов ≤ max_spam_pct),
 * с MX, управляемых нашим аккаунтом Beget (API отвечает по домену), не домены ветки «ящик на батч»; не больше per_day
 * в сутки и не больше per_domain_max ящиков на домен (рабочие + ждущие возврата): горит именно домен, а всплеск объёма
 * с домена — главная причина ожогов (июль 2026). Домены — по свободному месту, затем по чистоте.
 *
 * Ящик — как у ветки «ящик на батч» (вымышленная персона, организация из резерва — давнее всех использованная), но
 * обычный входящий: без пересылки, ответы читает приём по IMAP; пул main, стартовый лимит прогрева (рампу ведёт
 * emails:warmup-ramp). Сбой после создания ящика в Beget — ящик удаляем.
 */
class MailboxReplenisher
{
    public function __construct(
        private readonly BegetMailApi $beget,
        private readonly OrgReserve $reserve,
        private readonly PersonaFactory $personas,
        private readonly BulkSenderImporter $importer,
    ) {
    }

    /**
     * Сколько ящиков создать на каких доменах: по одному на домен с наименьшим занятым местом (при равном — чище),
     * пока не закрыт дефицит, не исчерпан дневной лимит и есть место под потолком домена.
     *
     * @param array<string,array{occupied:int, spam_pct:float}> $domains
     * @return array<string,int>
     */
    public static function allocate(int $deficit, array $domains, int $perDay, int $perDomainMax): array
    {
        $left = min(max(0, $deficit), max(0, $perDay));
        $out = [];
        while ($left > 0) {
            $best = null;
            $bestKey = null;
            foreach ($domains as $d => $x) {
                $occupied = $x['occupied'] + ($out[$d] ?? 0);
                if ($occupied >= $perDomainMax) {
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
            $left--;
        }

        return $out;
    }

    /**
     * @return array{target:int, working:int, revivable:int, deficit:int, live:array<string,float>, plan:array<string,int>,
     *               created:string[], errors:string[]}
     */
    public function run(bool $dryRun = false): array
    {
        $cfg = (array) config('services.mailbox_replenish', []);
        $target = max(0, (int) ($cfg['target'] ?? 90));
        $perDay = max(0, (int) ($cfg['per_day'] ?? 2));
        $perDomainMax = max(1, (int) ($cfg['per_domain_max'] ?? 8));
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
            'live' => array_map(fn ($h) => $h['spam_pct'], $live), 'plan' => [], 'created' => [], 'errors' => []];
        if ($res['deficit'] <= 0 || $live === [] || !$this->beget->isConfigured()) {
            return $res;
        }

        $domains = [];
        $mailboxes = [];
        foreach ($live as $d => $h) {
            if (!checkdnsrr($d, 'MX')) {
                continue;
            }
            try {
                $mailboxes[$d] = $this->beget->listMailboxes($d); // не наш домен в Beget — исключение
            } catch (\Throwable) {
                continue;
            }
            $domains[$d] = ['occupied' => $h['working'] + $h['disabled'], 'spam_pct' => $h['spam_pct']];
        }
        $res['plan'] = self::allocate($res['deficit'], $domains, $perDay, $perDomainMax);
        if ($dryRun) {
            return $res;
        }
        foreach ($res['plan'] as $d => $n) {
            for ($i = 0; $i < $n; $i++) {
                try {
                    $res['created'][] = $this->create($d, $mailboxes[$d]);
                } catch (\Throwable $e) {
                    $res['errors'][] = "{$d}: " . $e->getMessage();
                    Log::error('MailboxReplenisher: ящик не создан', ['domain' => $d, 'error' => $e->getMessage()]);
                }
            }
        }

        return $res;
    }

    /** @param array<int,array{mailbox:string}> $mailboxes ящики домена в Beget */
    private function create(string $domain, array $mailboxes): string
    {
        if (!SenderPool::hasColumn()) {
            throw new RuntimeException('Нет колонки reports.senders.pool — нужна миграция.');
        }
        $org = $this->reserve->pickLeastRecentlyUsed() ?? throw new RuntimeException('Резерв организаций пуст');

        // Занятые адреса: ящики домена в Beget (в т.ч. заведённые вручную) и отправители на домене.
        $taken = [];
        foreach ($mailboxes as $m) {
            $taken[mb_strtolower($m['mailbox'] . '@' . $domain)] = true;
        }
        foreach (DB::connection('reports')->table('senders')->where('email', 'like', '%@' . $domain)->pluck('email') as $e) {
            $taken[mb_strtolower((string) $e)] = true;
        }
        $persona = $this->personas->make($domain, $taken);
        $password = BegetMailApi::password();
        $phone = PersonaFactory::legendPhone($org->phone, (string) $org->inn);
        $cfg = (array) config('services.mailbox_replenish', []);

        $this->beget->createMailbox($domain, $persona['local'], $password);
        try {
            $res = $this->importer->importBlocks([[
                'email' => $persona['email'], 'password' => $password, 'user' => $persona['email'],
                'smtp' => (string) ($cfg['smtp'] ?? 'smtp.beget.com:465'), 'imap' => (string) ($cfg['imap'] ?? 'imap.beget.com:993'),
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
                $this->beget->dropMailbox($domain, $persona['local']);
            } catch (\Throwable $drop) {
                Log::warning('MailboxReplenisher: откат ящика не удался', ['email' => $persona['email'], 'error' => $drop->getMessage()]);
            }
            throw $e;
        }
        $this->reserve->markUsed((int) $org->id);
        Log::info('MailboxReplenisher: ящик создан', ['email' => $persona['email'], 'sender_id' => $row['sender_id'] ?? null, 'org' => $org->inn]);

        return $persona['email'];
    }
}
