<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActItem extends Model
{
    protected $fillable = [
        'act_id',
        'type',
        'subscription_charge_id',
        'balance_charge_id',
        'report_access_id',
        'sort_order',
        'name',
        'unit',
        'quantity',
        'price',
        'sum',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'sum' => 'decimal:2',
    ];

    public function act(): BelongsTo
    {
        return $this->belongsTo(Act::class);
    }

    public function subscriptionCharge(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCharge::class);
    }

    public function balanceCharge(): BelongsTo
    {
        return $this->belongsTo(BalanceCharge::class);
    }

    public function reportAccess(): BelongsTo
    {
        return $this->belongsTo(ReportAccess::class);
    }

    /**
     * Автоматический расчет суммы при сохранении
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->sum = $item->quantity * $item->price;
        });

        static::saved(function ($item) {
            $item->act->recalculate();
        });

        static::deleted(function ($item) {
            $item->act->recalculate();
        });
    }
}
