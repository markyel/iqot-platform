<?php

namespace App\Console\Commands;

use App\Models\Reports\Sender;
use App\Services\Senders\SenderConnectivityChecker;
use Illuminate\Console\Command;

/**
 * Проверка подключаемости ящиков (SMTP-AUTH + IMAP-LOGIN) — страховка от неверного
 * пароля/хоста ДО ввода в рассылку. Веб-зеркало — кнопка на странице импорта.
 *
 *   php artisan emails:check-senders --ids=144,149        точечно
 *   php artisan emails:check-senders --recent=1           созданные за N суток
 *   php artisan emails:check-senders --domain=emoil.ru    по домену
 *   php artisan emails:check-senders                       все is_active=1
 */
class CheckSenders extends Command
{
    protected $signature = 'emails:check-senders {--ids= : список id через запятую} {--recent= : созданные за N суток} {--domain= : фильтр по домену} {--only-bad : печатать только проблемные}';

    protected $description = 'Проверка подключаемости ящиков (SMTP-AUTH + IMAP-LOGIN)';

    public function handle(SenderConnectivityChecker $checker): int
    {
        $q = Sender::query();
        if ($ids = $this->option('ids')) {
            $q->whereIn('id', array_filter(array_map('intval', explode(',', (string) $ids))));
        } elseif ($recent = $this->option('recent')) {
            $q->where('created_at', '>=', now()->subDays(max(1, (int) $recent)));
        } elseif ($dom = $this->option('domain')) {
            $q->where('email', 'like', '%@' . ltrim((string) $dom, '@'));
        } else {
            $q->where('is_active', 1);
        }
        $senders = $q->orderBy('email')->get();

        if ($senders->isEmpty()) {
            $this->warn('Ящики не найдены по заданному фильтру.');
            return self::SUCCESS;
        }

        $onlyBad = (bool) $this->option('only-bad');
        $ok = 0; $bad = [];
        foreach ($senders as $s) {
            $r = $checker->check($s);
            if ($r['ok']) {
                $ok++;
                if (!$onlyBad) {
                    $this->line(sprintf('  <info>OK</info>   #%d %s', $s->id, $s->email));
                }
            } else {
                $bad[] = $s->id;
                $this->line(sprintf('  <error>FAIL</error> #%d %s — %s', $s->id, $s->email, $checker->summary($r)));
            }
        }

        $this->info(sprintf('Проверено %d: рабочих %d, проблемных %d%s',
            $senders->count(), $ok, count($bad), $bad ? ' (id: ' . implode(',', $bad) . ')' : ''));

        return count($bad) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
