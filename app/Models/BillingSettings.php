<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSettings extends Model
{
    protected $fillable = [
        'name',
        'full_name',
        'inn',
        'kpp',
        'ogrnip',
        'ogrn',
        'address',
        'bank_name',
        'bank_bik',
        'bank_corr_account',
        'bank_account',
        'director_name',
        'director_short',
        'director_position',
        'accountant_name',
        'registration_date',
        'email',
        'phone',
        'website',
        'signature_image',
        'stamp_image',
        'invoice_number_mask',
        'invoice_number_start',
        'invoice_number_current',
        'vat_enabled',
        'vat_rate',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'vat_enabled' => 'boolean',
        'vat_rate' => 'decimal:2',
    ];

    /**
     * Получить текущие настройки биллинга (первая запись)
     */
    public static function current(): ?self
    {
        return self::first();
    }
}
