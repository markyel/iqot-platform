<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignImage extends Model
{
    protected $fillable = [
        'campaign_id',
        'original_src',
        'file_path',
        'cid',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
