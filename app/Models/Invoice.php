<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'user_id',
        'number',
        'invoice_date',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'total',
        'status',
        'paid_at',
        'sent_at',
        'cancelled_at',
        'description',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'subtotal' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * Генерация уникального номера счета на основе настроек
     */
    public static function generateNumber(): string
    {
        $settings = BillingSettings::current();

        if (!$settings) {
            // Fallback на старый метод, если настройки не заданы
            $lastInvoice = self::latest('id')->first();
            $lastNumber = $lastInvoice ? (int) $lastInvoice->number : 610000;
            return (string) ($lastNumber + 1);
        }

        // Инкрементируем счетчик
        $settings->increment('invoice_number_current');
        $settings->refresh();

        // Вычисляем итоговый номер: начальное значение + счетчик
        $invoiceNumber = $settings->invoice_number_start + $settings->invoice_number_current;

        // Применяем маску
        $mask = $settings->invoice_number_mask;
        $now = now();

        $number = str_replace(
            ['{NUMBER}', '{YYYY}', '{YY}', '{MM}', '{DD}'],
            [
                $invoiceNumber,
                $now->format('Y'),
                $now->format('y'),
                $now->format('m'),
                $now->format('d'),
            ],
            $mask
        );

        return $number;
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
     * Отметить счет как оплаченный и начислить средства пользователю
     */
    public function markAsPaid(): void
    {
        if ($this->status === 'paid') {
            return;
        }

        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Начисляем средства на баланс пользователя (только subtotal, без НДС)
        $this->user->increment('balance', $this->subtotal);

        \Log::info("Invoice #{$this->number} marked as paid. Added {$this->subtotal} to user {$this->user_id} balance.");
    }

    /**
     * Снять отметку об оплате
     */
    public function markAsUnpaid(): void
    {
        if ($this->status !== 'paid') {
            return;
        }

        // Списываем средства с баланса пользователя (только subtotal, без НДС)
        $this->user->decrement('balance', $this->subtotal);

        $this->update([
            'status' => 'sent',
            'paid_at' => null,
        ]);

        \Log::info("Invoice #{$this->number} marked as unpaid. Deducted {$this->subtotal} from user {$this->user_id} balance.");
    }

    /**
     * Отменить счет
     */
    public function cancel(): void
    {
        if ($this->status === 'paid') {
            // Если счет был оплачен, возвращаем средства
            $this->user->decrement('balance', $this->subtotal);
            \Log::info("Invoice #{$this->number} cancelled (was paid). Deducted {$this->subtotal} from user {$this->user_id} balance.");
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        \Log::info("Invoice #{$this->number} cancelled.");
    }

    /**
     * Получить сумму прописью
     */
    public function getTotalWordsAttribute(): string
    {
        return $this->numberToWords($this->total);
    }

    /**
     * Конвертация числа в слова (упрощенная версия)
     */
    private function numberToWords(float $amount): string
    {
        $rubles = floor($amount);
        $kopecks = round(($amount - $rubles) * 100);

        // Здесь должна быть полноценная реализация конвертации числа в слова
        // Для примера возвращаем простой шаблон
        return number_format($rubles, 0, ',', ' ') . ' руб. ' . str_pad($kopecks, 2, '0', STR_PAD_LEFT) . ' коп.';
    }

    /**
     * Получить название статуса
     */
    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Черновик',
            'sent' => 'Отправлен',
            'paid' => 'Оплачен',
            'cancelled' => 'Отменен',
            default => 'Неизвестно',
        };
    }
}
