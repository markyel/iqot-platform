<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Диагностический endpoint. Позволяет клиенту проверить, что ключ валиден
 * и тариф содержит api_access. Возвращает минимальную информацию о клиенте.
 */
class PingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');

        return response()->json([
            'ok' => true,
            'api_client_id' => $client->id,
            'user_id' => $client->user_id,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
