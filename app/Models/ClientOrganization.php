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
        'ogrn',
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
        $attributes = static::sanitizeImportAttributes($attributes);

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
     * Защитная нормализация реквизитов перед вставкой. Данные из Excel/AI бывают
     * кривыми (в колонку реквизита заезжает телефон/адрес), а ИНН/КПП/ОГРН и
     * строковые поля ограничены по длине — без чистки insert падает (1406).
     *
     * @param array<string,mixed> $attributes
     * @return array<string,mixed>
     */
    private static function sanitizeImportAttributes(array $attributes): array
    {
        // Числовые реквизиты: только цифры и строго ожидаемая длина, иначе null.
        $attributes['inn'] = static::digitsOfLength($attributes['inn'] ?? null, [10, 12]);
        $attributes['kpp'] = static::digitsOfLength($attributes['kpp'] ?? null, [9]);
        $attributes['ogrn'] = static::digitsOfLength($attributes['ogrn'] ?? null, [13, 15]);

        // Строковые поля обрезаем под длину колонок (legal/actual_address — TEXT).
        $limits = [
            'name' => 500,
            'contact_person' => 255,
            'phone' => 50,
            'email' => 255,
            'director_name' => 255,
        ];
        foreach ($limits as $field => $max) {
            if (isset($attributes[$field]) && is_string($attributes[$field])) {
                $attributes[$field] = mb_substr(trim($attributes[$field]), 0, $max);
            }
        }

        return $attributes;
    }

    /**
     * Вернуть только цифры значения, если их количество входит в $lengths,
     * иначе null (значение признаётся невалидным реквизитом).
     *
     * @param int[] $lengths
     */
    private static function digitsOfLength(mixed $value, array $lengths): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return in_array(strlen($digits), $lengths, true) ? $digits : null;
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
