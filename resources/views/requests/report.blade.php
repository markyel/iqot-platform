@extends('layouts.cabinet')

@section('title', 'Отчет по заявке ' . $externalRequest->request_number)

@push('styles')
<style>
    /* Light theme for user reports */
    .report-card {
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

    .status-draft { background: #f3f4f6; color: #6b7280; }
    .status-new { background: #dbeafe; color: #1e40af; }
    .status-active { background: #d1fae5; color: #065f46; }
    .status-collecting { background: #fef3c7; color: #92400e; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }
    .status-emails-sent { background: #e0e7ff; color: #3730a3; }
    .status-responses-received { background: #ddd6fe; color: #5b21b6; }
    .status-queued-for-sending { background: #fef3c7; color: #78350f; }

    .info-label {
        color: #6b7280;
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }

    .info-value {
        color: #111827;
        font-weight: 600;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 0.5rem;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981, #059669);
        transition: width 0.3s;
    }

    .item-card {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .item-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .item-name {
        color: #111827;
        font-weight: 600;
        font-size: 1.125rem;
        margin-bottom: 0.5rem;
    }

    .item-meta {
        color: #6b7280;
        font-size: 0.875rem;
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

    .no-offers {
        text-align: center;
        padding: 2rem;
        color: #9ca3af;
        font-style: italic;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
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
</style>
@endpush

@section('content')
<div style="max-width: 1600px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('cabinet.my.requests.show', $request->id) }}" class="back-link">
            ← Назад к заявке
        </a>
        <div style="display: flex; justify-content: space-between; align-items: start; margin-top: 1rem;">
            <div>
                <h1 style="font-size: 2rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;">
                    Отчет по заявке {{ $externalRequest->request_number }}
                </h1>
                <p style="color: #6b7280;">
                    Создана {{ $externalRequest->created_at ? $externalRequest->created_at->format('d.m.Y в H:i') : '—' }}
                </p>
            </div>
            <div>
                @php
                    $statusClass = 'status-' . str_replace('_', '-', $externalRequest->status);
                    $statusLabel = \App\Models\ExternalRequest::getStatusLabels()[$externalRequest->status] ?? $externalRequest->status;
                @endphp
                <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
        </div>
    </div>

    <!-- Основная информация о заявке -->
    <div class="report-card">
        <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">Информация о заявке</h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
            @if($externalRequest->title)
            <div>
                <div class="info-label">Название</div>
                <div class="info-value">{{ $externalRequest->title }}</div>
            </div>
            @endif

            @if($externalRequest->collection_deadline)
            <div>
                <div class="info-label">Срок сбора</div>
                <div class="info-value">{{ $externalRequest->collection_deadline->format('d.m.Y H:i') }}</div>
            </div>
            @endif
        </div>

        <!-- Прогресс выполнения -->
        <div>
            <div class="info-label">Прогресс выполнения</div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="flex: 1;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: {{ $externalRequest->completion_percentage }}%"></div>
                    </div>
                </div>
                <div style="color: #111827; font-weight: 700; font-size: 1.125rem;">
                    {{ number_format($externalRequest->completion_percentage, 0) }}%
                </div>
            </div>
        </div>

        @if($externalRequest->notes)
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
            <div class="info-label">Заметки</div>
            <div style="color: #374151; white-space: pre-wrap; margin-top: 0.5rem;">{{ $externalRequest->notes }}</div>
        </div>
        @endif
    </div>

    <!-- Статистика -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Всего позиций</div>
            <div class="stat-value">{{ $externalRequest->total_items }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">С предложениями</div>
            <div class="stat-value stat-value-accent">{{ $externalRequest->items_with_offers }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Процент закрытия</div>
            <div class="stat-value stat-value-accent">
                {{ $externalRequest->total_items > 0 ? number_format(($externalRequest->items_with_offers / $externalRequest->total_items) * 100, 0) : 0 }}%
            </div>
        </div>
    </div>

    <!-- Товарные позиции с предложениями -->
    <div class="report-card">
        <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">
            Товарные позиции ({{ $externalRequest->items->count() }})
        </h2>

        @forelse($externalRequest->items as $item)
        <div class="item-card">
            <div class="item-header">
                <div style="flex: 1;">
                    <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                        Позиция #{{ $item->position_number }}
                    </div>
                    <div class="item-name">{{ $item->name }}</div>
                    <div class="item-meta">
                        @if($item->brand)
                            <span>Бренд: <strong style="color: #374151;">{{ $item->brand }}</strong></span> •
                        @endif
                        @if($item->article)
                            <span>Артикул: <strong style="color: #374151;">{{ $item->article }}</strong></span> •
                        @endif
                        <span>Количество: <strong style="color: #374151;">{{ $item->quantity }} {{ $item->unit }}</strong></span>
                    </div>
                    @if($item->description)
                    <div style="color: #6b7280; font-size: 0.875rem; margin-top: 0.5rem;">
                        {{ $item->description }}
                    </div>
                    @endif
                </div>
                <div>
                    @php
                        $itemStatusClass = 'status-badge status-' . str_replace('_', '-', $item->status);
                        $itemStatusLabel = \App\Models\ExternalRequestItem::getStatusLabels()[$item->status] ?? $item->status;
                    @endphp
                    <span class="{{ $itemStatusClass }}" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">
                        {{ $itemStatusLabel }}
                    </span>
                </div>
            </div>

            @if($item->offers->count() > 0)
            <!-- Статистика по позиции -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px;">
                <div>
                    <div style="color: #6b7280; font-size: 0.75rem;">Предложений</div>
                    <div style="color: #059669; font-weight: 700; font-size: 1.125rem;">{{ $item->offers->count() }}</div>
                </div>
                @if($item->min_price)
                <div>
                    <div style="color: #6b7280; font-size: 0.75rem;">Мин. цена</div>
                    <div style="color: #111827; font-weight: 700; font-size: 1.125rem;">{{ number_format($item->min_price, 2) }} ₽</div>
                </div>
                @endif
                @if($item->max_price)
                <div>
                    <div style="color: #6b7280; font-size: 0.75rem;">Макс. цена</div>
                    <div style="color: #111827; font-weight: 700; font-size: 1.125rem;">{{ number_format($item->max_price, 2) }} ₽</div>
                </div>
                @endif
            </div>

            <!-- Таблица предложений -->
            <table class="offers-table">
                <thead>
                    <tr>
                        <th>Поставщик</th>
                        <th>Цена за ед.</th>
                        <th>Общая цена</th>
                        <th>Срок поставки</th>
                        <th>Условия оплаты</th>
                        <th>Статус</th>
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
                        <td>
                            @php
                                $offerStatusClass = 'status-badge status-' . $offer->status;
                                $offerStatusLabel = \App\Models\ExternalOffer::getStatusLabels()[$offer->status] ?? $offer->status;
                            @endphp
                            <span class="{{ $offerStatusClass }}" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                {{ $offerStatusLabel }}
                            </span>
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
                Нет предложений по данной позиции
            </div>
            @endif
        </div>
        @empty
        <div class="no-offers">
            Товарные позиции отсутствуют
        </div>
        @endforelse
    </div>
</div>
@endsection
