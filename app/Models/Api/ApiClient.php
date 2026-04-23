<?php

namespace App\Models\Api;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiClient extends Model
{
    protected $fillable = [
        'user_id',
        'is_active',
        'overdraft_percent',
        'auto_approve_green',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'overdraft_percent' => 'decimal:2',
        'auto_approve_green' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function activeKeys(): HasMany
    {
        return $this->keys()->whereNull('revoked_at');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ApiSubmission::class);
    }
}
