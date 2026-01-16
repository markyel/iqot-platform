<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAccess extends Model
{
    protected $fillable = [
        'user_id',
        'request_id',
        'report_number',
        'price',
        'accessed_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'accessed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
