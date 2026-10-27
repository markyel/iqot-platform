<?php

namespace App\Console\Commands;

use App\Services\Ops\OpsAlerter;
use App\Services\Senders\MailboxReplenisher;
use Illuminate\Console\Command;

/**
 * Пополнение основного пула ящиков до цели (MailboxReplenisher, решение владельца 27.10.2026). Раз в сутки
 * после возврата и прогрева, до плана дня. Флаг EMAILS_MAILBOX_REPLENISH_ENABLED, ручной прогон — --force.
 */
class ReplenishMailboxes extends Command
{
    protected $signature = 'emails:replenish-mailboxes {--dry-run : Показать план, ничего не создавая} {--force : Запуск при выключенном флаге}';

    protected $description = 'Пополнение основного пула ящиков на живых доменах Beget до цели';

    public function handle(MailboxReplenisher $replenisher, OpsAlerter $alerter): int
    {
        $dry = (bool) $this->option('dry-run');
        if (!$dry && !$this->option('force') && !config('services.mailbox_replenish.enabled')) {
            $this->warn('Пополнение пула выключено (EMAILS_MAILBOX_REPLENISH_ENABLED=false). --force / --dry-run для ручного запуска.');

            return self::SUCCESS;
        }
        $r = $replenisher->run($dry);
        $live = implode(', ', array_map(fn ($d, $p) => "{$d} {$p}%", array_keys($r['live']), $r['live']));
        $this->info("Пул: рабочих {$r['working']}, ждут возврата на живых доменах {$r['revivable']}, цель {$r['target']}, дефицит {$r['deficit']}");
        $this->line("Живые домены (спам за окно): " . ($live !== '' ? $live : 'нет'));
        $this->info('План создания: ' . ($r['plan'] === [] ? 'нет' : json_encode($r['plan'], JSON_UNESCAPED_UNICODE)) . ($dry ? ' (dry-run)' : ''));
        foreach ($r['created'] as $email) {
            $this->line("  создан {$email}");
        }
        foreach ($r['errors'] as $err) {
            $this->error("  ошибка: {$err}");
        }
        if (!$dry && ($r['created'] !== [] || $r['errors'] !== [])) {
            $alerter->send('<b>Пополнение пула ящиков</b>: рабочих ' . $r['working'] . ' из цели ' . $r['target']
                . ($r['created'] !== [] ? "\nСозданы: " . e(implode(', ', $r['created'])) : '')
                . ($r['errors'] !== [] ? "\nОшибки: " . e(implode('; ', $r['errors'])) : ''));
        }

        return self::SUCCESS;
    }
}
