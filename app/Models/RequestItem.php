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
        'position_number',
        'name',
        'equipment_type',
        'equipment_brand',
        'article',
        'brand',
        'manufacturer_article',
        'category',
        'product_type_id',
        'domain_id',
        'type_confidence',
        'domain_confidence',
        'classification_needs_review',
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
            'position_number' => 'integer',
            'quantity' => 'integer',
            'type_confidence' => 'decimal:2',
            'domain_confidence' => 'decimal:2',
            'classification_needs_review' => 'boolean',
            'min_price' => 'decimal:2',
            'avg_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'offers_count' => 'integer',
        ];
    }

    /**
     * Типы оборудования
     */
    const EQUIPMENT_TYPE_LIFT = 'lift';
    const EQUIPMENT_TYPE_ESCALATOR = 'escalator';

    public static function equipmentTypes(): array
    {
        return [
            self::EQUIPMENT_TYPE_LIFT => 'Лифт',
            self::EQUIPMENT_TYPE_ESCALATOR => 'Эскалатор',
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

    /**
     * Проверка заполненности обязательных полей
     */
    public function isValid(): bool
    {
        return !empty($this->name) &&
               !empty($this->equipment_type) &&
               !empty($this->equipment_brand) &&
               !empty($this->manufacturer_article);
    }

    /**
     * Получить список незаполненных обязательных полей
     */
    public function getMissingRequiredFields(): array
    {
        $missing = [];

        if (empty($this->name)) $missing[] = 'Полное название';
        if (empty($this->equipment_type)) $missing[] = 'Тип оборудования (лифт/эскалатор)';
        if (empty($this->equipment_brand)) $missing[] = 'Марка оборудования';
        if (empty($this->manufacturer_article)) $missing[] = 'Артикул производителя';

        return $missing;
    }
}
