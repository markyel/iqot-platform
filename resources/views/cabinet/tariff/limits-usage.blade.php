@extends('layouts.cabinet')

@section('title', 'Детализация лимитов')

@section('content')
<x-page-header
    title="Детализация использования лимитов"
    description="Подробная информация об использовании тарифных лимитов"
    :breadcrumbs="[
        ['label' => 'Мой тариф', 'url' => route('cabinet.tariff.index')],
        ['label' => 'Детализация лимитов']
    ]"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('cabinet.tariff.index')" icon="arrow-left">
            Назад к тарифу
        </x-button>
    </x-slot>
</x-page-header>

<!-- Информация о тарифе -->
@if($tariff)
<div class="card">
    <div class="card-header">
        <i data-lucide="package" style="width: 1.25rem; height: 1.25rem;"></i>
        Текущий тариф
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-4);">
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Тариф
                </div>
                <div style="font-size: var(--text-xl); font-weight: 600;">
                    {{ $tariff->tariffPlan->name }}
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Период действия
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    @if($tariff->started_at && $tariff->expires_at)
                        {{ $tariff->started_at->format('d.m.Y') }} - {{ $tariff->expires_at->format('d.m.Y') }}
                    @else
                        Бессрочно
                    @endif
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Осталось дней
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    @if($tariff->expires_at)
                        {{ max(0, now()->diffInDays($tariff->expires_at, false)) }} дней
                    @else
                        ∞
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Лимиты на позиции -->
<div class="card">
    <div class="card-header">
        <i data-lucide="file-text" style="width: 1.25rem; height: 1.25rem;"></i>
        Лимит на позиции в заявках
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Использовано
                </div>
                <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--primary-600);">
                    {{ $itemsUsed }}
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Лимит
                </div>
                <div style="font-size: var(--text-2xl); font-weight: 600;">
                    @if($itemsLimit !== null)
                        {{ $itemsLimit }}
                    @else
                        ∞
                    @endif
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Осталось
                </div>
                <div style="font-size: var(--text-2xl); font-weight: 600; color: {{ $itemsRemaining > 0 ? 'var(--success-600)' : 'var(--danger-600)' }};">
                    @if($itemsLimit !== null)
                        {{ max(0, $itemsRemaining) }}
                    @else
                        ∞
                    @endif
                </div>
            </div>
        </div>

        @if($itemsLimit !== null && $itemsLimit > 0)
        <div class="progress-bar" style="height: 12px; margin-bottom: var(--space-2);">
            <div class="progress-fill" style="width: {{ min(100, ($itemsUsed / $itemsLimit) * 100) }}%; background: {{ $itemsUsed > $itemsLimit ? 'var(--danger-600)' : 'linear-gradient(90deg, var(--primary-600), var(--accent-600))' }};"></div>
        </div>
        <div style="font-size: var(--text-sm); color: var(--neutral-600);">
            Использовано {{ number_format(min(100, ($itemsUsed / $itemsLimit) * 100), 1) }}% от лимита
        </div>

        @if($itemsUsed > $itemsLimit && $tariff)
            <div class="alert alert-warning" style="margin-top: var(--space-4);">
                <i data-lucide="alert-triangle" class="alert-icon"></i>
                <div class="alert-content">
                    <strong>Превышение лимита!</strong> Сверх лимита: {{ $itemsUsed - $itemsLimit }} позиций.<br>
                    Стоимость: {{ number_format($tariff->tariffPlan->price_per_item_over_limit, 2) }} ₽ за позицию
                </div>
            </div>
        @endif
        @endif
    </div>
</div>

<!-- Лимиты на отчеты -->
<div class="card">
    <div class="card-header">
        <i data-lucide="bar-chart-3" style="width: 1.25rem; height: 1.25rem;"></i>
        Лимит на открытые отчеты
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Использовано
                </div>
                <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--primary-600);">
                    {{ $reportsUsed }}
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Лимит
                </div>
                <div style="font-size: var(--text-2xl); font-weight: 600;">
                    @if($reportsLimit !== null)
                        {{ $reportsLimit }}
                    @else
                        ∞
                    @endif
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Осталось
                </div>
                <div style="font-size: var(--text-2xl); font-weight: 600; color: {{ $reportsRemaining > 0 ? 'var(--success-600)' : 'var(--danger-600)' }};">
                    @if($reportsLimit !== null)
                        {{ max(0, $reportsRemaining) }}
                    @else
                        ∞
                    @endif
                </div>
            </div>
        </div>

        @if($reportsLimit !== null && $reportsLimit > 0)
        <div class="progress-bar" style="height: 12px; margin-bottom: var(--space-2);">
            <div class="progress-fill" style="width: {{ min(100, ($reportsUsed / $reportsLimit) * 100) }}%; background: {{ $reportsUsed > $reportsLimit ? 'var(--danger-600)' : 'linear-gradient(90deg, var(--primary-600), var(--accent-600))' }};"></div>
        </div>
        <div style="font-size: var(--text-sm); color: var(--neutral-600);">
            Использовано {{ number_format(min(100, ($reportsUsed / $reportsLimit) * 100), 1) }}% от лимита
        </div>

        @if($reportsUsed > $reportsLimit && $tariff)
            <div class="alert alert-warning" style="margin-top: var(--space-4);">
                <i data-lucide="alert-triangle" class="alert-icon"></i>
                <div class="alert-content">
                    <strong>Превышение лимита!</strong> Сверх лимита: {{ $reportsUsed - $reportsLimit }} отчетов.<br>
                    Стоимость: {{ number_format($tariff->tariffPlan->price_per_report_over_limit, 2) }} ₽ за отчет
                </div>
            </div>
        @endif
        @endif
    </div>
</div>

<!-- История использования -->
<div class="card">
    <div class="card-header">
        <i data-lucide="activity" style="width: 1.25rem; height: 1.25rem;"></i>
        История использования лимитов
    </div>
    @if($limitUsage->count() > 0)
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Дата</th>
                        <th>Тип</th>
                        <th>Описание</th>
                        <th style="width: 120px; text-align: right;">Количество</th>
                        <th style="width: 120px; text-align: right;">Стоимость</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($limitUsage as $usage)
                    <tr>
                        <td data-label="Дата">{{ $usage['date']->format('d.m.Y H:i') }}</td>
                        <td data-label="Тип">
                            @if($usage['type'] === 'items')
                                <x-badge type="primary" size="sm">
                                    <i data-lucide="file-text" style="width: 0.875rem; height: 0.875rem;"></i>
                                    Позиции
                                </x-badge>
                            @else
                                <x-badge type="accent" size="sm">
                                    <i data-lucide="bar-chart-3" style="width: 0.875rem; height: 0.875rem;"></i>
                                    Отчет
                                </x-badge>
                            @endif
                        </td>
                        <td data-label="Описание">{{ $usage['description'] }}</td>
                        <td data-label="Количество" style="text-align: right; font-family: var(--font-mono);">
                            {{ $usage['quantity'] }}
                        </td>
                        <td data-label="Стоимость" style="text-align: right; font-family: var(--font-mono); font-weight: 600;">
                            @if($usage['cost'] > 0)
                                <span style="color: var(--danger-600);">{{ number_format($usage['cost'], 2) }} ₽</span>
                            @else
                                <span style="color: var(--neutral-600);">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if($limitUsage->hasPages())
    <div class="card-footer">
        {{ $limitUsage->links() }}
    </div>
    @endif
    @else
    <div class="card-body">
        <x-empty-state
            icon="inbox"
            title="Нет данных"
            description="История использования лимитов пока пуста"
        />
    </div>
    @endif
</div>

@push('styles')
<style>
.progress-bar {
    width: 100%;
    background: var(--neutral-200);
    border-radius: var(--radius-full);
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: var(--radius-full);
    transition: width 0.3s ease;
}
</style>
@endpush

@push('scripts')
<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush
@endsection
