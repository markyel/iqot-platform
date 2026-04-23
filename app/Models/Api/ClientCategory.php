<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientCategory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'api_client_id',
        'external_code',
        'path_segments',
        'full_path',
        'leaf_name',
        'depth',
        'raw_metadata',
        'first_seen_at',
        'last_seen_at',
        'hit_count',
    ];

    protected $casts = [
        'path_segments' => 'array',
        'raw_metadata' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ClientCategoryCandidate::class);
    }

    public function activeCandidates(): HasMany
    {
        return $this->candidates()->where('is_active', true);
    }
}
