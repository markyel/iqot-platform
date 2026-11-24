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
    protected $signature = 'monitoring:site-recipe {action : learn|eval} {--domain=*} {--pages=30}';

    protected $description = 'Мониторинг: пилот рецепта карточки сайта (селекторы без ИИ)';

    public function handle(): int
    {
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
        $learner = SiteRecipeLearner::make();
        $samples = $learner->samples($site, 6);
        if (count($samples) < 3) {
            $this->warn("{$site->domain}: мало известных страниц с ценой (" . count($samples) . ')');

            return;
        }
        $best = null;
        foreach ([0, 2, 4] as $offset) {
            $pair = array_slice($samples, $offset, 2);
            if (count($pair) < 2) {
                break;
            }
            $p = $learner->propose(array_merge($pair, $samples));
            if (!$p['recipe']) {
                $this->line("  попытка {$offset}: ИИ не дал селектор цены " . json_encode($p['raw'], JSON_UNESCAPED_UNICODE));
                continue;
            }
            $v = SiteRecipeLearner::validate($p['recipe'], $samples);
            $this->line("  попытка {$offset}: " . json_encode($p['recipe']->selectors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . " → {$v['hits']}/{$v['total']}");
            if ($best === null || $v['hits'] > $best[1]['hits']) {
                $best = [$p['recipe'], $v];
            }
            if ($v['hits'] === $v['total']) {
                break;
            }
        }
        if ($best === null) {
            $this->error("{$site->domain}: рецепт не выведен");

            return;
        }
        [$recipe, $v] = $best;
        $score = $v['total'] > 0 ? $v['hits'] / $v['total'] : 0;
        $site->forceFill(['recipe' => $recipe->selectors, 'recipe_score' => round($score, 3), 'recipe_at' => now()])->save();
        $this->info("{$site->domain}: рецепт сохранён, совпадение цены {$v['hits']}/{$v['total']}");
        foreach ($v['rows'] as $r) {
            if (!$r['ok']) {
                $this->line("    мимо: {$r['url']} — известно {$r['known']}, рецепт {$r['got']}");
            }
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
