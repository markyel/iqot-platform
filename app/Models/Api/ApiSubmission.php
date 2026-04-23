<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApiSubmission extends Model
{
    protected $fillable = [
        'api_client_id',
        'external_id',
        'idempotency_key',
        'client_ref',
        'client_organization_id',
        'sender_id',
        'deadline_at',
        'status',
        'stage',
        'status_changed_at',
        'internal_request_id',
        'promoted_at',
        'items_total',
        'items_accepted',
        'items_rejected',
        'rejected_summary',
        'ready_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'deadline_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'promoted_at' => 'datetime',
        'ready_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rejected_summary' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(UserSender::class, 'sender_id');
    }

    public function inbox(): HasOne
    {
        return $this->hasOne(ApiInbox::class);
    }

    public function staging(): HasOne
    {
        return $this->hasOne(RequestStaging::class);
    }
}
