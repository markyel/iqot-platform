<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Recipes\SiteRecipe;
use App\Services\Monitoring\Recipes\SiteRecipeLearner;
use App\Services\Monitoring\Web\EgressPool;
use App\Services\Monitoring\Web\PageFetcher;
use App\Support\Monitoring\ArticleNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Пилот «рецепт карточки сайта» (24.11.2026, решение владельца): проверка страниц не меняется — только замер.
 *
 *   monitoring:site-recipe learn --domain=apexx.ru   вывести рецепт (ИИ по 2 известным карточкам), проверить на 6, сохранить
 *   monitoring:site-recipe eval --domain=apexx.ru    на известных страницах: совпадает ли цена рецепта с подтверждённой,
 *                                                     и отличил бы рецепт (наш код в названии/артикуле) наш товар от чужого
 */
class SiteRecipeCommand extends Command
{
    protected $signature = 'monitoring:site-recipe {action : learn|eval|auto} {--domain=*} {--pages=30} {--min-pages=10} {--limit=10}';

    protected $description = 'Мониторинг: пилот рецепта карточки сайта (селекторы без ИИ)';

    public function handle(): int
    {
        if ($this->argument('action') === 'auto') {
            $this->auto();

            return self::SUCCESS;
        }
        foreach ((array) $this->option('domain') as $d) {
            $site = MpSite::query()->where('domain', trim($d))->first();
            if (!$site) {
                $this->warn("{$d}: сайта нет");
                continue;
            }
            $this->argument('action') === 'learn' ? $this->learn($site) : $this->evaluate($site);
        }

        return self::SUCCESS;
    }

    private function learn(MpSite $site): void
    {
        $r = SiteRecipeLearner::make()->learn($site);
        foreach ($r['log'] as $l) {
            $this->line("  {$l}");
        }
        $this->info("{$site->domain}: " . match ($r['status']) {
            'active' => 'рецепт в работе, совпадение ' . $r['score'],
            'weak' => 'рецепт слабый (' . $r['score'] . ') — не применяется',
            'failed' => 'рецепт не выведен',
            default => 'мало известных страниц с ценой',
        });
    }

    /**
     * Ежедневно: рецепты для частых сайтов без стандартной разметки (≥ --min-pages страниц с ценой), у которых рецепта
     * нет или он снят; рабочие рецепты старше 7 дней — перепроверка на свежих карточках.
     */
    private function auto(): void
    {
        if (!config('monitoring.enabled')) {
            $this->warn('Мониторинг выключен (MONITORING_ENABLED=false).');

            return;
        }
        $min = max(3, (int) $this->option('min-pages'));
        $limit = max(1, (int) $this->option('limit'));
        $learner = SiteRecipeLearner::make();
        $sites = MpSite::query()->where(fn ($q) => $q->whereNull('has_structured_markup')->orWhere('has_structured_markup', false))
            ->where(fn ($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '<', now()))
            ->whereIn('id', DB::table('mp_listings')->where('bench', false)->where('status', 'active')->where('price', '>', 0)
                ->groupBy('site_id')->havingRaw('COUNT(*) >= ?', [$min])->select('site_id'))
            ->get();
        $learned = 0;
        foreach ($sites as $site) {
            $active = SiteRecipe::forSite($site) !== null;
            if ($active && $site->recipe_at !== null && $site->recipe_at->gt(now()->subDays(7))) {
                continue;
            }
            if ($active) {
                $v = $learner->revalidate($site);
                $this->line("{$site->domain}: перепроверка {$v['hits']}/{$v['total']}" . (SiteRecipe::forSite($site->refresh()) ? '' : ' — снят'));
                continue;
            }
            // Слабый или неудачный рецепт не выводим заново чаще раза в 14 дней.
            if ($site->recipe_at !== null && $site->recipe_at->gt(now()->subDays(14))) {
                continue;
            }
            if ($learned++ >= $limit) {
                break;
            }
            $this->learn($site);
        }
    }

    private function evaluate(MpSite $site): void
    {
        if (!$site->recipe) {
            $this->warn("{$site->domain}: рецепта нет — сначала learn");

            return;
        }
        $recipe = new SiteRecipe((array) $site->recipe);
        $n = (int) $this->option('pages');
        $fetcher = app(PageFetcher::class);
        $channel = EgressPool::make()->pick($site->domain, $site->egress);
        $rows = DB::table('mp_listings')->where('site_id', $site->id)->where('bench', false)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('status', 'active')->where('price', '>', 0))->orWhere('status', 'mismatch'))
            ->inRandomOrder()->limit($n * 2)->get(['id', 'url', 'status', 'price', 'product_id']);
        $st = ['ours' => 0, 'ours_price_ok' => 0, 'ours_price_none' => 0, 'ours_code' => 0, 'alien' => 0, 'alien_code' => 0, 'fetch_fail' => 0];
        $misses = [];
        foreach ($rows as $r) {
            $isOurs = $r->status === 'active';
            if (($isOurs ? $st['ours'] : $st['alien']) >= $n) {
                continue;
            }
            $res = $fetcher->fetch($r->url, ['egress' => $channel, 'rate_ms' => max(1500, (int) $site->rate_limit_ms)]);
            for ($i = 0; $i < 10 && $res->deferred(); $i++) {
                usleep(max(500, $res->retryAfterMs) * 1000);
                $res = $fetcher->fetch($r->url, ['egress' => $channel, 'rate_ms' => max(1500, (int) $site->rate_limit_ms)]);
            }
            if (!$res->ok() || !$res->isHtml()) {
                $st['fetch_fail']++;
                continue;
            }
            $a = $recipe->apply($res->body);
            $codes = DB::table('mp_identifiers')->where('product_id', $r->product_id)->whereIn('kind', ['oem', 'mpn'])->pluck('value_norm')->all();
            $tokens = ArticleNormalizer::tokens((string) $a['title'] . ' ' . (string) $a['sku']);
            $codeHit = array_intersect(array_map('strval', $codes), array_map('strval', array_keys($tokens))) !== [];
            if ($isOurs) {
                $st['ours']++;
                $st['ours_code'] += $codeHit ? 1 : 0;
                if ($a['price'] === null) {
                    $st['ours_price_none']++;
                } elseif (abs($a['price'] - (float) $r->price) <= max(0.01, (float) $r->price * 0.01)) {
                    $st['ours_price_ok']++;
                } elseif (count($misses) < 5) {
                    $misses[] = "{$r->url}: подтверждено {$r->price}, рецепт {$a['price']} ({$a['price_text']})";
                }
            } else {
                $st['alien']++;
                $st['alien_code'] += $codeHit ? 1 : 0;
            }
        }
        $pct = fn ($a, $b) => $b > 0 ? round(100 * $a / $b) . '%' : '—';
        $this->info("{$site->domain}: наших страниц {$st['ours']} — цена рецепта совпала {$st['ours_price_ok']} ({$pct($st['ours_price_ok'], $st['ours'])}), "
            . "без цены {$st['ours_price_none']}, наш код в названии/артикуле {$st['ours_code']} ({$pct($st['ours_code'], $st['ours'])}); "
            . "чужих страниц {$st['alien']} — наш код в названии/артикуле {$st['alien_code']} ({$pct($st['alien_code'], $st['alien'])}); не загрузились {$st['fetch_fail']}");
        foreach ($misses as $m) {
            $this->line("    расхождение: {$m}");
        }
    }
}
