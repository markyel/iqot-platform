<?php

namespace App\Services\Api;

use App\Models\Api\ApiInbox;
use App\Models\Api\ApiSubmission;
use App\Models\Api\RequestItemStaging;
use App\Models\Api\RequestStaging;
use App\Models\BalanceHold;
use App\Models\ExternalRequest;
use Illuminate\Support\Facades\DB;

/**
 * Отмена submission по запросу клиента (§11.5).
 *
 * Правила:
 *  - финальные состояния (completed, cancelled) → no-op с ответом "already_final".
 *  - pre-promoted: все staging items → rejected(cancelled_by_client),
 *    holds release, rejected_summary дополняется, submission → status=cancelled.
 *  - post-promoted: reports.request → status=cancelled. Holds и сборы КП
 *    продолжают обычный lifecycle на стороне reports (по существующему механизму).
 *  - Discovery — не прерывается (§7.5).
 */
class SubmissionCancelService
{
    public function cancel(ApiSubmission $submission, ?string $reason): array
    {
        if (in_array($submission->status, ['completed', 'cancelled'], true)) {
            return ['status' => 'already_final', 'current' => $submission->status];
        }

        $wasPromoted = $submission->internal_request_id !== null;

        DB::connection(config('database.default'))->transaction(function () use ($submission, $reason) {
            $rejectedSummary = $submission->rejected_summary ?? [];
            if (!is_array($rejectedSummary)) {
                $rejectedSummary = [];
            }

            // Если staging не создан (submission ещё в inbox) — разбираем payload.
            $stagingItems = RequestItemStaging::query()
                ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
                ->whereNotIn('item_status', ['rejected', 'promoted'])
                ->get();

            if ($stagingItems->isEmpty()) {
                $inbox = ApiInbox::where('api_submission_id', $submission->id)->first();
                if ($inbox && is_array($inbox->raw_payload)) {
                    foreach (($inbox->raw_payload['items'] ?? []) as $it) {
                        $rejectedSummary[] = [
                            'client_ref' => $it['client_ref'] ?? null,
                            'name' => $it['name'] ?? '',
                            'reason' => 'cancelled_by_client',
                            'message' => $reason ?: 'Cancelled by client',
                            'retryable' => false,
                        ];
                    }
                    $inbox->delete();
                }
            } else {
                foreach ($stagingItems as $it) {
                    if ($it->balance_hold_id) {
                        /** @var BalanceHold|null $hold */
                        $hold = BalanceHold::find($it->balance_hold_id);
                        if ($hold && $hold->status === 'held') {
                            $hold->update([
                                'status' => 'released',
                                'released_at' => now(),
                            ]);
                        }
                    }

                    $rejectedSummary[] = [
                        'client_ref' => $it->client_item_ref,
                        'name' => $it->name,
                        'reason' => 'cancelled_by_client',
                        'message' => $reason ?: 'Cancelled by client',
                        'retryable' => false,
                    ];

                    $it->update(['balance_hold_id' => null]);
                    $it->delete();
                }
            }

            // Подстраховка: все не промоутнутые holds submission → released.
            BalanceHold::query()
                ->where('api_submission_id', $submission->id)
                ->where('status', 'held')
                ->whereNull('request_item_id') // не трогаем holds уже привязанные к reports.request_items
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                ]);

            $rejectedCount = count($rejectedSummary);

            $submission->update([
                'status' => 'cancelled',
                'stage' => 'finalised',
                'status_changed_at' => now(),
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'rejected_summary' => $rejectedSummary,
                'items_rejected' => $rejectedCount,
            ]);

            RequestStaging::query()
                ->where('api_submission_id', $submission->id)
                ->update(['stage' => 'finalised']);
        });

        // Если уже промоутнут — отмена на стороне reports (вне iqot-транзакции).
        if ($wasPromoted) {
            ExternalRequest::query()
                ->where('id', $submission->internal_request_id)
                ->update(['status' => ExternalRequest::STATUS_CANCELLED]);
        }

        return ['status' => 'cancelled', 'was_promoted' => $wasPromoted];
    }
}
