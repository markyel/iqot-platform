<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCategoryCandidate extends Model
{
    protected $fillable = [
        'client_category_id',
        'product_type_id',
        'domain_id',
        'priority',
        'confidence',
        'source',
        'is_active',
        'hit_count',
        'last_hit_at',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'is_active' => 'boolean',
        'last_hit_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClientCategory::class, 'client_category_id');
    }
}
