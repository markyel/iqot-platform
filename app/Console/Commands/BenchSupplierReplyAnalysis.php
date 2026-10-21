<?php

namespace App\Console\Commands;

use App\Services\Analysis\DocumentTextExtractor;
use App\Services\Analysis\EmailBodyCleaner;
use App\Services\Analysis\SupplierReplyAnalyzer;
use App\Services\Analysis\WebPageFetcher;
use App\Services\Api\OpenAIClassifierClient;
use App\Services\Monitoring\LlmCostCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Стенд: разбор ответов поставщиков разными моделями на одних и тех же реальных письмах (21.10.2026, выбор модели
 * дешевле gpt-4o). Эталон — свежий прогон gpt-4o в тех же условиях (без догрузки страниц). Сравниваются тип письма,
 * найденные цены (позиция, цена ±1%, НДС), наличие вопросов; стоимость — по фактическому расходу токенов.
 * Ничего не пишет в боевые таблицы; результат — storage/app/bench/analysis-*.json.
 */
class BenchSupplierReplyAnalysis extends Command
{
    protected $signature = 'emails:bench-analysis {--limit=150} {--days=14}
        {--models=openai:gpt-4o,openai:gpt-4.1-mini,openai:gpt-5-mini,beget:gpt-6-luna,beget:deepseek-v4-flash,beget:gemini-3.8-flash}';

    protected $description = 'Сравнить модели разбора ответов поставщиков на реальных письмах (без записи в боевые таблицы)';

    public function handle(): int
    {
        $db = DB::connection('reports');
        $limit = max(10, (int) $this->option('limit'));
        $since = now()->subDays((int) $this->option('days'));
        // Срез: КП 40%, вопросы 20%, отказы 20%, прочее 20% — по оценке прошлого разбора.
        $quota = ['offer' => (int) round($limit * 0.4), 'question' => (int) round($limit * 0.2), 'rejection' => (int) round($limit * 0.2)];
        $quota['other'] = $limit - array_sum($quota);
        $ids = [];
        foreach ($quota as $type => $n) {
            $q = $db->table('email_messages')->where('direction', 'incoming')->where('ai_processed', 1)->where('created_at', '>=', $since)
                ->whereRaw("JSON_EXTRACT(ai_classification, '$.prefiltered') IS NULL");
            $t = "JSON_UNQUOTE(JSON_EXTRACT(ai_classification, '$.email_type'))";
            $type === 'other'
                ? $q->whereRaw("$t NOT IN ('offer','mixed','question','rejection')")
                : ($type === 'offer' ? $q->whereRaw("$t IN ('offer','mixed')") : $q->whereRaw("$t = ?", [$type]));
            $ids = array_merge($ids, $q->inRandomOrder()->limit($n)->pluck('id')->map(fn ($v) => (int) $v)->all());
        }
        $this->info('Писем в выборке: ' . count($ids));

        $ec = config('services.email_analysis');
        $models = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('models')))));
        $clients = [];
        foreach ($models as $spec) {
            [$provider, $model] = array_pad(explode(':', $spec, 2), 2, '');
            $clients[$spec] = [$this->client($provider), $model];
        }
        $cost = new LlmCostCalculator();
        $cleaner = new EmailBodyCleaner();
        $extractor = new DocumentTextExtractor((int) ($ec['doc_max_chars'] ?? 30000), (int) ($ec['pdf_max_bytes'] ?? 0));
        $fetcher = new WebPageFetcher(8000, 15, null, 200, 'Mozilla/5.0');
        $rows = [];

        foreach ($ids as $n => $id) {
            $m = $db->table('email_messages as em')->join('email_conversations as ec', 'ec.id', '=', 'em.conversation_id')
                ->leftJoin('suppliers as s', 's.id', '=', 'ec.supplier_id')->where('em.id', $id)
                ->first(['em.id', 'em.subject', 'em.body_text', 'em.body_html', 'em.from_email', 'ec.batch_id', 's.name as supplier_name']);
            if (!$m) {
                continue;
            }
            $attachments = $db->table('email_attachments')->where('email_message_id', $id)->get(['file_name', 'mime_type', 'local_path'])->all();
            $itemIds = array_values(array_filter(array_map('intval', (array) json_decode((string) $db->table('email_batches')->where('id', $m->batch_id)->value('request_items'), true))));
            $items = $itemIds === [] ? [] : $db->table('request_items')->whereIn('id', $itemIds)
                ->get(['id as item_id', 'position_number', 'name', 'brand', 'article', 'quantity', 'unit', 'category', 'description'])->all();
            $context = [
                'sender_name' => $m->supplier_name, 'sender_email' => $m->from_email, 'subject' => $m->subject,
                'body' => $cleaner->clean($m->body_text, $m->body_html), 'document_text' => $extractor->extractFromAttachments($attachments),
                'items' => $items, 'has_documents' => $attachments !== [],
            ];
            $row = ['message_id' => $id, 'has_documents' => $attachments !== [], 'results' => []];
            foreach ($clients as $spec => [$client, $model]) {
                $analyzer = new SupplierReplyAnalyzer($client, $fetcher, ['model' => $model, 'max_tokens' => (int) ($ec['max_tokens'] ?? 4096), 'fetch_urls' => false, 'fetch_max' => 0]);
                $t0 = microtime(true);
                $res = $analyzer->analyze($context);
                $u = $client->lastUsage() ?? [];
                $usd = $u ? ($u['cost_rub'] > 0 ? $u['cost_rub'] / 95 : $cost->usd((string) $u['model'], (int) $u['prompt_tokens'] - (int) ($u['cached_tokens'] ?? 0) / 2, (int) $u['completion_tokens'])) : 0;
                $row['results'][$spec] = [
                    'failed' => !empty($res['ai_failed']), 'sec' => round(microtime(true) - $t0, 1), 'usd' => round($usd, 5),
                    'email_type' => $res['email_type'] ?? null, 'questions' => count($res['questions'] ?? []),
                    'offers' => array_map(fn ($o) => ['item_id' => (int) ($o['item_id'] ?? 0), 'price' => $o['price_per_unit'] ?? null,
                        'vat' => $o['price_includes_vat'] ?? ($o['vat_included'] ?? null), 'currency' => $o['currency'] ?? null], $res['offers'] ?? []),
                ];
            }
            $rows[] = $row;
            if (($n + 1) % 10 === 0) {
                $this->line('  ' . ($n + 1) . ' писем');
            }
        }

        $base = $models[0];
        $this->table(['модель', 'сбоев', 'тип совпал', 'цены: найдено/эталон', 'цены совпали', 'лишние цены', 'вопросы совпали', '$ на письмо', 'сек'],
            array_map(fn ($spec) => $this->score($rows, $spec, $base), $models));
        $path = storage_path('app/bench/analysis-' . now()->format('Ymd-His') . '.json');
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info("Подробности: {$path}");

        return self::SUCCESS;
    }

    private function client(string $provider): OpenAIClassifierClient
    {
        $oc = config('services.openai_classifier');
        if ($provider === 'beget') {
            $b = OpenAIClassifierClient::backupConfig();

            return new OpenAIClassifierClient((string) ($b['base_url'] ?? ''), (string) ($b['api_key'] ?? ''), '', 'gpt-6-luna', 'gpt-6.1-sol', 120);
        }

        return new OpenAIClassifierClient(rtrim((string) $oc['base_url'], '/'), (string) $oc['api_key'], (string) ($oc['proxy_key'] ?? ''),
            (string) $oc['model_mini'], (string) $oc['model_full'], 120);
    }

    /** @return array<int,string> строка таблицы: сравнение модели с эталоном */
    private function score(array $rows, string $spec, string $base): array
    {
        $n = $failed = $typeOk = $qOk = 0;
        $basePrices = $found = $match = $extra = 0;
        $usd = $sec = 0.0;
        foreach ($rows as $r) {
            $c = $r['results'][$spec] ?? null;
            $b = $r['results'][$base] ?? null;
            if (!$c || !$b || $b['failed']) {
                continue;
            }
            $n++;
            $usd += $c['usd'];
            $sec += $c['sec'];
            if ($c['failed']) {
                $failed++;
                continue;
            }
            $typeOk += (int) ($c['email_type'] === $b['email_type'] || (in_array($c['email_type'], ['offer', 'mixed'], true) && in_array($b['email_type'], ['offer', 'mixed'], true)));
            $qOk += (int) (($c['questions'] > 0) === ($b['questions'] > 0));
            $bp = array_filter($b['offers'], fn ($o) => $o['price'] !== null);
            $cp = array_filter($c['offers'], fn ($o) => $o['price'] !== null);
            $basePrices += count($bp);
            $found += count($cp);
            foreach ($bp as $o) {
                foreach ($cp as $x) {
                    if ($x['item_id'] === $o['item_id'] && abs((float) $x['price'] - (float) $o['price']) <= max(1.0, 0.01 * (float) $o['price'])) {
                        $match++;
                        break;
                    }
                }
            }
            foreach ($cp as $x) {
                $hit = false;
                foreach ($bp as $o) {
                    $hit = $hit || $x['item_id'] === $o['item_id'];
                }
                $extra += (int) !$hit;
            }
        }
        $pct = fn ($a, $b) => $b > 0 ? round(100 * $a / $b) . '%' : '—';

        return [$spec, (string) $failed, $pct($typeOk, $n - $failed), "{$found}/{$basePrices}", $pct($match, $basePrices), (string) $extra,
            $pct($qOk, $n - $failed), $n > 0 ? number_format($usd / $n, 4) : '—', $n > 0 ? number_format($sec / $n, 1) : '—'];
    }
}
