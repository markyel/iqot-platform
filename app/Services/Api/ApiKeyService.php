<?php

namespace App\Services\Api;

use App\Models\Api\ApiClient;
use App\Models\Api\ApiKey;
use Illuminate\Support\Facades\DB;

/**
 * Генерация, валидация и управление API-ключами (§9.2 / §9.4 / §9.5).
 *
 * Формат ключа: "iqot_live_" + 48 hex chars (24 random bytes).
 * Полный ключ возвращается клиенту один раз при генерации. В БД хранится SHA-256.
 */
class ApiKeyService
{
    public const PREFIX_LIVE = 'iqot_live_';
    public const MAX_ACTIVE_KEYS_PER_CLIENT = 3;
    public const REVOKED_GRACE_DAYS = 30;

    /**
     * Создаёт новый API-ключ для api_client.
     *
     * @param ApiClient $client
     * @param string $name человекочитаемое имя ("ERP Production")
     * @param array<string>|null $ipWhitelist список IP/CIDR
     * @return array{plain_key:string,record:ApiKey}
     * @throws \RuntimeException при превышении лимита ключей
     */
    public function generate(ApiClient $client, string $name, ?array $ipWhitelist = null): array
    {
        $activeCount = $client->activeKeys()->count();
        if ($activeCount >= self::MAX_ACTIVE_KEYS_PER_CLIENT) {
            throw new \RuntimeException('api_key_limit_reached');
        }

        $random = bin2hex(random_bytes(24)); // 48 hex chars
        $plainKey = self::PREFIX_LIVE . $random;

        $record = DB::transaction(function () use ($client, $name, $ipWhitelist, $plainKey) {
            return ApiKey::create([
                'api_client_id' => $client->id,
                'key_hash' => hash('sha256', $plainKey),
                'key_prefix' => substr($plainKey, 0, 14),              // "iqot_live_XXXX"
                'key_last4'  => substr($plainKey, -4),
                'name' => $name,
                'ip_whitelist' => $ipWhitelist,
                'request_count' => 0,
            ]);
        });

        return ['plain_key' => $plainKey, 'record' => $record];
    }

    /**
     * Ищет ключ по переданной строке Bearer. Возвращает запись или null.
     * Не валидирует ip_whitelist и не проверяет api_client.is_active — это делает middleware.
     */
    public function lookup(string $plainKey): ?ApiKey
    {
        if (!str_starts_with($plainKey, self::PREFIX_LIVE)) {
            return null;
        }
        $hash = hash('sha256', $plainKey);
        return ApiKey::query()->where('key_hash', $hash)->first();
    }

    public function isRevokedWithinGrace(ApiKey $key): bool
    {
        if ($key->revoked_at === null) {
            return false;
        }
        return $key->revoked_at->gt(now()->subDays(self::REVOKED_GRACE_DAYS));
    }

    public function revoke(ApiKey $key): void
    {
        if ($key->revoked_at === null) {
            $key->revoked_at = now();
            $key->save();
        }
    }

    public function touchUsage(ApiKey $key, string $ip): void
    {
        // Быстрый UPDATE без refresh модели.
        ApiKey::query()->where('id', $key->id)->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'request_count' => DB::raw('request_count + 1'),
        ]);
    }

    /**
     * Проверка IP против whitelist (один IP или список CIDR/IP).
     */
    public function ipMatchesWhitelist(?array $whitelist, string $ip): bool
    {
        if (empty($whitelist)) {
            return true;
        }
        foreach ($whitelist as $entry) {
            if ($this->ipInEntry($ip, (string) $entry)) {
                return true;
            }
        }
        return false;
    }

    private function ipInEntry(string $ip, string $entry): bool
    {
        if (!str_contains($entry, '/')) {
            return $ip === $entry;
        }
        [$subnet, $bits] = explode('/', $entry, 2);
        $bits = (int) $bits;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        if ($bits <= 0 || $bits > 32) {
            return false;
        }
        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
