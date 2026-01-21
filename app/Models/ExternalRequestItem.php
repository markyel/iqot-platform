<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalRequestItem extends Model
{
    protected $connection = 'reports';
    protected $table = 'request_items';

    protected $fillable = [
        'request_id',
        'position_number',
        'name',
        'brand',
        'article',
        'quantity',
        'unit',
        'category',
        'description',
        'status',
        'offers_count',
        'min_price',
        'max_price',
        'avg_price',
        'notes',
        'product_type_id',
        'domain_id',
        'type_confidence',
        'domain_confidence',
        'classification_needs_review',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'avg_price' => 'decimal:2',
        'type_confidence' => 'decimal:2',
        'domain_confidence' => 'decimal:2',
        'classification_needs_review' => 'boolean',
        'offers_count' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_HAS_OFFERS = 'has_offers';
    public const STATUS_PARTIAL_OFFERS = 'partial_offers';
    public const STATUS_NO_OFFERS = 'no_offers';
    public const STATUS_CLARIFICATION_NEEDED = 'clarification_needed';

    public function request(): BelongsTo
    {
        return $this->belongsTo(ExternalRequest::class, 'request_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ExternalOffer::class, 'request_item_id');
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function applicationDomain(): BelongsTo
    {
        return $this->belongsTo(ApplicationDomain::class, 'domain_id');
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Ожидание',
            self::STATUS_HAS_OFFERS => 'Есть предложения',
            self::STATUS_PARTIAL_OFFERS => 'Частичные предложения',
            self::STATUS_NO_OFFERS => 'Нет предложений',
            self::STATUS_CLARIFICATION_NEEDED => 'Нужно уточнение',
        ];
    }
}
