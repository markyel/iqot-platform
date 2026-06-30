<?php

namespace App\Console\Commands;

use App\Services\Generate\Batch;
use App\Services\Generate\CampaignEmailBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Досыл по обогащённому пулу (волна 2). По батчам, созданным >= followup_delay_days
 * назад, если по заявке мало откликов (< followup_min_responses ответивших):
 *   - «отпускаем» придержанный пул расширения (status=pending, wave=2, scheduled_at
 *     в далёком будущем — см. CampaignPersister::HELD_UNTIL → scheduled_at=NOW);
 *   - ГЕНЕРИМ письма НОВЫМ поставщикам, найденным discovery из Яндекс-таргетинга этого
 *     батча (campaign_discoveries) за прошедшие дни — пул к волне 2 уже обогащён.
 * Если откликов достаточно — отменяем придержанные и помечаем discovery обработанными.
 *
 * Идемпотентно: отпущенные/отменённые письма и emailed=1 discovery не переобрабатываются.
 */
class DispatchFollowupEmails extends Command
{
    private const CONN = 'reports';

    /** Порог, выше которого scheduled_at считается «придержанным» (HELD_UNTIL = 2037-12-31). */
    private const HELD_THRESHOLD = '2037-01-01 00:00:00';

    protected $signature = 'emails:dispatch-followup
        {--force : Запустить при выключенном флаге EMAILS_POOL_FOLLOWUP_ENABLED}';

    protected $description = 'Досыл по обогащённому пулу (волна 2: расширение + discovery) при малом отклике';

    public function handle(): int
    {
        if (!$this->option('force') && !config('services.email_pool.followup_enabled', true)) {
            $this->warn('emails:dispatch-followup выключен (EMAILS_POOL_FOLLOWUP_ENABLED=false).');
            return self::SUCCESS;
        }

        $delayDays = max(0, (int) config('services.email_pool.followup_delay_days', 2));
        $minResponses = max(1, (int) config('services.email_pool.followup_min_responses', 3));
        $cutoff = now()->subDays($delayDays);

        // Батчи-кандидаты: с придержанной волной 2 ИЛИ с необработанными discovery,
        // созданные достаточно давно.
        $held = DB::connection(self::CONN)->table('email_queue as q')
            ->join('email_batches as b', 'b.id', '=', 'q.batch_id')
            ->where('q.wave', 2)->where('q.status', 'pending')->where('q.scheduled_at', '>=', self::HELD_THRESHOLD)
            ->where('b.created_at', '<=', $cutoff)
            ->distinct()->pluck('q.batch_id')->all();
        $disc = DB::connection(self::CONN)->table('campaign_discoveries as cd')
            ->join('email_batches as b', 'b.id', '=', 'cd.batch_id')
            ->where('cd.emailed', 0)->where('b.created_at', '<=', $cutoff)
            ->distinct()->pluck('cd.batch_id')->all();
        $batchIds = array_values(array_unique(array_merge($held, $disc)));

        if ($batchIds === []) {
            $this->info('Нет батчей для досыла.');
            return self::SUCCESS;
        }

        $released = 0;
        $cancelled = 0;
        $enriched = 0;
        $now = now();
        $builder = new CampaignEmailBuilder();

        foreach ($batchIds as $batchId) {
            $responses = DB::connection(self::CONN)->table('email_queue')
                ->where('batch_id', $batchId)
                ->whereIn('status', ['replied', 'reply_processed', 'in_conversation'])
                ->count();

            $heldQ = fn () => DB::connection(self::CONN)->table('email_queue')
                ->where('batch_id', $batchId)->where('wave', 2)->where('status', 'pending')
                ->where('scheduled_at', '>=', self::HELD_THRESHOLD);

            if ($responses >= $minResponses) {
                $cancelled += $heldQ()->update([
                    'status' => 'cancelled',
                    'error_message' => "followup skipped: enough responses ({$responses})",
                    'updated_at' => $now,
                ]);
                // Откликов хватает — discovery по этому батчу не шлём, помечаем обработанными.
                DB::connection(self::CONN)->table('campaign_discoveries')
                    ->where('batch_id', $batchId)->where('emailed', 0)->update(['emailed' => 1]);
                Log::info('Followup: cancelled wave 2 (enough responses)', ['batch_id' => $batchId, 'responses' => $responses]);
                continue;
            }

            // Мало откликов → отпускаем расширение + досылаем новым из discovery.
            $released += $heldQ()->update(['scheduled_at' => $now, 'updated_at' => $now]);
            $enriched += $this->enrichFromDiscoveries($batchId, $builder);
            Log::info('Followup: released + enriched', ['batch_id' => $batchId, 'responses' => $responses]);
        }

        $this->info("Батчей: " . count($batchIds) . " | отпущено: {$released} | новых (discovery): {$enriched} | отменено: {$cancelled}");
        return self::SUCCESS;
    }

    /**
     * Сгенерировать письма (волна 2, status=pending, scheduled NOW) поставщикам,
     * найденным discovery для этого батча и ещё не отправленным. Контекст батча
     * берём из email_batches.gen_context. Возвращает число сгенерированных писем.
     */
    private function enrichFromDiscoveries(int $batchId, CampaignEmailBuilder $builder): int
    {
        $batchRow = DB::connection(self::CONN)->table('email_batches')->where('id', $batchId)->first();
        if (!$batchRow || empty($batchRow->gen_context)) {
            return 0;
        }

        // Новые поставщики этого батча, которым ещё не слали (дедуп против всей очереди батча).
        $alreadyEmailed = DB::connection(self::CONN)->table('email_queue')
            ->where('batch_id', $batchId)->pluck('supplier_id')->map(fn ($v) => (int) $v)->all();
        $alreadyEmailed = array_flip($alreadyEmailed);

        $discoveries = DB::connection(self::CONN)->table('campaign_discoveries')
            ->where('batch_id', $batchId)->where('emailed', 0)->get();
        if ($discoveries->isEmpty()) {
            return 0;
        }

        $batch = $this->rebuildBatch($batchRow);
        $now = now();
        $count = 0;

        foreach ($discoveries as $disc) {
            $sid = (int) $disc->supplier_id;
            if (isset($alreadyEmailed[$sid])) {
                DB::connection(self::CONN)->table('campaign_discoveries')->where('id', $disc->id)->update(['emailed' => 1]);
                continue;
            }
            $sup = DB::connection(self::CONN)->table('suppliers')->where('id', $sid)->where('is_active', 1)
                ->first(['id', 'name', 'email', 'contact_person']);
            if (!$sup || !$sup->email) {
                DB::connection(self::CONN)->table('campaign_discoveries')->where('id', $disc->id)->update(['emailed' => 1]);
                continue;
            }

            $supplier = [
                'id' => (int) $sup->id,
                'name' => $sup->name,
                'email' => $sup->email,
                'contact_person' => $sup->contact_person,
                // Сайт найден Яндексом → даём ссылку-намёк (письмо группы A).
                'found_urls' => $disc->source_url ? [['url' => $disc->source_url, 'item_id' => 0, 'item_name' => '']] : [],
            ];

            $email = $builder->build($batch, $supplier);
            $token = (string) ($email['tracking_token'] ?? '');
            DB::connection(self::CONN)->table('email_queue')->insert([
                'batch_id' => $batchId,
                'token' => $token,
                'sender_id' => (int) ($email['sender_id'] ?? 0),
                'supplier_id' => $sid,
                'from_email' => (string) ($email['from_email'] ?? ''),
                'to_email' => (string) ($email['to_email'] ?? ''),
                'subject' => (string) ($email['subject'] ?? ''),
                'body_html' => (string) ($email['body_html'] ?? ''),
                'tracking_token' => $token,
                'priority' => 0,
                'scheduled_at' => $now,
                'status' => 'pending',
                'wave' => 2,
            ]);
            DB::connection(self::CONN)->table('campaign_discoveries')->where('id', $disc->id)->update(['emailed' => 1]);
            $count++;
        }

        return $count;
    }

    /** Пересобрать Batch из email_batches.gen_context (достаточно для CampaignEmailBuilder). */
    private function rebuildBatch(object $batchRow): Batch
    {
        $ctx = json_decode((string) $batchRow->gen_context, true) ?: [];

        $batch = new Batch();
        $batch->batchId = (int) $batchRow->id;
        $batch->sender = $ctx['sender'] ?? [];
        $batch->emailTemplate = $ctx['email_template'] ?? [];
        $batch->aiBody = $ctx['ai_body'] ?? [];
        $batch->trackingToken = (string) $batchRow->tracking_token;
        $batch->requestNumbers = $ctx['request_numbers'] ?? [];
        $batch->requestIds = $ctx['request_ids'] ?? [];
        $batch->isCustomerRequest = (bool) ($ctx['is_customer_request'] ?? false);
        $batch->customerCompany = $ctx['customer']['company'] ?? null;
        $batch->customerContactPerson = $ctx['customer']['contact_person'] ?? null;
        $batch->customerEmail = $ctx['customer']['email'] ?? null;
        $batch->customerPhone = $ctx['customer']['phone'] ?? null;
        $batch->itemsCount = (int) ($ctx['items_count'] ?? 0);

        $itemIds = json_decode((string) $batchRow->request_items, true) ?: [];
        if ($itemIds !== []) {
            $batch->items = DB::connection(self::CONN)->table('request_items')
                ->whereIn('id', $itemIds)
                ->get(['id', 'position_number', 'name', 'brand', 'article', 'quantity', 'unit', 'category', 'description'])
                ->map(fn ($r) => (array) $r)->all();
        }

        return $batch;
    }
}
