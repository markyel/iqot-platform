<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    protected $connection = 'reports';
    protected $table = 'product_types';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'keywords',
        'parent_id',
        'sort_order',
        'is_active',
        'is_leaf',
        'status',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_leaf' => 'boolean',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
        'keywords' => 'array',
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
            ->where('status', 'active')
            ->where('is_leaf', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Scope: только pending записи
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: только AI-созданные
     */
    public function scopeAiGenerated($query)
    {
        return $query->where('created_by', 'ai_suggested');
    }

    /**
     * Scope: только созданные вручную
     */
    public function scopeManual($query)
    {
        return $query->where('created_by', 'manual');
    }

    /**
     * Одобрить тип
     */
    public function approve(array $updates = []): bool
    {
        $data = array_merge($updates, [
            'status' => 'active',
            'is_active' => true,
        ]);

        return $this->update($data);
    }

    /**
     * Отклонить тип (деактивировать)
     */
    public function reject(): bool
    {
        return $this->update([
            'status' => 'active',
            'is_active' => false,
        ]);
    }
}
