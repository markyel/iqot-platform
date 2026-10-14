<?php

namespace App\Services\Monitoring;

use App\Models\User;

/**
 * Лимиты мониторинга из тарифного плана пользователя (docs/monitoring-design.md §11a).
 */
class MonitoringPlanLimits
{
    /** @return array{available:bool, included:int, max:int, price:float, kp_multiplier:float, plan:?string} */
    public function forUser(User $user): array
    {
        $plan = $user->getActiveTariff()?->tariffPlan;
        $max = (int) ($plan->monitoring_items_max ?? 0);

        return [
            'available' => $plan !== null && $max > 0,
            'included' => (int) ($plan->monitoring_items_included ?? 0),
            'max' => $max,
            'price' => (float) ($plan->monitoring_price_per_item ?? 0),
            'kp_multiplier' => (float) ($plan->monitoring_kp_multiplier ?? 3),
            'plan' => $plan?->code,
        ];
    }
}
