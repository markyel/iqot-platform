<?php

namespace App\Services\Monitoring\Billing;

use App\Models\Monitoring\MonitoringProgram;
use App\Models\User;
use App\Services\InvoiceTrackingService;
use App\Services\Monitoring\MonitoringPlanLimits;
use App\Services\Ops\OpsAlerter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Тарификация мониторинга (docs/monitoring-design.md §11a, решения владельца 16.10.2026).
 *
 * Единица — покрытая позиция (свежая цена в пределах target_freshness_days программы). Позиция со свежим КП
 * от поставщика по запросу этой программы (mp_asks.program_id, в пределах email_refresh_days) — ставка ×kp_multiplier,
 * даже если есть цены с сайтов. Начисление — за сутки: (web × ставка + КП × ставка × множитель) / 30, минус
 * включённые в план позиции (monitoring_items_included, общие на все программы клиента; сперва в счёт web).
 * Списание — накопленные дни, раз в charge_every_days (7) и в конце периода подписки. Нет денег — предупреждение,
 * через grace_days — программы клиента на паузу (paused_reason = billing); оплатил — списание и снятие паузы.
 */
class MonitoringBilling
{
    public function __construct(
        private readonly MonitoringPlanLimits $limits,
        private readonly OpsAlerter $alerter,
    ) {
    }

    private function tz(): string
    {
        return (string) config('monitoring.billing.timezone', 'Europe/Moscow');
    }

    /**
     * Начисление за сутки $day (дата МСК). Идемпотентно: незаписанные в списание дни пересчитываются.
     *
     * @return array<int,array<string,mixed>> строки по программам
     */
    public function accrue(CarbonImmutable $day): array
    {
        $tz = $this->tz();
        $day = CarbonImmutable::parse($day->toDateString(), $tz)->startOfDay();
        $start = CarbonImmutable::parse((string) config('monitoring.billing.start_date', '2026-10-15'), $tz)->startOfDay();
        if ($day->lt($start) || $day->gt(CarbonImmutable::now($tz)->startOfDay())) {
            return [];
        }
        $dayEnd = $day->endOfDay()->utc();
        $live = $day->isSameDay(CarbonImmutable::now($tz));

        $programs = MonitoringProgram::query()->where('status', 'active')->where('created_at', '<=', $dayEnd)
            ->orderBy('user_id')->orderBy('id')->get();
        $out = [];
        foreach ($programs->groupBy('user_id') as $userId => $list) {
            $user = User::find($userId);
            if (!$user) {
                continue;
            }
            $plan = $this->limits->forUser($user);
            $rate = round((float) $plan['price'], 2);
            $mult = round((float) $plan['kp_multiplier'], 2);
            $left = max(0, (int) $plan['included']);
            foreach ($list as $p) {
                $existing = DB::table('mp_billing_days')->where('program_id', $p->id)->where('day', $day->toDateString())->first();
                if ($existing && $existing->charge_id) {
                    $left = max(0, $left - (int) $existing->included_web - (int) $existing->included_kp);
                    continue;
                }
                [$web, $kp] = $this->coveredCounts($p, $dayEnd, $live);
                $incWeb = min($web, $left);
                $left -= $incWeb;
                $incKp = min($kp, $left);
                $left -= $incKp;
                $amount = round((($web - $incWeb) * $rate + ($kp - $incKp) * $rate * $mult) / 30, 2);
                $row = [
                    'user_id' => $userId, 'covered_web' => $web, 'covered_kp' => $kp, 'included_web' => $incWeb, 'included_kp' => $incKp,
                    'rate' => $rate, 'kp_multiplier' => $mult, 'amount' => $amount, 'updated_at' => now(),
                ];
                DB::table('mp_billing_days')->updateOrInsert(['program_id' => $p->id, 'day' => $day->toDateString()], $row + ['created_at' => now()]);
                $out[] = ['program_id' => $p->id] + $row;
            }
        }

        return $out;
    }

    /**
     * Покрытые позиции на конец суток: [без свежего КП, со свежим КП программы].
     * Текущие сутки — по program_items.last_priced_at (то же, что items_covered и API). Прошедшие — по истории
     * наблюдений цен (статус ok) и КП в окне свежести; ограничения «узких» позиций (ItemScope) здесь не учитываются.
     *
     * @return array{0:int,1:int}
     */
    private function coveredCounts(MonitoringProgram $p, CarbonImmutable $dayEnd, bool $live): array
    {
        $fresh = $dayEnd->subDays(max(1, (int) $p->target_freshness_days))->toDateTimeString();
        $kpFrom = $dayEnd->subDays(max(1, (int) ($p->email_refresh_days ?: 60)))->toDateTimeString();
        $end = $dayEnd->toDateTimeString();
        $kpExists = "EXISTS (SELECT 1 FROM mp_kp_offers k JOIN mp_asks a ON a.id = k.ask_id
            WHERE a.program_id = pi.program_id AND k.product_id = pi.product_id AND k.received_at BETWEEN ? AND ?)";

        if ($live) {
            $covered = "pi.last_priced_at >= ?";
            $bind = [now()->subDays(max(1, (int) $p->target_freshness_days))->toDateTimeString()];
        } else {
            $covered = "(EXISTS (SELECT 1 FROM mp_price_observations o JOIN mp_listings l ON l.id = o.listing_id AND l.bench = 0
                    WHERE o.product_id = pi.product_id AND o.status = 'ok' AND o.price IS NOT NULL AND o.observed_at BETWEEN ? AND ?)
                OR EXISTS (SELECT 1 FROM mp_kp_offers k2 JOIN mp_asks a2 ON a2.id = k2.ask_id
                    WHERE a2.program_id = pi.program_id AND k2.product_id = pi.product_id AND k2.received_at BETWEEN ? AND ?))";
            $bind = [$fresh, $end, $fresh, $end];
        }
        $r = DB::selectOne("SELECT COUNT(*) covered, SUM({$kpExists}) kp FROM program_items pi
            WHERE pi.program_id = ? AND pi.active = 1 AND pi.product_id IS NOT NULL AND pi.created_at <= ? AND {$covered}",
            array_merge([$kpFrom, $end, $p->id, $end], $bind));
        $kp = (int) ($r->kp ?? 0);

        return [max(0, (int) $r->covered - $kp), $kp];
    }

    /**
     * Списание накопленных дней. Срок — самый старый несписанный день старше charge_every_days, либо период
     * подписки кончается в ближайшие сутки (расчётный период совпадает с подпиской), либо $force.
     *
     * @return array<int,string> строки отчёта
     */
    public function charge(bool $force = false): array
    {
        $tz = $this->tz();
        $today = CarbonImmutable::now($tz)->toDateString();
        $every = max(1, (int) config('monitoring.billing.charge_every_days', 7));
        $report = [];
        $userIds = DB::table('mp_billing_days')->whereNull('charge_id')->where('day', '<', $today)->distinct()->pluck('user_id');
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (!$user) {
                continue;
            }
            $days = DB::table('mp_billing_days')->where('user_id', $userId)->whereNull('charge_id')->where('day', '<', $today)
                ->orderBy('day')->get();
            $oldest = CarbonImmutable::parse((string) $days->first()->day, $tz);
            $tariff = $user->getActiveTariff();
            $periodEnds = $tariff?->expires_at && $tariff->expires_at->lte(now()->addDay());
            if (!$force && !$periodEnds && $oldest->gt(CarbonImmutable::now($tz)->startOfDay()->subDays($every))) {
                continue;
            }
            $total = round((float) $days->sum('amount'), 2);
            $programs = MonitoringProgram::withTrashed()->whereIn('id', $days->pluck('program_id')->unique())->get()->keyBy('id');

            if ($total > 0 && $user->available_balance < $total) {
                $report[] = $this->insufficient($user, $programs, $total);
                continue;
            }
            foreach ($days->groupBy('program_id') as $programId => $rows) {
                $this->chargeProgram($user, (int) $programId, $rows, $programs[$programId] ?? null);
            }
            $this->clearWarning($programs);
            $report[] = sprintf('user %d: списано %s ₽ за %d дн. (%d программ)', $userId, number_format($total, 2, ',', ' '),
                $days->pluck('day')->unique()->count(), $days->pluck('program_id')->unique()->count());
        }

        return $report;
    }

    private function chargeProgram(User $user, int $programId, $rows, ?MonitoringProgram $program): void
    {
        $amount = round((float) $rows->sum('amount'), 2);
        $from = (string) $rows->min('day');
        $to = (string) $rows->max('day');
        $detail = [
            'program' => $program?->name, 'rate' => (float) $rows->last()->rate, 'kp_multiplier' => (float) $rows->last()->kp_multiplier,
            'days' => $rows->map(fn ($r) => ['day' => (string) $r->day, 'web' => (int) $r->covered_web, 'kp' => (int) $r->covered_kp,
                'included' => (int) $r->included_web + (int) $r->included_kp, 'amount' => (float) $r->amount])->values()->all(),
        ];
        DB::transaction(function () use ($user, $programId, $rows, $amount, $from, $to, $detail) {
            $chargeId = DB::table('mp_billing_charges')->insertGetId([
                'user_id' => $user->id, 'program_id' => $programId, 'period_from' => $from, 'period_to' => $to,
                'days' => $rows->count(), 'amount' => $amount, 'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE),
                'charged_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('mp_billing_days')->whereIn('id', $rows->pluck('id'))->update(['charge_id' => $chargeId, 'updated_at' => now()]);
            if ($amount > 0) {
                $user->decrement('balance', $amount);
                app(InvoiceTrackingService::class)->trackSpending($user, $amount, 'monitoring', $chargeId);
            }
        });
        Log::info('MonitoringBilling: списание', ['user_id' => $user->id, 'program_id' => $programId, 'amount' => $amount, 'from' => $from, 'to' => $to]);
    }

    /** Нет средств: сначала предупреждение, через grace_days — пауза программ клиента. */
    private function insufficient(User $user, $programs, float $total): string
    {
        $grace = max(0, (int) config('monitoring.billing.grace_days', 3));
        $warnedAt = $programs->map(fn ($p) => $p->settings['billing']['warning_at'] ?? null)->filter()->min();
        $msg = sprintf('Мониторинг: у клиента %s (user %d) не хватает средств на списание %s ₽ (доступно %s ₽).',
            $user->email, $user->id, number_format($total, 2, ',', ' '), number_format((float) $user->available_balance, 2, ',', ' '));
        if ($warnedAt === null) {
            foreach ($programs as $p) {
                $s = $p->settings ?? [];
                $s['billing'] = ['warning_at' => now()->toIso8601String(), 'due' => $total, 'pause_after' => now()->addDays($grace)->toIso8601String()];
                $p->forceFill(['settings' => $s])->save();
            }
            $this->alerter->alert("monitoring-billing:{$user->id}", $msg . " Предупреждение; пауза через {$grace} дн., если не пополнит.");
            Log::warning('MonitoringBilling: предупреждение', ['user_id' => $user->id, 'due' => $total]);

            return "user {$user->id}: не хватает средств ({$total} ₽) — предупреждение";
        }
        if (CarbonImmutable::parse($warnedAt)->addDays($grace)->isPast()) {
            foreach ($programs->where('status', 'active') as $p) {
                $p->forceFill(['status' => 'paused', 'paused_reason' => 'billing'])->save();
            }
            $this->alerter->alert("monitoring-billing:{$user->id}", $msg . ' Программы поставлены на паузу.');
            Log::warning('MonitoringBilling: пауза', ['user_id' => $user->id, 'due' => $total]);

            return "user {$user->id}: не хватает средств ({$total} ₽) — программы на паузе";
        }

        return "user {$user->id}: не хватает средств ({$total} ₽) — ждём пополнения до " . CarbonImmutable::parse($warnedAt)->addDays($grace)->toDateString();
    }

    private function clearWarning($programs): void
    {
        foreach ($programs as $p) {
            $s = $p->settings ?? [];
            $changed = isset($s['billing']);
            unset($s['billing']);
            $fill = ['settings' => $s ?: null];
            if ($p->status === 'paused' && $p->paused_reason === 'billing') {
                $fill += ['status' => 'active', 'paused_reason' => null];
                $changed = true;
            }
            if ($changed) {
                $p->forceFill($fill)->save();
            }
        }
    }

    /** Для API/админки: начислено и не списано, последнее списание, предупреждение. */
    /**
     * Списания за мониторинг для лент транзакций и сумм расходов (админка, кабинет, разбивка по счёту) — 22.10.2026
     * первое списание прошло, но в лентах его не было: они собирали только заявки/абонплату/отчёты/пополнения.
     *
     * @return \Illuminate\Support\Collection<int, array{created_at:\Carbon\Carbon, description:string, amount:float}>
     */
    public static function chargesForFeed(int $userId, ?\DateTimeInterface $since = null): \Illuminate\Support\Collection
    {
        return DB::table('mp_billing_charges as c')->leftJoin('monitoring_programs as p', 'p.id', '=', 'c.program_id')
            ->where('c.user_id', $userId)->where('c.amount', '>', 0)
            ->when($since, fn ($q) => $q->where('c.charged_at', '>=', $since))
            ->orderBy('c.charged_at')
            ->get(['c.id', 'c.amount', 'c.charged_at', 'c.period_from', 'c.period_to', 'c.days', 'p.name'])
            ->map(fn ($c) => [
                'created_at' => \Carbon\Carbon::parse($c->charged_at),
                'description' => 'Мониторинг цен «' . ($c->name ?? ('программа #' . $c->id)) . '»: покрытые позиции за '
                    . \Carbon\Carbon::parse($c->period_from)->format('d.m') . '–' . \Carbon\Carbon::parse($c->period_to)->format('d.m.Y') . " ({$c->days} дн.)",
                'amount' => (float) $c->amount,
            ]);
    }

    public static function chargedSum(int $userId): float
    {
        return (float) DB::table('mp_billing_charges')->where('user_id', $userId)->sum('amount');
    }

    public function programSummary(MonitoringProgram $p): array
    {
        $unbilled = DB::table('mp_billing_days')->where('program_id', $p->id)->whereNull('charge_id');
        $last = DB::table('mp_billing_charges')->where('program_id', $p->id)->orderByDesc('id')->first();
        $w = $p->settings['billing'] ?? null;

        return [
            'accrued_unbilled' => round((float) (clone $unbilled)->sum('amount'), 2),
            'unbilled_days' => (clone $unbilled)->count(),
            'last_charge' => $last ? ['amount' => (float) $last->amount, 'period_from' => (string) $last->period_from,
                'period_to' => (string) $last->period_to, 'charged_at' => CarbonImmutable::parse($last->charged_at)->toIso8601String()] : null,
            'warning' => $w ? ['insufficient_balance' => true, 'due' => (float) ($w['due'] ?? 0), 'pause_after' => $w['pause_after'] ?? null] : null,
        ];
    }
}
