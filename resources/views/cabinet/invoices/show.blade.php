@extends('layouts.cabinet')

@section('title', 'Счет № ' . $invoice->number)

@section('content')
<x-page-header
    title="Счет № {{ $invoice->number }}"
    description="от {{ $invoice->invoice_date->format('d.m.Y') }}"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('cabinet.tariff.index')" icon="arrow-left">
            Назад к балансу
        </x-button>
        <x-button variant="primary" :href="route('cabinet.invoices.download', $invoice->id)" icon="download">
            Скачать PDF
        </x-button>
    </x-slot>
</x-page-header>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom: var(--space-6);">
    <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
    {{ session('success') }}
</div>
@endif

<!-- Статус счета -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-2);">
                    Статус счета
                </div>
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    @if($invoice->status === 'paid')
                        <span class="badge badge-success" style="font-size: var(--text-base); padding: var(--space-2) var(--space-4);">
                            <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                            Оплачен
                        </span>
                        <span style="color: var(--neutral-600); font-size: var(--text-sm);">
                            {{ $invoice->paid_at->format('d.m.Y в H:i') }}
                        </span>
                    @elseif($invoice->status === 'sent')
                        <span class="badge badge-warning" style="font-size: var(--text-base); padding: var(--space-2) var(--space-4);">
                            <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                            Ожидает оплаты
                        </span>
                    @elseif($invoice->status === 'cancelled')
                        <span class="badge badge-secondary" style="font-size: var(--text-base); padding: var(--space-2) var(--space-4);">
                            <i data-lucide="x-circle" style="width: 16px; height: 16px;"></i>
                            Отменен
                        </span>
                    @endif
                </div>
            </div>

            <div style="text-align: right;">
                <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-2);">
                    Сумма к оплате
                </div>
                <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--primary-600);">
                    {{ number_format($invoice->total, 2, ',', ' ') }} ₽
                </div>
            </div>
        </div>

        @if($invoice->status === 'sent')
        <div class="alert alert-info" style="margin-top: var(--space-6);">
            <i data-lucide="info" style="width: 16px; height: 16px;"></i>
            <div>
                <strong>Как оплатить:</strong>
                <ol style="margin: var(--space-2) 0 0 var(--space-5); padding: 0;">
                    <li>Скачайте счет в формате PDF</li>
                    <li>Оплатите его через ваш банк</li>
                    <li>После оплаты свяжитесь с нами для подтверждения платежа</li>
                    <li>Средства будут зачислены на ваш баланс</li>
                </ol>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Детали счета -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title">Детали счета</h2>
    </div>
    <div class="card-body">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--neutral-200);">
                    <th style="padding: var(--space-3); text-align: left; font-weight: 600;">№</th>
                    <th style="padding: var(--space-3); text-align: left; font-weight: 600;">Наименование</th>
                    <th style="padding: var(--space-3); text-align: center; font-weight: 600;">Ед.</th>
                    <th style="padding: var(--space-3); text-align: center; font-weight: 600;">Кол-во</th>
                    <th style="padding: var(--space-3); text-align: right; font-weight: 600;">Цена</th>
                    <th style="padding: var(--space-3); text-align: right; font-weight: 600;">Сумма</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $index => $item)
                <tr style="border-bottom: 1px solid var(--neutral-200);">
                    <td style="padding: var(--space-3);">{{ $index + 1 }}</td>
                    <td style="padding: var(--space-3);">{{ $item->name }}</td>
                    <td style="padding: var(--space-3); text-align: center;">{{ $item->unit }}</td>
                    <td style="padding: var(--space-3); text-align: center;">{{ $item->quantity }}</td>
                    <td style="padding: var(--space-3); text-align: right;">{{ number_format($item->price, 2, ',', ' ') }}</td>
                    <td style="padding: var(--space-3); text-align: right; font-weight: 600;">{{ number_format($item->sum, 2, ',', ' ') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="border-top: 2px solid var(--neutral-200);">
                    <td colspan="5" style="padding: var(--space-3); text-align: right; font-weight: 600;">Итого:</td>
                    <td style="padding: var(--space-3); text-align: right; font-weight: 600;">{{ number_format($invoice->subtotal, 2, ',', ' ') }}</td>
                </tr>
                <tr>
                    <td colspan="5" style="padding: var(--space-3); text-align: right;">В том числе НДС ({{ $invoice->vat_rate }}%):</td>
                    <td style="padding: var(--space-3); text-align: right;">{{ number_format($invoice->vat_amount, 2, ',', ' ') }}</td>
                </tr>
                <tr style="background: var(--neutral-50);">
                    <td colspan="5" style="padding: var(--space-3); text-align: right; font-weight: 700; font-size: var(--text-lg);">Всего к оплате:</td>
                    <td style="padding: var(--space-3); text-align: right; font-weight: 700; font-size: var(--text-lg); color: var(--primary-600);">
                        {{ number_format($invoice->total, 2, ',', ' ') }} ₽
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Реквизиты -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6);">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Поставщик</h3>
        </div>
        <div class="card-body">
            <div style="line-height: 1.8;">
                <strong>{{ $seller->name }}</strong><br>
                ИНН: {{ $seller->inn }}<br>
                @if($seller->ogrnip)
                    ОГРНИП: {{ $seller->ogrnip }}<br>
                @endif
                {{ $seller->address }}<br>
                @if($seller->email)
                    Email: {{ $seller->email }}<br>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Покупатель</h3>
        </div>
        <div class="card-body">
            <div style="line-height: 1.8;">
                <strong>{{ $buyer->company_name ?? $buyer->name }}</strong><br>
                @if($buyer->inn)
                    ИНН: {{ $buyer->inn }}<br>
                @endif
                @if($buyer->kpp)
                    КПП: {{ $buyer->kpp }}<br>
                @endif
                {{ $buyer->address ?? 'Не указан' }}<br>
                Email: {{ $buyer->email }}<br>
            </div>
        </div>
    </div>
</div>
@endsection
