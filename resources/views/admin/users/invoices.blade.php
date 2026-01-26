@extends('layouts.cabinet')

@section('title', 'Счета пользователя: ' . $user->name)

@section('content')
<x-page-header 
    :title="'Счета пользователя: ' . $user->name" 
    :description="$user->email"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('admin.users.show', $user)" icon="arrow-left">
            К профилю пользователя
        </x-button>
    </x-slot>
</x-page-header>

<!-- Список счетов -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        @if($invoices->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>Номер</th>
                    <th>Дата</th>
                    <th>Сумма</th>
                    <th>Потрачено</th>
                    <th>Остаток</th>
                    <th>Статус</th>
                    <th style="text-align: right;">Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $invoice)
                <tr>
                    <td style="font-family: var(--font-mono); font-weight: 600;">
                        <a href="{{ route('admin.billing.invoices.show', $invoice->id) }}" style="color: var(--primary-600); text-decoration: none;">
                            {{ $invoice->number }}
                        </a>
                    </td>
                    <td style="color: var(--neutral-600); font-size: var(--text-sm); white-space: nowrap;">
                        {{ $invoice->invoice_date->format('d.m.Y') }}
                    </td>
                    <td style="font-family: var(--font-mono); font-weight: 600; white-space: nowrap;">
                        {{ number_format($invoice->subtotal, 2, ',', ' ') }} ₽
                    </td>
                    <td style="font-family: var(--font-mono); color: var(--danger-600); white-space: nowrap;">
                        {{ number_format($invoice->spent_amount ?? 0, 2, ',', ' ') }} ₽
                    </td>
                    <td style="font-family: var(--font-mono); color: var(--success-600); white-space: nowrap;">
                        {{ number_format($invoice->remaining_amount ?? $invoice->subtotal, 2, ',', ' ') }} ₽
                        @if(($invoice->spent_amount ?? 0) > 0)
                            <div style="margin-top: var(--space-1); height: 4px; background: var(--neutral-200); border-radius: var(--radius-full); overflow: hidden;">
                                <div style="height: 100%; background: var(--danger-500); width: {{ min(100, $invoice->usage_percent ?? 0) }}%;"></div>
                            </div>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusVariant = match($invoice->status) {
                                'draft' => 'secondary',
                                'sent' => 'warning',
                                'paid' => 'success',
                                'closed' => 'info',
                                'cancelled' => 'danger',
                                default => 'secondary'
                            };
                        @endphp
                        <x-badge :variant="$statusVariant" size="sm">{{ $invoice->status_name }}</x-badge>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: var(--space-2); justify-content: flex-end;">
                            <x-button variant="secondary" size="sm" :href="route('admin.billing.invoices.show', $invoice->id)" icon="eye">
                                Открыть
                            </x-button>
                            @if(in_array($invoice->status, ['paid', 'closed']))
                                <x-button variant="primary" size="sm" :href="route('admin.billing.invoices.download-act', $invoice->id)" icon="file-text" target="_blank">
                                    Акт
                                </x-button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if($invoices->hasPages())
        <div style="padding: var(--space-4); border-top: 1px solid var(--neutral-200);">
            {{ $invoices->links() }}
        </div>
        @endif
        @else
        <div style="padding: var(--space-8); text-align: center;">
            <i data-lucide="inbox" style="width: 3rem; height: 3rem; color: var(--neutral-300); margin: 0 auto var(--space-4);"></i>
            <div style="font-size: var(--text-base); color: var(--neutral-600);">У пользователя нет счетов</div>
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
