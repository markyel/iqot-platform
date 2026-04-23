<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiInbox extends Model
{
    protected $table = 'api_inbox';

    protected $fillable = [
        'api_submission_id',
        'raw_payload',
        'status',
        'retry_count',
        'last_error',
        'locked_until',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'locked_until' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ApiSubmission::class, 'api_submission_id');
    }
}
