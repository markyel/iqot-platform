<?php

namespace App\Console\Commands\Monitoring;

use App\Services\Monitoring\Billing\MonitoringBilling;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Тарификация мониторинга (docs/monitoring-design.md §11a).
 *   accrue [--date=Y-m-d]        — начисление за сутки (по умолчанию сегодня МСК; планировщик — 23:50 МСК)
 *   backfill --from=Y-m-d        — начисление за прошедшие дни по сегодня включительно
 *   charge [--force]             — списание накопленного (раз в 7 дн. и в конце периода подписки; 00:20 МСК)
 *   status                       — начислено/списано по программам
 */
class BillingCommand extends Command
{
    protected $signature = 'monitoring:billing {action} {--date=} {--from=} {--force}';

    protected $description = 'Мониторинг цен: начисление за покрытые позиции и списание с баланса';

    public function handle(MonitoringBilling $billing): int
    {
        if (!config('monitoring.billing.enabled', true) && !$this->option('force')) {
            $this->warn('Тарификация мониторинга выключена (MONITORING_BILLING_ENABLED=false).');

            return self::SUCCESS;
        }
        $tz = (string) config('monitoring.billing.timezone', 'Europe/Moscow');

        switch ($this->argument('action')) {
            case 'accrue':
                $day = $this->option('date') ? CarbonImmutable::parse((string) $this->option('date'), $tz) : CarbonImmutable::now($tz);
                $this->printAccrual($day, $billing->accrue($day));
                break;
            case 'backfill':
                $from = CarbonImmutable::parse((string) ($this->option('from') ?: config('monitoring.billing.start_date')), $tz)->startOfDay();
                for ($d = $from; $d->lte(CarbonImmutable::now($tz)->startOfDay()); $d = $d->addDay()) {
                    $this->printAccrual($d, $billing->accrue($d));
                }
                break;
            case 'charge':
                $lines = $billing->charge((bool) $this->option('force'));
                foreach ($lines ?: ['Списывать нечего.'] as $l) {
                    $this->line($l);
                }
                break;
            case 'status':
                $this->table(['программа', 'дней', 'начислено', 'списано', 'не списано'],
                    DB::table('mp_billing_days as d')->join('monitoring_programs as p', 'p.id', '=', 'd.program_id')
                        ->selectRaw('p.id, p.name, COUNT(*) days, SUM(d.amount) total, SUM(CASE WHEN d.charge_id IS NOT NULL THEN d.amount ELSE 0 END) charged')
                        ->groupBy('p.id', 'p.name')->get()
                        ->map(fn ($r) => ["#{$r->id} {$r->name}", $r->days, $r->total, $r->charged, round($r->total - $r->charged, 2)])->all());
                break;
            default:
                $this->error('Действия: accrue [--date=] | backfill --from= | charge [--force] | status');

                return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function printAccrual(CarbonImmutable $day, array $rows): void
    {
        foreach ($rows as $r) {
            $this->line(sprintf('%s · программа %d: web %d, с КП %d, в плане %d → %s ₽ (ставка %s, ×%s)', $day->toDateString(), $r['program_id'],
                $r['covered_web'], $r['covered_kp'], $r['included_web'] + $r['included_kp'], number_format($r['amount'], 2, ',', ' '), $r['rate'], $r['kp_multiplier']));
        }
        if ($rows === []) {
            $this->line($day->toDateString() . ': начислять нечего');
        }
    }
}
