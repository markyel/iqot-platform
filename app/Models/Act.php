<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Act extends Model
{
    protected $fillable = [
        'user_id',
        'number',
        'act_date',
        'period_year',
        'period_month',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'total',
        'status',
        'generated_at',
        'sent_at',
        'signed_at',
        'notes',
    ];

    protected $casts = [
        'act_date' => 'date',
        'subtotal' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ActItem::class)->orderBy('sort_order');
    }

    /**
     * Получить название периода
     */
    public function getPeriodNameAttribute(): string
    {
        $months = [
            1 => 'январь', 2 => 'февраль', 3 => 'март', 4 => 'апрель',
            5 => 'май', 6 => 'июнь', 7 => 'июль', 8 => 'август',
            9 => 'сентябрь', 10 => 'октябрь', 11 => 'ноябрь', 12 => 'декабрь'
        ];

        return $months[$this->period_month] . ' ' . $this->period_year;
    }

    /**
     * Пересчитать суммы на основе позиций
     */
    public function recalculate(): void
    {
        $this->load('items');

        $this->subtotal = $this->items->sum('sum');
        $this->vat_amount = round($this->subtotal * ($this->vat_rate / 100), 2);
        $this->total = $this->subtotal + $this->vat_amount;

        $this->save();
    }

    /**
     * Генерация уникального номера акта (УПД)
     */
    public static function generateNumber(): string
    {
        $lastAct = self::latest('id')->first();
        $lastNumber = $lastAct ? (int) $lastAct->number : 0;

        return (string) ($lastNumber + 1);
    }

    /**
     * Получить название статуса
     */
    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Черновик',
            'generated' => 'Сформирован',
            'sent' => 'Отправлен',
            'signed' => 'Подписан',
            default => 'Неизвестно',
        };
    }
}
