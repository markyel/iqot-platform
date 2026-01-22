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
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
        'keywords' => 'array',
    ];

    /**
     * Родительский домен
     */
    public function parent()
    {
        return $this->belongsTo(ApplicationDomain::class, 'parent_id');
    }

    /**
     * Дочерние домены
     */
    public function children()
    {
        return $this->hasMany(ApplicationDomain::class, 'parent_id');
    }

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
     * Одобрить домен
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
     * Отклонить домен (деактивировать)
     */
    public function reject(): bool
    {
        return $this->update([
            'status' => 'active',
            'is_active' => false,
        ]);
    }
}
