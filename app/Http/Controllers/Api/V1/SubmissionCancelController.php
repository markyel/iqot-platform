<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiClient;
use App\Models\Api\ApiSubmission;
use App\Services\Api\SubmissionCancelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionCancelController extends Controller
{
    public function __construct(private readonly SubmissionCancelService $canceller)
    {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');
        $requestId = (string) $request->attributes->get('api_request_id');
        $externalId = str_starts_with($id, 'sub_') ? substr($id, 4) : $id;

        /** @var ApiSubmission|null $submission */
        $submission = ApiSubmission::query()
            ->where('api_client_id', $client->id)
            ->where('external_id', $externalId)
            ->first();

        if (!$submission) {
            return response()->json([
                'error' => [
                    'code' => 'submission_not_found',
                    'message' => 'Submission not found.',
                    'request_id' => $requestId,
                ],
            ], 404)->header('X-Request-Id', $requestId);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $result = $this->canceller->cancel($submission, $data['reason'] ?? null);

        $submission->refresh();

        return response()->json([
            'submission_id' => 'sub_' . $submission->external_id,
            'status' => $submission->status,
            'cancelled_at' => $submission->cancelled_at?->toIso8601String(),
            'was_promoted' => $result['was_promoted'] ?? false,
        ])->header('X-Request-Id', $requestId);
    }
}
