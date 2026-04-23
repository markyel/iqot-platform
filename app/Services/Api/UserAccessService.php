<?php

namespace App\Services\Api;

use App\Models\User;
use App\Models\UserTariff;
use Illuminate\Support\Facades\Cache;

/**
 * Определяет, есть ли у пользователя доступ к API (§9.1).
 *
 * Источники доступа:
 *  - Фича `api_access=true` в активном тарифе пользователя (tariff_plans.features JSON).
 *  - Активный addon «API доступ» (зарезервировано на будущее; механизм addons пока не реализован).
 *
 * Кэшируется в память процесса для скорости внутри запроса.
 */
class UserAccessService
{
    /** @var array<int, bool> */
    private array $cache = [];

    public function hasApiAccess(int $userId): bool
    {
        if (array_key_exists($userId, $this->cache)) {
            return $this->cache[$userId];
        }

        $hasTariffFeature = $this->hasTariffFeature($userId);
        // Addons пока не реализованы; задел:
        // $hasAddon = $this->hasActiveAddon($userId);

        return $this->cache[$userId] = $hasTariffFeature;
    }

    private function hasTariffFeature(int $userId): bool
    {
        $tariff = UserTariff::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->with('tariffPlan:id,features')
            ->first();

        if (!$tariff || !$tariff->tariffPlan) {
            return false;
        }

        $features = $tariff->tariffPlan->features;
        if (is_string($features)) {
            $features = json_decode($features, true) ?: [];
        }

        return is_array($features) && !empty($features['api_access']);
    }
}
