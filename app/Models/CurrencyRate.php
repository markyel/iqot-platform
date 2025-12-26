<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $connection = 'reports';
    protected $table = 'currency_rates';

    const UPDATED_AT = 'updated_at';
    const CREATED_AT = null;

    protected $fillable = [
        'currency_code',
        'rate_to_rub',
        'updated_by',
        'is_active',
    ];

    protected $casts = [
        'rate_to_rub' => 'decimal:4',
        'is_active' => 'boolean',
        'updated_at' => 'datetime',
    ];

    /**
     * Конвертировать сумму в рубли
     */
    public static function convertToRub(float $amount, string $currencyCode): float
    {
        if ($currencyCode === 'RUB' || $currencyCode === 'RUR') {
            return $amount;
        }

        $rate = static::where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->first();

        if (!$rate) {
            return $amount; // Возвращаем исходную сумму, если курс не найден
        }

        return $amount * $rate->rate_to_rub;
    }

    /**
     * Получить курс валюты
     */
    public static function getRate(string $currencyCode): ?float
    {
        if ($currencyCode === 'RUB' || $currencyCode === 'RUR') {
            return 1.0;
        }

        $rate = static::where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->value('rate_to_rub');

        return $rate ? (float) $rate : null;
    }
}
