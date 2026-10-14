<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Листинг: товар × страница сайта поставщика. Текущая цена со всеми признаками и
 * состояние проверки (см. ListingChecker).
 */
class MpListing extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPICIOUS = 'suspicious';
    public const STATUS_GONE = 'gone';
    public const STATUS_FAILED = 'failed';
    public const STATUS_MISMATCH = 'mismatch';
    public const STATUS_BLOCKED = 'blocked';

    protected $table = 'mp_listings';

    protected $fillable = [
        'product_id', 'site_id', 'url', 'url_hash', 'bench', 'match_confidence', 'match_method',
        'match_evidence', 'price', 'currency', 'price_kind', 'vat_included', 'price_unit',
        'price_unit_qty', 'min_order_qty', 'availability', 'stock_qty', 'lead_days',
        'price_evidence', 'status', 'fail_count', 'observed_at', 'checked_at', 'next_check_at',
        'final_url_hash', 'content_hash', 'price_method', 'etag', 'last_modified',
        'confirmed_price', 'confirmed_at',
    ];

    protected $casts = [
        'bench' => 'boolean',
        'match_confidence' => 'float',
        'price' => 'float',
        'vat_included' => 'boolean',
        'price_unit_qty' => 'float',
        'min_order_qty' => 'float',
        'stock_qty' => 'float',
        'confirmed_price' => 'float',
        'observed_at' => 'datetime',
        'checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'product_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MpSite::class, 'site_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(MpPriceObservation::class, 'listing_id');
    }
}
