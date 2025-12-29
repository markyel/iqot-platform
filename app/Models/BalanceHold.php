<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Снять заморозку
     */
    public function release(): void
    {
        $this->update([
            'status' => 'released',
            'released_at' => now(),
        ]);
    }

    /**
     * Списать замороженные средства
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
}
