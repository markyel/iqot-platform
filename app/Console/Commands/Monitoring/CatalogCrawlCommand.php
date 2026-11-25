<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Discovery\CatalogCrawler;
use App\Support\Monitoring\DomainPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Обход каталога сайтов-лидеров без sitemap / с неполным (25.11.2026, шаг 2 индекса лидеров).
 *   --domain=…       конкретные сайты
 *   --auto           лидеры (≥ --min-listings наших страниц), у которых в индексе < 70% наших страниц; раз в неделю
 */
class CatalogCrawlCommand extends Command
{
    protected $signature = 'monitoring:catalog-crawl {--domain=*} {--auto} {--min-listings=30} {--max-pages=2000} {--skip=chipdip.ru}';

    protected $description = 'Мониторинг: обход каталога сайтов-лидеров без sitemap → индекс карточек';

    public function handle(): int
    {
        if ($this->option('auto') && !config('monitoring.enabled')) {
            $this->warn('Мониторинг выключен (MONITORING_ENABLED=false).');

            return self::SUCCESS;
        }
        $skip = array_filter(array_map('trim', explode(',', (string) $this->option('skip'))));
        $domains = array_filter(array_map('trim', (array) $this->option('domain')));
        if ($domains === [] && $this->option('auto')) {
            $domains = collect(DB::select("
                SELECT s.domain, COUNT(l.id) n,
                       SUM(EXISTS (SELECT 1 FROM mp_site_pages p WHERE p.site_id = s.id AND p.url_hash = l.url_hash)) in_index
                FROM mp_sites s JOIN mp_listings l ON l.site_id = s.id AND l.bench = 0
                WHERE (s.blocked_until IS NULL OR s.blocked_until < NOW()) AND s.kind NOT IN ('marketplace','aggregator')
                  AND (s.sitemap_indexed_at IS NULL OR s.sitemap_indexed_at < NOW() - INTERVAL 6 DAY OR s.sitemap_pages = 0)
                GROUP BY s.id HAVING n >= ? AND in_index < 0.7 * n ORDER BY n DESC", [(int) $this->option('min-listings')]))
                ->pluck('domain')->all();
        }
        $crawler = CatalogCrawler::make();
        foreach ($domains as $d) {
            if (in_array($d, $skip, true) || DomainPolicy::isBlocked($d)) {
                continue;
            }
            $site = MpSite::query()->where('domain', $d)->first();
            if (!$site || $site->isBlocked()) {
                $this->warn("{$d}: нет сайта или заблокирован");
                continue;
            }
            $t = microtime(true);
            $r = $crawler->crawl($site, (int) $this->option('max-pages'));
            $this->line(sprintf('%s: %s, шаблон %s, загружено страниц %d, карточек %d, новых в индексе %d, %.0f c', $d, $r['status'],
                isset($r['pattern']) ? '/' . implode('/', $r['pattern']['prefix']) . ' ×' . $r['pattern']['depth'] : '—',
                $r['fetched'], $r['products'], $r['added'], microtime(true) - $t));
        }

        return self::SUCCESS;
    }
}
