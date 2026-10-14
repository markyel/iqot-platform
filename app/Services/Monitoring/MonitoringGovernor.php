<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\DB;

/**
 * Регулятор суточных бюджетов мониторинга (docs/monitoring-f0-spec.md §6).
 *
 * reserve() — атомарно увеличивает счётчик ресурса в mp_budget_ledger и возвращает
 * false, если суточная квота исчерпана (вызывающий откладывает работу).
 * record() — учёт фактического расхода (стоимость LLM и т. п.).
 * Квоты общие на все программы (config monitoring.quotas); доля программы — Ф1.
 */
class MonitoringGovernor
{
    public const RESOURCES = ['yandex_query', 'fetch_http', 'fetch_headless', 'llm_call', 'email'];

    public function reserve(string $resource, int $units = 1, int $programId = 0): bool
    {
        $quota = (int) (config("monitoring.quotas.{$resource}") ?? 0);
        $day = now()->toDateString();

        if ($quota > 0) {
            $used = (int) DB::table('mp_budget_ledger')
                ->where('day', $day)->where('resource', $resource)
                ->sum('units');
            if ($used + $units > $quota) {
                return false;
            }
        }

        $this->bump($day, $programId, $resource, $units, 0.0);

        return true;
    }

    /** Есть ли ещё суточный бюджет на LLM ($). */
    public function hasLlmBudget(): bool
    {
        $limit = (float) config('monitoring.quotas.llm_usd', 0);
        if ($limit <= 0) {
            return true;
        }
        $spent = (float) DB::table('mp_budget_ledger')
            ->where('day', now()->toDateString())->where('resource', 'llm_call')
            ->sum('cost_usd');

        return $spent < $limit;
    }

    public function record(string $resource, int $units, float $costUsd, int $programId = 0, float $costRub = 0.0): void
    {
        $this->bump(now()->toDateString(), $programId, $resource, $units, $costUsd, $costRub);
    }

    /** @return array<string,array{units:int,cost_usd:float}> расход за день по ресурсам */
    public function today(): array
    {
        $out = [];
        foreach (DB::table('mp_budget_ledger')->where('day', now()->toDateString())
            ->selectRaw('resource, SUM(units) u, SUM(cost_usd) c')->groupBy('resource')->get() as $r) {
            $out[$r->resource] = ['units' => (int) $r->u, 'cost_usd' => (float) $r->c];
        }

        return $out;
    }

    private function bump(string $day, int $programId, string $resource, int $units, float $costUsd, float $costRub = 0.0): void
    {
        DB::statement(
            'INSERT INTO mp_budget_ledger (day, program_id, resource, units, cost_usd, cost_rub, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE units = units + VALUES(units), cost_usd = cost_usd + VALUES(cost_usd),
                 cost_rub = cost_rub + VALUES(cost_rub), updated_at = NOW()',
            [$day, $programId, $resource, $units, $costUsd, $costRub]
        );
    }
}
