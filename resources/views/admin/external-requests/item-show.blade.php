@extends('layouts.cabinet')

@section('title', 'Позиция #' . $item->position_number)

@push('styles')
<style>
    /* Light theme for admin */
    .admin-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .back-link {
        color: #10b981;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 1rem;
    }

    .back-link:hover {
        color: #059669;
    }

    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .status-pending { background: #f3f4f6; color: #6b7280; }
    .status-has-offers { background: #d1fae5; color: #065f46; }
    .status-partial-offers { background: #fef3c7; color: #92400e; }
    .status-no-offers { background: #fee2e2; color: #991b1b; }
    .status-clarification-needed { background: #dbeafe; color: #1e40af; }

    .info-label {
        color: #6b7280;
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }

    .info-value {
        color: #111827;
        font-weight: 600;
    }

    .offers-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        background: #ffffff;
        border-radius: 8px;
        overflow: hidden;
    }

    .offers-table th {
        text-align: left;
        padding: 0.75rem;
        background: #f9fafb;
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 600;
        border-bottom: 2px solid #e5e7eb;
    }

    .offers-table td {
        padding: 0.75rem;
        border-top: 1px solid #f3f4f6;
        color: #374151;
        font-size: 0.875rem;
    }

    .offers-table tbody tr:hover {
        background: #f9fafb;
    }

    .price-highlight {
        color: #059669;
        font-weight: 700;
        font-size: 1rem;
    }

    .price-best {
        background: #d1fae5;
        border: 1px solid #a7f3d0;
        border-radius: 4px;
        padding: 0.5rem 0.75rem;
    }

    .supplier-name {
        color: #111827;
        font-weight: 600;
    }

    .stat-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1rem;
    }

    .stat-label {
        color: #6b7280;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        color: #111827;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .stat-value-accent {
        color: #059669;
    }

    .no-offers {
        text-align: center;
        padding: 3rem;
        color: #9ca3af;
        font-style: italic;
    }
</style>
@endpush

@section('content')
<div style="max-width: 1400px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.items.index') }}" class="back-link">
            ← Назад к списку позиций
        </a>
        <div style="display: flex; justify-content: space-between; align-items: start; margin-top: 1rem;">
            <div>
                <h1 style="font-size: 2rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;">
                    Позиция #{{ $item->position_number }}
                </h1>
                <p style="color: #6b7280;">
                    Из заявки
                    @if($item->request)
                        <span style="color: #10b981; font-weight: 600;">
                            {{ $item->request->request_number }}
                        </span>
                    @endif
                </p>
            </div>
            <div>
                @php
                    $statusClass = 'status-' . str_replace('_', '-', $item->status);
                    $statusLabel = \App\Models\ExternalRequestItem::getStatusLabels()[$item->status] ?? $item->status;
                @endphp
                <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
        </div>
    </div>

    <!-- Информация о позиции -->
    <div class="admin-card">
        <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">Информация о позиции</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
            <div>
                <div class="info-label">Название</div>
                <div class="info-value">{{ $item->name }}</div>
            </div>

            @if($item->brand)
            <div>
                <div class="info-label">Бренд</div>
                <div class="info-value">{{ $item->brand }}</div>
            </div>
            @endif

            @if($item->article)
            <div>
                <div class="info-label">Артикул</div>
                <div class="info-value">{{ $item->article }}</div>
            </div>
            @endif

            <div>
                <div class="info-label">Количество</div>
                <div class="info-value">{{ $item->quantity }} {{ $item->unit }}</div>
            </div>

            @if($item->category)
            <div>
                <div class="info-label">Категория</div>
                <div class="info-value">{{ $item->category }}</div>
            </div>
            @endif
        </div>

        @if($item->description)
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
            <div class="info-label">Описание</div>
            <div style="color: #374151; margin-top: 0.5rem;">{{ $item->description }}</div>
        </div>
        @endif
    </div>

    <!-- Статистика -->
    @if($item->offers->count() > 0)
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="stat-card">
            <div class="stat-label">Всего предложений</div>
            <div class="stat-value stat-value-accent">{{ $item->offers->count() }}</div>
        </div>
        @if($item->min_price)
        <div class="stat-card">
            <div class="stat-label">Минимальная цена</div>
            <div class="stat-value">{{ number_format($item->min_price, 2) }} ₽</div>
        </div>
        @endif
        @if($item->max_price)
        <div class="stat-card">
            <div class="stat-label">Максимальная цена</div>
            <div class="stat-value">{{ number_format($item->max_price, 2) }} ₽</div>
        </div>
        @endif
        @if($item->avg_price)
        <div class="stat-card">
            <div class="stat-label">Средняя цена</div>
            <div class="stat-value">{{ number_format($item->avg_price, 2) }} ₽</div>
        </div>
        @endif
    </div>
    @endif

    <!-- Предложения от поставщиков -->
    <div class="admin-card">
        <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">
            Предложения от поставщиков ({{ $item->offers->count() }})
        </h2>

        @if($item->offers->count() > 0)
        <table class="offers-table">
            <thead>
                <tr>
                    <th>Поставщик</th>
                    <th>Цена за ед.</th>
                    <th>Общая цена</th>
                    <th>Срок поставки</th>
                    <th>Условия оплаты</th>
                    <th>Дата ответа</th>
                </tr>
            </thead>
            <tbody>
                @foreach($item->offers as $index => $offer)
                <tr>
                    <td>
                        <div class="supplier-name">{{ $offer->supplier->name ?? 'Не указан' }}</div>
                        @if($offer->supplier && $offer->supplier->email)
                        <div style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;">
                            {{ $offer->supplier->email }}
                        </div>
                        @endif
                        @if($offer->supplier && $offer->supplier->phone)
                        <div style="color: #6b7280; font-size: 0.75rem;">
                            {{ $offer->supplier->phone }}
                        </div>
                        @endif
                    </td>
                    <td>
                        @if($offer->price_per_unit)
                        <div class="{{ $index === 0 ? 'price-best' : '' }}">
                            <span class="price-highlight">{{ number_format($offer->price_per_unit_in_rub, 2) }} ₽</span>
                            @if($offer->currency !== 'RUB')
                                <div style="color: #6b7280; font-size: 0.75rem;">{{ number_format($offer->price_per_unit, 2) }} {{ $offer->currency }}</div>
                            @endif
                            @if($offer->price_includes_vat)
                            <div style="color: #6b7280; font-size: 0.75rem;">с НДС</div>
                            @else
                            <div style="color: #6b7280; font-size: 0.75rem;">без НДС</div>
                            @endif
                        </div>
                        @else
                        <span style="color: #6b7280;">—</span>
                        @endif
                    </td>
                    <td>
                        @if($offer->total_price)
                        <span style="color: #111827; font-weight: 600;">{{ number_format($offer->total_price_in_rub, 2) }} ₽</span>
                        @if($offer->currency !== 'RUB')
                            <div style="color: #6b7280; font-size: 0.75rem;">{{ number_format($offer->total_price, 2) }} {{ $offer->currency }}</div>
                        @endif
                        @else
                        <span style="color: #6b7280;">—</span>
                        @endif
                    </td>
                    <td>
                        @if($offer->delivery_days)
                        <span>{{ $offer->delivery_days }} дн.</span>
                        @else
                        <span style="color: #6b7280;">—</span>
                        @endif
                    </td>
                    <td>
                        @if($offer->payment_terms)
                        <span>{{ $offer->payment_terms }}</span>
                        @else
                        <span style="color: #6b7280;">—</span>
                        @endif
                    </td>
                    <td style="color: #6b7280; font-size: 0.875rem;">
                        @if($offer->response_received_at)
                            {{ $offer->response_received_at->format('d.m.Y H:i') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @if($offer->notes)
                <tr>
                    <td colspan="6" style="background: #f9fafb; padding: 0.75rem;">
                        <div style="color: #6b7280; font-size: 0.75rem; margin-bottom: 0.25rem;">Примечание:</div>
                        <div style="color: #111827; font-size: 0.875rem;">{{ $offer->notes }}</div>
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
        @else
        <div class="no-offers">
            По данной позиции нет предложений от поставщиков
        </div>
        @endif
    </div>
</div>
@endsection
