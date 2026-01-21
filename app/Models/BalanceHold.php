<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BalanceHold extends Model
{
    protected $fillable = [
        'user_id',
        'request_id',
        'amount',
        'status',
        'description',
        'released_at',
        'charged_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'released_at' => 'datetime',
        'charged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(BalanceCharge::class);
    }

    /**
     * Снять заморозку (вернуть ВСЮ оставшуюся сумму на баланс)
     */
    public function release(): void
    {
        if ($this->status !== 'held') {
            return;
        }

        // Возвращаем только оставшуюся незамороженную сумму
        $remainingAmount = $this->getRemainingAmount();
        
        if ($remainingAmount > 0) {
            $this->user->increment('balance', $remainingAmount);
        }

        $this->update([
            'status' => 'released',
            'released_at' => now(),
        ]);
    }

    /**
     * Списать замороженные средства полностью (устаревший метод)
     */
    public function charge(): void
    {
        if ($this->status !== 'held') {
            return;
        }

        $this->user->decrement('balance', $this->amount);
        $this->update([
            'status' => 'charged',
            'charged_at' => now(),
        ]);
    }

    /**
     * Списать средства за конкретную позицию
     */
    public function chargeForItem(int $externalRequestItemId, float $itemCost, string $description = null): void
    {
        if ($this->status !== 'held') {
            return;
        }

        // Проверяем, не списывали ли уже за эту позицию
        $existingCharge = $this->charges()
            ->where('external_request_item_id', $externalRequestItemId)
            ->first();

        if ($existingCharge) {
            return; // Уже списано за эту позицию
        }

        // Создаем запись о списании
        $charge = BalanceCharge::create([
            'user_id' => $this->user_id,
            'balance_hold_id' => $this->id,
            'request_id' => $this->request_id,
            'external_request_item_id' => $externalRequestItemId,
            'amount' => $itemCost,
            'description' => $description ?? "Списание за позицию #{$externalRequestItemId}",
        ]);

        // Списываем с баланса пользователя
        $this->user->decrement('balance', $itemCost);

        // Проверяем, списаны ли все позиции
        $totalCharged = $this->charges()->sum('amount');
        if ($totalCharged >= $this->amount) {
            $this->update([
                'status' => 'charged',
                'charged_at' => now(),
            ]);
        }
    }

    /**
     * Получить сумму уже списанных средств
     */
    public function getChargedAmount(): float
    {
        return (float) $this->charges()->sum('amount');
    }

    /**
     * Получить остаток замороженных средств
     */
    public function getRemainingAmount(): float
    {
        return max(0, $this->amount - $this->getChargedAmount());
    }
}
