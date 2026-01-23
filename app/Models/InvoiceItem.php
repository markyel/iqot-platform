<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'sort_order',
        'name',
        'unit',
        'quantity',
        'price',
        'sum',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'sum' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Автоматический расчет суммы при сохранении
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->sum = $item->quantity * $item->price;
        });

        static::saved(function ($item) {
            $item->invoice->recalculate();
        });

        static::deleted(function ($item) {
            $item->invoice->recalculate();
        });
    }
}
