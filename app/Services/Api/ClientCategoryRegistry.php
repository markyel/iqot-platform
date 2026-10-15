<?php

namespace App\Services\Api;

use App\Models\Api\ClientCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Регистрация категории заказчика (`client_category: {code, path[], metadata}`) — та же семантика,
 * что у SubmissionService::upsertClientCategories (общая таблица client_categories на api_client):
 * категории заявок и мониторинга одного клиента — одни и те же, и соответствие таксономии IQOT
 * (client_category_candidates) переиспользуется в обоих потоках.
 */
class ClientCategoryRegistry
{
    /** @param array{code?: string, path?: array<int,string>, metadata?: array<string,mixed>}|null $cat */
    public function upsert(int $apiClientId, ?array $cat): ?int
    {
        if (!$cat || empty($cat['code'])) {
            return null;
        }
        $code = mb_substr((string) $cat['code'], 0, 128);
        $path = is_array($cat['path'] ?? null) ? array_values(array_map('strval', $cat['path'])) : [];
        $leaf = $path ? (string) end($path) : $code;

        ClientCategory::query()->updateOrInsert(
            ['api_client_id' => $apiClientId, 'external_code' => $code],
            [
                'path_segments' => json_encode($path, JSON_UNESCAPED_UNICODE),
                'full_path' => Str::limit(implode(' / ', $path), 512, ''),
                'leaf_name' => Str::limit($leaf, 255, ''),
                'depth' => count($path),
                'raw_metadata' => isset($cat['metadata']) ? json_encode($cat['metadata'], JSON_UNESCAPED_UNICODE) : null,
                'last_seen_at' => now(),
            ]
        );
        ClientCategory::query()->where('api_client_id', $apiClientId)->where('external_code', $code)->update([
            'hit_count' => DB::raw('hit_count + 1'),
            'first_seen_at' => DB::raw('COALESCE(first_seen_at, CURRENT_TIMESTAMP)'),
        ]);

        return (int) ClientCategory::query()->where('api_client_id', $apiClientId)->where('external_code', $code)->value('id');
    }
}
