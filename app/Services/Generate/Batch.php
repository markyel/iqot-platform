<?php

namespace App\Services\Generate;

/**
 * DTO одного батча рассылки (порт объектов из n8n-узла «Group Items»).
 *
 * Несёт сгруппированные позиции и ключи маршрутизации; по ходу конвейера
 * дозаполняется назначенным отправителем, профильным списком поставщиков,
 * сгенерированным токеном/телом и id вставленного email_batches.
 */
class Batch
{
    /** @var array<int,int> id заявок, попавших в батч */
    public array $requestIds = [];

    /** @var array<int,string> номера заявок */
    public array $requestNumbers = [];

    public bool $isCustomerRequest = false;

    public ?int $clientOrganizationId = null;
    public ?string $customerCompany = null;
    public ?string $customerContactPerson = null;
    public ?string $customerEmail = null;
    public ?string $customerPhone = null;

    /** Категория (для совместимости/логов): одна или 'mixed'/'Смешанное'. */
    public string $category = 'Другое';

    /** @var array<int,string> целевые категории (OLD routing) или ['NEW_ROUTING']. */
    public array $targetCategories = [];

    public bool $useNewRouting = false;

    /** @var array<int,int> product_type_id для NEW routing. */
    public array $productTypeIds = [];

    /** @var array<int,int> domain_id для NEW routing. */
    public array $domainIds = [];

    /** @var array<int,array<string,mixed>> позиции батча (как из request_items). */
    public array $items = [];

    public int $itemsCount = 0;

    // ── Дозаполняется на следующих шагах конвейера ──────────────────────────

    /** @var array<string,mixed>|null назначенный отправитель (Get Sender + орг). */
    public ?array $sender = null;

    /** @var array<int,array<string,mixed>> профильные поставщики. */
    public array $suppliers = [];

    /** @var array<int,int> id поставщиков (dedup). */
    public array $supplierIds = [];

    /**
     * Пул расширения (волна 2): поставщики, срезанные при ужесточении сверх-большого
     * пула. Письма им строятся сразу, но держатся (scheduled_at в будущем) до досыла.
     * @var array<int,array<string,mixed>>
     */
    public array $expansionSuppliers = [];

    /** Базовый трекинг-токен батча (до per-supplier суффикса). */
    public ?string $trackingToken = null;

    /** @var array<string,string>|null AI-тело: greeting/introduction/closing. */
    public ?array $aiBody = null;

    public ?string $aiModel = null;

    /** @var array<string,mixed>|null email_templates по preferred_template_id отправителя. */
    public ?array $emailTemplate = null;

    /** @var array<string,mixed>|null email_tones по template.ai_tone. */
    public ?array $emailTone = null;

    /** id вставленной строки email_batches. */
    public ?int $batchId = null;
}
