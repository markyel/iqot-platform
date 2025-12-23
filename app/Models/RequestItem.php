<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'name',
        'article',
        'brand',
        'quantity',
        'unit',
        'description',
        'min_price',
        'avg_price',
        'max_price',
        'offers_count',
        'best_supplier_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'min_price' => 'decimal:2',
            'avg_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'offers_count' => 'integer',
        ];
    }

    /**
     * Заявка
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * Предложения по этой позиции
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * Лучший поставщик
     */
    public function bestSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'best_supplier_id');
    }

    /**
     * Пересчёт статистики по ценам
     */
    public function recalculatePrices(): void
    {
        $offers = $this->offers()->whereNotNull('price')->get();
        
        if ($offers->isEmpty()) {
            $this->update([
                'min_price' => null,
                'avg_price' => null,
                'max_price' => null,
                'offers_count' => 0,
                'best_supplier_id' => null,
            ]);
            return;
        }

        $prices = $offers->pluck('price');
        $bestOffer = $offers->sortBy('price')->first();

        $this->update([
            'min_price' => $prices->min(),
            'avg_price' => $prices->avg(),
            'max_price' => $prices->max(),
            'offers_count' => $offers->count(),
            'best_supplier_id' => $bestOffer->supplier_id,
        ]);
    }
}
