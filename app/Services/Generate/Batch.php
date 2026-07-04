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
     * Пул расширения (волна 2). Legacy: поставщики, срезанные при ужесточении сверх-
     * большого пула (держатся до досыла). Waves-v2: ТЁПЛЫЕ — совпали в Яндексе только по
     * облегчённым запросам (tier2), шлём с задержкой wave2_delay_days.
     * @var array<int,array<string,mixed>>
     */
    public array $expansionSuppliers = [];

    /**
     * Холодная волна (волна 3, waves-v2 tier3): поставщики, НЕ совпавшие в Яндексе ни по
     * одному запросу. Письма строятся, но держатся (held); релиз — followup при КП < порога.
     * @var array<int,array<string,mixed>>
     */
    public array $coldSuppliers = [];

    /**
     * Кандидаты discovery (новые домены из Яндекс-таргетинга): {url, product_type_id,
     * domain_id}. Диспатчатся в discovery ПОСЛЕ persist (нужен batch_id) — найденные
     * поставщики обогащают волну 2 этого батча.
     * @var array<int,array<string,mixed>>
     */
    public array $discoveryCandidates = [];

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
