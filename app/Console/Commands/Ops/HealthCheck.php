<?php

namespace App\Console\Commands\Ops;

use App\Services\Ops\OpsAlerter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Служебные проверки прода (каждые 5 мин) → Telegram-бот IQOT:
 *  - диск: свободно < disk_free_min_pct, или место убывает быстрее disk_drop_gb_per_hour
 *    (инцидент 15.10.2026 — бинлог MySQL забил диск за ночь);
 *  - почта: писем email_queue.status=error за час > email_errors_per_hour (август: релей висел
 *    месяц, 48% писем ушли в error незамеченными);
 *  - очереди: задачи, которые никто не берёт дольше stuck_job_minutes (воркеры встали);
 *  - релеи рассылки: /health.
 * --test — тестовое сообщение.
 */
class HealthCheck extends Command
{
    protected $signature = 'ops:health-check {--test : отправить тестовое сообщение}';

    protected $description = 'Служебные проверки прода (диск, почта, очереди, релеи) с оповещением в Telegram';

    /** Очереди, где отложенные/накопившиеся задачи — норма (мониторинг раздаёт пачками). */
    private const BACKLOG_OK_QUEUES = ['monitor', 'monitor-index', 'monitor-import'];

    public function handle(OpsAlerter $alerter): int
    {
        $host = gethostname() ?: 'prod';
        if ($this->option('test')) {
            $ok = $alerter->send("✅ <b>IQOT: тест оповещений</b>\nСервер {$host}, " . now()->format('d.m H:i') . ' UTC');
            $this->info($ok ? 'Отправлено.' : 'Не отправлено (нет OPS_ALERT_TELEGRAM_CHAT_ID / токена или Telegram недоступен — см. лог).');

            return $ok ? self::SUCCESS : self::FAILURE;
        }
        $cfg = config('services.ops_alerts');

        // 1. Диск.
        $total = (float) @disk_total_space('/');
        $free = (float) @disk_free_space('/');
        if ($total > 0) {
            $pct = 100 * $free / $total;
            $freeGb = round($free / 1024 ** 3, 1);
            if ($pct < (float) $cfg['disk_free_min_pct']) {
                $alerter->alert('disk_low', sprintf('Диск: свободно %.0f%% (%s ГБ). Проверить бинлог MySQL (/var/lib/mysql), логи воркеров (/var/log/iqot-*), mp_site_pages.', $pct, $freeGb));
            } else {
                $alerter->resolve('disk_low', sprintf('Диск: свободно %.0f%% (%s ГБ).', $pct, $freeGb));
            }

            // Скорость убывания — по замерам за последний час.
            $samples = array_values(array_filter((array) Cache::get('ops:disk:samples', []), fn ($s) => $s[0] > time() - 7200));
            $samples[] = [time(), $free];
            Cache::put('ops:disk:samples', $samples, now()->addHours(3));
            $hourAgo = null;
            foreach ($samples as $s) {
                if ($s[0] <= time() - 3300) {
                    $hourAgo = $s;
                }
            }
            if ($hourAgo !== null) {
                $dropGb = ($hourAgo[1] - $free) / 1024 ** 3 / ((time() - $hourAgo[0]) / 3600);
                if ($dropGb > (float) $cfg['disk_drop_gb_per_hour']) {
                    $hoursLeft = $dropGb > 0 ? $free / 1024 ** 3 / $dropGb : INF;
                    $alerter->alert('disk_fast', sprintf('Диск убывает %.1f ГБ/ч, свободно %s ГБ — заполнится примерно через %.0f ч.', $dropGb, $freeGb, $hoursLeft));
                } else {
                    $alerter->resolve('disk_fast', sprintf('Диск: убыль %.1f ГБ/ч, свободно %s ГБ.', max(0, $dropGb), $freeGb));
                }
            }
        }

        // 2. Ошибки отправки писем за час.
        try {
            $errors = DB::connection('reports')->table('email_queue')->where('status', 'error')
                ->where('updated_at', '>', now()->subHour())->count();
            if ($errors > (int) $cfg['email_errors_per_hour']) {
                $sample = (string) DB::connection('reports')->table('email_queue')->where('status', 'error')
                    ->where('updated_at', '>', now()->subHour())->orderByDesc('updated_at')->value('error_message');
                $alerter->alert('email_errors', "Почта: {$errors} писем в error за час. Последняя ошибка: " . htmlspecialchars(mb_substr($sample, 0, 300)));
            } else {
                $alerter->resolve('email_errors', "Почта: ошибок за час {$errors}.");
            }
        } catch (\Throwable $e) {
            $alerter->alert('reports_db', 'БД reports недоступна: ' . htmlspecialchars(mb_substr($e->getMessage(), 0, 200)));
        }

        // 3. Очереди: задачи, которые никто не забирает.
        $stuck = DB::table('jobs')->whereNull('reserved_at')
            ->where('available_at', '<', now()->subMinutes((int) $cfg['stuck_job_minutes'])->getTimestamp())
            ->whereNotIn('queue', self::BACKLOG_OK_QUEUES)
            ->selectRaw('queue, COUNT(*) c')->groupBy('queue')->pluck('c', 'queue')->all();
        $bad = array_filter($stuck, fn ($c) => $c >= (int) $cfg['stuck_job_min_count']);
        if ($bad !== []) {
            $list = implode(', ', array_map(fn ($q, $c) => "{$q}: {$c}", array_keys($bad), $bad));
            $alerter->alert('queues_stuck', "Очереди: задачи не забираются дольше {$cfg['stuck_job_minutes']} мин — {$list}. Проверить воркеры: systemctl list-units 'iqot-*worker@*'.");
        } else {
            $alerter->resolve('queues_stuck', 'Очереди разбираются.');
        }

        // 4. Релеи рассылки.
        foreach ((array) config('services.email_dispatch.microservice_urls', []) as $relay) {
            $url = rtrim((string) ($relay['url'] ?? ''), '/');
            if ($url === '') {
                continue;
            }
            $key = 'relay:' . md5($url);
            try {
                $ok = Http::timeout(8)->connectTimeout(5)->get($url . '/health')->successful();
            } catch (\Throwable) {
                $ok = false;
            }
            $ok ? $alerter->resolve($key, "Релей {$url} отвечает.") : $alerter->alert($key, "Релей рассылки {$url} не отвечает на /health.");
        }

        $this->info('ok');

        return self::SUCCESS;
    }
}
