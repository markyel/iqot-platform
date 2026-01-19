<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TariffPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'monthly_price',
        'items_limit',
        'reports_limit',
        'price_per_item_over_limit',
        'price_per_report_over_limit',
        'features',
        'pdf_reports_enabled',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'price_per_item_over_limit' => 'decimal:2',
        'price_per_report_over_limit' => 'decimal:2',
        'features' => 'array',
        'pdf_reports_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Пользователи, подписанные на этот тариф
     */
    public function userTariffs(): HasMany
    {
        return $this->hasMany(UserTariff::class);
    }

    /**
     * Активные подписки на этот тариф
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->userTariffs()->where('is_active', true);
    }

    /**
     * Scope для активных тарифов
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для упорядочивания
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Проверка, является ли тариф бесплатным
     */
    public function isFree(): bool
    {
        return $this->monthly_price == 0;
    }

    /**
     * Проверка, есть ли лимиты
     */
    public function hasLimits(): bool
    {
        return $this->items_limit !== null || $this->reports_limit !== null;
    }

    /**
     * Получить название с ценой
     */
    public function getNameWithPriceAttribute(): string
    {
        if ($this->isFree()) {
            return $this->name . ' (бесплатно)';
        }

        return $this->name . ' (' . number_format($this->monthly_price, 0, ',', ' ') . ' ₽/мес)';
    }

    /**
     * Проверить, есть ли у тарифа определенная фича
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Кастомные тарифы для системы
     */
    public const CODE_START = 'start';
    public const CODE_BASIC = 'basic';
    public const CODE_EXTENDED = 'extended';
    public const CODE_PROFESSIONAL = 'professional';

    /**
     * Получить тариф по коду
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Получить стартовый тариф (бесплатный)
     */
    public static function getStartTariff(): ?self
    {
        return static::findByCode(self::CODE_START);
    }

    /**
     * Получить стоимость одной позиции для пользователя
     * Учитывает лимиты тарифа и использование
     */
    public function getItemCost(User $user): float
    {
        // Если лимита нет - бесплатно
        if ($this->items_limit === null) {
            return 0;
        }

        // Получаем использование
        $limitsInfo = app(\App\Services\TariffService::class)->getUserLimitsInfo($user);
        $itemsUsed = $limitsInfo['items_used'] ?? 0;

        // Если в пределах лимита - бесплатно
        if ($itemsUsed < $this->items_limit) {
            return 0;
        }

        // Если превышен лимит - берем стоимость за сверхлимитную позицию
        return (float) $this->price_per_item_over_limit;
    }

    /**
     * Получить стоимость доступа к отчету для пользователя
     * Учитывает лимиты тарифа и использование
     */
    public function getReportCost(User $user): float
    {
        // Если лимита нет - бесплатно
        if ($this->reports_limit === null) {
            return 0;
        }

        // Получаем использование
        $limitsInfo = app(\App\Services\TariffService::class)->getUserLimitsInfo($user);
        $reportsUsed = $limitsInfo['reports_used'] ?? 0;

        // Если в пределах лимита - бесплатно
        if ($reportsUsed < $this->reports_limit) {
            return 0;
        }

        // Если превышен лимит - берем стоимость за сверхлимитный отчет
        return (float) $this->price_per_report_over_limit;
    }

    /**
     * Проверить, доступна ли генерация PDF отчетов для тарифа
     */
    public function canGeneratePdfReports(): bool
    {
        return $this->pdf_reports_enabled;
    }
}
