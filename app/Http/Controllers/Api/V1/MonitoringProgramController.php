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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Публичный API мониторинга цен (Ф0): программы и приём номенклатуры.
 * Формат ошибок — как у submissions: {error:{code,message,request_id,details?}}.
 */
class MonitoringProgramController extends Controller
{
    /** КП старше — не показываем (цена устарела). */
    private const KP_MAX_AGE_DAYS = 180;

    /** @var array<int, array<int,string>> product_id => OEM/MPN (кеш на запрос, для ItemScope) */
    private array $productCodes = [];

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
            'price_mode' => 'sometimes|in:lite,hybrid',
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
            // Все OEM-артикулы позиции (кросс-номера) — по ним ищем и только по ним показываем страницы.
            'items.*.oem_articles' => 'nullable|array|max:20',
            'items.*.oem_articles.*' => 'nullable|string|max:255',
            'items.*.unit' => 'nullable|string|max:32',
            'items.*.attributes' => 'nullable|array',
            'items.*.active' => 'nullable|boolean',
            // Категория заказчика — как в заявках: по ней (а не поштучно AI) товары относятся к таксономии IQOT.
            'items.*.client_category' => 'nullable|array',
            'items.*.client_category.code' => 'required_with:items.*.client_category|string|max:128',
            'items.*.client_category.path' => 'nullable|array|max:10',
            'items.*.client_category.path.*' => 'string|max:255',
            'items.*.client_category.metadata' => 'nullable|array',
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
                'oem_articles' => $i->oem_articles ?? [],
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

        $sources = $this->sourcesFor($items->pluck('product_id')->unique()->all());
        $out = $items->map(fn (ProgramItem $i) => $this->priceItem($i, $sources[$i->product_id] ?? collect()))->values();

        return response()->json([
            'items' => $out,
            'next_cursor' => $next,
            'server_time' => $serverTime->toIso8601String(),
        ])->header('X-Request-Id', $rid);
    }

    /** Программы клиента. */
    public function index(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $limits = $this->limits->forUser($client->user);
        $programs = MonitoringProgram::query()->where('api_client_id', $client->id)->orderByDesc('id')->get();

        return response()->json(['programs' => $programs->map(fn ($p) => $this->programArray($p, $limits))->values()])
            ->header('X-Request-Id', $this->rid($request));
    }

    /**
     * Карточка позиции: сопоставление, сводка цен, все источники с признаками и история цен
     * по каждому источнику за history_days (по умолчанию 90, максимум 365).
     */
    public function item(Request $request, string $id, string $clientRef): JsonResponse
    {
        $rid = $this->rid($request);
        $program = $this->find($request, $id);
        $item = $program ? ProgramItem::query()->where('program_id', $program->id)->where('client_ref', $clientRef)->first() : null;
        if (!$item) {
            return $this->error(404, 'not_found', 'Item not found.', $rid);
        }
        $data = [
            'client_ref' => $item->client_ref,
            'name' => $item->raw_name,
            'brand' => $item->raw_brand,
            'article' => $item->raw_article,
            'oem_articles' => $item->oem_articles ?? [],
            'active' => $item->active,
            'match_status' => $item->match_status,
            'match_note' => $item->match_note,
            'product' => null,
        ] + ($item->product_id ? [] : ['summary' => null, 'sources' => []]);

        if ($item->product_id) {
            $product = \App\Models\Monitoring\MpProduct::query()->with('identifiers')->find($item->product_id);
            $data['product'] = $product ? [
                'name' => $product->name,
                'brand' => $product->brand,
                // Кросс-номера, которые товару добавили другие клиенты, позиции не показываем.
                'identifiers' => $product->identifiers->whereIn('kind', ['oem', 'mpn', 'gtin'])
                    ->filter(fn ($x) => \App\Support\Monitoring\ItemScope::itemCodes($item) === [] || $x->kind === 'gtin'
                        || in_array((string) $x->value_norm, \App\Support\Monitoring\ItemScope::itemCodes($item), true))
                    ->map(fn ($x) => ['kind' => $x->kind, 'value' => $x->value])->values(),
            ] : null;
            $days = min(365, max(1, (int) $request->query('history_days', 90)));
            $sources = $this->sourcesFor([$item->product_id], true, $days)[$item->product_id] ?? collect();
            $data = array_merge($data, $this->priceItem($item, $sources));
        }

        return response()->json($data)->header('X-Request-Id', $rid);
    }

    /** Снять позицию с мониторинга (active=false; вернуть — повторной загрузкой с active=true). */
    public function destroyItem(Request $request, string $id, string $clientRef): JsonResponse
    {
        $rid = $this->rid($request);
        $program = $this->find($request, $id);
        $item = $program ? ProgramItem::query()->where('program_id', $program->id)->where('client_ref', $clientRef)->first() : null;
        if (!$item) {
            return $this->error(404, 'not_found', 'Item not found.', $rid);
        }
        $item->forceFill(['active' => false])->save();
        $program->refreshCounters();

        return response()->json(['client_ref' => $item->client_ref, 'active' => false])->header('X-Request-Id', $rid);
    }

    /**
     * Выгрузка программы в CSV (UTF-8 с BOM, разделитель «;» — открывается в Excel): строка на
     * позицию × источник с ценой (позиции без цены — одной строкой). Для 10–30 тыс. позиций —
     * потоком, без лимита страницы.
     */
    public function export(Request $request, string $id)
    {
        $rid = $this->rid($request);
        $program = $this->find($request, $id);
        if (!$program) {
            return $this->error(404, 'not_found', 'Program not found.', $rid);
        }

        return response()->streamDownload(function () use ($program) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['client_ref', 'name', 'brand', 'article', 'match_status', 'best_price', 'min_price', 'median_price', 'max_price',
                'sources_priced', 'supplier_domain', 'url', 'price', 'currency', 'vat_included', 'price_unit', 'availability',
                'observed_at', 'web_price', 'email_confirmed', 'kp', 'kp_price', 'site_trust_level'], ';');
            ProgramItem::query()->where('program_id', $program->id)->where('active', true)->orderBy('id')
                ->chunkById(500, function ($items) use ($out) {
                    $sources = $this->sourcesFor($items->pluck('product_id')->filter()->unique()->all());
                    foreach ($items as $i) {
                        $p = $i->product_id ? $this->priceItem($i, $sources[$i->product_id] ?? collect()) : ['summary' => null, 'sources' => collect()];
                        $s = $p['summary'] ?? null;
                        $base = [$i->client_ref, $i->raw_name, $i->raw_brand, $i->raw_article, $i->match_status,
                            $s['best_price'] ?? null, $s['min'] ?? null, $s['median'] ?? null, $s['max'] ?? null, $s['sources_priced'] ?? 0];
                        $priced = collect($p['sources'])->filter(fn ($x) => ($x['price'] !== null || $x['kp'] !== null) && $x['status'] === 'active');
                        if ($priced->isEmpty()) {
                            fputcsv($out, array_merge($base, array_fill(0, 13, null)), ';');
                            continue;
                        }
                        foreach ($priced as $x) {
                            fputcsv($out, array_merge($base, [$x['supplier_domain'], $x['url'], $x['price'], $x['currency'],
                                $x['vat_included'] === null ? null : (int) $x['vat_included'], $x['price_unit'], $x['availability'],
                                $x['observed_at'], (int) $x['flags']['web_price'], (int) $x['flags']['email_confirmed'], (int) $x['flags']['kp'],
                                $x['kp']['price'] ?? null, $x['site_trust_level']]), ';');
                        }
                    }
                });
            fclose($out);
        }, 'monitoring_' . $program->external_id . '_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Request-Id' => $rid,
        ]);
    }

    /**
     * Источники цен по товарам: листинги (active/suspicious) + последняя сверка с КП по листингу.
     *
     * @param array<int,int> $productIds
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, array<string,mixed>>>
     */
    private function sourcesFor(array $productIds, bool $withHistory = false, int $historyDays = 90)
    {
        if ($productIds === []) {
            return collect();
        }
        $this->productCodes += \App\Support\Monitoring\ItemScope::productCodes(array_diff($productIds, array_keys($this->productCodes)));
        $listings = \App\Models\Monitoring\MpListing::query()->with('site')->whereIn('product_id', $productIds)->where('bench', false)
            ->whereIn('status', [\App\Models\Monitoring\MpListing::STATUS_ACTIVE, \App\Models\Monitoring\MpListing::STATUS_SUSPICIOUS])
            ->get();
        // КП по листингу: из сверок и из КП-офферов Гибрида (в т.ч. по страницам «цена по запросу») — берём последнее.
        $kp = collect();
        foreach (\App\Models\Monitoring\MpVerification::query()->whereIn('listing_id', $listings->pluck('id'))->whereNotNull('kp_price')->get() as $v) {
            $kp->push(['listing_id' => $v->listing_id, 'price' => (float) $v->kp_price, 'currency' => $v->kp_currency,
                'vat_included' => $v->kp_vat_included, 'at' => $v->verified_at]);
        }
        $offers = \App\Models\Monitoring\MpKpOffer::query()->whereIn('product_id', $productIds)
            ->where('received_at', '>=', now()->subDays(self::KP_MAX_AGE_DAYS))->orderBy('received_at')->get();
        foreach ($offers->whereNotNull('listing_id') as $o) {
            $kp->push(['listing_id' => $o->listing_id, 'price' => (float) $o->price, 'currency' => $o->currency,
                'vat_included' => $o->vat_included, 'at' => $o->received_at]);
        }
        $kp = $kp->sortBy('at')->keyBy('listing_id'); // последнее по времени перекрывает
        $history = $withHistory
            ? \App\Models\Monitoring\MpPriceObservation::query()->whereIn('listing_id', $listings->pluck('id'))
                ->where('observed_at', '>=', now()->subDays($historyDays))->whereNotNull('price')
                ->whereIn('status', ['ok', 'suspicious'])->orderBy('observed_at')
                ->get(['listing_id', 'price', 'currency', 'method', 'observed_at'])->groupBy('listing_id')
            : collect();

        $rows = $listings->map(function ($l) use ($kp, $history, $withHistory) {
            $v = $kp[$l->id] ?? null;
            $row = [
                'source_type' => 'site',
                'supplier_domain' => $l->site?->domain,
                'url' => $l->url,
                'price' => $l->price !== null ? (float) $l->price : null,
                'currency' => $l->currency,
                'price_kind' => $l->price_kind,
                'vat_included' => $l->vat_included,
                'price_unit' => $l->price_unit,
                'price_unit_qty' => $l->price_unit_qty !== null ? (float) $l->price_unit_qty : null,
                'availability' => $l->availability,
                'observed_at' => $l->observed_at?->toIso8601String(),
                'status' => $l->status,
                'flags' => [
                    'web_price' => $l->price !== null,
                    'email_confirmed' => $l->confirmed_at !== null,
                    'kp' => $v !== null,
                ],
                'email_confirmed_at' => $l->confirmed_at?->toIso8601String(),
                'kp' => $v ? ['price' => $v['price'], 'currency' => $v['currency'], 'vat_included' => $v['vat_included'],
                    'received_at' => $v['at']?->toIso8601String()] : null,
                'site_trust_level' => (int) ($l->site?->trust_level ?? 0),
                'product_id' => $l->product_id,
                // Служебное (вырезается в priceItem): коды товара на странице — фильтр ItemScope.
                '_codes' => \App\Support\Monitoring\ItemScope::listingCodes($l->matched_codes, $l->match_method, $l->match_evidence),
            ];
            if ($withHistory) {
                $row['history'] = ($history[$l->id] ?? collect())->map(fn ($o) => [
                    'observed_at' => $o->observed_at?->toIso8601String(), 'price' => (float) $o->price,
                    'currency' => $o->currency, 'source' => $o->method === 'kp' ? 'kp' : 'web',
                ])->values();
            }

            return $row;
        });

        // КП поставщиков без страницы товара (запрос пулу IQOT) — отдельные источники без url.
        $poolOffers = $offers->whereNull('listing_id')->sortBy('received_at')->keyBy(fn ($o) => $o->product_id . '|' . $o->supplier_id);
        if ($poolOffers->isNotEmpty()) {
            $suppliers = DB::connection('reports')->table('suppliers')->whereIn('id', $poolOffers->pluck('supplier_id')->unique())
                ->get(['id', 'name', 'email', 'website'])->keyBy('id');
            $askProgram = DB::table('mp_asks')->whereIn('id', $poolOffers->pluck('ask_id')->filter()->unique())->pluck('program_id', 'id');
            foreach ($poolOffers as $o) {
                $s = $suppliers[$o->supplier_id] ?? null;
                $kpRow = ['price' => (float) $o->price, 'currency' => $o->currency, 'vat_included' => $o->vat_included,
                    'received_at' => $o->received_at?->toIso8601String()];
                $row = [
                    'source_type' => 'kp',
                    'supplier_domain' => $s ? self::supplierDomain($s) : null,
                    'url' => null,
                    'price' => null, 'currency' => $o->currency, 'price_kind' => null, 'vat_included' => $o->vat_included,
                    'price_unit' => null, 'price_unit_qty' => null, 'availability' => null,
                    'observed_at' => $o->received_at?->toIso8601String(), 'status' => 'active',
                    'flags' => ['web_price' => false, 'email_confirmed' => false, 'kp' => true],
                    'email_confirmed_at' => null, 'kp' => $kpRow, 'site_trust_level' => null,
                    'product_id' => $o->product_id,
                    // КП пула запрашивалось по позиции конкретной программы (её названию/артикулу).
                    '_program' => (int) ($askProgram[$o->ask_id] ?? 0),
                ];
                if ($withHistory) {
                    $row['history'] = [['observed_at' => $kpRow['received_at'], 'price' => $kpRow['price'], 'currency' => $o->currency, 'source' => 'kp']];
                }
                $rows->push($row);
            }
        }

        return $rows->groupBy('product_id')->map(fn ($g) => $g->map(fn ($r) => \Illuminate\Support\Arr::except($r, ['product_id']))->values());
    }

    /**
     * Источники, видимые позиции (ItemScope): у «узкой» позиции (товар несёт чужие кросс-номера) —
     * только страницы с её кодами и КП пула, запрошенные по её программе. Служебные поля — вырезаем.
     */
    private function scopeSources(ProgramItem $i, $sources)
    {
        $itemCodes = \App\Support\Monitoring\ItemScope::itemCodes($i);
        $productCodes = $this->productCodes[(int) $i->product_id] ?? [];
        $narrow = \App\Support\Monitoring\ItemScope::isNarrow($itemCodes, $productCodes);

        return collect($sources)->filter(function ($s) use ($i, $itemCodes, $productCodes, $narrow) {
            if (!$narrow) {
                return true;
            }
            if (($s['source_type'] ?? 'site') === 'kp') {
                return ($s['_program'] ?? 0) === (int) $i->program_id;
            }

            return \App\Support\Monitoring\ItemScope::allows($itemCodes, $productCodes, (array) ($s['_codes'] ?? []));
        })->map(fn ($s) => \Illuminate\Support\Arr::except($s, ['_codes', '_program']))->values();
    }

    /** Домен поставщика из КП: сайт, иначе домен почты (кроме бесплатной почты). */
    private static function supplierDomain(object $s): ?string
    {
        $free = ['mail.ru', 'gmail.com', 'yandex.ru', 'ya.ru', 'yandex.by', 'yandex.kz', 'bk.ru', 'list.ru', 'inbox.ru',
            'internet.ru', 'rambler.ru', 'mail.com', 'outlook.com', 'hotmail.com', 'yahoo.com', 'icloud.com'];
        if ($s->website) {
            $host = parse_url(str_contains($s->website, '//') ? $s->website : 'http://' . $s->website, PHP_URL_HOST);
            if ($host) {
                return preg_replace('~^www\.~', '', mb_strtolower($host));
            }
        }
        $d = $s->email && str_contains($s->email, '@') ? mb_strtolower(trim(substr(strrchr($s->email, '@'), 1))) : null;

        return $d && !in_array($d, $free, true) ? $d : null;
    }

    /** Позиция с ценами: сводка по сопоставимым ценам (RUB, розница/«от») + источники. */
    private function priceItem(ProgramItem $i, $sources): array
    {
        $sources = $this->scopeSources($i, $sources);
        // Цена источника для сводки: цена сайта, а где её нет («по запросу», пул) — цена КП.
        $priced = collect($sources)->map(function ($s) {
            $s['_price'] = $s['price'] ?? ($s['kp']['price'] ?? null);
            $s['_currency'] = $s['price'] !== null ? ($s['currency'] ?? 'RUB') : ($s['kp']['currency'] ?? 'RUB');

            return $s;
        })->filter(fn ($s) => $s['_price'] !== null && $s['status'] === 'active'
            && $s['_currency'] === 'RUB' && $s['price_kind'] !== 'wholesale_tier')->sortBy('_price')->values();
        $prices = $priced->pluck('_price')->all();
        $n = count($prices);
        $median = $n === 0 ? null : ($n % 2 ? $prices[intdiv($n, 2)] : round(($prices[$n / 2 - 1] + $prices[$n / 2]) / 2, 2));
        $best = $priced->first();

        return [
            'client_ref' => $i->client_ref,
            'name' => $i->raw_name,
            'prices_changed_at' => $i->prices_changed_at?->toIso8601String(),
            'last_priced_at' => $i->last_priced_at?->toIso8601String(),
            'best_price' => $best ? ['price' => $best['_price'], 'currency' => $best['_currency'], 'supplier_domain' => $best['supplier_domain'],
                'url' => $best['url'], 'price_source' => $best['price'] !== null ? 'web' : 'kp', 'flags' => $best['flags']] : null,
            'summary' => $n === 0 ? null : [
                'best_price' => $prices[0], 'min' => $prices[0], 'median' => $median, 'max' => $prices[$n - 1],
                'sources_priced' => $n, 'currency' => 'RUB',
            ],
            'sources' => collect($sources)->values(),
        ];
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
                'kp_price_multiplier' => $limits['kp_multiplier'],
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
