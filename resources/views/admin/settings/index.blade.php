@extends('layouts.cabinet')

@section('title', 'Настройки системы')

<x-page-header
    title="Настройки системы"
    description="Управление параметрами работы системы мониторинга позиций"
/>

@section('content')
<div style="max-width: 900px; margin: 0 auto;">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">
            {{ session('success') }}
        </div>
    @endif

    <div class="alert alert-info" style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: start; gap: var(--space-3);">
            <i data-lucide="info" class="icon-md"></i>
            <div>
                <strong>Информация</strong>
                <p style="margin-top: var(--space-1); margin-bottom: 0;">
                    Здесь вы можете настроить основные параметры работы системы мониторинга позиций.
                    Изменения вступают в силу немедленно для всех пользователей.
                </p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="settings" class="icon-md"></i>
                Настройки ценообразования
            </h2>
        </div>
        <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            <h3 style="margin: 0 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="dollar-sign" class="icon-sm"></i>
                Базовые настройки системы
            </h3>

            <div class="form-group">
                <label class="form-label">
                    Стоимость разблокировки отчета по позиции (₽)
                </label>
                <input
                    type="number"
                    name="item_unlock_price"
                    value="{{ $unlockPrice }}"
                    step="0.01"
                    min="0"
                    class="input"
                    style="max-width: 400px;"
                    required
                >
                <small class="form-help">
                    Эта сумма будет списываться с баланса пользователя при получении полного доступа к отчету по позиции
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Стоимость мониторинга одной позиции в заявке (₽)
                </label>
                <input
                    type="number"
                    name="price_per_item"
                    value="{{ $pricePerItem }}"
                    step="0.01"
                    min="0"
                    class="input"
                    style="max-width: 400px;"
                    required
                >
                <small class="form-help">
                    Эта сумма замораживается на балансе при создании заявки (за каждую позицию). После обработки заявки средства списываются
                </small>
            </div>

            <h3 style="margin: var(--space-8) 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="tag" class="icon-sm"></i>
                Тарифы для лендинга
            </h3>

            <div class="form-group">
                <label class="form-label">
                    Стоимость мониторинга позиции в заявке (₽)
                </label>
                <input
                    type="number"
                    name="pricing_monitoring"
                    value="{{ $pricingMonitoring }}"
                    step="0.01"
                    min="0"
                    class="input"
                    style="max-width: 400px;"
                    required
                >
                <small class="form-help">
                    Цена за мониторинг одной позиции для разовых операций (отображается на лендинге)
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Стоимость разблокировки отчета (₽)
                </label>
                <input
                    type="number"
                    name="pricing_report_unlock"
                    value="{{ $pricingReportUnlock }}"
                    step="0.01"
                    min="0"
                    class="input"
                    style="max-width: 400px;"
                    required
                >
                <small class="form-help">
                    Цена за разблокировку отчета для разовых операций (отображается на лендинге)
                </small>
            </div>

            <h3 style="margin: var(--space-8) 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="zap" class="icon-sm"></i>
                Тариф «Базовый»
            </h3>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-4);">
                <div class="form-group">
                    <label class="form-label">Стоимость подписки (₽/мес)</label>
                    <input type="number" name="subscription_basic_price" value="{{ $subscriptionBasicPrice }}" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Позиций в месяц (шт)</label>
                    <input type="number" name="subscription_basic_positions" value="{{ $subscriptionBasicPositions }}" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Отчетов в месяц (шт)</label>
                    <input type="number" name="subscription_basic_reports" value="{{ $subscriptionBasicReports }}" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: позиция (₽)</label>
                    <input type="number" name="subscription_basic_overlimit_position" value="{{ $subscriptionBasicOverlimitPosition }}" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: отчет (₽)</label>
                    <input type="number" name="subscription_basic_overlimit_report" value="{{ $subscriptionBasicOverlimitReport }}" step="0.01" min="0" class="input" required>
                </div>
            </div>

            <h3 style="margin: var(--space-8) 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="trending-up" class="icon-sm"></i>
                Тариф «Расширенный»
            </h3>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-4);">
                <div class="form-group">
                    <label class="form-label">Стоимость подписки (₽/мес)</label>
                    <input type="number" name="subscription_advanced_price" value="{{ $subscriptionAdvancedPrice }}" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Позиций в месяц (шт)</label>
                    <input type="number" name="subscription_advanced_positions" value="{{ $subscriptionAdvancedPositions }}" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Отчетов в месяц (шт)</label>
                    <input type="number" name="subscription_advanced_reports" value="{{ $subscriptionAdvancedReports }}" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: позиция (₽)</label>
                    <input type="number" name="subscription_advanced_overlimit_position" value="{{ $subscriptionAdvancedOverlimitPosition }}" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: отчет (₽)</label>
                    <input type="number" name="subscription_advanced_overlimit_report" value="{{ $subscriptionAdvancedOverlimitReport }}" step="0.01" min="0" class="input" required>
                </div>
            </div>

            <h3 style="margin: var(--space-8) 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="star" class="icon-sm"></i>
                Тариф «Профессиональный»
            </h3>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-4);">
                <div class="form-group">
                    <label class="form-label">Стоимость подписки (₽/мес)</label>
                    <input type="number" name="subscription_pro_price" value="{{ $subscriptionProPrice }}" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Позиций в месяц (шт)</label>
                    <input type="number" name="subscription_pro_positions" value="{{ $subscriptionProPositions }}" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Отчетов в месяц (шт)</label>
                    <input type="number" name="subscription_pro_reports" value="{{ $subscriptionProReports }}" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: позиция (₽)</label>
                    <input type="number" name="subscription_pro_overlimit_position" value="{{ $subscriptionProOverlimitPosition }}" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: отчет (₽)</label>
                    <input type="number" name="subscription_pro_overlimit_report" value="{{ $subscriptionProOverlimitReport }}" step="0.01" min="0" class="input" required>
                </div>
            </div>

            <div style="margin-top: var(--space-8);">
                <x-button variant="accent" type="submit" icon="check">
                    Сохранить настройки
                </x-button>
            </div>
        </form>
        </div>
    </div>

    <div class="card" style="margin-top: var(--space-6);">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="bar-chart-3" class="icon-md"></i>
                Статистика системы
            </h2>
        </div>
        <div class="card-body">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4);">
            <x-stat-card
                icon="dollar-sign"
                icon-type="success"
                :value="number_format($unlockPrice, 0) . ' ₽'"
                label="Цена разблокировки"
            />
            <x-stat-card
                icon="package"
                icon-type="success"
                :value="number_format($pricePerItem, 0) . ' ₽'"
                label="Цена за позицию"
            />
            <x-stat-card
                icon="users"
                icon-type="primary"
                :value="\App\Models\User::count()"
                label="Всего пользователей"
            />
            <x-stat-card
                icon="shopping-cart"
                icon-type="primary"
                :value="\App\Models\ItemPurchase::count()"
                label="Всего покупок"
            />
            <x-stat-card
                icon="trending-up"
                icon-type="accent"
                :value="number_format(\App\Models\ItemPurchase::sum('amount'), 2) . ' ₽'"
                label="Общая выручка"
            />
        </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
