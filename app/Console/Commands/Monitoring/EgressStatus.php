<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Web\EgressPool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Исходящие каналы мониторинга: состояние пула, закрепление сайтов, проба каналов и
 * разблокировка сайтов, забаненных ДО появления пула (бан IP сервера IQOT, напр. lift-snab
 * 14.10.2026) — для них «direct» помечается забаненным, и они идут через прокси.
 */
class EgressStatus extends Command
{
    protected $signature = 'monitoring:egress
        {--probe : запрос через каждый канал — проверить доступность и внешний IP}
        {--unblock-banned : снять блок с сайтов, заблокированных за 401/403/капчу, и перевести их на другой канал}';

    protected $description = 'Мониторинг цен: исходящие каналы (прокси) — состояние, проба, разблокировка';

    public function handle(): int
    {
        $pool = EgressPool::make();
        $assigned = DB::table('mp_sites')->whereNotNull('egress')->groupBy('egress')->pluck(DB::raw('COUNT(*)'), 'egress');

        $rows = [];
        foreach ($pool->names() as $name) {
            $c = $pool->get($name);
            $row = [$name, $c['proxy'] ?? '—', $c['fallback'] ? 'да' : '', $pool->isDown($name) ? 'DOWN' : 'ok', (int) ($assigned[$name] ?? 0)];
            if ($this->option('probe')) {
                $row[] = $this->probe($c['proxy']);
            }
            $rows[] = $row;
        }
        $headers = ['канал', 'прокси', 'резерв', 'состояние', 'сайтов'];
        if ($this->option('probe')) {
            $headers[] = 'проба (внешний IP)';
        }
        $this->table($headers, $rows);

        if ($this->option('unblock-banned')) {
            $this->unblock($pool);
        }

        return self::SUCCESS;
    }

    private function probe(?string $proxy): string
    {
        try {
            $t = microtime(true);
            $r = Http::timeout(15)->connectTimeout(8)
                ->withOptions(array_filter(['proxy' => $proxy]))
                ->get('https://api.ipify.org');

            return $r->successful()
                ? trim($r->body()) . sprintf(' (%.1f c)', microtime(true) - $t)
                : 'HTTP ' . $r->status();
        } catch (\Throwable $e) {
            return 'ошибка: ' . mb_substr($e->getMessage(), 0, 80);
        }
    }

    private function unblock(EgressPool $pool): void
    {
        $sites = MpSite::query()
            ->where('blocked_until', '>', now())
            ->where(fn ($q) => $q->where('blocked_reason', 'like', 'HTTP 40%')->orWhere('blocked_reason', 'captcha'))
            ->get();

        $moved = 0;
        $listings = 0;
        foreach ($sites as $site) {
            $banned = $site->egress ?: EgressPool::DIRECT;
            if ($pool->get($banned) !== null) {
                $pool->markBanned($banned, $site->domain, 'pre-pool: ' . $site->blocked_reason);
            }
            $next = $pool->pick($site->domain);
            if ($next === null) {
                continue;
            }
            $site->forceFill(['blocked_until' => null, 'blocked_reason' => null, 'egress' => $next['name']])->save();
            $listings += MpListing::query()->where('site_id', $site->id)->where('status', MpListing::STATUS_BLOCKED)
                ->update(['status' => MpListing::STATUS_ACTIVE, 'next_check_at' => now(), 'updated_at' => now()]);
            $moved++;
            $this->line("  {$site->domain}: {$banned} → {$next['name']}");
        }
        $this->info("Разблокировано сайтов: {$moved} из {$sites->count()}, листингов в проверку: {$listings}");
    }
}
