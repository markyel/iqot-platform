<?php

namespace App\Console\Commands;

use App\Services\Api\OpenAIClassifierClient;
use App\Services\Generate\Batch;
use App\Services\Generate\CampaignBodyGenerator;
use App\Services\Generate\CampaignSenderAssigner;
use App\Support\Senders\SenderPool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Письма, собранные с ЗАПАСНЫМ текстом (ИИ был недоступен — 21.10.2026 кончился бюджет OpenAI, все 511 батчей
 * утреннего плана получили один текст), пересобираются, как только ИИ снова доступен: для ящика генерируется
 * обычный текст, и в ещё не отправленных письмах заменяются только куски запасного текста — метки, позиции,
 * тема, подпись не трогаются. Отправленные письма не меняются.
 */
class RebodyFallbackEmails extends Command
{
    protected $signature = 'emails:rebody {--hours=48 : Батчи не старше N часов} {--limit=600 : Батчей за прогон} {--dry-run}';

    protected $description = 'Пересобрать ещё не отправленные письма с запасным текстом (после сбоя ИИ)';

    public function handle(): int
    {
        $db = DB::connection('reports');
        $since = now()->subHours(max(1, (int) $this->option('hours')));
        $dry = (bool) $this->option('dry-run');

        $q = $db->table('email_batches as b')->where('b.created_at', '>=', $since)
            ->whereExists(fn ($w) => $w->from('email_queue as eq')->whereColumn('eq.batch_id', 'b.id')->where('eq.status', 'pending'))
            ->where(function ($w) {
                $w->where('b.ai_model', 'fallback');
                foreach (CampaignBodyGenerator::FALLBACKS as $f) {
                    $w->orWhere('b.email_body_text', 'like', '%' . $f['introduction'] . '%');
                }
            });
        $monitoring = DB::table('mp_asks')->whereNotNull('batch_id')->where('created_at', '>=', $since)->pluck('batch_id')->map(fn ($v) => (int) $v)->all();
        if ($monitoring !== []) {
            $q->whereNotIn('b.id', $monitoring); // проверочные письма Гибрида — свои инструкции тела, вне этой пересборки
        }
        $batches = $q->orderBy('b.id')->limit((int) $this->option('limit'))->get(['b.id', 'b.sender_id', 'b.request_items', 'b.email_body_text']);
        if ($batches->isEmpty()) {
            $this->info('Писем с запасным текстом в очереди нет.');

            return self::SUCCESS;
        }

        $cfg = config('services.email_generate');
        $gen = new CampaignBodyGenerator(app(OpenAIClassifierClient::class), (string) $cfg['body_model'], (float) $cfg['body_temperature'], (int) $cfg['max_tokens']);
        $assigner = new CampaignSenderAssigner();
        $alias = SenderPool::aliasIds();
        $senders = [];
        $done = 0;
        $letters = 0;

        foreach ($batches as $b) {
            $ids = array_values(array_filter(array_map('intval', (array) json_decode((string) $b->request_items, true))));
            if ($ids === []) {
                continue;
            }
            $items = $db->table('request_items as ri')->join('requests as rq', 'rq.id', '=', 'ri.request_id')->whereIn('ri.id', $ids)
                ->get(['ri.*', 'rq.request_number', 'rq.is_customer_request', 'rq.customer_company', 'rq.customer_contact_person', 'rq.customer_email', 'rq.customer_phone'])
                ->map(fn ($x) => (array) $x)->all();
            if ($items === []) {
                continue;
            }
            $batch = new Batch();
            $batch->items = $items;
            $batch->itemsCount = count($items);
            $batch->domainIds = array_values(array_unique(array_filter(array_map(fn ($i) => (int) ($i['domain_id'] ?? 0), $items))));
            $batch->sender = $senders[(int) $b->sender_id] ??= $assigner->fullSender((int) $b->sender_id);
            $batch->purpose = isset($alias[(int) $b->sender_id]) ? 'alias' : null;
            $first = $items[0];
            $batch->isCustomerRequest = count(array_filter($items, fn ($i) => empty($i['is_customer_request']))) === 0;
            if ($batch->isCustomerRequest) {
                $batch->customerCompany = $first['customer_company'] ?? null;
                $batch->customerContactPerson = $first['customer_contact_person'] ?? null;
                $batch->customerEmail = $first['customer_email'] ?? null;
                $batch->customerPhone = $first['customer_phone'] ?? null;
            }

            $old = $this->fallbackVariant((string) $b->email_body_text);
            if ($old === null) {
                continue;
            }
            $new = $dry ? null : $gen->generate($batch);
            if (!$dry && ($batch->aiModel === 'fallback' || CampaignBodyGenerator::isFallback($new))) {
                $this->warn('ИИ по-прежнему недоступен — пересборка отложена.');
                break;
            }

            $rows = $db->table('email_queue')->where('batch_id', $b->id)->where('status', 'pending')->get(['id', 'body_html']);
            if ($dry) {
                $done++;
                $letters += $rows->count();
                continue;
            }
            foreach ($rows as $row) {
                $html = $this->replaceParts((string) $row->body_html, $old, $new);
                if ($html !== $row->body_html) {
                    $letters += $db->table('email_queue')->where('id', $row->id)->where('status', 'pending')->update(['body_html' => $html]);
                }
            }
            $db->table('email_batches')->where('id', $b->id)->update([
                'email_body_text' => trim(implode("\n\n", array_filter([$new['greeting'] ?? '', $new['introduction'] ?? '', $new['closing'] ?? '']))),
                'ai_model' => (string) $cfg['body_model'],
            ]);
            $done++;
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Батчей: {$done}, писем пересобрано: {$letters}");

        return self::SUCCESS;
    }

    /** Какой запасной вариант стоит в батче (по вступлению). */
    private function fallbackVariant(string $bodyText): ?array
    {
        foreach (CampaignBodyGenerator::FALLBACKS as $f) {
            if (str_contains($bodyText, $f['introduction'])) {
                return $f;
            }
        }

        return null;
    }

    /** Замена кусков запасного текста в абзацах письма (`>текст</p>`), без касания остального. */
    private function replaceParts(string $html, array $old, array $new): string
    {
        foreach (['greeting', 'introduction', 'request', 'closing'] as $k) {
            $to = trim((string) ($new[$k] ?? ''));
            if ($to !== '' && ($old[$k] ?? '') !== '' && $to !== $old[$k]) {
                $html = str_replace('>' . $old[$k] . '</p>', '>' . $to . '</p>', $html);
            }
        }
        if (!empty($new['found_intro']) && !empty($old['found_intro'])) {
            $html = str_replace($old['found_intro'], (string) $new['found_intro'], $html);
        }

        return $html;
    }
}
