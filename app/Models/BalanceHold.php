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
        'request_item_id',
        'api_submission_id',
        'request_items_staging_id',
        'amount',
        'status',
        'description',
        'released_at',
        'release_reason',
        'charged_at',
    ];

    /** Причины разморозки (balance_holds.release_reason). */
    public const RELEASE_EXPIRED = 'expired';                 // срок сбора КП истёк, <3 цен
    public const RELEASE_MODERATION_REJECTED = 'moderation_rejected';
    public const RELEASE_MODERATION_TIMEOUT = 'moderation_timeout';
    public const RELEASE_DEADLINE_EXPIRED = 'deadline_expired'; // до рассылки истёк deadline
    public const RELEASE_NO_SUPPLIERS = 'no_suppliers';
    public const RELEASE_CANCELLED = 'cancelled';

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
     * Снять заморозку: остаток снова доступен.
     *
     * Заморозка НЕ уменьшает users.balance (User::holdBalance только создаёт запись,
     * доступный баланс = balance − held). Поэтому при снятии баланс НЕ увеличиваем —
     * раньше здесь был increment, и клиент получал сумму заморозки сверху.
     */
    public function release(?string $reason = null): void
    {
        if ($this->status !== 'held') {
            return;
        }

        $this->update([
            'status' => 'released',
            'released_at' => now(),
            'release_reason' => $reason,
        ]);
    }

    /**
     * Списание постфактум по уже снятой заморозке: позиция набрала ≥3 цен после
     * истечения срока (release_reason='expired'). Баланс может уйти в минус — это
     * овердрафт (§10.5), API предупреждает заголовком X-Balance-Warning.
     */
    public function chargeLate(int $externalRequestItemId, float $amount, string $description): bool
    {
        if ($this->status !== 'released' || $this->release_reason !== self::RELEASE_EXPIRED) {
            return false;
        }
        if ($this->charges()->where('external_request_item_id', $externalRequestItemId)->exists()) {
            return false;
        }

        $charge = BalanceCharge::create([
            'user_id' => $this->user_id,
            'balance_hold_id' => $this->id,
            'request_id' => $this->request_id,
            'external_request_item_id' => $externalRequestItemId,
            'amount' => $amount,
            'description' => $description,
        ]);

        $this->user->decrement('balance', $amount);
        app(\App\Services\InvoiceTrackingService::class)->trackBalanceCharge($charge);

        $this->update([
            'status' => 'charged',
            'charged_at' => now(),
        ]);

        return true;
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

        // Отслеживаем расходование средств из оплаченных счетов
        app(\App\Services\InvoiceTrackingService::class)->trackBalanceCharge($charge);

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
