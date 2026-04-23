<?php

namespace App\Models\Api;

use App\Models\BalanceHold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestItemStaging extends Model
{
    protected $table = 'request_items_staging';

    protected $fillable = [
        'request_staging_id',
        'client_item_ref',
        'position_number',
        'name',
        'article',
        'brand',
        'quantity',
        'unit',
        'description',
        'client_category_id',
        'product_type_id',
        'domain_id',
        'type_confidence',
        'domain_confidence',
        'classification_source',
        'needs_review',
        'trust_level',
        'item_status',
        'rejection_reason',
        'rejection_message',
        'balance_hold_id',
        'promoted_request_item_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'type_confidence' => 'decimal:2',
        'domain_confidence' => 'decimal:2',
        'needs_review' => 'boolean',
    ];

    public function staging(): BelongsTo
    {
        return $this->belongsTo(RequestStaging::class, 'request_staging_id');
    }

    public function clientCategory(): BelongsTo
    {
        return $this->belongsTo(ClientCategory::class, 'client_category_id');
    }

    public function balanceHold(): BelongsTo
    {
        return $this->belongsTo(BalanceHold::class, 'balance_hold_id');
    }
}
