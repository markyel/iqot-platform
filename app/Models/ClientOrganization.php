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
        'legal_address',
        'actual_address',
        'contact_person',
        'phone',
        'email',
        'director_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Найти организацию по ИНН (или по названию, если ИНН не задан),
     * либо создать новую. Используется при групповом импорте отправителей.
     *
     * @param array<string,mixed> $attributes
     */
    public static function findOrCreateForImport(array $attributes): self
    {
        $inn = trim((string) ($attributes['inn'] ?? ''));
        $name = trim((string) ($attributes['name'] ?? ''));

        if ($inn !== '') {
            $existing = static::where('inn', $inn)->first();
        } elseif ($name !== '') {
            $existing = static::where('name', $name)->first();
        } else {
            $existing = null;
        }

        if ($existing) {
            return $existing;
        }

        return static::create(array_merge(
            ['is_active' => true],
            array_filter($attributes, static fn ($v) => $v !== null && $v !== '')
        ));
    }

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
