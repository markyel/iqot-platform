<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationDomain extends Model
{
    protected $connection = 'reports';
    protected $table = 'application_domains';

    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Получить активные области для выбора
     */
    public static function getActiveForSelect(): array
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
