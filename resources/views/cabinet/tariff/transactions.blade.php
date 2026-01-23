@extends('layouts.cabinet')

@section('title', 'Детализация расходов')

@section('content')
<x-page-header
    title="Детализация расходов"
    description="История пополнений и списаний средств"
    :breadcrumbs="[
        ['label' => 'Мой тариф', 'url' => route('cabinet.tariff.index')],
        ['label' => 'Детализация расходов']
    ]"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('cabinet.tariff.index')" icon="arrow-left">
            Назад к тарифу
        </x-button>
    </x-slot>
</x-page-header>

<!-- Сводка по балансу -->
<div class="card">
    <div class="card-header">
        <i data-lucide="wallet" style="width: 1.25rem; height: 1.25rem;"></i>
        Текущий баланс
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-6);">
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Доступно
                </div>
                <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--primary-600);">
                    {{ number_format($user->available_balance, 2) }} ₽
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Заморожено
                </div>
                <div style="font-size: var(--text-2xl); font-weight: 600; color: var(--warning-600);">
                    {{ number_format($user->held_balance, 2) }} ₽
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Всего
                </div>
                <div style="font-size: var(--text-2xl); font-weight: 600;">
                    {{ number_format($user->balance, 2) }} ₽
                </div>
            </div>
        </div>
    </div>
</div>

<!-- История операций -->
<div class="card">
    <div class="card-header">
        <i data-lucide="list" style="width: 1.25rem; height: 1.25rem;"></i>
        История операций
    </div>
    @if($transactions->count() > 0)
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Дата</th>
                        <th>Операция</th>
                        <th>Описание</th>
                        <th style="width: 150px; text-align: right;">Сумма</th>
                        <th style="width: 150px; text-align: right;">Баланс после</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $transaction)
                    <tr>
                        <td data-label="Дата">
                            {{ $transaction['created_at']->format('d.m.Y H:i') }}
                        </td>
                        <td data-label="Операция">
                            @if($transaction['type'] === 'top_up')
                                <x-badge type="completed" size="sm">
                                    <i data-lucide="plus-circle" style="width: 0.875rem; height: 0.875rem;"></i>
                                    Пополнение
                                </x-badge>
                            @elseif($transaction['type'] === 'deposit')
                                <x-badge type="completed" size="sm">
                                    <i data-lucide="plus-circle" style="width: 0.875rem; height: 0.875rem;"></i>
                                    Пополнение
                                </x-badge>
                            @elseif($transaction['type'] === 'hold')
                                <x-badge type="pending" size="sm">
                                    <i data-lucide="lock" style="width: 0.875rem; height: 0.875rem;"></i>
                                    Заморозка
                                </x-badge>
                            @elseif($transaction['type'] === 'charge')
                                <x-badge type="in-progress" size="sm">
                                    <i data-lucide="minus-circle" style="width: 0.875rem; height: 0.875rem;"></i>
                                    Списание
                                </x-badge>
                            @elseif($transaction['type'] === 'release')
                                <x-badge type="draft" size="sm">
                                    <i data-lucide="unlock" style="width: 0.875rem; height: 0.875rem;"></i>
                                    Разморозка
                                </x-badge>
                            @elseif($transaction['type'] === 'report_access')
                                <x-badge type="cancelled" size="sm">
                                    <i data-lucide="file-text" style="width: 0.875rem; height: 0.875rem;"></i>
                                    Открытие отчета
                                </x-badge>
                            @elseif($transaction['type'] === 'item_purchase')
                                <x-badge type="cancelled" size="sm">
                                    <i data-lucide="shopping-cart" style="width: 0.875rem; height: 0.875rem;"></i>
                                    Покупка позиции
                                </x-badge>
                            @elseif($transaction['type'] === 'subscription')
                                <x-badge type="cancelled" size="sm">
                                    <i data-lucide="calendar" style="width: 0.875rem; height: 0.875rem;"></i>
                                    Абонентская плата
                                </x-badge>
                            @endif
                        </td>
                        <td data-label="Описание">
                            {{ $transaction['description'] }}
                        </td>
                        <td data-label="Сумма" style="text-align: right; font-weight: 600; font-family: var(--font-mono);">
                            @if($transaction['type'] === 'top_up' || $transaction['type'] === 'deposit' || $transaction['type'] === 'release')
                                <span style="color: var(--success-600);">+{{ number_format(abs($transaction['amount']), 2) }} ₽</span>
                            @else
                                <span style="color: var(--danger-600);">-{{ number_format($transaction['amount'], 2) }} ₽</span>
                            @endif
                        </td>
                        <td data-label="Баланс после" style="text-align: right; font-family: var(--font-mono);">
                            {{ number_format($transaction['balance_after'] ?? 0, 2) }} ₽
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">
        {{ $transactions->links() }}
    </div>
    @endif
    @else
    <div class="card-body">
        <x-empty-state
            icon="inbox"
            title="Нет операций"
            description="История операций с балансом пока пуста"
        />
    </div>
    @endif
</div>

@push('scripts')
<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush
@endsection
