<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'user_id',
        'code',
        'title',
        'type',
        'status',
        'items_count',
        'items_with_offers',
        'suppliers_contacted',
        'suppliers_responded',
        'total_offers',
        'summary',
        'file_path',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'items_count' => 'integer',
            'items_with_offers' => 'integer',
            'suppliers_contacted' => 'integer',
            'suppliers_responded' => 'integer',
            'total_offers' => 'integer',
            'summary' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Типы отчётов
     */
    const TYPE_SINGLE = 'single';    // По одной заявке
    const TYPE_COMBINED = 'combined'; // Сводный по нескольким

    /**
     * Статусы
     */
    const STATUS_GENERATING = 'generating';
    const STATUS_READY = 'ready';
    const STATUS_ERROR = 'error';

    /**
     * Заявка
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * Пользователь
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Процент покрытия позиций
     */
    public function getCoveragePercentAttribute(): int
    {
        if ($this->items_count === 0) {
            return 0;
        }
        return (int) round(($this->items_with_offers / $this->items_count) * 100);
    }

    /**
     * Процент отклика поставщиков
     */
    public function getResponseRateAttribute(): int
    {
        if ($this->suppliers_contacted === 0) {
            return 0;
        }
        return (int) round(($this->suppliers_responded / $this->suppliers_contacted) * 100);
    }

    /**
     * Готов ли отчёт
     */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /**
     * Есть ли файл для скачивания
     */
    public function hasFile(): bool
    {
        return !empty($this->file_path) && file_exists(storage_path('app/' . $this->file_path));
    }
}
