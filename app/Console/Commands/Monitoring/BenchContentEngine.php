<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MpIdentifier;
use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpProduct;
use App\Models\Monitoring\MpSite;
use App\Models\Monitoring\ProgramItem;
use App\Services\Monitoring\ListingChecker;
use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\DomainPolicy;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Стенд сверки проверщика листинга с Content Engine (docs/monitoring-f0-spec.md §10):
 * берём известные CE-предложения (выгрузка из БД CE в CSV), прогоняем ListingChecker на
 * их URL во ВРЕМЕННЫХ листингах (bench=1, в выдачу не идут) и сравниваем цену,
 * наличие, идентичность и метод. Это проверка извлечения, НЕ замер поиска (поиск Ф1 —
 * вслепую).
 *
 * CSV (с заголовком): lw_code,oem,brand,name,url,price,currency,availability,ce_method,observed_at
 */
class BenchContentEngine extends Command
{
    protected $signature = 'monitoring:bench-ce {csv} {--program= : программа пилота (товары по client_ref=lw_code)}
        {--limit=200} {--offset=0} {--cleanup : удалить bench-листинги после прогона}';

    protected $description = 'Мониторинг цен: стенд сверки проверщика листинга с ценами Content Engine';

    public function handle(): int
    {
        $path = (string) $this->argument('csv');
        if (!is_file($path)) {
            $this->error("CSV not found: {$path}");

            return self::FAILURE;
        }
        $rows = $this->readCsv($path);
        $rows = array_slice($rows, (int) $this->option('offset'), (int) $this->option('limit'));
        $checker = ListingChecker::make();

        $out = [];
        $stats = ['total' => 0, 'by_status' => [], 'by_method' => [], 'price_match' => 0, 'price_compared' => 0,
            'ce_struct_match' => 0, 'ce_struct_compared' => 0, 'cost_usd' => 0.0, 'no_product' => 0, 'blocked_domain' => 0];
        $benchIds = [];

        foreach ($rows as $i => $r) {
            $url = trim((string) ($r['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $stats['total']++;
            if (DomainPolicy::isBlocked($url)) {
                $stats['blocked_domain']++;
                $out[] = $r + ['iqot_status' => 'blocked_domain'];
                continue;
            }
            $product = $this->productFor($r);
            if (!$product) {
                $stats['no_product']++;
                $out[] = $r + ['iqot_status' => 'no_product'];
                continue;
            }

            $site = MpSite::forUrl($url);
            $listing = MpListing::query()->firstOrCreate(
                ['product_id' => $product->id, 'url_hash' => UrlCanonicalizer::hash($url)],
                ['site_id' => $site->id, 'url' => UrlCanonicalizer::canonicalize($url), 'bench' => true, 'status' => MpListing::STATUS_ACTIVE]
            );
            if ($listing->bench) {
                $benchIds[] = $listing->id;
            }

            $res = ['status' => 'deferred'];
            for ($try = 0; $try < 30 && ($res['status'] ?? '') === 'deferred'; $try++) {
                $res = $checker->check($listing->refresh());
                if (($res['status'] ?? '') === 'deferred') {
                    usleep((int) min(5_000_000, max(200_000, ($res['retry_after_ms'] ?? 1000) * 1000)));
                }
            }
            $listing->refresh();

            $ce = (float) ($r['price'] ?? 0);
            $iq = $listing->price;
            $match = null;
            if ($ce > 0 && $iq !== null && $res['status'] === 'ok') {
                $match = abs($iq - $ce) / $ce <= 0.01;
                $stats['price_compared']++;
                $stats['price_match'] += $match ? 1 : 0;
                if (($r['ce_method'] ?? '') === 'structured') {
                    $stats['ce_struct_compared']++;
                    $stats['ce_struct_match'] += $match ? 1 : 0;
                }
            }
            $stats['by_status'][$res['status']] = ($stats['by_status'][$res['status']] ?? 0) + 1;
            $m = $res['method'] ?? '-';
            $stats['by_method'][$m] = ($stats['by_method'][$m] ?? 0) + 1;
            $stats['cost_usd'] += (float) ($res['cost_usd'] ?? 0);

            $out[] = $r + [
                'iqot_status' => $res['status'],
                'iqot_method' => $res['method'] ?? null,
                'iqot_price' => $iq,
                'iqot_currency' => $listing->currency,
                'iqot_availability' => $listing->availability,
                'iqot_vat' => $listing->vat_included === null ? '' : ($listing->vat_included ? '1' : '0'),
                'iqot_unit' => $listing->price_unit,
                'iqot_kind' => $listing->price_kind,
                'iqot_match' => $listing->match_method,
                'iqot_note' => $res['note'] ?? null,
                'price_match' => $match === null ? '' : ($match ? '1' : '0'),
                'cost_usd' => $res['cost_usd'] ?? 0,
            ];
            if (($i + 1) % 10 === 0) {
                $this->line(sprintf('%d/%d …', $i + 1, count($rows)));
            }
        }

        $file = 'monitoring/bench/ce-' . now()->format('Ymd-His') . '.csv';
        Storage::disk('local')->put($file, $this->toCsv($out));

        $this->info('Bench report: storage/app/' . $file);
        $this->table(['metric', 'value'], [
            ['rows', $stats['total']],
            ['no product in program', $stats['no_product']],
            ['blocked domain (marketplace etc.)', $stats['blocked_domain']],
            ['status', json_encode($stats['by_status'])],
            ['method', json_encode($stats['by_method'])],
            ['price ±1% vs CE (all compared)', $this->pct($stats['price_match'], $stats['price_compared'])],
            ['price ±1% vs CE (CE from markup)', $this->pct($stats['ce_struct_match'], $stats['ce_struct_compared'])],
            ['LLM cost, $', round($stats['cost_usd'], 4)],
        ]);

        if ($this->option('cleanup') && $benchIds !== []) {
            MpListing::query()->whereIn('id', $benchIds)->where('bench', true)->delete();
            $this->info('Bench listings removed: ' . count($benchIds));
        }

        return self::SUCCESS;
    }

    /** Товар пилота: по client_ref = LW-код в программе, иначе по OEM. */
    private function productFor(array $r): ?MpProduct
    {
        if (($program = $this->option('program')) && ($lw = trim((string) ($r['lw_code'] ?? ''))) !== '') {
            $pid = ProgramItem::query()->where('program_id', (int) $program)->where('client_ref', $lw)->value('product_id');
            if ($pid) {
                return MpProduct::with('identifiers')->find($pid);
            }
        }
        $oem = ArticleNormalizer::normalize((string) ($r['oem'] ?? ''));
        if ($oem !== '') {
            $pid = MpIdentifier::query()->where('value_norm', $oem)->value('product_id');
            if ($pid) {
                return MpProduct::with('identifiers')->find($pid);
            }
        }

        return null;
    }

    /** @return array<int,array<string,string>> */
    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '\\') ?: [];
        $header = array_map(fn ($h) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $header);
        $rows = [];
        while (($r = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (count($r) === count($header)) {
                $rows[] = array_combine($header, $r);
            }
        }
        fclose($fh);

        return $rows;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function toCsv(array $rows): string
    {
        if ($rows === []) {
            return '';
        }
        $cols = [];
        foreach ($rows as $r) {
            foreach (array_keys($r) as $k) {
                $cols[$k] = true;
            }
        }
        $cols = array_keys($cols);
        $fh = fopen('php://memory', 'r+');
        fputcsv($fh, $cols, ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($fh, array_map(fn ($c) => is_scalar($r[$c] ?? null) || ($r[$c] ?? null) === null ? (string) ($r[$c] ?? '') : json_encode($r[$c]), $cols), ',', '"', '\\');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return (string) $csv;
    }

    private function pct(int $a, int $b): string
    {
        return $b > 0 ? sprintf('%d/%d (%.1f%%)', $a, $b, 100 * $a / $b) : '—';
    }
}
