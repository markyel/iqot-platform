<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Наблюдение проверки листинга — включая сбои и «цены нет».
 */
class MpPriceObservation extends Model
{
    public const STATUS_OK = 'ok';
    public const STATUS_SUSPICIOUS = 'suspicious';
    public const STATUS_GONE = 'gone';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SOFT404 = 'soft404';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_MISMATCH = 'mismatch';
    public const STATUS_NO_PRICE = 'no_price';

    protected $table = 'mp_price_observations';

    public $timestamps = false;

    protected $fillable = [
        'listing_id', 'product_id', 'price', 'currency', 'price_kind', 'vat_included',
        'price_unit', 'availability', 'method', 'status', 'note', 'content_hash', 'cost_usd',
        'observed_at',
    ];

    protected $casts = [
        'price' => 'float',
        'vat_included' => 'boolean',
        'cost_usd' => 'float',
        'observed_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MpListing::class, 'listing_id');
    }
}
