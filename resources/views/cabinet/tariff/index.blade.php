@extends('layouts.cabinet')

@section('title', 'Мой тариф')

@section('content')
<!-- Page Header -->
<x-page-header title="Мой тариф" description="Управление тарифным планом и лимитами">
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('cabinet.invoices.index')" icon="file-text">
            Документы
        </x-button>
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

        <div style="margin-top: var(--space-6); padding-top: var(--space-6); border-top: 1px solid var(--neutral-200);">
            <div style="display: flex; gap: var(--space-3); flex-wrap: wrap;">
                <x-button variant="primary" icon="plus" onclick="openTopUpModal()">
                    Пополнить баланс
                </x-button>
                @if(!auth()->user()->promo_code_id)
                    <x-button variant="secondary" icon="gift" onclick="openPromoCodeModal()">
                        Активировать промокод
                    </x-button>
                @else
                    <div style="display: flex; align-items: center; gap: var(--space-2); padding: 0.5rem 1rem; background: var(--success-50); border: 1px solid var(--success-200); border-radius: var(--radius-md);">
                        <i data-lucide="check-circle" style="width: 1rem; height: 1rem; color: var(--success-600);"></i>
                        <span style="font-size: var(--text-sm); color: var(--success-700);">
                            Промокод активирован
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно пополнения баланса -->
<div id="topUpModal" class="modal-overlay" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-container">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <div style="width: 40px; height: 40px; border-radius: var(--radius-lg); background: var(--primary-100); display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="wallet" style="width: 1.25rem; height: 1.25rem; color: var(--primary-600);"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Пополнение баланса</h3>
                        <p style="margin: 0; font-size: var(--text-sm); color: var(--neutral-600);">Выставление счета на оплату</p>
                    </div>
                </div>
                <button type="button" onclick="closeTopUpModal()" style="background: none; border: none; cursor: pointer; padding: var(--space-2); color: var(--neutral-600);">
                    <i data-lucide="x" style="width: 1.25rem; height: 1.25rem;"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('cabinet.invoices.request') }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info" style="margin-bottom: var(--space-4);">
                        <i data-lucide="info" style="width: 16px; height: 16px;"></i>
                        <div>
                            Вам будет выставлен счет на указанную сумму. После оплаты счета средства автоматически поступят на ваш баланс.
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="amount">
                            Сумма пополнения (₽) <span class="text-danger">*</span>
                        </label>
                        <input
                            type="number"
                            id="amount"
                            name="amount"
                            class="input"
                            min="100"
                            step="0.01"
                            placeholder="Минимум 100 ₽"
                            required
                        >
                        <small class="form-help">
                            Минимальная сумма пополнения: 100 ₽
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="notes">
                            Комментарий (опционально)
                        </label>
                        <textarea
                            id="notes"
                            name="notes"
                            class="input"
                            rows="3"
                            placeholder="Например: Пополнение для тестовой заявки"
                        ></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <x-button type="button" variant="secondary" onclick="closeTopUpModal()">
                        Отмена
                    </x-button>
                    <x-button type="submit" variant="primary" icon="file-text">
                        Выставить счет
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openTopUpModal() {
    document.getElementById('topUpModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    setTimeout(() => lucide.createIcons(), 100);
}

function closeTopUpModal() {
    document.getElementById('topUpModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Закрытие по клику на overlay
document.addEventListener('click', function(event) {
    const modal = document.getElementById('topUpModal');
    if (event.target === modal) {
        closeTopUpModal();
    }
});

// Закрытие по Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeTopUpModal();
    }
});

lucide.createIcons();
</script>
@endpush

@push('styles')
<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(2px);
    z-index: 9999;
    overflow-y: auto;
}

.modal-dialog {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-6);
}

.modal-container {
    background: var(--neutral-0);
    border-radius: var(--radius-xl);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 540px;
    width: 100%;
    position: relative;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-6);
    border-bottom: 1px solid var(--neutral-200);
}

.modal-body {
    padding: var(--space-6);
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: var(--space-3);
    padding: var(--space-6);
    border-top: 1px solid var(--neutral-200);
    background: var(--neutral-50);
    border-bottom-left-radius: var(--radius-xl);
    border-bottom-right-radius: var(--radius-xl);
}
</style>
@endpush

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
                                @if($tariff->pdf_reports_enabled)
                                    Экспорт в PDF.
                                @endif
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
                        @if($tariff->pdf_reports_enabled)
                        <div style="display: flex; align-items: start; gap: var(--space-2); margin-bottom: var(--space-2);">
                            <i data-lucide="check" style="width: 16px; height: 16px; color: var(--color-success); margin-top: 2px;"></i>
                            <span style="font-size: var(--text-sm);">
                                Экспорт отчета в PDF
                            </span>
                        </div>
                        @endif
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

<!-- Модальное окно активации промокода -->
<div id="promoCodeModal" class="modal-overlay" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-container">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <div style="width: 40px; height: 40px; border-radius: var(--radius-lg); background: var(--accent-100); display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="gift" style="width: 1.25rem; height: 1.25rem; color: var(--accent-600);"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Активация промокода</h3>
                        <p style="margin: 0; font-size: var(--text-sm); color: var(--neutral-600);">Введите промокод для пополнения баланса</p>
                    </div>
                </div>
                <button type="button" onclick="closePromoCodeModal()" style="background: none; border: none; cursor: pointer; padding: var(--space-2); color: var(--neutral-600);">
                    <i data-lucide="x" style="width: 1.25rem; height: 1.25rem;"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('cabinet.tariff.apply-promo-code') }}">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="promo_code_input">Промокод</label>
                        <input
                            type="text"
                            id="promo_code_input"
                            name="promo_code"
                            class="input"
                            placeholder="Введите код"
                            style="text-transform: uppercase;"
                            required
                            autocomplete="off"
                        >
                        <small class="form-help" style="display: block; margin-top: 0.5rem;">
                            Промокод можно использовать только один раз
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <x-button type="button" variant="ghost" onclick="closePromoCodeModal()">
                        Отмена
                    </x-button>
                    <x-button type="submit" variant="accent" icon="check">
                        Активировать
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Reinitialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Promo code modal
    function openPromoCodeModal() {
        document.getElementById('promoCodeModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            document.getElementById('promo_code_input')?.focus();
            lucide.createIcons();
        }, 100);
    }

    function closePromoCodeModal() {
        document.getElementById('promoCodeModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    // Close on outside click
    document.getElementById('promoCodeModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closePromoCodeModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePromoCodeModal();
        }
    });
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
