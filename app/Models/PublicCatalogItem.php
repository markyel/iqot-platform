<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicCatalogItem extends Model
{
    protected $table = 'public_catalog_items';

    protected $fillable = [
        'external_item_id',
        'name',
        'brand',
        'article',
        'quantity',
        'unit',
        'category',
        'product_type_id',
        'product_type_name',
        'domain_id',
        'domain_name',
        'external_request_id',
        'request_number',
        'offers_count',
        'min_price',
        'max_price',
        'currency',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'offers_count' => 'integer',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Получить позицию из external БД
     */
    public function getExternalItem(): ?ExternalRequestItem
    {
        return ExternalRequestItem::find($this->external_item_id);
    }

    /**
     * Тип оборудования
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    /**
     * Область применения
     */
    public function applicationDomain(): BelongsTo
    {
        return $this->belongsTo(ApplicationDomain::class, 'domain_id');
    }

    /**
     * Scope: только опубликованные
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope: с предложениями
     */
    public function scopeWithOffers($query)
    {
        return $query->where('offers_count', '>', 0);
    }

    /**
     * Scope: фильтр по типу оборудования
     */
    public function scopeByProductType($query, $productTypeId)
    {
        return $query->where('product_type_id', $productTypeId);
    }

    /**
     * Scope: фильтр по области применения
     */
    public function scopeByDomain($query, $domainId)
    {
        return $query->where('domain_id', $domainId);
    }

    /**
     * Scope: поиск по названию, бренду или артикулу
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('brand', 'like', "%{$search}%")
              ->orWhere('article', 'like', "%{$search}%");
        });
    }

    /**
     * Получить количество товаров по категориям
     */
    public static function getCategoriesWithCounts()
    {
        return self::published()
            ->withOffers()
            ->select('product_type_id', 'product_type_name')
            ->selectRaw('COUNT(*) as items_count')
            ->whereNotNull('product_type_id')
            ->groupBy('product_type_id', 'product_type_name')
            ->orderBy('items_count', 'desc')
            ->get();
    }

    /**
     * Получить URL для детальной страницы
     */
    public function getUrlAttribute(): string
    {
        return route('catalog.show', $this->id);
    }
}
