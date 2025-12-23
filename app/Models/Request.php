<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Request extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'title',
        'description',
        'status',
        'items_count',
        'suppliers_count',
        'offers_count',
        'collection_started_at',
        'collection_ended_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'collection_started_at' => 'datetime',
            'collection_ended_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /**
     * Статусы заявки
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_SENDING = 'sending';
    const STATUS_COLLECTING = 'collecting';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Черновик',
            self::STATUS_PENDING => 'Ожидает отправки',
            self::STATUS_SENDING => 'Отправка запросов',
            self::STATUS_COLLECTING => 'Сбор ответов',
            self::STATUS_COMPLETED => 'Завершена',
            self::STATUS_CANCELLED => 'Отменена',
        ];
    }

    /**
     * Генерация уникального кода заявки
     */
    public static function generateCode(): string
    {
        return 'REQ-' . date('Ymd') . '-' . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Пользователь (заказчик)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Позиции в заявке
     */
    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    /**
     * Полученные предложения
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * Готовый отчёт
     */
    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    /**
     * Поставщики, которым отправлены запросы
     */
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'request_suppliers')
            ->withPivot(['status', 'sent_at', 'responded_at'])
            ->withTimestamps();
    }

    /**
     * Процент завершённости
     */
    public function getCompletionPercentAttribute(): int
    {
        if ($this->items_count === 0) {
            return 0;
        }
        
        $itemsWithOffers = $this->items()->whereHas('offers')->count();
        return (int) round(($itemsWithOffers / $this->items_count) * 100);
    }

    /**
     * Завершена ли заявка
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
