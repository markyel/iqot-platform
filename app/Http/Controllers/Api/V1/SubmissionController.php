<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateSubmissionRequest;
use App\Models\Api\ApiClient;
use App\Models\Api\ApiSubmission;
use App\Services\Api\SubmissionService;
use Illuminate\Http\JsonResponse;

class SubmissionController extends Controller
{
    public function __construct(private readonly SubmissionService $submissions)
    {
    }

    public function store(CreateSubmissionRequest $request): JsonResponse
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');
        $requestId = (string) $request->attributes->get('api_request_id');
        $idempotencyKey = $request->header('Idempotency-Key');

        $payload = $request->toPayload();

        $result = $this->submissions->create($client, $payload, $idempotencyKey);

        return match ($result['status']) {
            'created' => $this->acceptedResponse($result['submission'], 202, $requestId),
            'replayed' => $this->acceptedResponse($result['submission'], 200, $requestId),
            'conflict' => $this->errorResponse(
                409, 'idempotency_key_conflict',
                'Idempotency-Key reused with different payload.', $requestId
            ),
            'sender_not_configured' => $this->errorResponse(
                400, 'sender_not_configured',
                $result['message'] ?? 'Sender is not configured for this user / organization.',
                $requestId
            ),
            'insufficient_balance' => $this->errorResponse(
                402, 'insufficient_balance',
                $result['message'] ?? 'Insufficient balance to create submission.',
                $requestId,
                $result['details'] ?? null
            ),
            default => $this->errorResponse(500, 'internal_error', 'Unexpected service state.', $requestId),
        };
    }

    private function acceptedResponse(ApiSubmission $submission, int $status, string $requestId): JsonResponse
    {
        return response()->json([
            'submission_id' => 'sub_' . $submission->external_id,
            'status' => $submission->status,
            'stage' => $submission->stage,
            'client_ref' => $submission->client_ref,
            'items_count' => $submission->items_total,
            'created_at' => $submission->created_at?->toIso8601String(),
            'estimated_ready_at' => $submission->created_at?->copy()->addHour()?->toIso8601String(),
        ], $status)->header('X-Request-Id', $requestId);
    }

    private function errorResponse(
        int $status,
        string $code,
        string $message,
        string $requestId,
        ?array $details = null
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
            'request_id' => $requestId,
        ];
        if ($details !== null) {
            $error['details'] = $details;
        }
        return response()->json(['error' => $error], $status)
            ->header('X-Request-Id', $requestId);
    }
}
