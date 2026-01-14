<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    protected $connection = 'reports';
    protected $table = 'product_types';

    protected $fillable = [
        'name',
        'parent_id',
        'sort_order',
        'is_active',
        'is_leaf',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_leaf' => 'boolean',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
    ];

    /**
     * Родительский тип
     */
    public function parent()
    {
        return $this->belongsTo(ProductType::class, 'parent_id');
    }

    /**
     * Дочерние типы
     */
    public function children()
    {
        return $this->hasMany(ProductType::class, 'parent_id');
    }

    /**
     * Получить активные типы для выбора (только листовые)
     */
    public static function getActiveForSelect(): array
    {
        return static::where('is_active', true)
            ->where('is_leaf', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
