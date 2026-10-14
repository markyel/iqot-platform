<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Идентификатор канонического товара. Уникальность (kind, value_norm, brand_norm).
 */
class MpIdentifier extends Model
{
    public const KIND_OEM = 'oem';
    public const KIND_MPN = 'mpn';
    public const KIND_EAN = 'ean';
    public const KIND_ALIAS = 'alias';

    protected $table = 'mp_identifiers';

    protected $fillable = ['product_id', 'kind', 'value', 'value_norm', 'brand_norm', 'source'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'product_id');
    }
}
