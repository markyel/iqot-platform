<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'api_client_id',
        'key_hash',
        'key_prefix',
        'key_last4',
        'name',
        'ip_whitelist',
        'last_used_at',
        'last_used_ip',
        'request_count',
        'revoked_at',
    ];

    protected $casts = [
        'ip_whitelist' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $hidden = [
        'key_hash',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
