@extends('layouts.cabinet')

@section('title', 'Мой тариф')

@section('content')
<!-- Page Header -->
<x-page-header title="Мой тариф" description="Управление тарифным планом и лимитами">
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('cabinet.tariff.transactions')" icon="list">
            Детализация расходов
        </x-button>
        <x-button variant="secondary" :href="route('cabinet.tariff.limits-usage')" icon="bar-chart-3">
            Детализация лимитов
        </x-button>
    </x-slot>
</x-page-header>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom: var(--space-6);">
    <i data-lucide="check-circle" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 8px;"></i>
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="alert alert-error" style="margin-bottom: var(--space-6);">
    <i data-lucide="alert-circle" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 8px;"></i>
    {{ session('error') }}
</div>
@endif

<!-- Баланс пользователя -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <i data-lucide="wallet" style="width: 1.25rem; height: 1.25rem;"></i>
        Баланс
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-6);">
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Доступный баланс
                </div>
                <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--primary-600);">
                    {{ number_format(auth()->user()->available_balance, 2) }} ₽
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Заморожено
                </div>
                <div style="font-size: var(--text-2xl); font-weight: 600; color: var(--neutral-700);">
                    {{ number_format(auth()->user()->held_balance, 2) }} ₽
                </div>
                <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-top: var(--space-1);">
                    Средства на обработку заявок
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Всего на счету
                </div>
                <div style="font-size: var(--text-2xl); font-weight: 600;">
                    {{ number_format(auth()->user()->balance, 2) }} ₽
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Текущий тариф и лимиты -->
@if($currentTariff && $limitsInfo['has_tariff'])
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title">Текущий тариф</h2>
        @if($currentTariff->tariffPlan->monthly_price > 0 && $limitsInfo['days_left'] !== null)
        <span class="text-muted">
            <i data-lucide="clock" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle;"></i>
            Осталось {{ $limitsInfo['days_left'] }} дней
        </span>
        @endif
    </div>

    <div class="card-body">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-6);">
            <!-- Информация о тарифе -->
            <div>
                <h3 style="font-size: var(--text-2xl); font-weight: 600; margin-bottom: var(--space-2);">
                    {{ $currentTariff->tariffPlan->name }}
                </h3>
                <p class="text-muted" style="margin-bottom: var(--space-4);">
                    @if($currentTariff->tariffPlan->items_limit !== null || $currentTariff->tariffPlan->reports_limit !== null)
                        Включает
                        @if($currentTariff->tariffPlan->items_limit !== null)
                            {{ $currentTariff->tariffPlan->items_limit }} позиций в заявках
                        @endif
                        @if($currentTariff->tariffPlan->items_limit !== null && $currentTariff->tariffPlan->reports_limit !== null)
                            и
                        @endif
                        @if($currentTariff->tariffPlan->reports_limit !== null)
                            {{ $currentTariff->tariffPlan->reports_limit }} отчетов
                        @endif
                        в месяц.
                        @if($currentTariff->tariffPlan->price_per_item_over_limit > 0)
                            Сверх лимита — по {{ number_format($currentTariff->tariffPlan->price_per_item_over_limit, 0) }} ₽/позиция.
                        @endif
                    @else
                        Без включенных кредитов. Каждая позиция и отчет оплачиваются отдельно.
                    @endif
                </p>

                @if($currentTariff->tariffPlan->monthly_price > 0)
                <div style="margin-bottom: var(--space-2);">
                    <span class="text-muted">Абонентская плата:</span>
                    <strong style="font-size: var(--text-xl); color: var(--color-accent);">
                        {{ number_format($currentTariff->tariffPlan->monthly_price, 0, ',', ' ') }} ₽/мес
                    </strong>
                </div>
                @else
                <div style="margin-bottom: var(--space-2);">
                    <x-badge type="success">Бесплатный тариф</x-badge>
                </div>
                @endif

                @if($limitsInfo['expires_at'])
                <div class="text-muted" style="font-size: var(--text-sm);">
                    Действует до: {{ $limitsInfo['expires_at']->format('d.m.Y') }}
                </div>
                @endif
            </div>

            <!-- Лимиты позиций -->
            <div class="card" style="background: var(--color-bg-secondary);">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-3);">
                        <div style="width: 40px; height: 40px; border-radius: var(--radius-lg); background: var(--color-primary-alpha); display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="file-text" style="width: 20px; height: 20px; color: var(--color-primary);"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size: var(--text-sm);">Позиции в заявках</div>
                            <div style="font-size: var(--text-xl); font-weight: 600;">
                                @if($limitsInfo['items_limit'] !== null)
                                    {{ $limitsInfo['items_used'] }} / {{ $limitsInfo['items_limit'] }}
                                @else
                                    {{ $limitsInfo['items_used'] }} / ∞
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($limitsInfo['items_limit'] !== null)
                    <div class="progress-bar" style="margin-bottom: var(--space-2);">
                        <div class="progress-fill" style="width: {{ min(100, $limitsInfo['items_used_percentage'] ?? 0) }}%;"></div>
                    </div>
                    <div class="text-muted" style="font-size: var(--text-sm);">
                        @if($limitsInfo['items_remaining'] > 0)
                            Осталось: {{ $limitsInfo['items_remaining'] }}
                        @else
                            Лимит исчерпан. Сверх лимита: {{ number_format($currentTariff->tariffPlan->price_per_item_over_limit, 0) }} ₽/позиция
                        @endif
                    </div>
                    @else
                    <div class="text-muted" style="font-size: var(--text-sm);">
                        Безлимитное использование
                    </div>
                    @endif
                </div>
            </div>

            <!-- Лимиты отчетов -->
            <div class="card" style="background: var(--color-bg-secondary);">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-3);">
                        <div style="width: 40px; height: 40px; border-radius: var(--radius-lg); background: var(--color-accent-alpha); display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="bar-chart-3" style="width: 20px; height: 20px; color: var(--color-accent);"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size: var(--text-sm);">Открытые отчеты</div>
                            <div style="font-size: var(--text-xl); font-weight: 600;">
                                @if($limitsInfo['reports_limit'] !== null)
                                    {{ $limitsInfo['reports_used'] }} / {{ $limitsInfo['reports_limit'] }}
                                @else
                                    {{ $limitsInfo['reports_used'] }} / ∞
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($limitsInfo['reports_limit'] !== null)
                    <div class="progress-bar" style="margin-bottom: var(--space-2);">
                        <div class="progress-fill" style="width: {{ min(100, $limitsInfo['reports_used_percentage'] ?? 0) }}%;"></div>
                    </div>
                    <div class="text-muted" style="font-size: var(--text-sm);">
                        @if($limitsInfo['reports_remaining'] > 0)
                            Осталось: {{ $limitsInfo['reports_remaining'] }}
                        @else
                            Лимит исчерпан. Сверх лимита: {{ number_format($currentTariff->tariffPlan->price_per_report_over_limit, 0) }} ₽/отчет
                        @endif
                    </div>
                    @else
                    <div class="text-muted" style="font-size: var(--text-sm);">
                        Безлимитное использование
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@else
<div class="card" style="margin-bottom: var(--space-6);">
    <x-empty-state
        icon="alert-circle"
        title="Нет активного тарифа"
        description="Выберите тариф для начала работы"
    />
</div>
@endif

<!-- Доступные тарифы -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Доступные тарифы</h2>
    </div>

    <div class="card-body">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-6);">
            @foreach($availableTariffs as $tariff)
            <div class="card tariff-card" style="border: 2px solid {{ $currentTariff && $currentTariff->tariffPlan->id === $tariff->id ? 'var(--color-primary)' : 'var(--color-border)' }};">
                <div class="card-body">
                    <!-- Название тарифа -->
                    <div style="margin-bottom: var(--space-4);">
                        <h3 style="font-size: var(--text-xl); font-weight: 600; margin-bottom: var(--space-2);">
                            {{ $tariff->name }}
                            @if($currentTariff && $currentTariff->tariffPlan->id === $tariff->id)
                            <x-badge type="primary" style="margin-left: var(--space-2);">Текущий</x-badge>
                            @endif
                        </h3>
                        <div class="text-muted" style="font-size: var(--text-sm); min-height: 40px;">
                            @if($tariff->items_limit !== null || $tariff->reports_limit !== null)
                                Включает
                                @if($tariff->items_limit !== null)
                                    {{ $tariff->items_limit }} позиций в заявках
                                @endif
                                @if($tariff->items_limit !== null && $tariff->reports_limit !== null)
                                    и
                                @endif
                                @if($tariff->reports_limit !== null)
                                    {{ $tariff->reports_limit }} отчетов
                                @endif
                                в месяц.
                                @if($tariff->price_per_item_over_limit > 0)
                                    Сверх лимита — по {{ number_format($tariff->price_per_item_over_limit, 0) }} ₽/позиция.
                                @endif
                            @else
                                Без включенных кредитов. Каждая позиция и отчет оплачиваются отдельно.
                            @endif
                        </div>
                    </div>

                    <!-- Цена -->
                    <div style="margin-bottom: var(--space-4); padding: var(--space-4); background: var(--color-bg-secondary); border-radius: var(--radius-md);">
                        @if($tariff->monthly_price > 0)
                        <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--color-primary);">
                            {{ number_format($tariff->monthly_price, 0, ',', ' ') }} ₽
                        </div>
                        <div class="text-muted" style="font-size: var(--text-sm);">в месяц</div>
                        @else
                        <div style="font-size: var(--text-2xl); font-weight: 700; color: var(--color-success);">
                            Бесплатно
                        </div>
                        <div class="text-muted" style="font-size: var(--text-sm);">без абонентской платы</div>
                        @endif
                    </div>

                    <!-- Лимиты -->
                    <div style="margin-bottom: var(--space-4);">
                        <div style="display: flex; align-items: start; gap: var(--space-2); margin-bottom: var(--space-2);">
                            <i data-lucide="check" style="width: 16px; height: 16px; color: var(--color-success); margin-top: 2px;"></i>
                            <span style="font-size: var(--text-sm);">
                                @if($tariff->items_limit !== null)
                                    <strong>{{ $tariff->items_limit }}</strong> позиций в заявках
                                @else
                                    <strong>Безлимит</strong> позиций в заявках
                                @endif
                            </span>
                        </div>
                        <div style="display: flex; align-items: start; gap: var(--space-2); margin-bottom: var(--space-2);">
                            <i data-lucide="check" style="width: 16px; height: 16px; color: var(--color-success); margin-top: 2px;"></i>
                            <span style="font-size: var(--text-sm);">
                                @if($tariff->reports_limit !== null)
                                    <strong>{{ $tariff->reports_limit }}</strong> отчетов
                                @else
                                    <strong>Безлимит</strong> отчетов
                                @endif
                            </span>
                        </div>
                        @if($tariff->price_per_item_over_limit > 0)
                        <div style="display: flex; align-items: start; gap: var(--space-2);">
                            <i data-lucide="info" style="width: 16px; height: 16px; color: var(--color-text-muted); margin-top: 2px;"></i>
                            <span style="font-size: var(--text-sm); color: var(--color-text-muted);">
                                Сверх лимита: {{ number_format($tariff->price_per_item_over_limit, 0) }} ₽/позиция
                            </span>
                        </div>
                        @endif
                    </div>

                    <!-- Кнопка выбора -->
                    @if(!$currentTariff || $currentTariff->tariffPlan->id !== $tariff->id)
                    <form action="{{ route('cabinet.tariff.switch') }}" method="POST" onsubmit="return confirm('Вы уверены, что хотите сменить тариф на «{{ $tariff->name }}»?');">
                        @csrf
                        <input type="hidden" name="tariff_plan_id" value="{{ $tariff->id }}">
                        <x-button
                            type="submit"
                            variant="{{ $tariff->monthly_price > 0 ? 'primary' : 'accent' }}"
                            style="width: 100%;"
                        >
                            Выбрать тариф
                        </x-button>
                    </form>
                    @else
                    <x-button variant="ghost" style="width: 100%;" disabled>
                        Текущий тариф
                    </x-button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Reinitialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
@endpush

@push('styles')
<style>
.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--color-bg-tertiary);
    border-radius: var(--radius-full);
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--color-primary), var(--color-accent));
    border-radius: var(--radius-full);
    transition: width 0.3s ease;
}

.tariff-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.tariff-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.alert {
    padding: var(--space-4);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
}

.alert-success {
    background: var(--color-success-alpha);
    color: var(--color-success);
    border: 1px solid var(--color-success);
}

.alert-error {
    background: var(--color-error-alpha);
    color: var(--color-error);
    border: 1px solid var(--color-error);
}
</style>
@endpush
@endsection
