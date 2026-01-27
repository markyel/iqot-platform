<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'amount',
        'is_used',
        'used_by_user_id',
        'used_at',
        'description',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'used_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * Пользователь, который использовал промокод
     */
    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    /**
     * Генерация уникального кода промокода
     */
    public static function generateCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Проверка, доступен ли промокод для использования
     */
    public function isAvailable(): bool
    {
        return !$this->is_used;
    }

    /**
     * Активация промокода пользователем
     */
    public function activate(User $user): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $this->update([
            'is_used' => true,
            'used_by_user_id' => $user->id,
            'used_at' => now(),
        ]);

        return true;
    }
}
