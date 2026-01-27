<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'email',
        'data',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Помечает письмо как отправленное
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Помечает письмо как неудачное
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }
}
