<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiClient;
use App\Models\Api\ApiSubmission;
use App\Services\Api\SubmissionReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Чтение submission'ов через публичный API (§11.3, §11.4, §11.9).
 */
class SubmissionReadController extends Controller
{
    public function __construct(private readonly SubmissionReadService $reader)
    {
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $submission = $this->resolve($request, $id);
        if (!$submission) {
            return $this->notFound($request);
        }

        $payload = $this->reader->toStatusArray($submission);
        $response = response()->json($payload);
        $this->applyPollingHeaders($response, $submission, $request);
        return $response;
    }

    public function items(Request $request, string $id): JsonResponse
    {
        $submission = $this->resolve($request, $id);
        if (!$submission) {
            return $this->notFound($request);
        }

        $items = $this->reader->itemsArray($submission);
        $response = response()->json(['items' => $items]);
        $this->applyPollingHeaders($response, $submission, $request);
        return $response;
    }

    public function report(Request $request, string $id): JsonResponse
    {
        $submission = $this->resolve($request, $id);
        if (!$submission) {
            return $this->notFound($request);
        }

        $report = $this->reader->reportArray($submission);
        $requestId = (string) $request->attributes->get('api_request_id');
        if ($report === null) {
            return response()->json([
                'error' => [
                    'code' => 'report_not_ready',
                    'message' => 'No items reached minimum offers threshold yet.',
                    'request_id' => $requestId,
                ],
            ], 409)->header('X-Request-Id', $requestId);
        }
        return response()->json($report)->header('X-Request-Id', $requestId);
    }

    /**
     * Загружает submission по публичному id ("sub_<ulid>" или просто "<ulid>")
     * и проверяет что он принадлежит текущему api_client.
     */
    private function resolve(Request $request, string $id): ?ApiSubmission
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');
        $externalId = str_starts_with($id, 'sub_') ? substr($id, 4) : $id;

        return ApiSubmission::query()
            ->where('api_client_id', $client->id)
            ->where('external_id', $externalId)
            ->first();
    }

    private function notFound(Request $request): JsonResponse
    {
        $requestId = (string) $request->attributes->get('api_request_id');
        return response()->json([
            'error' => [
                'code' => 'submission_not_found',
                'message' => 'Submission not found or does not belong to this API key.',
                'request_id' => $requestId,
            ],
        ], 404)->header('X-Request-Id', $requestId);
    }

    private function applyPollingHeaders(JsonResponse $response, ApiSubmission $submission, Request $request): void
    {
        $response->header('X-Request-Id', (string) $request->attributes->get('api_request_id'));

        if ($submission->status_changed_at) {
            $response->header('X-Status-Changed-At', $submission->status_changed_at->toIso8601String());
        }

        $next = $this->reader->nextCheckAfter($submission);
        if ($next) {
            $response->header('X-Next-Check-After', $next->format(\DateTimeInterface::ATOM));
        }

        if ($submission->stage) {
            $response->header('X-Submission-Stage', $submission->stage);
        }
    }
}
