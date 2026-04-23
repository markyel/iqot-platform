<?php

namespace App\Models\Api;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSender extends Model
{
    protected $fillable = [
        'user_id',
        'client_organization_id',
        'external_sender_id',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Логическая ссылка на reports.client_organizations — cross-DB, без FK.
     * Ленивый аксессор реализуется при необходимости через ClientOrganization::find().
     */
}
