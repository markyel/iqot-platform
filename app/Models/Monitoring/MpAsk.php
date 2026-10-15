<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запрос поставщику по позиции, найденной на его сайте (Ф3 «Гибрид»): проверочный КП
 * (`verify`, с ценой сайта в письме) или RFQ мониторинга. Шлёт его адресное письмо через
 * техническую заявку программы в reports; ответ разбирает общий AI-конвейер.
 */
class MpAsk extends Model
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'mp_asks';

    protected $fillable = [
        'site_id', 'listing_id', 'product_id', 'program_id', 'supplier_id', 'kind', 'reason',
        'site_price', 'site_currency', 'site_vat_included', 'site_availability', 'site_observed_at',
        'status', 'request_item_id', 'batch_id', 'email_queue_id', 'queued_at', 'sent_at', 'answered_at', 'expires_at',
    ];

    protected $casts = [
        'site_price' => 'float',
        'site_vat_included' => 'boolean',
        'site_observed_at' => 'datetime',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'answered_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MpSite::class, 'site_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MpListing::class, 'listing_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'product_id');
    }
}
