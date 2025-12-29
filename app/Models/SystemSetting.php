<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['setting_key', 'setting_value', 'setting_type', 'description'];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('setting_key', $key)->first();
            return $setting ? $setting->setting_value : $default;
        });
    }

    public static function set(string $key, $value): void
    {
        self::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );
        Cache::forget("setting_{$key}");
    }
}
