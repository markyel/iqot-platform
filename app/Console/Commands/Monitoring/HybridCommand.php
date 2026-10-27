<?php

namespace App\Console\Commands\Monitoring;

use App\Services\Monitoring\Hybrid\SiteSupplierLinker;
use App\Services\Monitoring\Hybrid\VerificationCollector;
use App\Services\Monitoring\Hybrid\VerificationMailer;
use App\Services\Monitoring\Hybrid\VerificationPlanner;
use Illuminate\Console\Command;

/**
 * Ф3 «Гибрид» (docs/monitoring-design.md §2, решения владельца 2026-10-15):
 *   link-sites  сайты ↔ поставщики IQOT (кому слать проверочный КП)
 *   plan        выборка позиций на проверку → mp_asks (по уровню доверия, бюджет писем в сутки)
 *   plan-kp     запросы КП волнами до цели (6 цен: КП + надёжные цены сайтов): весь пул и поставщики с товаром на сайте,
 *               по убыванию отдачи, ≤30 на волну, досылка через 5 дней (RfqPlanner)
 *   send        адресные проверочные письма через тех. заявку программы (флаг MONITORING_HYBRID_SEND)
 *   collect     статусы отправки, КП → сверки и доверие, отказы «нет в наличии», просрочка
 *   link-items  позиции обычных заявок ↔ канонические товары
 *   rfq         «бесплатные» сверки из КП обычных заявок
 *   waves       снимок состояния волн (холостой прогон планировщика) — для страницы «Ход выполнения»
 *   daily       link-sites + plan + plan-kp + send + waves   (будни после plan-day)
 *   sync        collect + link-items + rfq + waves (каждые 30 мин)
 */
class HybridCommand extends Command
{
    protected $signature = 'monitoring:hybrid {step : link-sites|plan|plan-kp|send|collect|link-items|rfq|waves|daily|sync} {--dry-run} {--force}';

    protected $description = 'Мониторинг цен, Гибрид: проверочные КП к ценам сайтов и доверие к сайтам (Ф3)';

    public function handle(): int
    {
        if (!$this->option('force') && !config('monitoring.enabled')) {
            $this->warn('monitoring:hybrid выключен (MONITORING_ENABLED=false). --force для ручного запуска.');

            return self::SUCCESS;
        }
        $step = (string) $this->argument('step');
        $steps = match ($step) {
            'daily' => ['link-sites', 'plan', 'plan-kp', 'send', 'waves'],
            'sync' => ['collect', 'link-items', 'rfq', 'waves'],
            default => [$step],
        };
        foreach ($steps as $s) {
            $this->runStep($s);
        }

        return self::SUCCESS;
    }

    private function runStep(string $step): void
    {
        $dry = (bool) $this->option('dry-run');
        switch ($step) {
            case 'link-sites':
                $r = (new SiteSupplierLinker())->linkAll();
                $this->info("Сайты ↔ поставщики: связано {$r['linked']}, без поставщика {$r['none']}");
                break;
            case 'plan':
                $r = (new VerificationPlanner())->plan($dry);
                $this->info("Выборка: бюджет позиций {$r['budget_items']}, запланировано {$r['planned']} на {$r['sites']} сайтах" . ($dry ? ' (dry-run)' : ''));
                break;
            case 'plan-kp':
                $r = (new \App\Services\Monitoring\Hybrid\RfqPlanner())->plan($dry);
                $this->info("Запросы КП волнами: позиций {$r['positions']}, цель набрана {$r['done']}, ждут ответов волны {$r['in_wave']}, "
                    . "новых волн {$r['waves']} — запросов {$r['asks']} " . json_encode($r['by_reason'], JSON_UNESCAPED_UNICODE)
                    . ", без кандидатов {$r['no_candidates']}, занятых поставщиков пропущено {$r['saturated']}, лишних отменено {$r['cancelled']}"
                    . ($dry ? ' (dry-run)' : ''));
                if ($r['pool_by_supplier'] ?? []) {
                    $top = array_slice($r['pool_by_supplier'], 0, 15, true);
                    $emails = \Illuminate\Support\Facades\DB::connection('reports')->table('suppliers')->whereIn('id', array_keys($top))->pluck('email', 'id');
                    $this->line('  Пул: поставщиков ' . count($r['pool_by_supplier']) . ', больше всех запросов: ' . implode(', ', array_map(fn ($id, $n) => ($emails[$id] ?? $id) . " {$n}", array_keys($top), $top)));
                }
                break;
            case 'send':
                if (!$dry && !config('monitoring.hybrid.send_enabled')) {
                    $this->warn('Отправка проверочных писем выключена (MONITORING_HYBRID_SEND=false) — только --dry-run.');
                    $dry = true;
                }
                $r = (new VerificationMailer())->send($dry);
                $this->info("Письма мониторинга: {$r['letters']} писем" . (empty($r['by_kind']) ? '' : ' ' . json_encode($r['by_kind'])) . ", {$r['items']} позиций, пропущено поставщиков {$r['skipped_suppliers']}" . ($dry ? ' (dry-run)' : '') . (empty($r['skipped']) ? '' : ' ' . json_encode($r['skipped'], JSON_UNESCAPED_UNICODE)));
                break;
            case 'collect':
                $r = (new VerificationCollector())->collectAsks();
                $this->info('Ответы на проверки: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
                break;
            case 'link-items':
                $r = (new VerificationCollector())->linkRequestItems();
                $this->info('Позиции заявок ↔ товары: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
                break;
            case 'rfq':
                $n = (new VerificationCollector())->collectRfq();
                $this->info("Сверки из КП обычных заявок: {$n}");
                break;
            case 'waves':
                // Холостой прогон: «сколько позиций где сейчас» — ничего не создаёт и не отменяет (~7 с).
                $r = (new \App\Services\Monitoring\Hybrid\RfqPlanner())->plan(true);
                \Illuminate\Support\Facades\Cache::put(\App\Services\Monitoring\Hybrid\RfqPlanner::SNAPSHOT_KEY,
                    ['at' => now()->toIso8601String(), 'stats' => \Illuminate\Support\Arr::except($r, ['pool_by_supplier'])], now()->addHours(3));
                $this->info("Снимок волн: позиций {$r['positions']}, цель набрана {$r['done']}, идёт волна {$r['in_wave']}, готовы к волне {$r['waves']}, без кандидатов {$r['no_candidates']}");
                break;
            default:
                $this->error("Неизвестный шаг: {$step}");
        }
    }
}
