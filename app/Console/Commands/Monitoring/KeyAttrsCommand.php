<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpProduct;
use App\Services\Monitoring\Extract\KeyAttributeExtractor;
use App\Services\Monitoring\MonitoringGovernor;
use App\Support\Monitoring\KeyAttributes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ключевые признаки товаров (сторона, исполнение, типоразмер, этаж, модель, бренд — M02384, 22.10.2026):
 *  extract — выделить из наименований товаров без признаков (один ИИ-вызов на товар);
 *  scan    — без ИИ: сколько активных цен противоречат признакам по адресу страницы и цитатам;
 *            с --apply такие листинги — на перепроверку сейчас;
 *  recheck — все листинги с ценой (или --product) — на перепроверку новым промптом в течение --days суток.
 */
class KeyAttrsCommand extends Command
{
    protected $signature = 'monitoring:key-attrs {action : extract|scan|recheck|show}
        {--product= : Один товар (id)}
        {--limit=0 : Не больше N товаров}
        {--force : extract: заново и для товаров с признаками}
        {--apply : scan: отправить противоречащие на перепроверку}
        {--days=3 : recheck: растянуть перепроверку на N суток}';

    protected $description = 'Мониторинг: ключевые признаки товаров из наименования и перепроверка цен по ним';

    public function handle(MonitoringGovernor $governor): int
    {
        return match ($this->argument('action')) {
            'extract' => $this->extract($governor),
            'scan' => $this->scan(),
            'recheck' => $this->recheck(),
            'show' => $this->show(),
            default => $this->error('action: extract|scan|recheck|show') ?? self::FAILURE,
        };
    }

    private function products()
    {
        $q = MpProduct::query()->whereNull('merged_into_id')->with('identifiers')->orderBy('id');
        if ($id = $this->option('product')) {
            $q->whereKey((int) $id);
        }

        return $q;
    }

    private function extract(MonitoringGovernor $governor): int
    {
        $ex = KeyAttributeExtractor::make();
        $q = $this->products();
        if (!$this->option('force')) {
            $q->whereNull('key_attrs_at');
        }
        if ($limit = (int) $this->option('limit')) {
            $q->limit($limit);
        }
        $done = $empty = $failed = 0;
        $cost = 0.0;
        foreach ($q->get() as $p) {
            try {
                $attrs = $ex->extract($p);
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("#{$p->id}: " . mb_substr($e->getMessage(), 0, 150));
                continue;
            }
            $p->forceFill(['key_attrs' => $attrs, 'key_attrs_at' => now()])->save();
            $c = $ex->lastCost();
            $cost += $c;
            $governor->record('llm_call', 1, $c);
            $done++;
            $empty += $attrs === [] ? 1 : 0;
            if ($this->option('product') || $this->output->isVerbose()) {
                $this->line("#{$p->id} {$p->name}\n   " . json_encode($attrs, JSON_UNESCAPED_UNICODE));
            }
        }
        $this->info(sprintf('Признаки: %d товаров (без признаков %d), сбоев %d, $%.4f', $done, $empty, $failed, $cost));

        return self::SUCCESS;
    }

    private function show(): int
    {
        foreach ($this->products()->whereNotNull('key_attrs_at')->limit((int) $this->option('limit') ?: 20)->get() as $p) {
            $this->line("#{$p->id} {$p->name}");
            foreach ((array) $p->key_attrs as $a) {
                $this->line("   {$a['key']} = {$a['value']} [{$a['kind']}]" . ($a['conflict'] ? ' ≠ ' . implode(', ', $a['conflict']) : ''));
            }
        }

        return self::SUCCESS;
    }

    private function scan(): int
    {
        $byKey = [];
        $ids = [];
        $q = $this->products()->whereNotNull('key_attrs_at');
        foreach ($q->get(['id', 'key_attrs']) as $p) {
            $attrs = (array) $p->key_attrs;
            if (!array_filter($attrs, fn ($a) => !empty($a['conflict']))) {
                continue;
            }
            $rows = MpListing::query()->where('product_id', $p->id)->where('bench', false)
                ->whereIn('status', [MpListing::STATUS_ACTIVE, MpListing::STATUS_SUSPICIOUS])->whereNotNull('price')
                ->get(['id', 'url', 'match_evidence', 'price_evidence']);
            foreach ($rows as $l) {
                $text = str_replace(['-', '_', '/', '.'], ' ', rawurldecode((string) parse_url($l->url, PHP_URL_PATH)))
                    . ' ' . $l->match_evidence . ' ' . $l->price_evidence;
                if (($hit = KeyAttributes::conflictIn($attrs, $text)) !== null) {
                    $ids[] = $l->id;
                    $k = explode(':', $hit)[0];
                    $byKey[$k] = ($byKey[$k] ?? 0) + 1;
                    if ($this->output->isVerbose()) {
                        $this->line("   #{$l->id} [{$hit}] " . mb_substr($l->url, 0, 120));
                    }
                }
            }
        }
        arsort($byKey);
        $this->info('Цен, противоречащих признакам (адрес/цитаты, без ИИ): ' . count($ids));
        foreach ($byKey as $k => $n) {
            $this->line("   {$k}: {$n}");
        }
        if ($this->option('apply') && $ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('mp_listings')->whereIn('id', $chunk)
                    ->update(['content_hash' => null, 'etag' => null, 'last_modified' => null, 'next_check_at' => now()]);
            }
            $this->info('Отправлено на перепроверку: ' . count($ids));
        }

        return self::SUCCESS;
    }

    private function recheck(): int
    {
        $days = max(1, (int) $this->option('days'));
        $q = DB::table('mp_listings')->where('bench', 0)->whereNotNull('price')
            ->whereIn('status', [MpListing::STATUS_ACTIVE, MpListing::STATUS_SUSPICIOUS]);
        if ($id = $this->option('product')) {
            $q->where('product_id', (int) $id);
        }
        $n = 0;
        foreach ($q->orderBy('id')->pluck('id')->chunk(500) as $chunk) {
            foreach ($chunk as $lid) {
                DB::table('mp_listings')->where('id', $lid)->update([
                    'content_hash' => null, 'etag' => null, 'last_modified' => null,
                    'next_check_at' => $id ? now() : now()->addMinutes(random_int(0, $days * 1440)),
                ]);
                $n++;
            }
        }
        $this->info("На перепроверку: {$n} листингов" . ($id ? ' (сейчас)' : " (в течение {$days} сут.)"));

        return self::SUCCESS;
    }
}
