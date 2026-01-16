<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tariff_plan_id',
        'started_at',
        'expires_at',
        'items_used',
        'reports_used',
        'is_active',
        'last_charged_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_charged_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Пользователь, которому принадлежит тариф
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Тарифный план
     */
    public function tariffPlan(): BelongsTo
    {
        return $this->belongsTo(TariffPlan::class);
    }

    /**
     * Scope для активных тарифов
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Проверка, истек ли тариф
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Получить оставшиеся дни до истечения
     */
    public function getDaysLeft(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * Получить использованные позиции в процентах
     */
    public function getItemsUsedPercentage(): ?float
    {
        $limit = $this->tariffPlan->items_limit;

        if ($limit === null) {
            return null; // Безлимит
        }

        if ($limit == 0) {
            return 100;
        }

        return min(100, ($this->items_used / $limit) * 100);
    }

    /**
     * Получить использованные отчеты в процентах
     */
    public function getReportsUsedPercentage(): ?float
    {
        $limit = $this->tariffPlan->reports_limit;

        if ($limit === null) {
            return null; // Безлимит
        }

        if ($limit == 0) {
            return 100;
        }

        return min(100, ($this->reports_used / $limit) * 100);
    }

    /**
     * Проверка, можно ли создать заявку с указанным количеством позиций
     */
    public function canCreateRequest(int $itemsCount): bool
    {
        $limit = $this->tariffPlan->items_limit;

        // Если лимита нет, можно создавать
        if ($limit === null) {
            return true;
        }

        // Проверяем, не превысим ли лимит
        return ($this->items_used + $itemsCount) <= $limit;
    }

    /**
     * Получить количество позиций сверх лимита
     */
    public function getItemsOverLimit(int $itemsCount): int
    {
        $limit = $this->tariffPlan->items_limit;

        // Если лимита нет, нет превышения
        if ($limit === null) {
            return 0;
        }

        $afterUse = $this->items_used + $itemsCount;

        return max(0, $afterUse - $limit);
    }

    /**
     * Рассчитать стоимость создания заявки
     */
    public function calculateRequestCost(int $itemsCount): float
    {
        $overLimit = $this->getItemsOverLimit($itemsCount);

        if ($overLimit > 0) {
            return $overLimit * $this->tariffPlan->price_per_item_over_limit;
        }

        return 0;
    }

    /**
     * Проверка, можно ли открыть отчет
     */
    public function canOpenReport(): bool
    {
        $limit = $this->tariffPlan->reports_limit;

        // Если лимита нет, можно открывать
        if ($limit === null) {
            return true;
        }

        return $this->reports_used < $limit;
    }

    /**
     * Рассчитать стоимость открытия отчета
     */
    public function calculateReportCost(): float
    {
        if ($this->canOpenReport()) {
            return 0;
        }

        return $this->tariffPlan->price_per_report_over_limit;
    }

    /**
     * Использовать позиции (при создании заявки)
     */
    public function useItems(int $count): void
    {
        $this->increment('items_used', $count);
    }

    /**
     * Использовать отчет (при открытии)
     */
    public function useReport(): void
    {
        $this->increment('reports_used');
    }

    /**
     * Сбросить лимиты (при обновлении периода)
     */
    public function resetLimits(): void
    {
        $this->update([
            'items_used' => 0,
            'reports_used' => 0,
        ]);
    }

    /**
     * Продлить тариф на месяц
     */
    public function extendForMonth(): void
    {
        $this->update([
            'expires_at' => now()->addMonth(),
            'last_charged_at' => now(),
        ]);
    }

    /**
     * Деактивировать тариф
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
