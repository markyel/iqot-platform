@extends('layouts.cabinet')

@section('title', 'Акт №' . $act->number)

@section('content')
<x-page-header
    title="Акт №{{ $act->number }}"
    description="Детальная информация об акте за {{ $act->period_name }}"
>
    <x-slot:actions>
        <x-button
            variant="secondary"
            icon="arrow-left"
            :href="route('admin.billing.acts.index')"
        >
            Назад к списку
        </x-button>
        <x-button
            variant="primary"
            icon="download"
            :href="route('admin.billing.acts.download', $act->id)"
        >
            Скачать PDF
        </x-button>
    </x-slot:actions>
</x-page-header>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom: var(--space-6);">
        {{ session('success') }}
    </div>
@endif

<!-- Основная информация -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title">Информация об акте</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-6);">
            <div>
                <div style="display: grid; gap: var(--space-4);">
                    <div>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-1);">Номер акта</div>
                        <div style="font-weight: 600; font-size: var(--text-lg);">{{ $act->number }}</div>
                    </div>
                    <div>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-1);">Дата акта</div>
                        <div style="font-weight: 600;">{{ $act->act_date->format('d.m.Y') }}</div>
                    </div>
                    <div>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-1);">Период</div>
                        <div style="font-weight: 600;">{{ $act->period_name }}</div>
                    </div>
                    <div>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-1);">Пользователь</div>
                        <div>
                            <a href="{{ route('admin.users.show', $act->user->id) }}" style="color: var(--primary-600); text-decoration: none; font-weight: 600;">
                                {{ $act->user->name }}
                            </a>
                            <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-top: var(--space-1);">
                                {{ $act->user->email }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div style="display: grid; gap: var(--space-4);">
                    <div>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-1);">Статус</div>
                        <div>
                            @if($act->status === 'generated')
                                <span class="badge badge-success">
                                    <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                                    Сформирован
                                </span>
                            @elseif($act->status === 'sent')
                                <span class="badge badge-info">
                                    <i data-lucide="send" style="width: 14px; height: 14px;"></i>
                                    Отправлен
                                </span>
                            @elseif($act->status === 'signed')
                                <span class="badge badge-success">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                    Подписан
                                </span>
                            @else
                                <span class="badge badge-secondary">{{ $act->status }}</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-1);">Дата формирования</div>
                        <div style="font-weight: 600;">
                            @if($act->generated_at)
                                {{ $act->generated_at->format('d.m.Y H:i') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-1);">Дата отправки</div>
                        <div style="font-weight: 600;">
                            @if($act->sent_at)
                                {{ $act->sent_at->format('d.m.Y H:i') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-1);">Дата подписания</div>
                        <div style="font-weight: 600;">
                            @if($act->signed_at)
                                {{ $act->signed_at->format('d.m.Y H:i') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Позиции акта -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title">Оказанные услуги</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">№</th>
                    <th>Наименование</th>
                    <th style="width: 100px;">Ед. изм.</th>
                    <th style="width: 100px; text-align: right;">Кол-во</th>
                    <th style="width: 150px; text-align: right;">Цена, ₽</th>
                    <th style="width: 150px; text-align: right;">Сумма, ₽</th>
                </tr>
            </thead>
            <tbody>
                @foreach($act->items as $item)
                <tr>
                    <td>{{ $item->sort_order }}</td>
                    <td>
                        <div>{{ $item->name }}</div>
                        @if($item->type)
                            <div style="margin-top: var(--space-1);">
                                @if($item->type === 'subscription')
                                    <span class="badge badge-primary" style="font-size: var(--text-xs);">Абонентская плата</span>
                                @elseif($item->type === 'price_monitoring')
                                    <span class="badge badge-info" style="font-size: var(--text-xs);">Ценовой мониторинг</span>
                                @elseif($item->type === 'catalog_access')
                                    <span class="badge badge-success" style="font-size: var(--text-xs);">Доступ к отчётам</span>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>{{ $item->unit }}</td>
                    <td style="text-align: right; font-family: var(--font-mono);">{{ number_format($item->quantity, 0, ',', ' ') }}</td>
                    <td style="text-align: right; font-family: var(--font-mono);">{{ number_format($item->price, 2, ',', ' ') }}</td>
                    <td style="text-align: right; font-family: var(--font-mono); font-weight: 600;">{{ number_format($item->amount, 2, ',', ' ') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align: right; font-weight: 600; padding-top: var(--space-4);">Итого без НДС:</td>
                    <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; padding-top: var(--space-4);">{{ number_format($act->subtotal, 2, ',', ' ') }} ₽</td>
                </tr>
                @if($act->vat_amount > 0)
                <tr>
                    <td colspan="5" style="text-align: right; font-weight: 600;">НДС ({{ $act->vat_rate }}%):</td>
                    <td style="text-align: right; font-family: var(--font-mono); font-weight: 700;">{{ number_format($act->vat_amount, 2, ',', ' ') }} ₽</td>
                </tr>
                @endif
                <tr>
                    <td colspan="5" style="text-align: right; font-weight: 700; font-size: var(--text-lg); padding-top: var(--space-2);">Всего:</td>
                    <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; font-size: var(--text-lg); padding-top: var(--space-2); color: var(--primary-600);">{{ number_format($act->total, 2, ',', ' ') }} ₽</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Примечания (если есть) -->
@if($act->notes)
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Примечания</h2>
    </div>
    <div class="card-body">
        <p style="margin: 0; white-space: pre-wrap;">{{ $act->notes }}</p>
    </div>
</div>
@endif

@endsection
