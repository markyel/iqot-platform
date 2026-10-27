<?php

namespace App\Console\Commands;

use App\Services\Regru\RegruApi;
use Illuminate\Console\Command;

/**
 * Подключение домена к хостингу REG.RU пула ящиков: почтовый домен в ISPmanager (DKIM 2048, DMARC p=none, несуществующим
 * адресам — ошибка) и DNS-записи у регистратора (MX хостинга, SPF с IP сервера, DKIM, DMARC, mail.A), если домен
 * обслуживают DNS REG.RU. Ящики дальше создаёт emails:replenish-mailboxes (засев нового домена).
 */
class RegruMailDomain extends Command
{
    protected $signature = 'emails:regru-domain {domain : Домен}
        {--hosting= : id услуги хостинга (по умолчанию — первый из REGRU_POOL_HOSTING_IDS)}
        {--no-dns : Только почтовый домен, без записей DNS}';

    protected $description = 'Подключить домен к хостингу REG.RU пула ящиков: почтовый домен с DKIM/DMARC и DNS-записи';

    public function handle(RegruApi $api): int
    {
        $domain = mb_strtolower(trim((string) $this->argument('domain')));
        $sid = (int) ($this->option('hosting') ?: (config('services.regru.pool_hosting_ids')[0] ?? 0));
        if (!$api->isConfigured() || $sid <= 0) {
            $this->error('Не заданы REGRU_API_USER / REGRU_API_PASSWORD или хостинг (--hosting / REGRU_POOL_HOSTING_IDS).');

            return self::FAILURE;
        }
        if (in_array($domain, array_map('mb_strtolower', (array) config('services.regru.exclude_domains', [])), true)) {
            $this->error("{$domain} в REGRU_EXCLUDE_DOMAINS — не подключаем.");

            return self::FAILURE;
        }

        try {
            $h = $api->hosting($sid);
            $this->line("Хостинг {$sid}: {$h['login']}@{$h['host']}, IP {$h['ip']}");
            $md = $api->ensureMailDomain($sid, $domain);
            $this->info('Почтовый домен: ' . ($md['created'] ? 'создан' : 'уже был') . ', DKIM ' . ($md['dkim'] ? 'есть' : 'нет') . ', DMARC ' . ($md['dmarc'] ? 'есть' : 'нет'));
            if ($this->option('no-dns')) {
                return self::SUCCESS;
            }
            if (!$md['dkim'] || !$md['dmarc']) {
                $this->error('Нет значений DKIM/DMARC — DNS не трогаю.');

                return self::FAILURE;
            }
            try {
                $api->call('zone/nop', ['domains' => [['dname' => $domain]]]);
            } catch (\Throwable $e) {
                $this->warn("DNS домена не у REG.RU ({$e->getMessage()}) — пропишите вручную:");
                $this->line("  MX 10 mx1.hosting.reg.ru, MX 15 mx2.hosting.reg.ru");
                $this->line("  TXT @ v=spf1 include:_spf.hosting.reg.ru ip4:{$h['ip']} ~all");
                $this->line("  TXT dkim._domainkey {$md['dkim']}");
                $this->line("  TXT _dmarc {$md['dmarc']}");

                return self::SUCCESS;
            }
            foreach ($api->ensureMailDns($domain, $h['ip'], $md['dkim'], $md['dmarc']) as $rec => $state) {
                $this->line("  {$rec}: {$state}");
            }
            $this->info('Готово. Записи DNS REG.RU публикует за минуты; ящики создаст emails:replenish-mailboxes (засев).');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
