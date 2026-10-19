<?php

namespace App\Services\Requests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Управление заявками в админке (/manage/manage-requests) напрямую в reports.requests / request_items.
 * Заменяет n8n-воркфлоу «request-management» (n8n выключен 10.2026) с тем же форматом ответов, что ждут
 * контроллер и шаблоны: ['success' => bool, 'request' => [...], 'items' => [...], 'message' => ...].
 */
class RequestManagementService
{
    private const CONN = 'reports';

    /** Статусы, в которых заявку ещё можно править (как было в n8n). */
    public const EDITABLE = ['draft', 'new'];

    /** @param array<string,mixed> $data */
    public function createRequest(array $data): array
    {
        $items = array_values((array) ($data['items'] ?? []));
        if ($items === []) {
            return ['success' => false, 'message' => 'Нет позиций'];
        }
        try {
            $id = DB::connection(self::CONN)->transaction(function () use ($data, $items) {
                $now = now();
                $isCustomer = !empty($data['is_customer_request']);
                $id = (int) DB::connection(self::CONN)->table('requests')->insertGetId([
                    'user_id' => (int) ($data['user_id'] ?? config('services.requests.system_user_id', 1)),
                    'source' => 'admin',
                    'client_organization_id' => $isCustomer && !empty($data['client_organization_id']) ? (int) $data['client_organization_id'] : null,
                    'request_number' => $this->requestNumber(),
                    'title' => 'Заявка на ' . count($items) . ' ' . $this->plural(count($items)),
                    'status' => in_array($data['status'] ?? 'new', self::EDITABLE, true) ? $data['status'] : 'new',
                    'total_items' => count($items),
                    'items_with_offers' => 0,
                    'notes' => $data['notes'] ?? null,
                    'is_customer_request' => $isCustomer ? 1 : 0,
                    'customer_company' => $isCustomer ? ($data['customer_company'] ?? null) : null,
                    'customer_contact_person' => $isCustomer ? ($data['customer_contact_person'] ?? null) : null,
                    'customer_email' => $isCustomer ? ($data['customer_email'] ?? null) : null,
                    'customer_phone' => $isCustomer ? ($data['customer_phone'] ?? null) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                foreach ($items as $i => $it) {
                    DB::connection(self::CONN)->table('request_items')->insert([
                        'request_id' => $id,
                        'position_number' => $i + 1,
                        'name' => mb_substr((string) $it['name'], 0, 1000),
                        'brand' => isset($it['brand']) && $it['brand'] !== '' ? mb_substr((string) $it['brand'], 0, 255) : null,
                        'article' => isset($it['article']) && $it['article'] !== '' ? mb_substr((string) $it['article'], 0, 255) : null,
                        'quantity' => (float) ($it['quantity'] ?? 1),
                        'unit' => $it['unit'] ?? 'шт',
                        'category' => $it['category'] ?? null,
                        'product_type_id' => !empty($it['product_type_id']) ? (int) $it['product_type_id'] : null,
                        'domain_id' => !empty($it['domain_id']) ? (int) $it['domain_id'] : null,
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                return $id;
            });
        } catch (\Throwable $e) {
            Log::error('RequestManagementService: create failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Ошибка сохранения заявки: ' . $e->getMessage()];
        }

        return $this->getRequest($id);
    }

    public function getRequest(int $requestId): array
    {
        $req = DB::connection(self::CONN)->table('requests')->where('id', $requestId)->first();
        if (!$req) {
            return ['success' => false, 'message' => 'Заявка не найдена'];
        }
        $items = DB::connection(self::CONN)->table('request_items')->where('request_id', $requestId)
            ->orderBy('position_number')->orderBy('id')->get();

        return [
            'success' => true,
            'request' => (array) $req,
            'items' => $items->map(fn ($i) => array_merge((array) $i, ['quantity' => (float) $i->quantity == (int) $i->quantity ? (int) $i->quantity : (float) $i->quantity]))->all(),
        ];
    }

    /**
     * @param array<string,mixed> $filters status, is_customer_request, search, date_from, date_to
     * @param array<string,string> $sort
     * @param array{page?:int, per_page?:int} $pagination
     */
    public function listRequests(array $filters = [], array $sort = [], array $pagination = []): array
    {
        $page = max(1, (int) ($pagination['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($pagination['per_page'] ?? 20)));
        $q = DB::connection(self::CONN)->table('requests')
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', 'monitoring')); // тех. заявки мониторинга — не заявки клиентов
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (array_key_exists('is_customer_request', $filters)) {
            $q->where('is_customer_request', $filters['is_customer_request'] ? 1 : 0);
        }
        if (!empty($filters['search'])) {
            $s = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']) . '%';
            $q->where(fn ($w) => $w->where('request_number', 'like', $s)->orWhere('title', 'like', $s)->orWhere('customer_company', 'like', $s));
        }
        if (!empty($filters['date_from'])) {
            $q->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $q->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
        $total = (clone $q)->count();
        foreach ($sort ?: ['created_at' => 'desc'] as $col => $dir) {
            if (in_array($col, ['created_at', 'updated_at', 'id', 'status', 'request_number'], true)) {
                $q->orderBy($col, strtolower((string) $dir) === 'asc' ? 'asc' : 'desc');
            }
        }
        $rows = $q->forPage($page, $perPage)->get()->map(fn ($r) => (array) $r)->all();

        return [
            'success' => true,
            'requests' => $rows,
            'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => max(1, (int) ceil($total / $perPage))],
        ];
    }

    /** @param array<string,mixed> $data status, notes, customer_* */
    public function updateRequest(int $requestId, array $data): array
    {
        $req = DB::connection(self::CONN)->table('requests')->where('id', $requestId)->first(['status', 'is_customer_request']);
        if (!$req) {
            return ['success' => false, 'message' => 'Заявка не найдена'];
        }
        if (!in_array($req->status, self::EDITABLE, true)) {
            return ['success' => false, 'message' => 'Редактировать можно только черновик или заявку «В работу»'];
        }
        $upd = ['updated_at' => now()];
        foreach (['status', 'notes', 'customer_company', 'customer_contact_person', 'customer_email', 'customer_phone'] as $k) {
            if (array_key_exists($k, $data)) {
                $upd[$k] = $data[$k];
            }
        }
        if (isset($upd['status']) && !in_array($upd['status'], self::EDITABLE, true)) {
            unset($upd['status']);
        }
        DB::connection(self::CONN)->table('requests')->where('id', $requestId)->update($upd);

        return ['success' => true];
    }

    /**
     * Отмена: статус cancelled + ещё не отправленные письма батчей, целиком состоящих из позиций этой заявки.
     */
    public function cancelRequest(int $requestId, ?string $reason = null): array
    {
        $db = DB::connection(self::CONN);
        $req = $db->table('requests')->where('id', $requestId)->first(['status', 'notes']);
        if (!$req) {
            return ['success' => false, 'message' => 'Заявка не найдена'];
        }
        if (in_array($req->status, ['cancelled', 'completed'], true)) {
            return ['success' => false, 'message' => 'Заявка уже ' . ($req->status === 'cancelled' ? 'отменена' : 'завершена')];
        }
        $itemIds = $db->table('request_items')->where('request_id', $requestId)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $cancelledLetters = 0;
        $db->transaction(function () use ($db, $requestId, $req, $reason, $itemIds, &$cancelledLetters) {
            $note = trim((string) $req->notes . "\n" . 'Отменена ' . now('Europe/Moscow')->format('d.m.Y H:i') . ($reason ? ": {$reason}" : ''));
            $db->table('requests')->where('id', $requestId)->update(['status' => 'cancelled', 'notes' => $note, 'updated_at' => now()]);
            if ($itemIds === []) {
                return;
            }
            $batchIds = [];
            foreach ($db->table('email_batches')->where(function ($q) use ($itemIds) {
                foreach ($itemIds as $iid) {
                    $q->orWhereRaw('JSON_CONTAINS(request_items, ?)', [json_encode($iid)]);
                }
            })->get(['id', 'request_items']) as $b) {
                $ids = array_map('intval', (array) json_decode((string) $b->request_items, true));
                if ($ids !== [] && array_diff($ids, $itemIds) === []) {
                    $batchIds[] = (int) $b->id;
                }
            }
            if ($batchIds !== []) {
                $cancelledLetters = $db->table('email_queue')->whereIn('batch_id', $batchIds)->where('status', 'pending')
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
        });

        return ['success' => true, 'cancelled_letters' => $cancelledLetters];
    }

    /**
     * Вопросы поставщиков, ждущие ответа автора, по заявкам: [request_id => ['unanswered_questions' => n]].
     *
     * @param int[] $requestIds
     */
    public function questionsSummary(array $requestIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $requestIds)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', $ids);
        $rows = DB::connection(self::CONN)->select("SELECT ri.request_id rid, COUNT(DISTINCT sq.id) n
            FROM supplier_questions sq
            JOIN email_batches b ON b.id = sq.batch_id
            JOIN request_items ri ON ri.id = COALESCE(sq.request_item_id, CAST(JSON_UNQUOTE(JSON_EXTRACT(b.request_items, '$[0]')) AS UNSIGNED))
            WHERE sq.status = 'forwarded_to_author' AND ri.request_id IN ($in)
            GROUP BY ri.request_id");
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->rid] = ['unanswered_questions' => (int) $r->n];
        }

        return $out;
    }

    private function requestNumber(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $n = 'REQ-' . now('Europe/Moscow')->format('Ymd') . '-' . random_int(1000, 9999);
            if (!DB::connection(self::CONN)->table('requests')->where('request_number', $n)->exists()) {
                return $n;
            }
        }

        return 'REQ-' . now('Europe/Moscow')->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function plural(int $n): string
    {
        $m10 = $n % 10;
        $m100 = $n % 100;
        if ($m10 === 1 && $m100 !== 11) {
            return 'позицию';
        }
        if ($m10 >= 2 && $m10 <= 4 && ($m100 < 10 || $m100 >= 20)) {
            return 'позиции';
        }

        return 'позиций';
    }
}
