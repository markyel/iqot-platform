<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'request_item_id',
        'supplier_id',
        'quantity',
        'price',
        'total_price',
        'currency',
        'vat_included',
        'delivery_days',
        'payment_terms',
        'notes',
        'source_type',
        'source_file',
        'raw_data',
        'is_best',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'vat_included' => 'boolean',
            'delivery_days' => 'integer',
            'raw_data' => 'array',
            'is_best' => 'boolean',
        ];
    }

    /**
     * Типы источников КП
     */
    const SOURCE_EMAIL = 'email';
    const SOURCE_PDF = 'pdf';
    const SOURCE_EXCEL = 'excel';
    const SOURCE_WORD = 'word';
    const SOURCE_WEBSITE = 'website';
    const SOURCE_MANUAL = 'manual';

    /**
     * Заявка
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * Позиция в заявке
     */
    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

    /**
     * Поставщик
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Форматированная цена
     */
    public function getFormattedPriceAttribute(): string
    {
        $symbol = match($this->currency) {
            'USD' => '$',
            'EUR' => '€',
            default => '₽',
        };
        
        return number_format($this->price, 2, ',', ' ') . ' ' . $symbol;
    }

    /**
     * Пометка НДС
     */
    public function getVatLabelAttribute(): string
    {
        return $this->vat_included ? 'с НДС' : 'без НДС';
    }
}
