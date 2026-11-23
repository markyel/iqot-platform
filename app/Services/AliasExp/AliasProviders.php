<?php

namespace App\Services\AliasExp;

use App\Services\Regru\RegruApi;
use App\Services\Senders\Mailboxes\AliasMailboxProvider;
use App\Services\Senders\Mailboxes\BegetMailboxProvider;
use App\Services\Senders\Mailboxes\RegruMailboxProvider;
use App\Services\Senders\Mailboxes\TimewebMailboxProvider;
use App\Services\Timeweb\TimewebMailApi;

/**
 * Поставщик ящиков для домена ветки «ящик на батч»: alias_domains.provider (beget | regru | timeweb). Домен обслуживает один
 * поставщик; detect() определяет его при добавлении домена.
 */
class AliasProviders
{
    public const NAMES = ['regru', 'timeweb', 'beget'];

    /** @var array<string,AliasMailboxProvider> */
    private array $instances = [];

    public function __construct(private readonly BegetMailApi $beget, private readonly RegruApi $regru, private readonly TimewebMailApi $timeweb)
    {
    }

    public function get(string $name): AliasMailboxProvider
    {
        return $this->instances[$name] ??= match ($name) {
            'regru' => RegruMailboxProvider::forAlias($this->regru),
            'beget' => new BegetMailboxProvider($this->beget),
            'timeweb' => TimewebMailboxProvider::forAlias($this->timeweb),
            default => throw new \InvalidArgumentException("Неизвестный поставщик ящиков: {$name}"),
        };
    }

    public function forDomain(object $dom): AliasMailboxProvider
    {
        return $this->get((string) ($dom->provider ?? 'beget') ?: 'beget');
    }

    /** Кто из настроенных поставщиков обслуживает домен; null — никто (или API недоступно). */
    public function detect(string $domain): ?string
    {
        foreach (self::NAMES as $name) {
            try {
                $p = $this->get($name);
                if ($p->isConfigured() && $p->manages($domain)) {
                    return $name;
                }
            } catch (\Throwable) {
                // поставщик недоступен — пробуем следующего
            }
        }

        return null;
    }
}
