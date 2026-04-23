<?php

namespace App\Services\Api;

use App\Models\Api\ApiSubmission;
use App\Models\Api\RequestItemStaging;
use App\Models\ApplicationDomain;
use App\Models\ExternalOffer;
use App\Models\ExternalRequestItem;
use App\Models\ProductType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Агрегация данных submission из iqot + reports для GET-эндпоинтов (§11.3, §11.4, §11.9).
 *
 * Источники данных по этапам жизни:
 *   - До промоушена: staging-позиции в iqot.
 *   - После промоушена: reports.request_items + reports.request_item_responses (offers).
 *   - rejected_summary всегда из iqot.api_submissions.
 */
class SubmissionReadService
{
    public const MINIMUM_OFFERS = 3;

    /**
     * Основной объект для GET /submissions/{id} (§11.3).
     */
    public function toStatusArray(ApiSubmission $submission): array
    {
        $items = $this->items($submission);

        $counts = [
            'total' => (int) $submission->items_total,
            'accepted' => (int) $submission->items_accepted,
            'rejected' => (int) $submission->items_rejected,
            'awaiting_suppliers' => $items->where('status', 'awaiting_suppliers')->count(),
            'dispatched' => $items->whereIn('status', ['dispatched', 'collecting', 'ready_minimum', 'completed'])->count(),
            'with_offers_minimum' => $items->whereIn('status', ['ready_minimum', 'completed'])->count(),
            'completed' => $items->where('status', 'completed')->count(),
        ];

        return [
            'submission_id' => 'sub_' . $submission->external_id,
            'client_ref' => $submission->client_ref,
            'client_organization_id' => $submission->client_organization_id,
            'status' => $submission->status,
            'stage' => $submission->stage,
            'created_at' => $submission->created_at?->toIso8601String(),
            'status_changed_at' => $submission->status_changed_at?->toIso8601String(),
            'ready_at' => $submission->ready_at?->toIso8601String(),
            'deadline' => $submission->deadline_at?->toIso8601String(),
            'counts' => $counts,
            'items' => $items->values()->all(),
            'rejected_items' => $this->rejectedSummary($submission),
        ];
    }

    /**
     * Только items — для GET /submissions/{id}/items (§11.4).
     *
     * @return array<int,array<string,mixed>>
     */
    public function itemsArray(ApiSubmission $submission): array
    {
        return $this->items($submission)->values()->all();
    }

    /**
     * Отчёт — для GET /submissions/{id}/report (§11.9).
     * Возвращает null если ни одна позиция не достигла minimum offers.
     */
    public function reportArray(ApiSubmission $submission): ?array
    {
        if ($submission->internal_request_id === null) {
            return null; // ещё не промоутнут — нет offers.
        }

        $reportsItems = ExternalRequestItem::query()
            ->where('request_id', $submission->internal_request_id)
            ->get();
        if ($reportsItems->isEmpty()) {
            return null;
        }

        $offersByItem = ExternalOffer::query()
            ->whereIn('request_item_id', $reportsItems->pluck('id'))
            ->get()
            ->groupBy('request_item_id');

        // Предзагрузка поставщиков одним запросом чтобы избежать N+1.
        $supplierIds = $offersByItem->flatten()->pluck('supplier_id')->filter()->unique()->all();
        $suppliers = $this->loadSuppliersMap($supplierIds);

        $enriched = [];
        $anyReady = false;
        foreach ($reportsItems as $ri) {
            $offers = ($offersByItem->get($ri->id) ?? collect())
                ->filter(fn (ExternalOffer $o) => in_array($o->status, ['received', 'processed'], true))
                ->sortBy('price_per_unit')
                ->values();
            if ($offers->count() >= self::MINIMUM_OFFERS) {
                $anyReady = true;
            }
            $productType = $ri->product_type_id
                ? ProductType::find($ri->product_type_id)
                : null;
            $domain = $ri->domain_id ? ApplicationDomain::find($ri->domain_id) : null;

            $enriched[] = [
                'item_id' => 'sub_' . $submission->external_id . '_item_' . str_pad((string) $ri->position_number, 3, '0', STR_PAD_LEFT),
                'reports_item_id' => $ri->id,
                'client_ref' => null, // в reports не хранится; можно добавить позже
                'name' => $ri->name,
                'quantity' => (float) $ri->quantity,
                'unit' => $ri->unit,
                'classification' => [
                    'product_type' => $productType ? ['id' => $productType->id, 'name' => $productType->name, 'slug' => $productType->slug] : null,
                    'domain' => $domain ? ['id' => $domain->id, 'name' => $domain->name, 'slug' => $domain->slug] : null,
                ],
                'offers_count' => $offers->count(),
                'minimum_threshold' => self::MINIMUM_OFFERS,
                'status' => $this->publicItemStatusFromOffers($offers->count()),
                'best_offer_by_price' => $offers->first() ? $this->offerToArray($offers->first(), $suppliers) : null,
                'all_offers' => $offers->map(fn ($o) => $this->offerToArray($o, $suppliers))->all(),
            ];
        }

        if (!$anyReady) {
            return null;
        }

        return [
            'submission_id' => 'sub_' . $submission->external_id,
            'client_ref' => $submission->client_ref,
            'generated_at' => now()->toIso8601String(),
            'status' => $submission->status,
            'deadline' => $submission->deadline_at?->toIso8601String(),
            'items' => $enriched,
        ];
    }

    /**
     * Формирует массив items с унифицированной схемой (для §11.3/§11.4).
     * Источник — staging (если есть) или reports (если продвинуто + staging уже удалён).
     */
    private function items(ApiSubmission $submission): Collection
    {
        $stagingItems = RequestItemStaging::query()
            ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
            ->orderBy('position_number')
            ->get();

        // Если staging пуст, а submission promoted — читаем из reports.
        if ($stagingItems->isEmpty() && $submission->internal_request_id) {
            return $this->itemsFromReports($submission);
        }
        if ($stagingItems->isEmpty()) {
            return collect();
        }

        $productTypeIds = $stagingItems->pluck('product_type_id')->filter()->unique();
        $domainIds = $stagingItems->pluck('domain_id')->filter()->unique();
        $promotedIds = $stagingItems->pluck('promoted_request_item_id')->filter()->unique();

        $productTypes = ProductType::whereIn('id', $productTypeIds)->get()->keyBy('id');
        $domains = ApplicationDomain::whereIn('id', $domainIds)->get()->keyBy('id');
        $reportsItems = $promotedIds->isNotEmpty()
            ? ExternalRequestItem::whereIn('id', $promotedIds)->get()->keyBy('id')
            : collect();
        $offerCounts = $this->offerCountsForIds($promotedIds->all());

        return $stagingItems->map(function (RequestItemStaging $it) use ($submission, $productTypes, $domains, $reportsItems, $offerCounts) {
            $offersCount = 0;
            if ($it->promoted_request_item_id && isset($reportsItems[$it->promoted_request_item_id])) {
                $offersCount = $offerCounts[$it->promoted_request_item_id] ?? 0;
            }
            return [
                'item_id' => 'sub_' . $submission->external_id . '_item_' . str_pad((string) $it->position_number, 3, '0', STR_PAD_LEFT),
                'client_ref' => $it->client_item_ref,
                'name' => $it->name,
                'quantity' => (float) $it->quantity,
                'unit' => $it->unit,
                'status' => $this->publicItemStatus($it, $offersCount),
                'classification' => [
                    'product_type' => $it->product_type_id && isset($productTypes[$it->product_type_id])
                        ? ['id' => (int) $it->product_type_id, 'name' => $productTypes[$it->product_type_id]->name, 'slug' => $productTypes[$it->product_type_id]->slug]
                        : null,
                    'domain' => $it->domain_id && isset($domains[$it->domain_id])
                        ? ['id' => (int) $it->domain_id, 'name' => $domains[$it->domain_id]->name, 'slug' => $domains[$it->domain_id]->slug]
                        : null,
                ],
                'offers_count' => $offersCount,
                'minimum_threshold' => self::MINIMUM_OFFERS,
                'report_available' => $offersCount >= self::MINIMUM_OFFERS,
                'collection_deadline' => $submission->deadline_at?->toIso8601String(),
            ];
        });
    }

    private function itemsFromReports(ApiSubmission $submission): Collection
    {
        $reportsItems = ExternalRequestItem::query()
            ->where('request_id', $submission->internal_request_id)
            ->orderBy('position_number')
            ->get();
        $productTypes = ProductType::whereIn('id', $reportsItems->pluck('product_type_id')->filter())->get()->keyBy('id');
        $domains = ApplicationDomain::whereIn('id', $reportsItems->pluck('domain_id')->filter())->get()->keyBy('id');
        $offerCounts = $this->offerCountsForIds($reportsItems->pluck('id')->all());

        return $reportsItems->map(function (ExternalRequestItem $ri) use ($submission, $productTypes, $domains, $offerCounts) {
            $offersCount = $offerCounts[$ri->id] ?? 0;
            return [
                'item_id' => 'sub_' . $submission->external_id . '_item_' . str_pad((string) $ri->position_number, 3, '0', STR_PAD_LEFT),
                'client_ref' => null,
                'name' => $ri->name,
                'quantity' => (float) $ri->quantity,
                'unit' => $ri->unit,
                'status' => $this->publicItemStatusFromOffers($offersCount),
                'classification' => [
                    'product_type' => $ri->product_type_id && isset($productTypes[$ri->product_type_id])
                        ? ['id' => (int) $ri->product_type_id, 'name' => $productTypes[$ri->product_type_id]->name, 'slug' => $productTypes[$ri->product_type_id]->slug]
                        : null,
                    'domain' => $ri->domain_id && isset($domains[$ri->domain_id])
                        ? ['id' => (int) $ri->domain_id, 'name' => $domains[$ri->domain_id]->name, 'slug' => $domains[$ri->domain_id]->slug]
                        : null,
                ],
                'offers_count' => $offersCount,
                'minimum_threshold' => self::MINIMUM_OFFERS,
                'report_available' => $offersCount >= self::MINIMUM_OFFERS,
                'collection_deadline' => $submission->deadline_at?->toIso8601String(),
            ];
        });
    }

    /**
     * @param array<int> $ids reports.request_items.id
     * @return array<int,int> map id => offers count
     */
    private function offerCountsForIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        return ExternalOffer::query()
            ->whereIn('request_item_id', $ids)
            ->whereIn('status', ['received', 'processed'])
            ->selectRaw('request_item_id, COUNT(*) as c')
            ->groupBy('request_item_id')
            ->pluck('c', 'request_item_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Маппит внутренний staging + offers в публичный item status (§11.7).
     */
    private function publicItemStatus(RequestItemStaging $it, int $offers): string
    {
        return match ($it->item_status) {
            'pending', 'classified' => 'pending',
            'accepted' => 'accepted',
            'awaiting_suppliers' => 'awaiting_suppliers',
            'pool_ready' => 'accepted', // ждёт промоушена
            'promoted' => $this->publicItemStatusFromOffers($offers),
            'rejected' => 'rejected',
            default => 'pending',
        };
    }

    private function publicItemStatusFromOffers(int $offers): string
    {
        if ($offers >= self::MINIMUM_OFFERS) {
            return 'ready_minimum';
        }
        if ($offers > 0) {
            return 'collecting';
        }
        return 'dispatched';
    }

    private function rejectedSummary(ApiSubmission $submission): array
    {
        $raw = $submission->rejected_summary;
        if (!is_array($raw)) {
            return [];
        }
        return $raw;
    }

    /**
     * @param array<int, array<string,mixed>> $suppliersMap id => {id, name, email, phone}
     */
    private function offerToArray(ExternalOffer $o, array $suppliersMap = []): array
    {
        $supplier = $suppliersMap[$o->supplier_id] ?? null;
        $vatIncluded = (bool) $o->price_includes_vat;

        return [
            'supplier_id' => $o->supplier_id,
            'supplier' => $supplier ?: ['id' => $o->supplier_id, 'name' => null, 'email' => null, 'phone' => null],
            'price_per_unit' => (float) $o->price_per_unit,
            'total_price' => (float) $o->total_price,
            'currency' => $o->currency,
            'price_includes_vat' => $vatIncluded,
            'vat_label' => $vatIncluded ? 'с НДС' : 'без НДС',
            'delivery_days' => $o->delivery_days,
            'payment_terms' => $o->payment_terms,
            'notes' => $o->notes,
            'received_at' => $o->response_received_at?->toIso8601String(),
        ];
    }

    /**
     * Подгружает справочные данные поставщиков (id => {id, name, email, phone}).
     *
     * @param array<int> $supplierIds
     * @return array<int, array{id:int, name:string|null, email:string|null, phone:string|null}>
     */
    private function loadSuppliersMap(array $supplierIds): array
    {
        if (empty($supplierIds)) {
            return [];
        }
        $rows = DB::connection('reports')->table('suppliers')
            ->whereIn('id', $supplierIds)
            ->get(['id', 'name', 'email', 'phone']);
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->id] = [
                'id' => (int) $r->id,
                'name' => $r->name,
                'email' => $r->email,
                'phone' => $r->phone,
            ];
        }
        return $map;
    }

    /**
     * Рекомендуемый интервал опроса (§12.3).
     */
    public function nextCheckAfter(ApiSubmission $submission): ?\DateTimeInterface
    {
        return match ($submission->stage) {
            'inbox_buffered', 'classifying', 'awaiting_moderation', 'in_moderation' => now()->addMinutes(2),
            'awaiting_suppliers' => now()->addMinutes(30),
            'dispatching' => now()->addMinutes(5),
            default => match ($submission->status) {
                'ready_minimum' => now()->addHour(),
                'completed', 'cancelled' => null,
                default => now()->addMinutes(15),
            },
        };
    }
}
