<?php

namespace App\Services\Generate;

/**
 * Порт n8n-узла «Group Items» (УМНАЯ ГРУППИРОВКА v3.0 — двумерная классификация).
 *
 * Бьёт позиции заявок на батчи (≤ items_per_batch). Поддерживает обе системы
 * маршрутизации: НОВУЮ (product_type_id + domain_id) и СТАРУЮ (category→routing).
 * Именные заявки группируются per-request; обычные — кросс-заявочно по ключу
 * маршрутизации, затем чанкуются по лимиту.
 */
class CampaignItemGrouper
{
    private int $maxItemsPerBatch;
    private const MIN_ITEMS_PER_BATCH = 1;

    /** @var array<int,array<string,mixed>> categories: id,name,description,routing */
    private array $categories;

    private bool $useNewRouting;

    /**
     * @param array<int,array<string,mixed>> $categories из таблицы categories (is_active=1)
     */
    public function __construct(array $categories, bool $useNewRouting, int $maxItemsPerBatch = 5)
    {
        $this->categories = $categories;
        $this->useNewRouting = $useNewRouting;
        $this->maxItemsPerBatch = max(1, $maxItemsPerBatch);
    }

    /**
     * @param array<int,array<string,mixed>> $items позиции (как из request_items JOIN requests)
     * @return array<int,Batch>
     */
    public function group(array $items): array
    {
        /** @var array<int,Batch> $result */
        $result = [];

        // ── Разделяем на именные и обычные ──────────────────────────────────
        $customerItems = [];
        $normalItems = [];
        foreach ($items as $item) {
            if (!empty($item['is_customer_request'])) {
                $customerItems[] = $item;
            } else {
                $normalItems[] = $item;
            }
        }

        // ── Именные заявки (одинаково для обоих режимов) ─────────────────────
        $customerByRequest = [];
        foreach ($customerItems as $item) {
            $reqId = $item['request_id'];
            if (!isset($customerByRequest[$reqId])) {
                $customerByRequest[$reqId] = [
                    'request_id' => $reqId,
                    'request_number' => $item['request_number'] ?? null,
                    'client_organization_id' => $item['client_organization_id'] ?? null,
                    'customer_company' => $item['customer_company'] ?? null,
                    'customer_contact_person' => $item['customer_contact_person'] ?? null,
                    'customer_email' => $item['customer_email'] ?? null,
                    'customer_phone' => $item['customer_phone'] ?? null,
                    'items' => [],
                ];
            }
            $customerByRequest[$reqId]['items'][] = $item;
        }

        foreach ($customerByRequest as $req) {
            $itemCategories = $this->uniqueTruthy(array_map(
                static fn ($i) => $i['category'] ?? null,
                $req['items']
            ));

            $targetCategories = [];
            $productTypeIds = [];
            $domainIds = [];

            if ($this->useNewRouting) {
                $productTypeIds = $this->uniqueTruthy(array_map(
                    static fn ($i) => $i['product_type_id'] ?? null,
                    $req['items']
                ));
                $domainIds = $this->uniqueTruthy(array_map(
                    static fn ($i) => $i['domain_id'] ?? null,
                    $req['items']
                ));
                $targetCategories = ['NEW_ROUTING'];
            } else {
                foreach ($itemCategories as $cat) {
                    $targetCategories = array_merge($targetCategories, $this->getRouting($cat));
                }
                $targetCategories = array_values(array_unique($targetCategories));
                if (count($targetCategories) === 0) {
                    $targetCategories = ['Лифтовое оборудование', 'Все товары'];
                }
            }

            $batch = new Batch();
            $batch->requestIds = [$req['request_id']];
            $batch->requestNumbers = [$req['request_number']];
            $batch->isCustomerRequest = true;
            $batch->clientOrganizationId = $req['client_organization_id'] !== null ? (int) $req['client_organization_id'] : null;
            $batch->customerCompany = $req['customer_company'];
            $batch->customerContactPerson = $req['customer_contact_person'];
            $batch->customerEmail = $req['customer_email'];
            $batch->customerPhone = $req['customer_phone'];
            $batch->category = count($itemCategories) === 1 ? (string) $itemCategories[0] : 'mixed';
            $batch->targetCategories = $targetCategories;
            $batch->useNewRouting = $this->useNewRouting;
            $batch->productTypeIds = $productTypeIds;
            $batch->domainIds = $domainIds;
            $batch->items = $req['items'];
            $batch->itemsCount = count($req['items']);
            $result[] = $batch;
        }

        // ── Обычные заявки ──────────────────────────────────────────────────
        if ($this->useNewRouting) {
            $this->groupNormalNew($normalItems, $result);
        } else {
            $this->groupNormalOld($normalItems, $result);
        }

        return $result;
    }

    /**
     * НОВАЯ система: группировка по product_type_id + domain_id.
     *
     * @param array<int,array<string,mixed>> $normalItems
     * @param array<int,Batch> $result
     */
    private function groupNormalNew(array $normalItems, array &$result): void
    {
        $byTypeAndDomain = [];
        foreach ($normalItems as $item) {
            $key = $this->newRoutingKey($item);
            if (!isset($byTypeAndDomain[$key])) {
                $byTypeAndDomain[$key] = [
                    'product_type_id' => $item['product_type_id'] ?? null,
                    'domain_id' => $item['domain_id'] ?? null,
                    'category' => $item['category'] ?? null,
                    'items' => [],
                ];
            }
            $byTypeAndDomain[$key]['items'][] = $item;
        }

        foreach ($byTypeAndDomain as $group) {
            $groupItems = $group['items'];
            for ($i = 0; $i < count($groupItems); $i += $this->maxItemsPerBatch) {
                $batchItems = array_slice($groupItems, $i, $this->maxItemsPerBatch);

                $batch = new Batch();
                $batch->requestIds = $this->uniqueTruthy(array_map(static fn ($it) => $it['request_id'], $batchItems));
                $batch->requestNumbers = $this->uniqueValues(array_map(static fn ($it) => $it['request_number'] ?? null, $batchItems));
                $batch->isCustomerRequest = false;
                $batch->category = $group['category'] ?: 'Другое';
                $batch->targetCategories = ['NEW_ROUTING'];
                $batch->useNewRouting = true;
                $batch->productTypeIds = !empty($group['product_type_id']) ? [(int) $group['product_type_id']] : [];
                $batch->domainIds = !empty($group['domain_id']) ? [(int) $group['domain_id']] : [];
                $batch->items = $batchItems;
                $batch->itemsCount = count($batchItems);
                $result[] = $batch;
            }
        }
    }

    /**
     * СТАРАЯ система: группировка по category (large/medium/small).
     *
     * @param array<int,array<string,mixed>> $normalItems
     * @param array<int,Batch> $result
     */
    private function groupNormalOld(array $normalItems, array &$result): void
    {
        $byCategory = [];
        foreach ($normalItems as $item) {
            $cat = $item['category'] ?? 'Другое';
            $cat = ($cat === null || $cat === '') ? 'Другое' : $cat;
            $byCategory[$cat][] = $item;
        }

        $largeBatches = [];
        $mediumBatches = [];
        $smallItems = [];

        foreach ($byCategory as $category => $catItems) {
            if (count($catItems) > $this->maxItemsPerBatch) {
                $largeBatches[] = ['category' => $category, 'items' => $catItems];
            } elseif (count($catItems) >= self::MIN_ITEMS_PER_BATCH) {
                $mediumBatches[] = ['category' => $category, 'items' => $catItems];
            } else {
                foreach ($catItems as $item) {
                    $item['original_category'] = $category;
                    $smallItems[] = $item;
                }
            }
        }

        foreach ($largeBatches as $lb) {
            $category = $lb['category'];
            $catItems = $lb['items'];
            for ($i = 0; $i < count($catItems); $i += $this->maxItemsPerBatch) {
                $batchItems = array_slice($catItems, $i, $this->maxItemsPerBatch);
                $batch = new Batch();
                $batch->requestIds = $this->uniqueTruthy(array_map(static fn ($it) => $it['request_id'], $batchItems));
                $batch->requestNumbers = $this->uniqueValues(array_map(static fn ($it) => $it['request_number'] ?? null, $batchItems));
                $batch->isCustomerRequest = false;
                $batch->category = (string) $category;
                $batch->targetCategories = $this->getRouting((string) $category);
                $batch->useNewRouting = false;
                $batch->items = $batchItems;
                $batch->itemsCount = count($batchItems);
                $result[] = $batch;
            }
        }

        foreach ($mediumBatches as $mb) {
            $category = $mb['category'];
            $catItems = $mb['items'];
            $batch = new Batch();
            $batch->requestIds = $this->uniqueTruthy(array_map(static fn ($it) => $it['request_id'], $catItems));
            $batch->requestNumbers = $this->uniqueValues(array_map(static fn ($it) => $it['request_number'] ?? null, $catItems));
            $batch->isCustomerRequest = false;
            $batch->category = (string) $category;
            $batch->targetCategories = $this->getRouting((string) $category);
            $batch->useNewRouting = false;
            $batch->items = $catItems;
            $batch->itemsCount = count($catItems);
            $result[] = $batch;
        }

        if (count($smallItems) > 0) {
            usort($smallItems, static function ($a, $b) {
                $catA = $a['original_category'] ?? 'zzz';
                $catB = $b['original_category'] ?? 'zzz';
                return strcmp((string) $catA, (string) $catB);
            });

            for ($i = 0; $i < count($smallItems); $i += $this->maxItemsPerBatch) {
                $batchItems = array_slice($smallItems, $i, $this->maxItemsPerBatch);
                $batchCategories = $this->uniqueValues(array_map(static fn ($it) => $it['original_category'] ?? null, $batchItems));
                $batchCategory = count($batchCategories) === 1 ? (string) $batchCategories[0] : 'Смешанное';

                $allTarget = [];
                foreach ($batchCategories as $cat) {
                    $allTarget = array_merge($allTarget, $this->getRouting((string) $cat));
                }
                $targetCategories = array_values(array_unique($allTarget));

                $batch = new Batch();
                $batch->requestIds = $this->uniqueTruthy(array_map(static fn ($it) => $it['request_id'], $batchItems));
                $batch->requestNumbers = $this->uniqueValues(array_map(static fn ($it) => $it['request_number'] ?? null, $batchItems));
                $batch->isCustomerRequest = false;
                $batch->category = $batchCategory;
                $batch->targetCategories = count($targetCategories) > 0 ? $targetCategories : ['Все товары'];
                $batch->useNewRouting = false;
                $batch->items = $batchItems;
                $batch->itemsCount = count($batchItems);
                $result[] = $batch;
            }
        }
    }

    /**
     * Routing для СТАРОЙ системы по имени категории (порт getRouting).
     *
     * @return array<int,string>
     */
    private function getRouting(?string $categoryName): array
    {
        if (!$categoryName || $categoryName === 'mixed' || $categoryName === 'Смешанное') {
            return ['Лифтовое оборудование', 'Все товары'];
        }

        $cat = null;
        foreach ($this->categories as $c) {
            if (($c['name'] ?? null) === $categoryName) {
                $cat = $c;
                break;
            }
        }
        if (!$cat || empty($cat['routing'])) {
            return ['Все товары'];
        }

        $routing = $cat['routing'];
        if (is_string($routing)) {
            $decoded = json_decode($routing, true);
            if (!is_array($decoded)) {
                return ['Все товары'];
            }
            return $decoded;
        }
        if (is_array($routing)) {
            return $routing;
        }
        return ['Все товары'];
    }

    private function newRoutingKey(array $item): string
    {
        $typeId = $item['product_type_id'] ?? 0;
        $domainId = $item['domain_id'] ?? 0;
        return ($typeId ?: 0) . '_' . ($domainId ?: 0);
    }

    /**
     * Аналог [...new Set(arr.filter(Boolean))] — уникальные truthy-значения.
     *
     * @param array<int,mixed> $values
     * @return array<int,mixed>
     */
    private function uniqueTruthy(array $values): array
    {
        $out = [];
        foreach ($values as $v) {
            if ($v === null || $v === '' || $v === 0 || $v === '0' || $v === false) {
                continue;
            }
            if (!in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        return array_values($out);
    }

    /**
     * Аналог [...new Set(arr)] без filter — уникальные значения (включая 0/'').
     *
     * @param array<int,mixed> $values
     * @return array<int,mixed>
     */
    private function uniqueValues(array $values): array
    {
        $out = [];
        foreach ($values as $v) {
            if (!in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        return array_values($out);
    }
}
