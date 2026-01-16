<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceCharge extends Model
{
    protected $fillable = [
        'user_id',
        'balance_hold_id',
        'request_id',
        'external_request_item_id',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function balanceHold(): BelongsTo
    {
        return $this->belongsTo(BalanceHold::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
