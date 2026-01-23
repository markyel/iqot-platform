@extends('layouts.cabinet')

@section('title', 'Просмотр счета')

@section('content')
<x-page-header
    title="Счет № {{ $invoice->number }}"
    description="Детальная информация о счете"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('admin.billing.invoices.index')" icon="arrow-left">
            К списку счетов
        </x-button>
        <x-button
            variant="primary"
            :href="route('cabinet.invoices.download', $invoice->id)"
            icon="download"
            target="_blank"
        >
            Скачать PDF
        </x-button>
    </x-slot>
</x-page-header>

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

{{-- Основная информация --}}
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h3 class="card-title">Информация о счете</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6);">
            <div>
                <div style="margin-bottom: var(--space-4);">
                    <label class="form-label">Номер счета</label>
                    <div><strong style="font-size: var(--text-lg);">{{ $invoice->number }}</strong></div>
                </div>
                <div style="margin-bottom: var(--space-4);">
                    <label class="form-label">Дата выставления</label>
                    <div>{{ $invoice->invoice_date->format('d.m.Y') }}</div>
                </div>
                <div style="margin-bottom: var(--space-4);">
                    <label class="form-label">Статус</label>
                    <div>
                        @if($invoice->status === 'paid')
                            <span class="badge badge-success">
                                <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                Оплачен
                            </span>
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
                    </div>
                </div>
                @if($invoice->paid_at)
                    <div style="margin-bottom: var(--space-4);">
                        <label class="form-label">Дата оплаты</label>
                        <div>{{ $invoice->paid_at->format('d.m.Y H:i') }}</div>
                    </div>
                @endif
            </div>
            <div>
                <div style="margin-bottom: var(--space-4);">
                    <label class="form-label">Плательщик</label>
                    <div>
                        <strong>{{ $invoice->user->name }}</strong>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600);">
                            {{ $invoice->user->email }}
                        </div>
                        @if($invoice->user->company)
                            <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-top: var(--space-1);">
                                {{ $invoice->user->company }}
                            </div>
                        @endif
                    </div>
                </div>
                <div style="margin-bottom: var(--space-4);">
                    <label class="form-label">Текущий баланс пользователя</label>
                    <div>
                        <strong style="font-size: var(--text-lg); color: {{ $invoice->user->balance >= 0 ? 'var(--success-600)' : 'var(--danger-600)' }}">
                            {{ number_format($invoice->user->balance, 2, ',', ' ') }} ₽
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Суммы --}}
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h3 class="card-title">Финансовая информация</h3>
    </div>
    <div class="card-body">
        <table class="table" style="max-width: 600px;">
            <tbody>
                <tr>
                    <td style="width: 70%;"><strong>Сумма без НДС</strong></td>
                    <td style="text-align: right;"><strong>{{ number_format($invoice->subtotal, 2, ',', ' ') }} ₽</strong></td>
                </tr>
                @if($invoice->vat_rate > 0)
                    <tr>
                        <td>НДС ({{ number_format($invoice->vat_rate, 0) }}%)</td>
                        <td style="text-align: right;">{{ number_format($invoice->vat_amount, 2, ',', ' ') }} ₽</td>
                    </tr>
                @else
                    <tr>
                        <td>Без НДС</td>
                        <td style="text-align: right;">—</td>
                    </tr>
                @endif
                <tr style="border-top: 2px solid var(--neutral-200);">
                    <td><strong style="font-size: var(--text-lg);">Итого к оплате</strong></td>
                    <td style="text-align: right;">
                        <strong style="font-size: var(--text-lg); color: var(--primary-600);">
                            {{ number_format($invoice->total, 2, ',', ' ') }} ₽
                        </strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- Действия --}}
@if($invoice->status !== 'cancelled')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Действия</h3>
    </div>
    <div class="card-body">
        <div style="display: flex; gap: var(--space-4); flex-wrap: wrap;">
            @if($invoice->status !== 'paid')
                <div style="flex: 1; min-width: 300px;">
                    <form method="POST" action="{{ route('admin.billing.invoices.mark-as-paid', $invoice->id) }}"
                          onsubmit="return confirm('Отметить счет как оплаченный?\n\nСумма {{ number_format($invoice->subtotal, 2, ',', ' ') }} ₽ будет зачислена на баланс пользователя {{ $invoice->user->name }} ({{ $invoice->user->email }}).')">
                        @csrf
                        <p style="margin-bottom: var(--space-4); color: var(--neutral-700);">
                            <i data-lucide="alert-circle" style="width: 16px; height: 16px; color: var(--warning-600); vertical-align: text-bottom;"></i>
                            При отметке счета как оплаченного сумма <strong>{{ number_format($invoice->subtotal, 2, ',', ' ') }} ₽</strong>
                            будет автоматически зачислена на баланс пользователя.
                        </p>
                        <x-button type="submit" variant="success" icon="check">
                            Отметить как оплаченный
                        </x-button>
                    </form>
                </div>
            @else
                <div style="flex: 1; min-width: 300px;">
                    <form method="POST" action="{{ route('admin.billing.invoices.mark-as-unpaid', $invoice->id) }}"
                          onsubmit="return confirm('Снять отметку об оплате?\n\nСумма {{ number_format($invoice->subtotal, 2, ',', ' ') }} ₽ будет списана с баланса пользователя {{ $invoice->user->name }} ({{ $invoice->user->email }}).')">
                        @csrf
                        <p style="margin-bottom: var(--space-4); color: var(--neutral-700);">
                            <i data-lucide="alert-circle" style="width: 16px; height: 16px; color: var(--warning-600); vertical-align: text-bottom;"></i>
                            При снятии отметки об оплате сумма <strong>{{ number_format($invoice->subtotal, 2, ',', ' ') }} ₽</strong>
                            будет списана с баланса пользователя.
                        </p>
                        <x-button type="submit" variant="warning" icon="x">
                            Снять отметку об оплате
                        </x-button>
                    </form>
                </div>
            @endif

            <div style="flex: 1; min-width: 300px;">
                <form method="POST" action="{{ route('admin.billing.invoices.cancel', $invoice->id) }}"
                      onsubmit="return confirm('Отменить счет?\n\n@if($invoice->status === 'paid')Счет был оплачен. Сумма {{ number_format($invoice->subtotal, 2, ',', ' ') }} ₽ будет списана с баланса пользователя.\n\n@endifЭто действие нельзя отменить.')">
                    @csrf
                    <p style="margin-bottom: var(--space-4); color: var(--neutral-700);">
                        <i data-lucide="alert-triangle" style="width: 16px; height: 16px; color: var(--error-600); vertical-align: text-bottom;"></i>
                        @if($invoice->status === 'paid')
                            Счет был оплачен. При отмене сумма <strong>{{ number_format($invoice->subtotal, 2, ',', ' ') }} ₽</strong>
                            будет списана с баланса пользователя.
                        @else
                            Отменить счет. Это действие нельзя отменить.
                        @endif
                    </p>
                    <x-button type="submit" variant="danger" icon="x-circle">
                        Отменить счет
                    </x-button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@endsection
