<?php

namespace App\Services\Regru;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * REG.RU: REG.API 2 (https://www.reg.ru/reseller/api2doc — услуги, DNS-зона) и ISPmanager хостинга (почтовые домены и
 * ящики; в REG.API почтовых методов нет). Ключи — REGRU_API_USER / REGRU_API_PASSWORD (альтернативный пароль API,
 * IP прода — в белом списке API в кабинете). Логин и пароль панели хостинга отдаёт сам REG.API (service/get_details):
 * держим только в памяти процесса, не в кеше и не в логах. Ошибка API → RuntimeException без ключей в тексте.
 */
class RegruApi
{
    /** @var array<int,array{url:string, host:string, login:string, pass:string, ip:string}> */
    private array $hostings = [];

    public function isConfigured(): bool
    {
        return (string) config('services.regru.api_user') !== '' && (string) config('services.regru.api_password') !== '';
    }

    /** Вызов REG.API: параметры — JSON в input_data. @return array<string,mixed> answer */
    public function call(string $fn, array $params = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('REG.API: не заданы REGRU_API_USER / REGRU_API_PASSWORD');
        }
        $form = [
            'username' => config('services.regru.api_user'),
            'password' => config('services.regru.api_password'),
            'output_format' => 'json',
        ];
        if ($params !== []) {
            $form += ['input_format' => 'json', 'input_data' => json_encode($params, JSON_UNESCAPED_UNICODE)];
        }
        try {
            $resp = Http::timeout((int) config('services.regru.timeout', 30))->asForm()
                ->post(rtrim((string) config('services.regru.api_url'), '/') . '/' . $fn, $form);
        } catch (\Throwable $e) {
            throw new RuntimeException("REG.API {$fn}: транспорт — " . $e->getMessage());
        }
        $json = $resp->json();
        if (!is_array($json)) {
            throw new RuntimeException("REG.API {$fn}: HTTP {$resp->status()}, не JSON");
        }
        if (($json['result'] ?? '') !== 'success') {
            throw new RuntimeException("REG.API {$fn}: " . trim(($json['error_code'] ?? '') . ' ' . ($json['error_text'] ?? 'ошибка')));
        }

        return (array) ($json['answer'] ?? []);
    }

    /**
     * Хостинг ISPmanager по id услуги: адрес панели, логин/пароль, IP отправки.
     *
     * @return array{url:string, host:string, login:string, pass:string, ip:string}
     */
    public function hosting(int $serviceId): array
    {
        if (isset($this->hostings[$serviceId])) {
            return $this->hostings[$serviceId];
        }
        $svc = $this->call('service/get_details', ['services' => [['service_id' => $serviceId]]])['services'][0] ?? [];
        $d = (array) ($svc['details'] ?? []);
        if (($svc['servtype'] ?? '') !== 'srv_hosting_ispmgr' || empty($d['server_hostname']) || empty($d['login'])) {
            throw new RuntimeException("REG.API: услуга {$serviceId} — не хостинг ISPmanager");
        }

        return $this->hostings[$serviceId] = [
            'url' => 'https://' . $d['server_hostname'] . ':1500/ispmgr',
            'host' => (string) $d['server_hostname'],
            'login' => (string) $d['login'],
            'pass' => (string) ($d['passwd'] ?? ''),
            'ip' => (string) ($svc['extdetails']['service_ip'] ?? ''),
        ];
    }

    /** Вызов ISPmanager хостинга. @return array<string,mixed> doc */
    public function isp(int $serviceId, string $func, array $params = []): array
    {
        $h = $this->hosting($serviceId);
        try {
            $resp = Http::timeout((int) config('services.regru.timeout', 30))->asForm()
                ->post($h['url'], ['authinfo' => $h['login'] . ':' . $h['pass'], 'out' => 'json', 'func' => $func] + $params);
        } catch (\Throwable $e) {
            throw new RuntimeException("ISPmanager {$func}: транспорт — " . $e->getMessage());
        }
        $doc = $resp->json('doc');
        if (!is_array($doc)) {
            throw new RuntimeException("ISPmanager {$func}: HTTP {$resp->status()}, не JSON");
        }
        if (isset($doc['error'])) {
            throw new RuntimeException("ISPmanager {$func}: " . self::value($doc['error']['msg'] ?? $doc['error']));
        }

        return $doc;
    }

    /** @return string[] почтовые домены хостинга */
    public function mailDomains(int $serviceId): array
    {
        return array_values(array_filter(array_map(
            static fn ($e) => mb_strtolower(self::value($e['name'] ?? '')),
            (array) ($this->isp($serviceId, 'emaildomain')['elem'] ?? []),
        )));
    }

    /** @return string[] локальные части ящиков домена */
    public function mailboxes(int $serviceId, string $domain): array
    {
        return self::localsOf((array) ($this->isp($serviceId, 'email')['elem'] ?? []), $domain);
    }

    public function createMailbox(int $serviceId, string $domain, string $local, string $password): void
    {
        $this->isp($serviceId, 'email.edit', [
            'sok' => 'ok', 'name' => $local, 'domainname' => $domain, 'passwd' => $password, 'confirm' => $password,
        ]);
    }

    public function dropMailbox(int $serviceId, string $domain, string $local): void
    {
        $this->isp($serviceId, 'email.delete', ['elid' => $local . '@' . $domain]);
    }

    /**
     * Почтовый домен на хостинге (DKIM 2048, DMARC; письма на несуществующие адреса — ошибкой, не тихим сбросом).
     *
     * @return array{created:bool, dkim:?string, dmarc:?string} значения TXT для DNS
     */
    public function ensureMailDomain(int $serviceId, string $domain): array
    {
        $created = false;
        if (!in_array(mb_strtolower($domain), $this->mailDomains($serviceId), true)) {
            $this->isp($serviceId, 'emaildomain.edit', [
                'sok' => 'ok', 'name' => $domain, 'ipsrc' => $this->hosting($serviceId)['ip'], 'defaction' => 'error',
                'dkim' => 'on', 'dkim_selector' => 'dkim', 'dkim_keylen' => '2048', 'dmarc' => 'on',
                'spamassassin' => 'off', 'secure' => 'off',
            ]);
            $created = true;
        }
        foreach ((array) ($this->isp($serviceId, 'emaildomain')['elem'] ?? []) as $e) {
            if (mb_strtolower(self::value($e['name'] ?? '')) === mb_strtolower($domain)) {
                return ['created' => $created, 'dkim' => self::txtOf(self::value($e['dkim'] ?? '')), 'dmarc' => self::txtOf(self::value($e['dmarc'] ?? ''))];
            }
        }

        throw new RuntimeException("ISPmanager: почтовый домен {$domain} не появился");
    }

    /**
     * DNS-записи почты у регистратора (домен на ns1/ns2.reg.ru): MX хостинга, SPF, DKIM, DMARC, mail.A.
     * Уже существующие записи (по типу и началу значения) не дублируются.
     *
     * @return array<string,string> запись => «добавлена» | «есть»
     */
    public function ensureMailDns(string $domain, string $ip, string $dkim, string $dmarc): array
    {
        $d = [['dname' => $domain]];
        $have = [];
        foreach ((array) ($this->call('zone/get_resource_records', ['domains' => $d])['domains'][0]['rrs'] ?? []) as $rr) {
            $have[] = $rr['subname'] . ' ' . $rr['rectype'] . ' ' . $rr['content'];
        }
        $want = [
            ['zone/add_mx', ['subdomain' => '@', 'mail_server' => 'mx1.hosting.reg.ru.', 'priority' => 10], '@ MX mx1.hosting.reg.ru.'],
            ['zone/add_mx', ['subdomain' => '@', 'mail_server' => 'mx2.hosting.reg.ru.', 'priority' => 15], '@ MX mx2.hosting.reg.ru.'],
            ['zone/add_txt', ['subdomain' => '@', 'text' => "v=spf1 include:_spf.hosting.reg.ru ip4:{$ip} ~all"], '@ TXT v=spf1'],
            ['zone/add_txt', ['subdomain' => 'dkim._domainkey', 'text' => $dkim], 'dkim._domainkey TXT v=DKIM1'],
            ['zone/add_txt', ['subdomain' => '_dmarc', 'text' => $dmarc], '_dmarc TXT v=DMARC1'],
            ['zone/add_alias', ['subdomain' => 'mail', 'ipaddr' => $ip], "mail A {$ip}"],
        ];
        $out = [];
        foreach ($want as [$fn, $params, $sig]) {
            foreach ($have as $h) {
                if (str_starts_with($h, $sig)) {
                    $out[$sig] = 'есть';
                    continue 2;
                }
            }
            $res = $this->call($fn, ['domains' => $d] + $params)['domains'][0] ?? [];
            if (($res['result'] ?? '') !== 'success') {
                throw new RuntimeException("REG.API {$fn} {$sig}: " . ($res['error_text'] ?? $res['error_code'] ?? 'ошибка'));
            }
            $out[$sig] = 'добавлена';
        }

        return $out;
    }

    /** Пароль ящика: буквы и цифры (без спецсимволов — ими ломается authinfo панели). */
    public static function password(int $len = 18): string
    {
        $sets = ['abcdefghijkmnpqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789'];
        $chars = array_map(static fn ($s) => $s[random_int(0, strlen($s) - 1)], $sets);
        $all = implode('', $sets);
        while (count($chars) < $len) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);

        return implode('', $chars);
    }

    /**
     * Локальные части ящиков домена из списка ISPmanager `email` (name = полный адрес).
     *
     * @param array<int,array<string,mixed>> $elems
     * @return string[]
     */
    public static function localsOf(array $elems, string $domain): array
    {
        $suffix = '@' . mb_strtolower($domain);
        $out = [];
        foreach ($elems as $e) {
            $addr = mb_strtolower(self::value($e['name'] ?? ''));
            if (str_ends_with($addr, $suffix)) {
                $out[] = substr($addr, 0, -strlen($suffix));
            }
        }

        return $out;
    }

    /** Значение TXT из строки ISPmanager вида `name. IN TXT "…"`. */
    public static function txtOf(string $record): ?string
    {
        return preg_match('/"([^"]+)"/', $record, $m) ? $m[1] : null;
    }

    /** Поле ISPmanager: строка или {"$": "…"}. */
    public static function value(mixed $v): string
    {
        return is_array($v) ? (string) ($v['$'] ?? '') : (string) $v;
    }
}
