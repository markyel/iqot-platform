@extends('layouts.cabinet')

@section('title', 'Детализация списаний: ' . $user->name)

@php
    $typeLabels = [
        'charge' => 'Списание за позицию',
        'report_access' => 'Доступ к отчёту',
        'item_purchase' => 'Покупка позиции',
        'subscription' => 'Абонплата',
        'hold' => 'Заморозка',
        'release' => 'Возврат',
        'top_up' => 'Пополнение',
        'promo_code' => 'Промокод',
    ];
    $typeIcons = [
        'top_up' => 'arrow-down-circle',
        'hold' => 'lock',
        'release' => 'unlock',
        'charge' => 'shopping-cart',
        'report_access' => 'file-text',
        'item_purchase' => 'package',
        'subscription' => 'zap',
        'promo_code' => 'ticket',
    ];
    $typeColors = [
        'top_up' => 'var(--success-600)',
        'hold' => 'var(--warning-600)',
        'release' => 'var(--info-600)',
        'promo_code' => 'var(--success-600)',
        'charge' => 'var(--danger-600)',
        'report_access' => 'var(--danger-600)',
        'item_purchase' => 'var(--danger-600)',
        'subscription' => 'var(--danger-600)',
    ];
    // ссылка на CSV с текущими параметрами периода/режима
    $exportParams = array_merge(request()->only(['from', 'to', 'all']), ['export' => 'csv']);
@endphp

@section('content')
<x-page-header
    :title="'Детализация списаний: ' . $user->name"
    :description="$user->email"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('admin.users.show', $user)" icon="arrow-left">
            К профилю пользователя
        </x-button>
        @if($transactions->count() > 0)
            <x-button variant="primary" :href="route('admin.users.transactions', $user) . '?' . http_build_query($exportParams)" icon="download">
                Экспорт CSV
            </x-button>
        @endif
    </x-slot>
</x-page-header>

<!-- Фильтр по периоду -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.users.transactions', $user) }}">
            <div style="display: grid; grid-template-columns: 1fr 1fr auto auto; gap: var(--space-4); align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Период с</label>
                    <input type="date" name="from" class="input" value="{{ $from->format('Y-m-d') }}">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">по</label>
                    <input type="date" name="to" class="input" value="{{ $to->format('Y-m-d') }}">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="display: flex; align-items: center; gap: var(--space-2); white-space: nowrap;">
                        <input type="checkbox" name="all" value="1" {{ $showAll ? 'checked' : '' }}>
                        Показать все операции
                    </label>
                    <span style="font-size: var(--text-xs); color: var(--neutral-500);">заморозки, возвраты, пополнения</span>
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <x-button type="submit" variant="primary" icon="search">Показать</x-button>
                </div>
            </div>
        </form>

        <!-- Быстрые периоды -->
        <div style="display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-4);">
            @php
                $now = \Illuminate\Support\Carbon::now();
                $quick = [
                    'Текущий месяц' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                    'Прошлый месяц' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
                    'Текущий год' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
                    'Последние 30 дней' => [$now->copy()->subDays(30), $now->copy()],
                ];
            @endphp
            @foreach($quick as $label => $range)
                <a href="{{ route('admin.users.transactions', $user) }}?from={{ $range[0]->format('Y-m-d') }}&to={{ $range[1]->format('Y-m-d') }}{{ $showAll ? '&all=1' : '' }}"
                   style="text-decoration: none; padding: var(--space-1) var(--space-3); font-size: var(--text-xs); color: var(--neutral-700); background: var(--neutral-100); border: 1px solid var(--neutral-200); border-radius: var(--radius-full);">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <div style="margin-top: var(--space-3); font-size: var(--text-xs); color: var(--neutral-500);">
            Даты — по времени сервера (UTC). Период: {{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }}.
        </div>
    </div>
</div>

<!-- Сводка за период -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card">
        <div class="card-body">
            <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-1);">Списано за период</div>
            <div style="font-size: var(--text-2xl); font-weight: 700; color: var(--danger-600); font-family: var(--font-mono);">
                {{ number_format($summary['charged_total'], 2, ',', ' ') }} ₽
            </div>
            <div style="font-size: var(--text-xs); color: var(--neutral-500); margin-top: var(--space-1);">{{ $summary['count_charges'] }} операц.</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-1);">Пополнено за период</div>
            <div style="font-size: var(--text-2xl); font-weight: 700; color: var(--success-600); font-family: var(--font-mono);">
                {{ number_format($summary['topped_up_total'], 2, ',', ' ') }} ₽
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-2);">Списания по типам</div>
            @if(count($summary['by_type']) > 0)
                @foreach($summary['by_type'] as $type => $sum)
                    <div style="display: flex; justify-content: space-between; font-size: var(--text-sm); margin-bottom: var(--space-1);">
                        <span style="color: var(--neutral-600);">{{ $typeLabels[$type] ?? $type }}</span>
                        <span style="font-family: var(--font-mono);">{{ number_format($sum, 2, ',', ' ') }} ₽</span>
                    </div>
                @endforeach
            @else
                <div style="font-size: var(--text-sm); color: var(--neutral-400);">—</div>
            @endif
        </div>
    </div>
</div>

<!-- Детальная таблица -->
<div class="card">
    <div class="card-header">
        <i data-lucide="list" style="width: 1.25rem; height: 1.25rem;"></i>
        {{ $showAll ? 'Все операции за период' : 'Списания за период' }}
        <span style="margin-left: auto; font-size: var(--text-sm); color: var(--neutral-500); font-weight: 400;">{{ $transactions->count() }} строк</span>
    </div>
    <div class="card-body" style="padding: 0;">
        @if($transactions->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th style="white-space: nowrap;">Дата</th>
                    <th>Тип</th>
                    <th>Описание</th>
                    <th style="text-align: right;">Сумма</th>
                    <th style="text-align: right;">Баланс после</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $transaction)
                <tr>
                    <td style="color: var(--neutral-600); font-size: var(--text-sm); white-space: nowrap;">
                        {{ $transaction['created_at']->format('d.m.Y H:i') }}
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: var(--space-2); white-space: nowrap;">
                            <i data-lucide="{{ $typeIcons[$transaction['type']] ?? 'circle' }}" style="width: 1rem; height: 1rem; color: {{ $typeColors[$transaction['type']] ?? 'var(--neutral-600)' }};"></i>
                            <span style="font-size: var(--text-sm);">{{ $typeLabels[$transaction['type']] ?? $transaction['type'] }}</span>
                        </div>
                    </td>
                    <td>
                        <span style="font-size: var(--text-sm);">{{ $transaction['description'] }}</span>
                    </td>
                    <td style="text-align: right; font-family: var(--font-mono); font-weight: 600; white-space: nowrap;">
                        @if($transaction['amount'] < 0)
                            <span style="color: var(--success-600);">+{{ number_format(abs($transaction['amount']), 2, ',', ' ') }} ₽</span>
                        @else
                            <span style="color: var(--danger-600);">-{{ number_format($transaction['amount'], 2, ',', ' ') }} ₽</span>
                        @endif
                    </td>
                    <td style="text-align: right; font-family: var(--font-mono); color: var(--neutral-600); white-space: nowrap;">
                        @if($transaction['balance_after'] !== null)
                            {{ number_format($transaction['balance_after'], 2, ',', ' ') }} ₽
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div style="padding: var(--space-8); text-align: center;">
            <i data-lucide="inbox" style="width: 3rem; height: 3rem; color: var(--neutral-300); margin: 0 auto var(--space-4);"></i>
            <div style="font-size: var(--text-base); color: var(--neutral-600);">За выбранный период операций нет</div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
lucide.createIcons();
</script>
@endpush
@endsection
