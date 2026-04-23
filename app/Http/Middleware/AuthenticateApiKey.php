<?php

namespace App\Http\Middleware;

use App\Models\Api\ApiClient;
use App\Services\Api\ApiKeyService;
use App\Services\Api\UserAccessService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Аутентификация публичного API по Bearer-ключу (§9.4).
 *
 * Порядок проверок:
 *  1. Извлекаем Bearer. Нет → 401 invalid_api_key.
 *  2. lookup по SHA-256. Не найдено → 401 invalid_api_key.
 *  3. revoked_at IS NOT NULL и < 30 дней → 401 key_revoked.
 *  4. IP не в whitelist → 401 ip_not_whitelisted.
 *  5. api_client.is_active = 0 → 403 api_client_disabled.
 *  6. !UserAccessService::hasApiAccess(user_id) → 403 api_access_denied.
 *  7. UPDATE last_used_at / request_count.
 *  8. Инжектим ApiClient в attributes: $request->attributes->set('api_client', $apiClient).
 */
class AuthenticateApiKey
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService,
        private readonly UserAccessService $userAccessService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('api_request_id', $requestId);

        $bearer = $this->extractBearer($request);
        if ($bearer === null) {
            return $this->error(401, 'invalid_api_key', 'Missing or malformed Authorization header.', $requestId);
        }

        $key = $this->apiKeyService->lookup($bearer);
        if ($key === null) {
            return $this->error(401, 'invalid_api_key', 'API key is invalid.', $requestId);
        }

        if ($key->revoked_at !== null) {
            if ($this->apiKeyService->isRevokedWithinGrace($key)) {
                return $this->error(401, 'key_revoked', 'API key was revoked.', $requestId);
            }
            // За пределами grace period — ключ должен был быть физически удалён cleanup-джобой.
            return $this->error(401, 'invalid_api_key', 'API key is invalid.', $requestId);
        }

        $ip = $request->ip() ?? '';
        if (!$this->apiKeyService->ipMatchesWhitelist($key->ip_whitelist, $ip)) {
            return $this->error(401, 'ip_not_whitelisted', 'Client IP is not in the allow-list.', $requestId);
        }

        /** @var ApiClient|null $client */
        $client = ApiClient::query()->find($key->api_client_id);
        if (!$client) {
            return $this->error(401, 'invalid_api_key', 'API client not found.', $requestId);
        }
        if (!$client->is_active) {
            return $this->error(403, 'api_client_disabled', 'API client is disabled.', $requestId);
        }

        if (!$this->userAccessService->hasApiAccess($client->user_id)) {
            return $this->error(403, 'api_access_denied', 'User tariff does not include API access.', $requestId);
        }

        // Обновление usage — best-effort, не блокируем запрос при ошибке.
        try {
            $this->apiKeyService->touchUsage($key, $ip);
        } catch (\Throwable $e) {
            // swallow
        }

        $request->attributes->set('api_client', $client);
        $request->attributes->set('api_key', $key);

        $response = $next($request);
        if ($response instanceof Response) {
            $response->headers->set('X-Request-Id', $requestId);
        }
        return $response;
    }

    private function extractBearer(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (!$header || !is_string($header)) {
            return null;
        }
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return null;
        }
        $token = trim($m[1]);
        return $token !== '' ? $token : null;
    }

    private function error(int $status, string $code, string $message, string $requestId): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => $requestId,
            ],
        ], $status)->header('X-Request-Id', $requestId);
    }
}
