<?php

namespace App\Services\Timeweb;

use Illuminate\Support\Facades\Http;

/**
 * Почта Timeweb Cloud (публичное API, https://api.timeweb.cloud, Bearer-токен; спецификация —
 * https://timeweb.cloud/api-docs-data/bundle.json). Ящики, смена пароля, пересылка входящих без копии,
 * сбор писем на несуществующие адреса домена. Подключено 23.11.2026 (решение владельца) — второй независимый
 * от Beget и «Рунити» поставщик ящиков.
 */
class TimewebMailApi
{
    public function isConfigured(): bool
    {
        return (string) config('services.timeweb.token', '') !== '';
    }

    /** @return string[] локальные части ящиков домена */
    public function mailboxes(string $domain): array
    {
        $out = [];
        $offset = 0;
        do {
            $r = $this->call('get', '/api/v1/mail/domains/' . rawurlencode($domain), ['limit' => 100, 'offset' => $offset]);
            $page = (array) ($r['mailboxes'] ?? []);
            foreach ($page as $m) {
                $name = (string) ($m['mailbox'] ?? '');
                if ($name !== '') {
                    $out[] = mb_strtolower(str_contains($name, '@') ? strstr($name, '@', true) : $name);
                }
            }
            $offset += count($page);
            $total = (int) ($r['meta']['total'] ?? 0);
        } while ($page !== [] && $offset < $total);

        return $out;
    }

    public function createMailbox(string $domain, string $local, string $password): void
    {
        $this->call('post', '/api/v1/mail/domains/' . rawurlencode($domain), ['mailbox' => $local, 'password' => $password]);
    }

    public function dropMailbox(string $domain, string $local): void
    {
        $this->call('delete', $this->boxPath($domain, $local));
    }

    public function setPassword(string $domain, string $local, string $password): void
    {
        $this->call('patch', $this->boxPath($domain, $local), ['password' => $password]);
    }

    /** Вся входящая почта ящика — на $target, копию ящик не хранит. */
    public function forwardAndDelete(string $domain, string $local, string $target): void
    {
        $this->call('patch', $this->boxPath($domain, $local), ['forwarding_incoming' => [
            'is_enabled' => true, 'is_delete_messages' => true, 'incoming_list' => [$target],
        ]]);
    }

    /** Письма на несуществующие адреса домена — в $email. */
    public function setCatchAll(string $domain, string $email): void
    {
        $this->call('patch', '/api/v1/mail/domains/' . rawurlencode($domain) . '/info', ['email' => $email]);
    }

    /** @return array<string,mixed> */
    public function domainInfo(string $domain): array
    {
        return (array) ($this->call('get', '/api/v1/mail/domains/' . rawurlencode($domain) . '/info')['domain_info'] ?? []);
    }

    private function boxPath(string $domain, string $local): string
    {
        return '/api/v1/mail/domains/' . rawurlencode($domain) . '/mailboxes/' . rawurlencode($local);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function call(string $method, string $path, array $data = []): array
    {
        $req = Http::withToken((string) config('services.timeweb.token'))->acceptJson()
            ->timeout((int) config('services.timeweb.timeout', 30));
        $url = rtrim((string) config('services.timeweb.api_url', 'https://api.timeweb.cloud'), '/') . $path;
        $res = match ($method) {
            'get' => $req->get($url, $data),
            'delete' => $req->delete($url),
            default => $req->{$method}($url, $data),
        };
        if (!$res->successful()) {
            $msg = (string) ($res->json('message') ?? $res->json('error_code') ?? mb_substr($res->body(), 0, 300));
            throw new \RuntimeException("Timeweb {$method} {$path}: HTTP {$res->status()} {$msg}");
        }

        return (array) ($res->json() ?? []);
    }
}
