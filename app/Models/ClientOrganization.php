<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientOrganization extends Model
{
    protected $connection = 'reports';
    protected $table = 'client_organizations';

    protected $fillable = [
        'name',
        'inn',
        'kpp',
        'address',
        'contact_person',
        'contact_email',
        'contact_phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Получить активные организации для выбора
     */
    public static function getActiveForSelect(): array
    {
        return static::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($org) {
                $label = $org->name;
                if ($org->inn) {
                    $label .= " (ИНН: {$org->inn})";
                }
                return [$org->id => $label];
            })
            ->toArray();
    }
}
