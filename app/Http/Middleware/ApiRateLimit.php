<?php

namespace App\Http\Middleware;

use App\Models\Api\ApiClient;
use App\Models\Api\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate-limiter публичного API (§12.2). Параметризуется двумя аргументами:
 *   - bucket: произвольное имя корзины (например 'post_submissions').
 *   - limit: формат "N/unit" — N запросов за unit.
 *       unit: sec | min | hour | 15s
 *
 * Примеры:
 *   ApiRateLimit::class . ':post_submissions,10/min'
 *   ApiRateLimit::class . ':get_submission,1/15s'   // 1 раз в 15 сек на submission id
 *   ApiRateLimit::class . ':total,60/min'
 *
 * Для `get_submission` дополнительно учитывается `{id}` из роута.
 *
 * Счётчики хранятся в cache (file в dev, redis на проде). Атомарность на cache
 * add() + увеличение; small race допускается — не даёт точно 1 запрос в 15с под
 * конкурентной нагрузкой, но в рамках MVP это приемлемо.
 */
class ApiRateLimit
{
    private const UNITS = [
        'sec' => 1,
        '15s' => 15,
        'min' => 60,
        'hour' => 3600,
    ];

    public function handle(Request $request, Closure $next, string $bucket, string $limitSpec): Response
    {
        [$limit, $windowSec] = $this->parseLimit($limitSpec);

        $requestId = (string) $request->attributes->get('api_request_id');
        $keyId = $this->keyId($request);
        if ($keyId === null) {
            // Если не прошли auth middleware — не ограничиваем (defence in depth).
            return $next($request);
        }

        $scope = $this->scope($request, $bucket);
        $cacheKey = "api_rate:{$bucket}:{$keyId}:{$scope}";

        $remaining = $this->incrementAndCheck($cacheKey, $windowSec, $limit);

        if ($remaining < 0) {
            return $this->tooManyResponse($windowSec, $requestId);
        }

        $response = $next($request);
        if ($response instanceof Response) {
            $response->headers->set('X-RateLimit-Limit', (string) $limit);
            $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remaining));
            $response->headers->set('X-RateLimit-Reset', (string) (time() + $this->ttlRemaining($cacheKey, $windowSec)));
        }
        return $response;
    }

    /**
     * Возвращает оставшееся количество, либо -1 если превышено.
     */
    private function incrementAndCheck(string $key, int $windowSec, int $limit): int
    {
        // Cache::add возвращает true только если ключа ещё нет — тогда ставим 1 с TTL.
        // Если ключ уже есть — инкрементим.
        if (Cache::add($key, 1, $windowSec)) {
            return $limit - 1;
        }
        $count = Cache::increment($key);
        return $limit - (int) $count;
    }

    private function ttlRemaining(string $key, int $fallback): int
    {
        // Laravel Cache не всегда умеет отдать TTL; возвращаем fallback.
        return $fallback;
    }

    private function parseLimit(string $spec): array
    {
        if (!preg_match('#^(\d+)/(\w+)$#', $spec, $m)) {
            throw new \InvalidArgumentException('Bad rate limit spec: ' . $spec);
        }
        $limit = (int) $m[1];
        $unit = $m[2];
        if (!isset(self::UNITS[$unit])) {
            throw new \InvalidArgumentException('Unknown rate limit unit: ' . $unit);
        }
        return [$limit, self::UNITS[$unit]];
    }

    private function keyId(Request $request): ?int
    {
        /** @var ApiKey|null $key */
        $key = $request->attributes->get('api_key');
        if ($key) {
            return $key->id;
        }
        /** @var ApiClient|null $client */
        $client = $request->attributes->get('api_client');
        return $client?->id ?: null;
    }

    /**
     * Для get_submission привязываем счётчик к конкретному submission id.
     */
    private function scope(Request $request, string $bucket): string
    {
        if ($bucket === 'get_submission') {
            $id = (string) $request->route('id');
            return $id !== '' ? 'sub:' . $id : 'total';
        }
        return 'total';
    }

    private function tooManyResponse(int $retryAfter, string $requestId): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'rate_limit_exceeded',
                'message' => 'Rate limit exceeded. Retry later.',
                'request_id' => $requestId,
            ],
        ], 429)
        ->header('Retry-After', (string) $retryAfter)
        ->header('X-Request-Id', $requestId);
    }
}
