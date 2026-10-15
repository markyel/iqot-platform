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
 *   plan-kp     запросы КП поставщикам без цены на сайте: «по запросу» (всегда), затем пул IQOT (< 3 цен)
 *   send        адресные проверочные письма через тех. заявку программы (флаг MONITORING_HYBRID_SEND)
 *   collect     статусы отправки, КП → сверки и доверие, отказы «нет в наличии», просрочка
 *   link-items  позиции обычных заявок ↔ канонические товары
 *   rfq         «бесплатные» сверки из КП обычных заявок
 *   daily       link-sites + plan + plan-kp + send   (будни после plan-day)
 *   sync        collect + link-items + rfq (каждые 30 мин)
 */
class HybridCommand extends Command
{
    protected $signature = 'monitoring:hybrid {step : link-sites|plan|plan-kp|send|collect|link-items|rfq|daily|sync} {--dry-run} {--force}';

    protected $description = 'Мониторинг цен, Гибрид: проверочные КП к ценам сайтов и доверие к сайтам (Ф3)';

    public function handle(): int
    {
        if (!$this->option('force') && !config('monitoring.enabled')) {
            $this->warn('monitoring:hybrid выключен (MONITORING_ENABLED=false). --force для ручного запуска.');

            return self::SUCCESS;
        }
        $step = (string) $this->argument('step');
        $steps = match ($step) {
            'daily' => ['link-sites', 'plan', 'plan-kp', 'send'],
            'sync' => ['collect', 'link-items', 'rfq'],
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
                $this->info("Запросы КП: «по запросу» {$r['on_request']}, пул {$r['pool']}" . ($dry ? ' (dry-run)' : ''));
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
            default:
                $this->error("Неизвестный шаг: {$step}");
        }
    }
}
