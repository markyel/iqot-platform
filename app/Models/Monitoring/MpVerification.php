<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;

/**
 * Сверка КП ↔ цена сайта (Ф3): из проверочного письма (`ask`) или из КП обычной заявки (`rfq`).
 * Исход (PriceComparator) двигает уровень доверия сайта только при `price_mismatch`/`match`.
 */
class MpVerification extends Model
{
    protected $table = 'mp_verifications';

    protected $fillable = [
        'site_id', 'listing_id', 'product_id', 'supplier_id', 'ask_id', 'source', 'request_item_id',
        'site_price', 'site_vat_included', 'kp_price', 'kp_currency', 'kp_vat_included', 'diff_pct',
        'outcome', 'note', 'level_before', 'level_after', 'verified_at',
    ];

    protected $casts = [
        'site_price' => 'float',
        'kp_price' => 'float',
        'diff_pct' => 'float',
        'site_vat_included' => 'boolean',
        'kp_vat_included' => 'boolean',
        'verified_at' => 'datetime',
    ];
}
