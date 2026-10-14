<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiClient;
use App\Models\Monitoring\MonitoringProgram;
use App\Models\Monitoring\MpImport;
use App\Models\Monitoring\ProgramItem;
use App\Services\Monitoring\MonitoringPlanLimits;
use App\Services\Monitoring\ProgramItemImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Публичный API мониторинга цен (Ф0): программы и приём номенклатуры.
 * Формат ошибок — как у submissions: {error:{code,message,request_id,details?}}.
 */
class MonitoringProgramController extends Controller
{
    public function __construct(
        private readonly MonitoringPlanLimits $limits,
        private readonly ProgramItemImporter $importer,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $rid = $this->rid($request);
        $limits = $this->limits->forUser($client->user);
        if (!$limits['available']) {
            return $this->error(403, 'monitoring_not_available', 'Price monitoring is not included in the current tariff plan.', $rid);
        }

        $v = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:255',
            'price_mode' => 'sometimes|in:lite,hybrid',
            'target_freshness_days' => 'sometimes|integer|min:7|max:180',
            'site_refresh_days' => 'sometimes|integer|min:7|max:90',
            'settings' => 'sometimes|array',
        ]);
        if ($v->fails()) {
            return $this->error(422, 'validation_error', 'Invalid request.', $rid, $v->errors()->toArray());
        }

        $program = MonitoringProgram::create($v->validated() + [
            'api_client_id' => $client->id,
            'user_id' => $client->user_id,
            'status' => MonitoringProgram::STATUS_DRAFT,
        ]);

        return response()->json($this->programArray($program, $limits), 201)->header('X-Request-Id', $rid);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $program = $this->find($request, $id);
        if (!$program) {
            return $this->error(404, 'not_found', 'Program not found.', $this->rid($request));
        }

        return response()->json($this->programArray($program, $this->limits->forUser($program->user)))->header('X-Request-Id', $this->rid($request));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $rid = $this->rid($request);
        $program = $this->find($request, $id);
        if (!$program) {
            return $this->error(404, 'not_found', 'Program not found.', $rid);
        }
        $v = Validator::make($request->all(), [
            'name' => 'sometimes|string|min:2|max:255',
            'status' => 'sometimes|in:active,paused',
            'target_freshness_days' => 'sometimes|integer|min:7|max:180',
            'site_refresh_days' => 'sometimes|integer|min:7|max:90',
        ]);
        if ($v->fails()) {
            return $this->error(422, 'validation_error', 'Invalid request.', $rid, $v->errors()->toArray());
        }
        $data = $v->validated();
        if (($data['status'] ?? null) === 'paused') {
            $data['paused_reason'] = 'client';
        } elseif (($data['status'] ?? null) === 'active') {
            $data['paused_reason'] = null;
        }
        $program->update($data);

        return response()->json($this->programArray($program->refresh(), $this->limits->forUser($program->user)))->header('X-Request-Id', $rid);
    }

    public function storeItems(Request $request, string $id): JsonResponse
    {
        $rid = $this->rid($request);
        $program = $this->find($request, $id);
        if (!$program) {
            return $this->error(404, 'not_found', 'Program not found.', $rid);
        }
        $max = (int) config('monitoring.import.max_rows_per_call', 5000);
        $v = Validator::make($request->all(), [
            'items' => "required|array|min:1|max:{$max}",
            'items.*.client_ref' => 'required|string|max:191',
            'items.*.name' => 'required|string|min:2|max:1000',
            'items.*.brand' => 'nullable|string|max:255',
            'items.*.article' => 'nullable|string|max:255',
            'items.*.unit' => 'nullable|string|max:32',
            'items.*.attributes' => 'nullable|array',
            'items.*.active' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return $this->error(422, 'validation_error', 'Invalid items.', $rid, array_slice($v->errors()->toArray(), 0, 50, true));
        }

        $key = $request->header('Idempotency-Key');
        if ($key) {
            $existing = MpImport::query()->where('program_id', $program->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                return response()->json($this->importArray($existing), 200)->header('X-Request-Id', $rid);
            }
        }

        $import = $this->importer->queueRows($program, $request->input('items'), 'api', $key);

        return response()->json($this->importArray($import), 202)->header('X-Request-Id', $rid);
    }

    public function showImport(Request $request, string $id, string $importId): JsonResponse
    {
        $rid = $this->rid($request);
        $program = $this->find($request, $id);
        $import = $program ? MpImport::query()->where('program_id', $program->id)
            ->where('external_id', preg_replace('/^imp_/', '', $importId))->first() : null;
        if (!$import) {
            return $this->error(404, 'not_found', 'Import not found.', $rid);
        }

        return response()->json($this->importArray($import))->header('X-Request-Id', $rid);
    }

    public function items(Request $request, string $id): JsonResponse
    {
        $rid = $this->rid($request);
        $program = $this->find($request, $id);
        if (!$program) {
            return $this->error(404, 'not_found', 'Program not found.', $rid);
        }
        $q = ProgramItem::query()->where('program_id', $program->id)->orderBy('id');
        if ($status = $request->query('match_status')) {
            $q->where('match_status', $status);
        }
        if ($cursor = (int) $request->query('cursor', 0)) {
            $q->where('id', '>', $cursor);
        }
        $limit = min(1000, max(1, (int) $request->query('limit', 500)));
        $rows = $q->limit($limit + 1)->get();
        $next = $rows->count() > $limit ? $rows->slice(0, $limit)->last()->id : null;

        return response()->json([
            'items' => $rows->take($limit)->map(fn (ProgramItem $i) => [
                'client_ref' => $i->client_ref,
                'name' => $i->raw_name,
                'brand' => $i->raw_brand,
                'article' => $i->raw_article,
                'active' => $i->active,
                'match_status' => $i->match_status,
                'match_note' => $i->match_note,
                'last_priced_at' => $i->last_priced_at?->toIso8601String(),
            ])->values(),
            'next_cursor' => $next,
        ])->header('X-Request-Id', $rid);
    }

    /**
     * Цены позиций программы, изменившиеся с updated_since (курсор по id внутри окна).
     * Синхронизация клиента: запомнить server_time ответа → следующий запрос с
     * updated_since=server_time. У каждой цены — признаки источника (web / подтверждение
     * почтой / КП), ссылка на страницу поставщика (решение владельца 14.10.2026).
     */
    public function prices(Request $request, string $id): JsonResponse
    {
        $rid = $this->rid($request);
        $program = $this->find($request, $id);
        if (!$program) {
            return $this->error(404, 'not_found', 'Program not found.', $rid);
        }
        $serverTime = now();
        $since = $request->query('updated_since');
        try {
            $since = $since ? \Carbon\Carbon::parse((string) $since) : null;
        } catch (\Throwable) {
            return $this->error(422, 'validation_error', 'updated_since must be ISO 8601.', $rid);
        }
        $limit = min(500, max(1, (int) $request->query('limit', 200)));

        $q = ProgramItem::query()->where('program_id', $program->id)->where('active', true)
            ->whereNotNull('product_id')->orderBy('id');
        if ($since) {
            $q->where('prices_changed_at', '>=', $since);
        }
        if ($cursor = (int) $request->query('cursor', 0)) {
            $q->where('id', '>', $cursor);
        }
        $items = $q->limit($limit + 1)->get();
        $next = $items->count() > $limit ? $items->slice(0, $limit)->last()->id : null;
        $items = $items->take($limit);

        $listings = \App\Models\Monitoring\MpListing::query()->with('site')
            ->whereIn('product_id', $items->pluck('product_id')->unique())
            ->where('bench', false)
            ->whereIn('status', [\App\Models\Monitoring\MpListing::STATUS_ACTIVE, \App\Models\Monitoring\MpListing::STATUS_SUSPICIOUS])
            ->get()->groupBy('product_id');

        $out = $items->map(function (ProgramItem $i) use ($listings) {
            $sources = ($listings[$i->product_id] ?? collect())->map(fn ($l) => [
                'supplier_domain' => $l->site?->domain,
                'url' => $l->url,
                'price' => $l->price,
                'currency' => $l->currency,
                'price_kind' => $l->price_kind,
                'vat_included' => $l->vat_included,
                'price_unit' => $l->price_unit,
                'price_unit_qty' => $l->price_unit_qty,
                'availability' => $l->availability,
                'observed_at' => $l->observed_at?->toIso8601String(),
                'status' => $l->status,
                'flags' => [
                    'web_price' => true,
                    'email_confirmed' => $l->confirmed_at !== null,
                    'kp' => false,
                ],
                'site_trust_level' => (int) ($l->site?->trust_level ?? 0),
            ])->values();
            $priced = $sources->filter(fn ($s) => $s['price'] !== null && $s['status'] === 'active'
                && ($s['currency'] ?? 'RUB') === 'RUB' && $s['price_kind'] !== 'wholesale_tier');
            $best = $priced->sortBy('price')->first();

            return [
                'client_ref' => $i->client_ref,
                'name' => $i->raw_name,
                'prices_changed_at' => $i->prices_changed_at?->toIso8601String(),
                'last_priced_at' => $i->last_priced_at?->toIso8601String(),
                'best_price' => $best ? ['price' => $best['price'], 'currency' => $best['currency'], 'supplier_domain' => $best['supplier_domain'], 'url' => $best['url']] : null,
                'sources' => $sources,
            ];
        })->values();

        return response()->json([
            'items' => $out,
            'next_cursor' => $next,
            'server_time' => $serverTime->toIso8601String(),
        ])->header('X-Request-Id', $rid);
    }

    private function programArray(MonitoringProgram $p, array $limits): array
    {
        return [
            'program_id' => 'mon_' . $p->external_id,
            'name' => $p->name,
            'status' => $p->status,
            'price_mode' => $p->price_mode,
            'target_freshness_days' => $p->target_freshness_days,
            'site_refresh_days' => $p->site_refresh_days,
            'items' => [
                'total' => $p->items_total,
                'active' => $p->items_active,
                'matched' => $p->items_matched,
                'ambiguous' => $p->items_ambiguous,
                'covered' => $p->items_covered,
            ],
            'plan' => [
                'items_max' => $limits['max'],
                'items_included' => $limits['included'],
                'price_per_item' => $limits['price'],
            ],
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    private function importArray(MpImport $i): array
    {
        return [
            'import_id' => 'imp_' . $i->external_id,
            'status' => $i->status,
            'rows_total' => $i->rows_total,
            'rows_ok' => $i->rows_ok,
            'rows_failed' => $i->rows_failed,
            'errors' => $i->errors ?? [],
        ];
    }

    private function find(Request $request, string $id): ?MonitoringProgram
    {
        return MonitoringProgram::query()
            ->where('api_client_id', $this->client($request)->id)
            ->where('external_id', preg_replace('/^mon_/', '', $id))
            ->first();
    }

    private function client(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }

    private function rid(Request $request): string
    {
        return (string) $request->attributes->get('api_request_id');
    }

    private function error(int $status, string $code, string $message, string $rid, ?array $details = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message, 'request_id' => $rid];
        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status)->header('X-Request-Id', $rid);
    }
}
