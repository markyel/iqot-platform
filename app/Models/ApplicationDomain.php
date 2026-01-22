<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationDomain extends Model
{
    protected $connection = 'reports';
    protected $table = 'application_domains';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'keywords',
        'parent_id',
        'sort_order',
        'is_active',
        'status',
        'source',
        'is_verified',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
        'keywords' => 'array',
    ];

    /**
     * Получить активные области для выбора
     */
    public static function getActiveForSelect(): array
    {
        return static::where('is_active', true)
            ->where('status', 'active')
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
        return $query->where('source', 'ai_generated');
    }

    /**
     * Scope: неподтвержденные
     */
    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    /**
     * Одобрить домен
     */
    public function approve(array $updates = []): bool
    {
        $data = array_merge($updates, [
            'status' => 'active',
            'is_active' => true,
            'is_verified' => true,
        ]);

        return $this->update($data);
    }

    /**
     * Отклонить домен (мягкое удаление)
     */
    public function reject(): bool
    {
        return $this->update([
            'status' => 'active',
            'is_active' => false,
            'is_verified' => false,
        ]);
    }
}
