<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Web\EgressPool;
use App\Support\Monitoring\VatDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * НДС по корзине сайта (24.11.2026, решение владельца: только автомат, без ручного признака). Для сайтов, где НДС не
 * виден на страницах товаров (mp_sites.vat_default пуст) и у нас ≥ --min-pages цен: браузер кладёт известный товар в
 * корзину и читает её (deploy/monitoring/cart-vat-probe.cjs; к оформлению не переходит, форм не заполняет);
 * VatDetector::fromCart → mp_sites.vat_cart. Проба разовая, повтор — раз в 90 дней или при неудаче — через 14.
 */
class VatCartProbeCommand extends Command
{
    protected $signature = 'monitoring:vat-cart-probe {--domain=*} {--min-pages=10} {--limit=10} {--force}';

    protected $description = 'Мониторинг: признак НДС сайта по корзине (браузер, разово на сайт)';

    public function handle(): int
    {
        if (!$this->option('force') && !config('monitoring.enabled')) {
            $this->warn('Мониторинг выключен (MONITORING_ENABLED=false). --force для ручного прогона.');

            return self::SUCCESS;
        }
        $domains = array_filter(array_map('trim', (array) $this->option('domain')));
        $q = MpSite::query();
        if ($domains !== []) {
            $q->whereIn('domain', $domains);
        } else {
            $q->whereNull('vat_default')
                ->where(fn ($x) => $x->whereNull('vat_cart_at')
                    ->orWhere(fn ($y) => $y->whereNull('vat_cart')->where('vat_cart_at', '<', now()->subDays(14)))
                    ->orWhere('vat_cart_at', '<', now()->subDays(90)))
                ->where(fn ($x) => $x->whereNull('blocked_until')->orWhere('blocked_until', '<', now()))
                ->whereIn('id', DB::table('mp_listings')->where('bench', false)->where('status', 'active')->where('price', '>', 0)
                    ->groupBy('site_id')->havingRaw('COUNT(*) >= ?', [max(1, (int) $this->option('min-pages'))])->select('site_id'));
        }
        $sites = $q->limit(max(1, (int) $this->option('limit')))->get();
        $pool = EgressPool::make();
        foreach ($sites as $site) {
            [$vat, $note] = $this->probe($site, $pool);
            $site->forceFill(['vat_cart' => $vat, 'vat_cart_at' => now(), 'vat_cart_note' => mb_substr($note, 0, 190)])->save();
            $this->line(sprintf('%-28s %s — %s', $site->domain, $vat === null ? 'не определено' : ($vat ? 'с НДС' : 'без НДС'), $note));
        }

        return self::SUCCESS;
    }

    /** @return array{0: ?bool, 1: string} */
    private function probe(MpSite $site, EgressPool $pool): array
    {
        // Две свежие карточки с ценой: если на первой нет кнопки «В корзину» — вторая.
        $rows = DB::table('mp_listings')->where('site_id', $site->id)->where('bench', false)->where('status', 'active')
            ->where('price', '>', 0)->where(fn ($q) => $q->whereNull('availability')->orWhere('availability', '<>', 'out_of_stock'))
            ->orderByDesc('observed_at')->limit(2)->get(['url', 'price']);
        $channel = $pool->pick($site->domain, $site->egress);
        $last = 'нет карточек с ценой';
        foreach ($rows as $r) {
            $home = storage_path('app/headless');
            $p = new Process(['node', base_path('deploy/monitoring/cart-vat-probe.cjs'), $r->url, (string) ($channel['proxy'] ?? '')],
                base_path(), ['HOME' => $home, 'CHROME_PATH' => (string) config('services.email_analysis.headless_chrome_path', '/usr/bin/google-chrome-stable')]);
            $p->setTimeout(120);
            try {
                $p->run();
            } catch (\Throwable $e) {
                $last = 'сбой браузера: ' . $e->getMessage();
                continue;
            }
            $res = json_decode($p->getOutput(), true) ?: [];
            if (!empty($res['error']) || empty($res['cart_text'])) {
                $last = 'корзина не прочитана: ' . ($res['error'] ?? 'нет текста корзины') . ($res['clicked'] ? " (нажато «{$res['clicked']}»)" : '');
                continue;
            }
            $vat = VatDetector::fromCart((string) $res['cart_text'], (float) $r->price, (float) config('monitoring.vat_percent', 22));
            $note = 'корзина ' . $res['cart_url'] . ($vat === null ? ' — НДС не указан' : '');
            if ($vat !== null) {
                return [$vat, $note];
            }
            $last = $note;
        }

        return [null, $last];
    }
}
