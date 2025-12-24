<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company',
        'organization',
        'inn',
        'kpp',
        'phone',
        'telegram_id',
        'is_admin',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Доступ к Filament админке
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /**
     * Заявки пользователя
     */
    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    /**
     * Отчёты пользователя
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
