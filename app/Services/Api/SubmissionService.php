<?php

namespace App\Services\Api;

use App\Models\Api\ApiClient;
use App\Models\Api\ApiInbox;
use App\Models\Api\ApiSubmission;
use App\Models\Api\ClientCategory;
use App\Models\Api\UserSender;
use App\Models\BalanceHold;
use App\Models\User;
use App\Models\UserTariff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Создаёт api_submission, inbox-запись, попозиционные balance_holds
 * с учётом идемпотентности (Idempotency-Key), sender selection и overdraft.
 *
 * Возвращает:
 *  - status 'created' — новая submission создана (HTTP 202).
 *  - status 'replayed' — найден существующий submission с тем же Idempotency-Key
 *    и идентичным payload; возвращаем тот же (HTTP 200).
 *  - status 'conflict' — тот же ключ, но payload отличается (HTTP 409).
 *  - status 'sender_not_configured' — HTTP 400.
 *  - status 'insufficient_balance' — HTTP 402.
 */
class SubmissionService
{
    /**
     * @param ApiClient $client
     * @param array<string,mixed> $payload нормализованный payload из FormRequest
     * @param string|null $idempotencyKey из header или null
     * @return array{status:string, submission?:ApiSubmission, message?:string, details?:array}
     */
    public function create(ApiClient $client, array $payload, ?string $idempotencyKey): array
    {
        $payloadHash = $this->hashPayload($payload);
        $idempotencyKey = $idempotencyKey ?: $this->generateServerIdempotencyKey();

        // 1. Идемпотентность: если ключ уже есть — сравнить payload и вернуть replay/conflict.
        $existing = ApiSubmission::query()
            ->where('api_client_id', $client->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            $existingHash = $this->loadExistingPayloadHash($existing);
            if ($existingHash !== null && hash_equals($existingHash, $payloadHash)) {
                return ['status' => 'replayed', 'submission' => $existing];
            }
            return ['status' => 'conflict'];
        }

        // 2. Sender selection (§9.3).
        $sender = $this->resolveSender($client->user_id, $payload['client_organization_id'] ?? null);
        if (!$sender) {
            return [
                'status' => 'sender_not_configured',
                'message' => 'No active sender found for this user / organization.',
            ];
        }

        $user = User::find($client->user_id);
        if (!$user) {
            // Маловероятно — api_client.user_id FK cascade. Защита на всякий случай.
            return ['status' => 'insufficient_balance', 'message' => 'User not found.'];
        }

        // 3. Расчёт per-position hold (§10.3) с учётом текущего items_used в активном тарифе.
        $itemCount = count($payload['items']);
        $positions = $this->calculatePerPositionPrices($user, $itemCount);
        $requiredHold = array_sum(array_column($positions, 'price'));

        // 4. Проверка баланса с overdraft (§10.4).
        $available = (float) $user->balance + ($requiredHold * ((float) $client->overdraft_percent / 100.0));
        if ($requiredHold > $available) {
            return [
                'status' => 'insufficient_balance',
                'message' => sprintf(
                    'Required hold %.2f exceeds available balance %.2f (overdraft %s%%).',
                    $requiredHold, $available, $client->overdraft_percent
                ),
                'details' => [
                    'required_hold' => $requiredHold,
                    'balance' => (float) $user->balance,
                    'overdraft_percent' => (float) $client->overdraft_percent,
                ],
            ];
        }

        // 5. Транзакция: создать submission + inbox + холды.
        try {
            $submission = DB::transaction(function () use (
                $client, $payload, $idempotencyKey, $sender, $positions
            ) {
                $externalId = $this->generateExternalId();

                /** @var ApiSubmission $submission */
                $submission = ApiSubmission::create([
                    'api_client_id' => $client->id,
                    'external_id' => $externalId,
                    'idempotency_key' => $idempotencyKey,
                    'client_ref' => $payload['client_ref'] ?? null,
                    'client_organization_id' => $payload['client_organization_id'] ?? null,
                    'sender_id' => $sender->id,
                    'deadline_at' => $payload['deadline_at'] ?? null,
                    'status' => 'accepted',
                    'stage' => 'inbox_buffered',
                    'status_changed_at' => now(),
                    'items_total' => count($payload['items']),
                    'items_accepted' => 0,
                    'items_rejected' => 0,
                ]);

                ApiInbox::create([
                    'api_submission_id' => $submission->id,
                    'raw_payload' => $payload,
                    'status' => 'pending',
                    'retry_count' => 0,
                ]);

                // Upsert client_categories. client_category_id будет привязываться к
                // позициям в InboxProcessingWorker; здесь фиксируем словарь per-client.
                $this->upsertClientCategories($client->id, $payload['items']);

                // Попозиционные holds. amount=0 позиции hold не получают.
                foreach ($positions as $i => $pos) {
                    if ($pos['price'] <= 0) {
                        continue;
                    }
                    BalanceHold::create([
                        'user_id' => $client->user_id,
                        'api_submission_id' => $submission->id,
                        'request_items_staging_id' => null, // появится после InboxProcessingWorker
                        'request_id' => null,
                        'request_item_id' => null,
                        'amount' => $pos['price'],
                        'status' => 'held',
                        'description' => sprintf(
                            'API submission %s, позиция %d (%s)',
                            $externalId, $i + 1,
                            Str::limit($pos['name'] ?? '', 60, '…')
                        ),
                    ]);
                }

                // Расход тарифных позиций — симметрично web-flow (UserRequestController):
                // увеличиваем items_used на полное число позиций submission. Rejected
                // позиции в модерации всё равно считаются потраченной попыткой (как в web).
                $activeTariff = UserTariff::query()
                    ->where('user_id', $client->user_id)
                    ->where('is_active', true)
                    ->first();
                if ($activeTariff) {
                    $activeTariff->useItems(count($payload['items']));
                }

                return $submission;
            });
        } catch (\Throwable $e) {
            throw $e;
        }

        return ['status' => 'created', 'submission' => $submission];
    }

    /**
     * Стабильный hash payload для сравнения при повторном Idempotency-Key.
     * Нормализуем: сортируем ключи на верхних уровнях. items порядок значим.
     */
    public function hashPayload(array $payload): string
    {
        $normalized = $this->normalize($payload);
        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            // Если список — сохраняем порядок.
            $isList = array_keys($value) === range(0, count($value) - 1);
            if ($isList) {
                return array_map(fn ($v) => $this->normalize($v), $value);
            }
            ksort($value);
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->normalize($v);
            }
            return $out;
        }
        return $value;
    }

    private function loadExistingPayloadHash(ApiSubmission $submission): ?string
    {
        // Inbox может быть уже удалён (после классификации). Тогда сравнить нечем;
        // возвращаем null — в таком случае считаем replay невозможным и возвращаем conflict.
        $inbox = ApiInbox::query()
            ->where('api_submission_id', $submission->id)
            ->first();
        if (!$inbox) {
            return null;
        }
        return $this->hashPayload(is_array($inbox->raw_payload) ? $inbox->raw_payload : []);
    }

    private function resolveSender(int $userId, ?int $clientOrganizationId): ?UserSender
    {
        if ($clientOrganizationId !== null) {
            return UserSender::query()
                ->where('user_id', $userId)
                ->where('client_organization_id', $clientOrganizationId)
                ->where('is_active', true)
                ->first();
        }

        $default = UserSender::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
        if ($default) {
            return $default;
        }

        // Если default не задан, но есть ровно один активный — используем его.
        $senders = UserSender::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get();
        return $senders->count() === 1 ? $senders->first() : null;
    }

    /**
     * Возвращает массив ['price' => float, 'name' => string] по каждой позиции.
     * Цена = 0 если позиция попадает в лимит тарифа, иначе price_per_item_over_limit.
     *
     * @return array<int,array{price:float,name:string}>
     */
    public function calculatePerPositionPrices(User $user, int $itemCount): array
    {
        /** @var UserTariff|null $tariff */
        $tariff = UserTariff::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with('tariffPlan')
            ->first();

        if (!$tariff || !$tariff->tariffPlan) {
            // Нет активного тарифа — все позиции попадают в 0 (middleware уже проверил api_access,
            // но тариф мог истечь между проверками; на всякий случай безопасный default).
            return array_fill(0, $itemCount, ['price' => 0.0, 'name' => '']);
        }

        $plan = $tariff->tariffPlan;
        $limit = $plan->items_limit; // null = безлимит
        $perItem = (float) $plan->price_per_item_over_limit;

        $out = [];
        for ($i = 0; $i < $itemCount; $i++) {
            $globalPosition = $tariff->items_used + $i + 1; // 1-based
            if ($limit === null || $globalPosition <= $limit) {
                $out[] = ['price' => 0.0, 'name' => ''];
            } else {
                $out[] = ['price' => $perItem, 'name' => ''];
            }
        }
        return $out;
    }

    private function upsertClientCategories(int $apiClientId, array $items): void
    {
        foreach ($items as $item) {
            $cat = $item['client_category'] ?? null;
            if (!$cat || empty($cat['code'])) {
                continue;
            }

            $path = $cat['path'] ?? [];
            $pathArr = is_array($path) ? array_values($path) : [];
            $fullPath = implode(' / ', array_map('strval', $pathArr));
            $leaf = $pathArr ? (string) end($pathArr) : (string) $cat['code'];
            $depth = count($pathArr);

            ClientCategory::query()->updateOrInsert(
                ['api_client_id' => $apiClientId, 'external_code' => (string) $cat['code']],
                [
                    'path_segments' => json_encode($pathArr, JSON_UNESCAPED_UNICODE),
                    'full_path' => Str::limit($fullPath, 512, ''),
                    'leaf_name' => Str::limit($leaf, 255, ''),
                    'depth' => $depth,
                    'raw_metadata' => isset($cat['metadata'])
                        ? json_encode($cat['metadata'], JSON_UNESCAPED_UNICODE)
                        : null,
                    'last_seen_at' => now(),
                    // first_seen_at и hit_count: на INSERT СУБД поставит default; на UPDATE не трогаем.
                ]
            );

            // Инкрементируем hit_count + first_seen_at если только что создали.
            ClientCategory::query()
                ->where('api_client_id', $apiClientId)
                ->where('external_code', (string) $cat['code'])
                ->update([
                    'hit_count' => DB::raw('hit_count + 1'),
                    'first_seen_at' => DB::raw('COALESCE(first_seen_at, CURRENT_TIMESTAMP)'),
                ]);
        }
    }

    private function generateExternalId(): string
    {
        // ULID 26 символов, длина колонки CHAR(26) в api_submissions.external_id.
        // Публичный префикс "sub_" добавляется на уровне сериализации (ApiSubmissionResource).
        return (string) Str::ulid();
    }

    private function generateServerIdempotencyKey(): string
    {
        return 'srv_' . (string) Str::uuid();
    }
}
