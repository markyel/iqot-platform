<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestStaging extends Model
{
    protected $table = 'request_staging';

    protected $fillable = [
        'api_submission_id',
        'stage',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ApiSubmission::class, 'api_submission_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItemStaging::class, 'request_staging_id');
    }
}
