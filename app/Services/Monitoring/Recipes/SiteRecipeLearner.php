<?php

namespace App\Services\Monitoring\Recipes;

use App\Models\Monitoring\MpSite;
use App\Services\Api\OpenAIClassifierClient;
use App\Services\Monitoring\Web\EgressPool;
use App\Services\Monitoring\Web\PageFetcher;
use Illuminate\Support\Facades\DB;

/**
 * Вывод рецепта карточки сайта (пилот, 24.11.2026): ИИ получает урезанную вёрстку двух известных карточек (где мы уже
 * знаем цену и артикул) и предлагает CSS-селекторы; рецепт принимается, если на всех образцах сайта даёт ту же цену.
 */
class SiteRecipeLearner
{
    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly OpenAIClassifierClient $ai,
    ) {
    }

    public static function make(): self
    {
        return new self(app(PageFetcher::class), app(OpenAIClassifierClient::class));
    }

    /**
     * Известные страницы сайта с ценой и HTML.
     *
     * @return array<int, array{url:string, price:float, codes:array<int,string>, html:string}>
     */
    public function samples(MpSite $site, int $n, array $skipIds = []): array
    {
        $rows = DB::table('mp_listings as l')->where('l.site_id', $site->id)->where('l.bench', false)->where('l.status', 'active')
            ->where('l.price', '>', 0)->whereNotIn('l.id', $skipIds)->orderByDesc('l.observed_at')->limit($n * 2)
            ->get(['l.id', 'l.url', 'l.price', 'l.product_id', 'l.matched_codes']);
        $channel = EgressPool::make()->pick($site->domain, $site->egress);
        $out = [];
        foreach ($rows as $r) {
            if (count($out) >= $n) {
                break;
            }
            $res = $this->fetcher->fetch($r->url, ['egress' => $channel, 'rate_ms' => max(1500, (int) $site->rate_limit_ms)]);
            for ($i = 0; $i < 10 && $res->deferred(); $i++) {
                usleep(max(500, $res->retryAfterMs) * 1000);
                $res = $this->fetcher->fetch($r->url, ['egress' => $channel, 'rate_ms' => max(1500, (int) $site->rate_limit_ms)]);
            }
            if (!$res->ok() || !$res->isHtml()) {
                continue;
            }
            $codes = DB::table('mp_identifiers')->where('product_id', $r->product_id)->whereIn('kind', ['oem', 'mpn'])->pluck('value_norm')->all();
            $out[] = ['id' => (int) $r->id, 'url' => $r->url, 'price' => (float) $r->price, 'codes' => $codes, 'html' => $res->body];
        }

        return $out;
    }

    /**
     * @param array<int, array{url:string, price:float, codes:array<int,string>, html:string}> $samples
     * @return array{recipe:?SiteRecipe, raw:array<string,mixed>}
     */
    public function propose(array $samples): array
    {
        $parts = [];
        foreach (array_slice($samples, 0, 2) as $i => $s) {
            $parts[] = "=== Страница " . ($i + 1) . ": {$s['url']}\nИзвестно: цена основного товара {$s['price']} ₽"
                . ($s['codes'] ? '; его артикул(ы): ' . implode(', ', array_slice($s['codes'], 0, 3)) : '')
                . "\nФрагменты вёрстки:\n" . self::compact($s['html'], $s['price']);
        }
        $system = 'Ты — инженер парсинга интернет-магазинов. Тебе даны фрагменты вёрстки двух карточек товара ОДНОГО сайта '
            . 'и известная цена основного товара каждой. Составь CSS-селекторы, которые на ЛЮБОЙ карточке этого сайта выбирают: '
            . 'title — название основного товара; sku — артикул/код товара (если есть); price — ТЕКУЩУЮ цену основного товара '
            . '(не старую зачёркнутую, не цену похожих/рекомендованных товаров, не цену доставки); availability — наличие (если есть). '
            . 'Используй устойчивые классы, id, itemprop; не используй номера страниц, id конкретного товара, :nth-child по случайным позициям. '
            . 'Если поля нет — null. Ответ строго JSON: {"title":"…","sku":"…|null","price":"…","availability":"…|null"}.';
        try {
            $json = $this->ai->jsonCompletion((string) config('monitoring.llm.model', 'gpt-4o-mini'), $system, implode("\n\n", $parts), 600, 0.0);
        } catch (\Throwable $e) {
            return ['recipe' => null, 'raw' => ['error' => $e->getMessage()]];
        }
        $sel = array_map(fn ($v) => is_string($v) && trim($v) !== '' && mb_strtolower(trim($v)) !== 'null' ? trim($v) : null,
            array_intersect_key((array) $json, array_flip(['title', 'sku', 'price', 'availability'])));

        return ['recipe' => ($sel['price'] ?? null) ? new SiteRecipe($sel) : null, 'raw' => (array) $json];
    }

    /**
     * Вывести рецепт: до трёх попыток ИИ по разным парам образцов, лучший — на проверку по всем образцам; сохраняется
     * с долей совпадений (в работе — от SiteRecipe::MIN_SCORE).
     *
     * @return array{status:string, score?:float, selectors?:array<string,mixed>, log:array<int,string>}
     */
    public function learn(MpSite $site, int $samplesN = 6): array
    {
        $log = [];
        $samples = $this->samples($site, $samplesN);
        if (count($samples) < 3) {
            return ['status' => 'few_samples', 'log' => ['известных страниц с ценой: ' . count($samples)]];
        }
        $best = null;
        foreach ([0, 2, 4] as $offset) {
            $pair = array_slice($samples, $offset, 2);
            if (count($pair) < 2) {
                break;
            }
            $p = $this->propose($pair);
            if (!$p['recipe']) {
                $log[] = "попытка {$offset}: нет селектора цены";
                continue;
            }
            $v = self::validate($p['recipe'], $samples);
            $log[] = "попытка {$offset}: " . json_encode($p['recipe']->selectors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . " → {$v['hits']}/{$v['total']}";
            if ($best === null || $v['hits'] > $best[1]['hits']) {
                $best = [$p['recipe'], $v];
            }
            if ($v['hits'] === $v['total']) {
                break;
            }
        }
        if ($best === null) {
            $site->forceFill(['recipe_score' => 0, 'recipe_at' => now()])->save();

            return ['status' => 'failed', 'log' => $log];
        }
        [$recipe, $v] = $best;
        $score = round($v['total'] > 0 ? $v['hits'] / $v['total'] : 0, 3);
        $site->forceFill(['recipe' => $recipe->selectors, 'recipe_score' => $score, 'recipe_at' => now()])->save();

        return ['status' => $score >= SiteRecipe::MIN_SCORE ? 'active' : 'weak', 'score' => $score, 'selectors' => $recipe->selectors, 'log' => $log];
    }

    /**
     * Перепроверка рабочего рецепта на свежих известных карточках: сайт сменил вёрстку — рецепт снимается
     * (score=0) и выводится заново при следующем проходе.
     *
     * @return array{hits:int, total:int}
     */
    public function revalidate(MpSite $site, int $n = 4): array
    {
        $recipe = SiteRecipe::forSite($site);
        if ($recipe === null) {
            return ['hits' => 0, 'total' => 0];
        }
        $v = self::validate($recipe, $this->samples($site, $n));
        if ($v['total'] >= 3 && $v['hits'] / $v['total'] < 0.75) {
            $site->forceFill(['recipe_score' => 0, 'recipe_at' => now()])->save();
        } elseif ($v['total'] > 0) {
            $site->forceFill(['recipe_at' => now()])->save();
        }

        return ['hits' => $v['hits'], 'total' => $v['total']];
    }

    /**
     * Проверка рецепта на образцах: цена совпала (±1%) — попадание.
     *
     * @param array<int, array{url:string, price:float, codes:array<int,string>, html:string}> $samples
     * @return array{hits:int, total:int, rows:array<int,array<string,mixed>>}
     */
    public static function validate(SiteRecipe $recipe, array $samples): array
    {
        $hits = 0;
        $rows = [];
        foreach ($samples as $s) {
            $r = $recipe->apply($s['html']);
            $ok = $r['price'] !== null && $r['price_nodes'] <= SiteRecipe::MAX_PRICE_NODES
                && abs($r['price'] - $s['price']) <= max(0.01, $s['price'] * 0.01);
            $hits += $ok ? 1 : 0;
            $rows[] = ['url' => $s['url'], 'known' => $s['price'], 'got' => $r['price'], 'ok' => $ok, 'title' => $r['title'], 'sku' => $r['sku']];
        }

        return ['hits' => $hits, 'total' => count($samples), 'rows' => $rows];
    }

    /**
     * Урезанная вёрстка: без скриптов/стилей/svg и лишних атрибутов; заголовок H1, блок вокруг известной цены и
     * разметка itemprop — то, по чему составляются селекторы. Не больше ~12 тыс. символов.
     */
    public static function compact(string $html, float $price): string
    {
        $xp = SiteRecipe::xpath($html);
        if ($xp === null) {
            return '';
        }
        $doc = $xp->document;
        foreach (['//script', '//style', '//noscript', '//svg', '//iframe', '//comment()', '//link', '//meta[not(@itemprop)]'] as $q) {
            foreach (iterator_to_array($xp->query($q) ?: []) as $n) {
                $n->parentNode?->removeChild($n);
            }
        }
        foreach ($xp->query('//*[@*]') ?: [] as $el) {
            /** @var \DOMElement $el */
            foreach (iterator_to_array($el->attributes) as $a) {
                if (!in_array($a->name, ['class', 'id', 'itemprop', 'itemtype', 'content'], true) && !str_starts_with($a->name, 'data-price')) {
                    $el->removeAttribute($a->name);
                }
            }
        }
        $out = [];
        foreach ($xp->query('//h1') ?: [] as $h1) {
            $out[] = self::outer($doc, $h1, 1500);
            break;
        }
        // Узел с известной ценой (цифры без пробелов) и его предок примерно на 4 тыс. символов.
        $digits = (string) (int) round($price);
        foreach ($xp->query('//body//text()') ?: [] as $t) {
            if (!str_contains(preg_replace('/\D+/', '', (string) $t->nodeValue) ?? '', $digits)) {
                continue;
            }
            $node = $t->parentNode;
            for ($i = 0; $i < 8 && $node?->parentNode instanceof \DOMElement && mb_strlen($doc->saveHTML($node->parentNode)) < 4000; $i++) {
                $node = $node->parentNode;
            }
            $out[] = self::outer($doc, $node, 6000);
            break;
        }
        foreach ($xp->query('//*[@itemprop]') ?: [] as $n) {
            $out[] = self::outer($doc, $n, 300);
            if (count($out) > 25) {
                break;
            }
        }

        return mb_substr(implode("\n…\n", array_unique($out)), 0, 12000);
    }

    private static function outer(\DOMDocument $doc, \DOMNode $n, int $max): string
    {
        return mb_substr(preg_replace('/\s+/u', ' ', (string) $doc->saveHTML($n)) ?? '', 0, $max);
    }
}
