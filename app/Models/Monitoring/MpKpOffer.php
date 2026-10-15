<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;

/**
 * КП поставщика по товару мониторинга (Ф3 «Гибрид»): ответ на проверочное письмо (`verify`) или на
 * запрос КП поставщику без цены на сайте (`on_request` — товар на сайте «по запросу»; `pool` —
 * профильный пул IQOT). В API — источник цены с признаком «Есть КП / счёт».
 */
class MpKpOffer extends Model
{
    protected $table = 'mp_kp_offers';

    protected $fillable = [
        'product_id', 'supplier_id', 'ask_id', 'listing_id', 'site_id', 'request_item_id', 'source',
        'price', 'currency', 'vat_included', 'delivery_days', 'notes', 'received_at',
    ];

    protected $casts = [
        'price' => 'float',
        'vat_included' => 'boolean',
        'received_at' => 'datetime',
    ];
}
