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
        'full_name',
        'email',
        'password',
        'company',
        'organization',
        'inn',
        'kpp',
        'legal_address',
        'contact_person',
        'phone',
        'company_phone',
        'company_details',
        'telegram_id',
        'is_admin',
        'settings',
        'sender_id',
        'client_organization_id',
        'balance',
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

    /**
     * Заморозки баланса
     */
    public function balanceHolds(): HasMany
    {
        return $this->hasMany(BalanceHold::class);
    }

    /**
     * Доступный баланс (за вычетом замороженных средств)
     */
    public function getAvailableBalanceAttribute(): float
    {
        $heldAmount = $this->balanceHolds()
            ->where('status', 'held')
            ->sum('amount');

        return (float) ($this->balance - $heldAmount);
    }

    /**
     * Проверка возможности заморозки средств
     */
    public function canAfford(float $amount): bool
    {
        return $this->getAvailableBalanceAttribute() >= $amount;
    }

    /**
     * Заморозить средства на балансе
     */
    public function holdBalance(float $amount, ?int $requestId = null, ?string $description = null): BalanceHold
    {
        return $this->balanceHolds()->create([
            'request_id' => $requestId,
            'amount' => $amount,
            'status' => 'held',
            'description' => $description,
        ]);
    }
}
