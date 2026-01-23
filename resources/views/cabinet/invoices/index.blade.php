@extends('layouts.cabinet')

@section('title', 'Мои документы')

@section('content')
<x-page-header
    title="Мои документы"
    description="Список выставленных счетов и их статус"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('cabinet.tariff.index')" icon="arrow-left">
            Назад к тарифу
        </x-button>
    </x-slot>
</x-page-header>

@if($invoices->isEmpty())
    <div class="card">
        <div class="card-body" style="text-align: center; padding: var(--space-12) var(--space-6);">
            <i data-lucide="file-text" style="width: 48px; height: 48px; color: var(--neutral-400); margin: 0 auto var(--space-4);"></i>
            <h3 style="margin: 0 0 var(--space-2); font-size: var(--text-lg); color: var(--neutral-700);">
                Нет выставленных счетов
            </h3>
            <p style="margin: 0; color: var(--neutral-600);">
                Вы можете запросить счет на пополнение баланса из раздела "Мой тариф"
            </p>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Номер счёта</th>
                        <th>Дата</th>
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
                            <strong>{{ number_format($invoice->total, 2, ',', ' ') }} ₽</strong>
                        </td>
                        <td>
                            @if($invoice->status === 'paid')
                                <span class="badge badge-completed">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                    Оплачен
                                </span>
                            @elseif($invoice->status === 'sent')
                                <span class="badge badge-pending">
                                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                    Выставлен
                                </span>
                            @elseif($invoice->status === 'draft')
                                <span class="badge badge-draft">
                                    <i data-lucide="file" style="width: 14px; height: 14px;"></i>
                                    Черновик
                                </span>
                            @elseif($invoice->status === 'cancelled')
                                <span class="badge badge-cancelled">
                                    <i data-lucide="x-circle" style="width: 14px; height: 14px;"></i>
                                    Отменён
                                </span>
                            @else
                                <span class="badge badge-draft">
                                    {{ $invoice->status_name }}
                                </span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: var(--space-2); justify-content: flex-end;">
                                <x-button
                                    variant="secondary"
                                    size="sm"
                                    :href="route('cabinet.invoices.show', $invoice->id)"
                                    icon="eye"
                                >
                                    Просмотр
                                </x-button>
                                <x-button
                                    variant="primary"
                                    size="sm"
                                    :href="route('cabinet.invoices.download', $invoice->id)"
                                    icon="download"
                                >
                                    Скачать PDF
                                </x-button>
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
