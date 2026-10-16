<?php

namespace App\Services\AliasExp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Почтовое API Beget (https://beget.com/ru/kb/api/funkczii-dlya-raboty-s-pochtoj) для алгоритма
 * «ящик на батч» (docs/alias-mailbox-design.md). Ключи — BEGET_API_LOGIN / BEGET_API_PASSWORD в .env.
 * Ошибка API или транспорта → RuntimeException с кодом/текстом Beget (без ключей в сообщении).
 */
class BegetMailApi
{
    public function isConfigured(): bool
    {
        return (string) config('services.beget_api.login') !== '' && (string) config('services.beget_api.password') !== '';
    }

    /** @return array<int,array{mailbox:string,domain:string,spam_filter_status:mixed,forward_mail_status:string}> */
    public function listMailboxes(string $domain): array
    {
        return (array) $this->call('getMailboxList', ['domain' => $domain]);
    }

    public function mailboxExists(string $domain, string $mailbox): bool
    {
        foreach ($this->listMailboxes($domain) as $m) {
            if (mb_strtolower((string) ($m['mailbox'] ?? '')) === mb_strtolower($mailbox)) {
                return true;
            }
        }

        return false;
    }

    public function createMailbox(string $domain, string $mailbox, string $password): void
    {
        $this->call('createMailbox', ['domain' => $domain, 'mailbox' => $mailbox, 'mailbox_password' => $password]);
    }

    public function dropMailbox(string $domain, string $mailbox): void
    {
        $this->call('dropMailbox', ['domain' => $domain, 'mailbox' => $mailbox]);
    }

    /**
     * Вся входящая почта ящика уходит на $target и в самом ящике не хранится.
     * forward_mail_status Beget принимает только вместе со spam_filter_status и spam_filter (иначе INVALID_DATA).
     */
    public function forwardAndDelete(string $domain, string $mailbox, string $target): void
    {
        $this->call('forwardListAddMailbox', ['domain' => $domain, 'mailbox' => $mailbox, 'forward_mailbox' => $target]);
        $this->call('changeMailboxSettings', [
            'domain' => $domain, 'mailbox' => $mailbox,
            'spam_filter_status' => 1, 'spam_filter' => 5, 'forward_mail_status' => 'forward_and_delete',
        ]);
    }

    /** @return string[] адреса пересылки ящика */
    public function forwards(string $domain, string $mailbox): array
    {
        return array_values(array_filter(array_map(
            static fn ($r) => (string) ($r['forward_mailbox'] ?? ''),
            (array) $this->call('forwardListShow', ['domain' => $domain, 'mailbox' => $mailbox]),
        )));
    }

    /** Catch-all: письма на несуществующие адреса домена (в т.ч. удалённые ящики) — в $mailbox. */
    public function setCatchAll(string $domain, string $mailbox): void
    {
        $this->call('setDomainMail', ['domain' => $domain, 'domain_mailbox' => $mailbox]);
    }

    private function call(string $method, array $input): mixed
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Beget API: не заданы BEGET_API_LOGIN / BEGET_API_PASSWORD');
        }
        $url = rtrim((string) config('services.beget_api.url'), '/') . '/mail/' . $method;
        try {
            $resp = Http::timeout((int) config('services.beget_api.timeout', 30))->asForm()->post($url, [
                'login' => config('services.beget_api.login'),
                'passwd' => config('services.beget_api.password'),
                'input_format' => 'json',
                'output_format' => 'json',
                'input_data' => json_encode($input, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException("Beget API {$method}: транспорт — " . $e->getMessage());
        }
        $json = $resp->json();
        if (!is_array($json)) {
            throw new RuntimeException("Beget API {$method}: HTTP {$resp->status()}, не JSON");
        }
        // Два уровня статуса: запрос (ключи, лимиты) и ответ метода.
        if (($json['status'] ?? '') !== 'success') {
            throw new RuntimeException("Beget API {$method}: " . $this->errorText($json));
        }
        $answer = $json['answer'] ?? [];
        if (($answer['status'] ?? '') !== 'success') {
            throw new RuntimeException("Beget API {$method}: " . $this->errorText($answer));
        }

        return $answer['result'] ?? null;
    }

    private function errorText(array $node): string
    {
        $parts = [];
        foreach ((array) ($node['errors'] ?? []) as $e) {
            $parts[] = trim(($e['error_code'] ?? '') . ' ' . ($e['error_text'] ?? ''));
        }
        if ($parts === [] && isset($node['error_text'])) {
            $parts[] = trim(($node['error_code'] ?? '') . ' ' . $node['error_text']);
        }

        return $parts !== [] ? implode('; ', $parts) : 'неизвестная ошибка';
    }
}
