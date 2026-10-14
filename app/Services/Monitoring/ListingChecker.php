<?php

namespace App\Services\Monitoring;

use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpPriceObservation;
use App\Models\Monitoring\MpSite;
use App\Services\Analysis\HeadlessPageRenderer;
use App\Services\Monitoring\Extract\ListingIdentityVerifier;
use App\Services\Monitoring\Extract\LlmPriceExtractor;
use App\Services\Monitoring\Extract\StructuredPriceExtractor;
use App\Services\Monitoring\Web\FetchResult;
use App\Services\Monitoring\Web\PageFetcher;
use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\HtmlText;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Support\Facades\Log;

/**
 * Проверка цены известного листинга (docs/monitoring-f0-spec.md §7.4). Каркас —
 * PriceMonitor Content Engine, с правками уроков §8 дизайна:
 *  условный GET (304) → 404/мягкий 404 → капча/403 → headless-фолбэк → разметка →
 *  хеш блока товара (без LLM, если не изменился) → LLM → идентичность → санитария
 *  (скачок ×3 с повтором через 1–2 дня, выброс ×4 от медианы) → наблюдение.
 *  Цены нет — старую не держим. Сбои пишем в историю, повтор с отсрочкой.
 */
class ListingChecker
{
    private const CAPTCHA_RE = '~captcha|подтвердите, что вы не робот|checking your browser|access denied|ddos-guard|cf-chl|подозрительн\w+ трафик~u';
    private const NOT_FOUND_RE = '~товар не найден|страница не найдена|нет такой страницы|page not found|товар снят с продажи|ошибка 404~u';

    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly StructuredPriceExtractor $structured,
        private readonly ListingIdentityVerifier $verifier,
        private readonly MonitoringGovernor $governor,
        private readonly ?LlmPriceExtractor $llm = null,
        private readonly ?HeadlessPageRenderer $headless = null,
    ) {
    }

    public static function make(): self
    {
        return new self(
            app(PageFetcher::class),
            new StructuredPriceExtractor(),
            new ListingIdentityVerifier(),
            app(MonitoringGovernor::class),
            LlmPriceExtractor::make(),
            config('monitoring.fetch.headless') ? HeadlessPageRenderer::fromConfig() : null,
        );
    }

    /**
     * @return array{status: string, method: ?string, price: ?float, note: ?string, retry_after_ms?: int, cost_usd?: float}
     */
    public function check(MpListing $listing): array
    {
        $listing->loadMissing(['product.identifiers', 'site']);
        $product = $listing->product;
        $site = $listing->site;
        if (!$product || !$site) {
            return $this->result('skipped', null, null, 'no product/site');
        }
        if ($site->isBlocked()) {
            $listing->forceFill(['status' => MpListing::STATUS_BLOCKED, 'next_check_at' => $site->blocked_until])->save();

            return $this->result('blocked', null, null, 'site blocked until ' . $site->blocked_until);
        }
        if (!$this->governor->reserve('fetch_http')) {
            $listing->forceFill(['next_check_at' => now()->addDay()->startOfDay()->addHours(2)])->save();

            return $this->result('quota', null, null, 'fetch quota exhausted');
        }

        $useConditional = $listing->price !== null && $listing->status === MpListing::STATUS_ACTIVE;
        $res = $this->fetcher->fetch($listing->url, [
            'etag' => $useConditional ? $listing->etag : null,
            'last_modified' => $useConditional ? $listing->last_modified : null,
            'rate_ms' => (int) $site->rate_limit_ms,
        ]);

        if ($res->deferred()) {
            return $this->result('deferred', null, null, 'domain slot busy') + ['retry_after_ms' => $res->retryAfterMs];
        }
        if ($res->blockedByRobots) {
            return $this->fail($listing, 'robots.txt disallow', MpPriceObservation::STATUS_FAILED);
        }
        if ($res->notModified()) {
            $this->observe($listing, $listing->price, MpPriceObservation::STATUS_OK, 'not_modified', 'HTTP 304', $listing->content_hash);
            $this->schedule($listing, ['observed_at' => now(), 'fail_count' => 0]);

            return $this->result(MpPriceObservation::STATUS_OK, 'not_modified', $listing->price, 'HTTP 304');
        }
        if (in_array($res->status, [404, 410], true)) {
            return $this->gone($listing, MpPriceObservation::STATUS_GONE, "HTTP {$res->status}");
        }
        if (in_array($res->status, [401, 403, 429], true) || ($res->ok() && $this->looksLikeCaptcha($res->body))) {
            $site->block($res->ok() ? 'captcha' : "HTTP {$res->status}");
            $this->observe($listing, null, MpPriceObservation::STATUS_BLOCKED, 'none', $res->ok() ? 'captcha' : "HTTP {$res->status}");
            $listing->forceFill(['status' => MpListing::STATUS_BLOCKED, 'checked_at' => now(), 'next_check_at' => $site->blocked_until])->save();

            return $this->result(MpPriceObservation::STATUS_BLOCKED, null, null, 'blocked');
        }
        if (!$res->ok() || !$res->isHtml()) {
            return $this->fail($listing, $res->error ?? ($res->ok() ? 'not html' : "HTTP {$res->status}"));
        }

        $html = $res->body;
        $text = HtmlText::toText($html);
        $via = 'http';

        // JS-заглушка / сайт помечен как headless → рендер браузером.
        if ($this->headless && ($site->fetch_mode === 'headless' || mb_strlen($text) < (int) config('monitoring.fetch.http_min_chars', 300))) {
            if ($this->governor->reserve('fetch_headless')) {
                $rendered = $this->headless->renderHtml($listing->url);
                $renderedText = $rendered ? HtmlText::toText($rendered) : '';
                if (mb_strlen($renderedText) > mb_strlen($text)) {
                    $html = (string) $rendered;
                    $text = $renderedText;
                    $via = 'headless';
                    if ($site->fetch_mode !== 'headless') {
                        $site->forceFill(['fetch_mode' => 'headless'])->save();
                    }
                }
            }
        }

        // Мягкий 404: увели на корень/категорию или текст «товар не найден».
        $finalHash = UrlCanonicalizer::hash($res->finalUrl);
        if (($finalHash !== $listing->url_hash && UrlCanonicalizer::looksLikeNonProduct($res->finalUrl))
            || preg_match(self::NOT_FOUND_RE, mb_strtolower(mb_substr($text, 0, 3000)))) {
            return $this->gone($listing, MpPriceObservation::STATUS_SOFT404, 'soft404 → ' . mb_substr($res->finalUrl, 0, 150));
        }

        $codes = $product->articleCodes();
        $anchors = $product->articleValues() ?: array_slice(preg_split('/\s+/u', (string) $product->name) ?: [], 0, 3);
        $window = HtmlText::window($text, $anchors, (int) config('monitoring.llm.window_chars', 2500), (int) config('monitoring.llm.max_chars', 8000));
        $blockHash = sha1(preg_replace('/\s+/u', ' ', $window) ?? $window);

        $structured = $this->structured->extract($html, $codes);
        if ($structured !== null && $site->has_structured_markup !== true) {
            $site->forceFill(['has_structured_markup' => true])->save();
        }

        // Блок товара не изменился и разметки нет — цена та же, LLM не нужен.
        if ($structured === null && $listing->content_hash === $blockHash && $listing->checked_at !== null
            && $listing->status === MpListing::STATUS_ACTIVE) {
            $this->observe($listing, $listing->price, MpPriceObservation::STATUS_OK, 'unchanged', null, $blockHash);
            $this->schedule($listing, ['observed_at' => $listing->price !== null ? now() : $listing->observed_at, 'fail_count' => 0], $res, $finalHash);

            return $this->result(MpPriceObservation::STATUS_OK, 'unchanged', $listing->price, 'block unchanged');
        }

        $pageTokensHaveCode = $codes !== [] && array_intersect($codes, array_keys(ArticleNormalizer::tokens($window))) !== [];
        $needLlm = $structured === null || (!$structured['sku_matched'] && !$pageTokensHaveCode);
        $llm = null;
        $cost = 0.0;
        if ($needLlm && $this->llm) {
            if (!$this->governor->hasLlmBudget()) {
                $listing->forceFill(['next_check_at' => now()->addDay()->startOfDay()->addHours(2)])->save();

                return $this->result('quota', null, null, 'llm budget exhausted');
            }
            $llm = $this->llm->extract($product, $listing->url, $window);
            $cost = (float) $llm['cost_usd'];
            $this->governor->record('llm_call', 1, $cost);
            if ($llm['error'] !== null && $structured === null) {
                return $this->fail($listing, 'llm: ' . $llm['error'], MpPriceObservation::STATUS_FAILED, $cost);
            }
        }

        $identity = $this->verifier->verify($product, $window, $structured, $llm);
        if (!$identity['accepted']) {
            $this->observe($listing, null, MpPriceObservation::STATUS_MISMATCH, $structured ? 'structured' : 'llm', $identity['reason'], $blockHash, $cost);
            $listing->forceFill([
                'status' => MpListing::STATUS_MISMATCH,
                'match_evidence' => $identity['evidence'] ?: $identity['reason'],
                'checked_at' => now(),
                'next_check_at' => null,
            ])->save();

            return $this->result(MpPriceObservation::STATUS_MISMATCH, null, null, $identity['reason']) + ['cost_usd' => $cost];
        }

        $method = ($structured !== null && ($structured['sku_matched'] || $pageTokensHaveCode || $llm === null || $llm['found'] === false)) ? 'structured' : 'llm';
        $found = $method === 'structured' ? $structured : $llm;
        $price = $found['price'] ?? null;
        $fields = [
            'currency' => $price !== null ? (string) ($found['currency'] ?? 'RUB') : null,
            'price_kind' => $found['price_kind'] ?? ($price === null ? 'on_request' : 'retail'),
            'vat_included' => $found['vat_included'] ?? null,
            'price_unit' => $found['price_unit'] ?? null,
            'price_unit_qty' => $found['price_unit_qty'] ?? null,
            'min_order_qty' => $llm['min_order_qty'] ?? null,
            'availability' => $found['availability'] ?? ($llm['availability'] ?? null),
            'stock_qty' => $llm['stock_qty'] ?? null,
            'lead_days' => $llm['lead_days'] ?? null,
            'price_evidence' => mb_substr((string) ($method === 'llm' ? ($llm['evidence_price'] ?? '') : ($structured['source'] ?? '') . ' ' . ($structured['evidence'] ?? '')), 0, 400),
            'match_method' => $identity['method'],
            'match_confidence' => $identity['confidence'],
            'match_evidence' => $identity['evidence'],
        ];
        $siteFlags = ['publishes_prices' => $price !== null ? true : $site->publishes_prices];
        if (($fields['availability'] ?? null) && $fields['availability'] !== 'unknown') {
            $siteFlags['publishes_stock'] = true;
        }
        $site->forceFill($siteFlags)->save();

        // Цены нет («по запросу») — старую НЕ держим (урок CE), листинг жив.
        if ($price === null) {
            $this->observe($listing, null, MpPriceObservation::STATUS_NO_PRICE, $method, 'no price', $blockHash, $cost, $fields);
            $this->schedule($listing, $fields + ['price' => null, 'price_method' => $method, 'status' => MpListing::STATUS_ACTIVE, 'fail_count' => 0], $res, $finalHash, $blockHash);

            return $this->result(MpPriceObservation::STATUS_NO_PRICE, $method, null, 'no price') + ['cost_usd' => $cost];
        }

        // Скачок ×3 к текущей цене — только с повтором (через 1–2 дня, не через цикл).
        $reference = $listing->price;
        $markupFixesLlm = $method === 'structured' && $listing->price_method === 'llm';
        $ratio = (float) config('monitoring.sanity.jump_ratio', 3.0);
        if (!$markupFixesLlm && $reference && $reference > 0 && ($price / $reference > $ratio || $reference / $price > $ratio)) {
            $prev = MpPriceObservation::query()->where('listing_id', $listing->id)->orderByDesc('observed_at')->orderByDesc('id')->first();
            $tol = (float) config('monitoring.sanity.jump_confirm_tolerance', 0.05);
            $confirmed = $prev && $prev->status === MpPriceObservation::STATUS_SUSPICIOUS && $prev->price
                && abs($prev->price - $price) / $price <= $tol;
            if (!$confirmed) {
                $this->observe($listing, $price, MpPriceObservation::STATUS_SUSPICIOUS, $method, "jump from {$reference}", $blockHash, $cost, $fields);
                $listing->forceFill([
                    'status' => MpListing::STATUS_SUSPICIOUS,
                    'checked_at' => now(),
                    'next_check_at' => now()->addHours((int) config('monitoring.sanity.jump_recheck_hours', 36)),
                ])->save();

                return $this->result(MpPriceObservation::STATUS_SUSPICIOUS, $method, $price, "jump from {$reference}") + ['cost_usd' => $cost];
            }
        }

        $status = $this->isOutlier($listing, $price) ? MpListing::STATUS_SUSPICIOUS : MpListing::STATUS_ACTIVE;
        $this->observe($listing, $price, $status === MpListing::STATUS_ACTIVE ? MpPriceObservation::STATUS_OK : MpPriceObservation::STATUS_SUSPICIOUS,
            $method, $status === MpListing::STATUS_ACTIVE ? ($via === 'headless' ? 'headless' : null) : 'outlier vs median', $blockHash, $cost, $fields);
        $this->schedule($listing, $fields + [
            'price' => $price,
            'price_method' => $method,
            'status' => $status,
            'observed_at' => now(),
            'fail_count' => 0,
        ], $res, $finalHash, $blockHash);

        return $this->result($status === MpListing::STATUS_ACTIVE ? MpPriceObservation::STATUS_OK : MpPriceObservation::STATUS_SUSPICIOUS, $method, $price, $via) + ['cost_usd' => $cost];
    }

    /** Выброс ×4 от медианы при ≥4 ценах товара (флаг храним в БД, не считаем на лету). */
    private function isOutlier(MpListing $listing, float $price): bool
    {
        $others = MpListing::query()
            ->where('product_id', $listing->product_id)->where('id', '!=', $listing->id)
            ->where('status', MpListing::STATUS_ACTIVE)->where('bench', false)->whereNotNull('price')
            ->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', 'RUB'))
            ->pluck('price')->map(fn ($p) => (float) $p)->all();
        $all = array_merge($others, [$price]);
        if (count($all) < (int) config('monitoring.sanity.outlier_min_prices', 4)) {
            return false;
        }
        sort($all);
        $n = count($all);
        $median = $n % 2 ? $all[intdiv($n, 2)] : ($all[$n / 2 - 1] + $all[$n / 2]) / 2;
        $r = (float) config('monitoring.sanity.outlier_ratio', 4.0);

        return $median > 0 && ($price / $median > $r || $median / $price > $r);
    }

    private function gone(MpListing $listing, string $obsStatus, string $note): array
    {
        $this->observe($listing, null, $obsStatus, 'none', $note);
        $listing->forceFill([
            'status' => MpListing::STATUS_GONE,
            'price' => null,
            'checked_at' => now(),
            'next_check_at' => null,
        ])->save();

        return $this->result(MpPriceObservation::STATUS_GONE, null, null, $note);
    }

    private function fail(MpListing $listing, string $note, string $obsStatus = MpPriceObservation::STATUS_FAILED, float $cost = 0.0): array
    {
        $this->observe($listing, null, $obsStatus, 'none', $note, null, $cost);
        $fails = (int) $listing->fail_count + 1;
        $backoff = (array) config('monitoring.retry.backoff_minutes', [60, 360, 1440]);
        $max = (int) config('monitoring.retry.max_fails', 5);
        $listing->forceFill([
            'fail_count' => $fails,
            'status' => $fails >= $max ? MpListing::STATUS_FAILED : $listing->status,
            'checked_at' => now(),
            'next_check_at' => now()->addMinutes((int) ($backoff[min($fails - 1, count($backoff) - 1)] ?? 1440)),
        ])->save();
        Log::info('ListingChecker: fail', ['listing_id' => $listing->id, 'fails' => $fails, 'note' => $note]);

        return $this->result(MpPriceObservation::STATUS_FAILED, null, null, $note) + ['cost_usd' => $cost];
    }

    /** @param array<string,mixed> $fields */
    private function schedule(MpListing $listing, array $fields, ?FetchResult $res = null, ?string $finalHash = null, ?string $blockHash = null): void
    {
        $days = $this->refreshDaysFor((int) $listing->product_id);
        $jitter = (int) config('monitoring.schedule.jitter_pct', 10);
        $minutes = (int) round($days * 1440 * (1 + random_int(-$jitter, $jitter) / 100));
        $update = $fields + [
            'checked_at' => now(),
            'next_check_at' => now()->addMinutes($minutes),
        ];
        if ($res) {
            $update['etag'] = $res->headers['etag'] ?? null;
            $update['last_modified'] = $res->headers['last_modified'] ?? null;
        }
        if ($finalHash) {
            $update['final_url_hash'] = $finalHash;
        }
        if ($blockHash) {
            $update['content_hash'] = $blockHash;
        }
        $listing->forceFill($update)->save();
    }

    /**
     * Каденция переобхода = самая частая site_refresh_days среди активных программ,
     * где есть этот товар (иначе дефолт).
     */
    private function refreshDaysFor(int $productId): int
    {
        $days = \Illuminate\Support\Facades\DB::table('program_items as pi')
            ->join('monitoring_programs as p', 'p.id', '=', 'pi.program_id')
            ->where('pi.product_id', $productId)->where('pi.active', true)
            ->where('p.status', 'active')->whereNull('p.deleted_at')
            ->min('p.site_refresh_days');

        return max(1, (int) ($days ?: config('monitoring.default_refresh_days', 14)));
    }

    /** @param array<string,mixed> $fields */
    private function observe(MpListing $listing, ?float $price, string $status, string $method, ?string $note, ?string $hash = null, float $cost = 0.0, array $fields = []): void
    {
        MpPriceObservation::create([
            'listing_id' => $listing->id,
            'product_id' => $listing->product_id,
            'price' => $price,
            'currency' => $price !== null ? ($fields['currency'] ?? $listing->currency ?? 'RUB') : null,
            'price_kind' => $fields['price_kind'] ?? null,
            'vat_included' => $fields['vat_included'] ?? null,
            'price_unit' => $fields['price_unit'] ?? null,
            'availability' => $fields['availability'] ?? null,
            'method' => $method,
            'status' => $status,
            'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            'content_hash' => $hash,
            'cost_usd' => $cost,
            'observed_at' => now(),
        ]);
    }

    private function looksLikeCaptcha(string $body): bool
    {
        return strlen($body) < 30000 && preg_match(self::CAPTCHA_RE, mb_strtolower(mb_substr($body, 0, 20000))) === 1;
    }

    private function result(string $status, ?string $method, ?float $price, ?string $note): array
    {
        return ['status' => $status, 'method' => $method, 'price' => $price, 'note' => $note];
    }
}
