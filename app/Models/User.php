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
     * Тарифы пользователя
     */
    public function tariffs(): HasMany
    {
        return $this->hasMany(UserTariff::class);
    }

    /**
     * Активный тариф пользователя
     */
    public function activeTariff()
    {
        return $this->hasOne(UserTariff::class)->where('is_active', true)->latest();
    }

    /**
     * Получить активный тариф пользователя
     */
    public function getActiveTariff(): ?UserTariff
    {
        return $this->tariffs()->where('is_active', true)->with('tariffPlan')->first();
    }

    /**
     * Получить тарифный план пользователя
     */
    public function getTariffPlan(): ?TariffPlan
    {
        $activeTariff = $this->getActiveTariff();
        return $activeTariff ? $activeTariff->tariffPlan : null;
    }

    /**
     * Проверить, может ли пользователь создать заявку с указанным количеством позиций
     */
    public function canCreateRequestWithItems(int $itemsCount): bool
    {
        $activeTariff = $this->getActiveTariff();

        if (!$activeTariff) {
            return false; // Нет активного тарифа
        }

        return $activeTariff->canCreateRequest($itemsCount);
    }

    /**
     * Рассчитать стоимость создания заявки с учетом тарифа
     */
    public function calculateRequestCostWithTariff(int $itemsCount): float
    {
        $activeTariff = $this->getActiveTariff();

        if (!$activeTariff) {
            // Если нет тарифа, используем старую логику (цена за позицию)
            $pricePerItem = SystemSetting::get('price_per_item', 50);
            return $itemsCount * $pricePerItem;
        }

        return $activeTariff->calculateRequestCost($itemsCount);
    }

    /**
     * Назначить тариф пользователю
     */
    public function assignTariff(TariffPlan $tariffPlan): UserTariff
    {
        // Деактивируем все предыдущие тарифы
        $this->tariffs()->where('is_active', true)->update(['is_active' => false]);

        // Создаем новый активный тариф
        return $this->tariffs()->create([
            'tariff_plan_id' => $tariffPlan->id,
            'started_at' => now(),
            'expires_at' => $tariffPlan->monthly_price > 0 ? now()->addMonth() : null,
            'is_active' => true,
            'items_used' => 0,
            'reports_used' => 0,
        ]);
    }

    /**
     * Переключить тариф пользователя
     */
    public function switchTariff(TariffPlan $newTariffPlan): UserTariff
    {
        $currentTariff = $this->getActiveTariff();

        // Если есть текущий тариф, деактивируем его
        if ($currentTariff) {
            $currentTariff->deactivate();
        }

        // Списываем абонентскую плату с баланса, если тариф платный
        if ($newTariffPlan->monthly_price > 0) {
            if (!$this->canAfford($newTariffPlan->monthly_price)) {
                throw new \Exception('Недостаточно средств на балансе для активации тарифа');
            }

            $this->decrement('balance', $newTariffPlan->monthly_price);
        }

        return $this->assignTariff($newTariffPlan);
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
     * Замороженный баланс
     */
    public function getHeldBalanceAttribute(): float
    {
        return (float) $this->balanceHolds()
            ->where('status', 'held')
            ->sum('amount');
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

    /**
     * Проверить, есть ли доступ к позиции (через покупку или через свою заявку)
     */
    public function hasAccessToItem(ExternalRequestItem $item): bool
    {
        // Проверяем покупку
        if (ItemPurchase::where('user_id', $this->id)->where('item_id', $item->id)->exists()) {
            return true;
        }

        // Проверяем, принадлежит ли позиция заявке пользователя
        if ($item->request) {
            // Получаем номер заявки из external request
            $requestNumber = $item->request->request_number;

            // Проверяем, есть ли у пользователя заявка с таким номером
            return $this->requests()
                ->where('request_number', $requestNumber)
                ->where('synced_to_main_db', true)
                ->exists();
        }

        return false;
    }
}
