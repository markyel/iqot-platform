<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalSupplier extends Model
{
    protected $connection = 'reports';
    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'telegram_id',
        'telegram_username',
        'categories',
        'preferred_template_id',
        'notify_email',
        'notify_telegram',
        'notify_sms',
        'is_active',
        'rating',
        'notes',
        'alternative_emails',
        'website',
        'scope_product_types',
        'scope_domains',
        'profile_source',
        'profile_confidence',
        'profile_updated_at',
        'total_requests_sent',
        'total_responses_received',
        'total_prices_offered',
    ];

    protected $casts = [
        'categories' => 'array',
        'alternative_emails' => 'array',
        'notify_email' => 'boolean',
        'notify_telegram' => 'boolean',
        'notify_sms' => 'boolean',
        'is_active' => 'boolean',
        'rating' => 'decimal:2',
        'profile_confidence' => 'decimal:2',
        'profile_updated_at' => 'datetime',
        'total_requests_sent' => 'integer',
        'total_responses_received' => 'integer',
        'total_prices_offered' => 'integer',
    ];

    public function offers(): HasMany
    {
        return $this->hasMany(ExternalOffer::class, 'supplier_id');
    }
}
