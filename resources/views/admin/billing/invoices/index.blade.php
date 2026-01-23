@extends('layouts.cabinet')

@section('title', 'Счета')

@section('content')
<x-page-header
    title="Счета"
    description="Управление выставленными счетами"
/>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom: var(--space-6);">
        {{ session('success') }}
    </div>
@endif

@if(session('warning'))
    <div class="alert alert-warning" style="margin-bottom: var(--space-6);">
        {{ session('warning') }}
    </div>
@endif

{{-- Фильтры --}}
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.billing.invoices.index') }}">
            <div style="display: grid; grid-template-columns: 1fr 1fr 100px; gap: var(--space-4); align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" for="search">Поиск</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="input"
                        placeholder="Номер счета или email пользователя"
                    >
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" for="status">Статус</label>
                    <select id="status" name="status" class="input">
                        <option value="">Все статусы</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Черновик</option>
                        <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Выставлен</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Оплачен</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Отменён</option>
                    </select>
                </div>
                <x-button type="submit" variant="primary" icon="search">
                    Найти
                </x-button>
            </div>
        </form>
    </div>
</div>

{{-- Таблица счетов --}}
@if($invoices->isEmpty())
    <div class="card">
        <div class="card-body" style="text-align: center; padding: var(--space-12) var(--space-6);">
            <i data-lucide="file-text" style="width: 48px; height: 48px; color: var(--neutral-400); margin: 0 auto var(--space-4);"></i>
            <h3 style="margin: 0 0 var(--space-2); font-size: var(--text-lg); color: var(--neutral-700);">
                Счета не найдены
            </h3>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Номер</th>
                        <th>Дата</th>
                        <th>Пользователь</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th style="text-align: right;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                    <tr>
                        <td>
                            <strong>{{ $invoice->number }}</strong>
                        </td>
                        <td>
                            {{ $invoice->invoice_date->format('d.m.Y') }}
                        </td>
                        <td>
                            <div>
                                <div>
                                    <a href="{{ route('admin.users.show', $invoice->user->id) }}" style="color: var(--primary-600); text-decoration: none; font-weight: 600;">
                                        {{ $invoice->user->name }}
                                    </a>
                                </div>
                                <div style="font-size: var(--text-sm); color: var(--neutral-600);">
                                    {{ $invoice->user->email }}
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong>{{ number_format($invoice->total, 2, ',', ' ') }} ₽</strong>
                        </td>
                        <td>
                            @if($invoice->status === 'paid')
                                <span class="badge badge-success">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                    Оплачен
                                </span>
                                @if($invoice->paid_at)
                                    <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 2px;">
                                        {{ $invoice->paid_at->format('d.m.Y H:i') }}
                                    </div>
                                @endif
                            @elseif($invoice->status === 'sent')
                                <span class="badge badge-warning">
                                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                    Выставлен
                                </span>
                            @elseif($invoice->status === 'draft')
                                <span class="badge badge-secondary">
                                    <i data-lucide="file" style="width: 14px; height: 14px;"></i>
                                    Черновик
                                </span>
                            @else
                                <span class="badge badge-secondary">
                                    {{ $invoice->status_name }}
                                </span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: var(--space-2); justify-content: flex-end;">
                                <x-button
                                    variant="secondary"
                                    size="sm"
                                    :href="route('admin.billing.invoices.show', $invoice->id)"
                                    icon="eye"
                                >
                                    Просмотр
                                </x-button>
                                @if($invoice->status === 'paid')
                                    <form method="POST" action="{{ route('admin.billing.invoices.mark-as-unpaid', $invoice->id) }}" style="display: inline;">
                                        @csrf
                                        <x-button
                                            type="submit"
                                            variant="warning"
                                            size="sm"
                                            icon="x"
                                            onclick="return confirm('Снять отметку об оплате? Сумма {{ number_format($invoice->subtotal, 2, ',', ' ') }} ₽ будет списана с баланса пользователя.')"
                                        >
                                            Снять оплату
                                        </x-button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.billing.invoices.mark-as-paid', $invoice->id) }}" style="display: inline;">
                                        @csrf
                                        <x-button
                                            type="submit"
                                            variant="success"
                                            size="sm"
                                            icon="check"
                                            onclick="return confirm('Отметить счет как оплаченный? Сумма {{ number_format($invoice->subtotal, 2, ',', ' ') }} ₽ будет зачислена на баланс пользователя.')"
                                        >
                                            Оплачен
                                        </x-button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($invoices->hasPages())
        <div style="margin-top: var(--space-6);">
            {{ $invoices->links() }}
        </div>
    @endif
@endif

@endsection
