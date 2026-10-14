<?php

namespace App\Jobs\Monitoring;

use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\MonitoringGovernor;
use App\Services\Monitoring\Web\EgressPool;
use App\Services\Monitoring\Web\PageFetcher;
use App\Support\Monitoring\ProductPageDigest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Ф2: обход порции карточек одного сайта из sitemap → mp_site_pages.title/codes
 * (очередь monitor-index, отдельные воркеры — не занимают проверки цен).
 *
 * Страницы идут подряд с паузой сайта (слот домена — ожиданием, это один сайт), квота —
 * fetch_index (агрессивность). Бан (401/403/капча) — бан канала для домена и стоп; отказ
 * прокси — канал down и стоп; квота/время вышли — стоп. Недоделанные страницы снимаются
 * с очереди (index_queued_at = null) — их подберёт следующий тик диспетчера.
 */
class IndexSitePagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    /**
     * Меньше retry_after очереди database (умолчание фреймворка — 90 с, config/queue.php нет):
     * задача дольше считается зависшей и отдаётся второму воркеру → MaxAttemptsExceeded.
     * Остаток порции задача ставит следующей задачей сама — поток по сайту не прерывается.
     */
    private const TIME_BUDGET = 70;

    private const CAPTCHA_RE = '~captcha|подтвердите, что вы не робот|checking your browser|ddos-guard|cf-chl|проверка безопасности~u';

    /** @param array<int,int> $pageIds */
    public function __construct(private readonly int $siteId, private readonly array $pageIds)
    {
        $this->onQueue('monitor-index');
    }

    public function handle(PageFetcher $fetcher, MonitoringGovernor $governor): void
    {
        $started = time();
        $site = MpSite::find($this->siteId);
        $pool = EgressPool::make();
        $done = [];
        $continue = false; // остановились только по времени — остаток продолжит следующая задача
        try {
            if (!$site || $site->isBlocked()) {
                return;
            }
            $pages = DB::table('mp_site_pages')->whereIn('id', $this->pageIds)->get(['id', 'url']);
            foreach ($pages as $page) {
                if (time() - $started > self::TIME_BUDGET) {
                    $continue = true;
                    break;
                }
                if (!$governor->available('fetch_index')) {
                    break;
                }
                $channel = $pool->exhaustedFor($site->domain) ? null : $pool->pick($site->domain, $site->egress);
                if ($channel === null) {
                    break;
                }
                $res = null;
                while (time() - $started <= self::TIME_BUDGET) {
                    $res = $fetcher->fetch($page->url, ['egress' => $channel, 'rate_ms' => (int) $site->rate_limit_ms, 'max_bytes' => 800_000]);
                    if (!$res->deferred()) {
                        break;
                    }
                    usleep(min(5000, max(200, $res->retryAfterMs)) * 1000);
                }
                if ($res === null || $res->deferred()) {
                    $continue = true; // слот домена занят до конца бюджета — продолжит следующая задача
                    break;
                }
                $governor->record('fetch_index', 1, 0.0);
                if ($res->proxyFailed) {
                    $pool->markDown($channel['name'], (string) $res->error);
                    break;
                }
                $captcha = $res->ok() && strlen($res->body) < 30000 && preg_match(self::CAPTCHA_RE, mb_strtolower(mb_substr($res->body, 0, 20000)));
                if (in_array($res->status, [401, 403], true) || $captcha) {
                    $pool->markBanned($channel['name'], $site->domain, 'catalog index ' . ($captcha ? 'captcha' : "HTTP {$res->status}"));
                    break;
                }
                if ($res->status === 429) {
                    $site->forceFill(['rate_limit_ms' => min(60000, max((int) $site->rate_limit_ms, 1000) * 2)])->save();
                    break;
                }

                $update = ['fetched_at' => now(), 'http_status' => $res->status ?: null, 'index_queued_at' => null];
                if ($res->ok() && $res->isHtml()) {
                    $digest = ProductPageDigest::fromHtml($res->body);
                    $update['title'] = $digest['title'];
                    $update['codes'] = $digest['codes'] ? implode(' ', $digest['codes']) : null;
                }
                DB::table('mp_site_pages')->where('id', $page->id)->update($update);
                $done[] = $page->id;
            }
        } finally {
            $left = array_values(array_diff($this->pageIds, $done));
            if ($left !== [] && $continue) {
                DB::table('mp_site_pages')->whereIn('id', $left)->update(['index_queued_at' => now()]);
                self::dispatch($this->siteId, $left);
            } elseif ($left !== []) {
                DB::table('mp_site_pages')->whereIn('id', $left)->update(['index_queued_at' => null]);
            }
        }
    }
}
