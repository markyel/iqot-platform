<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalOffer extends Model
{
    protected $connection = 'reports';
    protected $table = 'request_item_responses';

    protected $fillable = [
        'request_item_id',
        'supplier_id',
        'email_queue_id',
        'batch_id',
        'status',
        'price_per_unit',
        'total_price',
        'currency',
        'price_includes_vat',
        'delivery_days',
        'payment_terms',
        'notes',
        'has_multi_responses',
        'response_received_at',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'price_per_unit' => 'decimal:2',
        'total_price' => 'decimal:2',
        'price_includes_vat' => 'boolean',
        'has_multi_responses' => 'boolean',
        'response_received_at' => 'datetime',
        'processed_at' => 'datetime',
        'delivery_days' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_NO_RESPONSE = 'no_response';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_PROCESSED = 'processed';

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(ExternalRequestItem::class, 'request_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ExternalSupplier::class, 'supplier_id');
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Ожидание',
            self::STATUS_RECEIVED => 'Получено',
            self::STATUS_NO_RESPONSE => 'Нет ответа',
            self::STATUS_DECLINED => 'Отклонено',
            self::STATUS_PROCESSED => 'Обработано',
        ];
    }

    /**
     * Получить цену за единицу в рублях
     */
    public function getPricePerUnitInRubAttribute(): ?float
    {
        if (!$this->price_per_unit) {
            return null;
        }

        return CurrencyRate::convertToRub($this->price_per_unit, $this->currency ?? 'RUB');
    }

    /**
     * Получить общую цену в рублях
     */
    public function getTotalPriceInRubAttribute(): ?float
    {
        if (!$this->total_price) {
            return null;
        }

        return CurrencyRate::convertToRub($this->total_price, $this->currency ?? 'RUB');
    }
}
