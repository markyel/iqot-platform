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
     * Включает pending записи для использования при создании заявок
     */
    public static function getActiveForSelect(): array
    {
        return static::where('is_active', true)
            ->whereIn('status', ['active', 'pending'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Получить активные области с дополнительной информацией
     * Для отображения статуса pending в UI
     */
    public static function getActiveWithStatus(): array
    {
        return static::where('is_active', true)
            ->whereIn('status', ['active', 'pending'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'created_by'])
            ->mapWithKeys(function ($item) {
                return [$item->id => [
                    'name' => $item->name,
                    'status' => $item->status,
                    'is_pending' => $item->status === 'pending',
                    'is_ai_generated' => $item->created_by === 'ai_suggested'
                ]];
            })
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
