<?php

namespace App\Jobs\Api;

use App\Models\ExternalRequest;
use App\Services\Api\PromotionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Heartbeat-джоб (§6.5).
 *
 * Ищет reports.requests с source='api' + api_submission_external_id, которые
 * появились >2 минут назад, и нет соответствующей связи в iqot.api_submissions.
 * Для каждой — повторяет Шаг 2 через PromotionService::reconcile().
 *
 * Scheduler: every 5 минут.
 */
class ReconcilePromotionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 1;

    public function __construct()
    {
        $this->onConnection('database');
    }

    public function handle(PromotionService $promoter): void
    {
        $cutoff = now()->subMinutes(2);

        $orphans = ExternalRequest::query()
            ->where('source', 'api')
            ->whereNotNull('api_submission_external_id')
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($orphans->isEmpty()) {
            return;
        }

        foreach ($orphans as $req) {
            $externalId = (string) $req->api_submission_external_id;
            // Если уже связано — пропускаем.
            $existing = \App\Models\Api\ApiSubmission::query()
                ->where('external_id', $externalId)
                ->value('internal_request_id');
            if ($existing === $req->id) {
                continue;
            }
            try {
                $promoter->reconcile($externalId, $req->id);
            } catch (\Throwable $e) {
                Log::error('ReconcilePromotionJob: item failed', [
                    'reports_request_id' => $req->id,
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
