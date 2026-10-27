<?php

namespace App\Services\Senders\Mailboxes;

use App\Services\Regru\RegruApi;
use App\Support\Senders\SmtpServerCaps;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Хостинг REG.RU (ISPmanager): домены пула — почтовые домены хостингов из services.regru.pool_hosting_ids (рабочие
 * хостинги туда не входят: на sm21 живёт partleader.ru). Почтовый домен заводится заранее (emails:regru-domain), здесь
 * только ящики. Отправка — через сервер хостинга (его IP в SPF), у аккаунта суточный лимит писем (SmtpServerCaps):
 * ящиков держим не больше, чем лимит / потолок прогрева ящика, а без заданного лимита сервера новых не создаём вовсе.
 */
class RegruMailboxProvider implements MailboxProvider
{
    /** @var array<string,int>|null домен => id услуги хостинга */
    private ?array $domains = null;

    public function __construct(private readonly RegruApi $api)
    {
    }

    public function name(): string
    {
        return 'regru';
    }

    public function isConfigured(): bool
    {
        return $this->api->isConfigured() && $this->hostingIds() !== [];
    }

    public function manages(string $domain): bool
    {
        return isset($this->domains()[mb_strtolower($domain)]);
    }

    public function seedDomains(): array
    {
        return array_keys($this->domains());
    }

    public function mailboxes(string $domain): array
    {
        return $this->api->mailboxes($this->service($domain), $domain);
    }

    public function createMailbox(string $domain, string $local, string $password): void
    {
        $this->api->createMailbox($this->service($domain), $domain, $local, $password);
    }

    public function dropMailbox(string $domain, string $local): void
    {
        $this->api->dropMailbox($this->service($domain), $domain, $local);
    }

    public function password(): string
    {
        return RegruApi::password();
    }

    public function smtp(string $domain): string
    {
        return $this->host($domain) . ':465';
    }

    public function imap(string $domain): string
    {
        return $this->host($domain) . ':993';
    }

    public function roomLeft(string $domain): ?int
    {
        $host = $this->host($domain);
        $cap = SmtpServerCaps::caps()[mb_strtolower($host)] ?? 0;

        return self::room($cap, (int) config('services.email_warmup.cap', 250), DB::connection('reports')->table('senders')
            ->whereRaw('LOWER(smtp_server) = ?', [mb_strtolower($host)])->where('is_active', 1)->count());
    }

    /** Место под ящики аккаунта: лимит / потолок ящика (не меньше 1) − заведённые; без лимита — 0 (не создаём). */
    public static function room(int $accountCap, int $perMailboxCap, int $existing): int
    {
        if ($accountCap <= 0) {
            return 0;
        }

        return max(0, max(1, intdiv($accountCap, max(1, $perMailboxCap))) - $existing);
    }

    public function account(string $domain): string
    {
        return 'regru:' . $this->host($domain);
    }

    /** @return int[] */
    private function hostingIds(): array
    {
        return array_values(array_filter(array_map('intval', (array) config('services.regru.pool_hosting_ids', []))));
    }

    /** @return array<string,int> */
    private function domains(): array
    {
        if ($this->domains === null) {
            $this->domains = [];
            $exclude = array_map('mb_strtolower', (array) config('services.regru.exclude_domains', []));
            foreach ($this->hostingIds() as $sid) {
                try {
                    foreach ($this->api->mailDomains($sid) as $d) {
                        if (!in_array($d, $exclude, true)) {
                            $this->domains[$d] = $sid;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('RegruMailboxProvider: хостинг недоступен', ['service_id' => $sid, 'error' => $e->getMessage()]);
                }
            }
        }

        return $this->domains;
    }

    private function service(string $domain): int
    {
        return $this->domains()[mb_strtolower($domain)] ?? throw new \RuntimeException("REG.RU: домен {$domain} не на хостинге пула");
    }

    private function host(string $domain): string
    {
        return $this->api->hosting($this->service($domain))['host'];
    }
}
