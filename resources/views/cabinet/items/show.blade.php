@extends('layouts.cabinet')

@section('title', 'Позиция')

@push('styles')
<style>
    /* Light theme for user cabinet */
    .cabinet-card {
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

    .price-hidden {
        color: #9ca3af;
        font-style: italic;
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

    .no-offers {
        text-align: center;
        padding: 3rem;
        color: #9ca3af;
        font-style: italic;
    }

    .btn-unlock {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 1rem 2rem;
        border-radius: 8px;
        border: none;
        font-weight: 700;
        font-size: 1.125rem;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
        width: 100%;
        text-align: center;
    }

    .btn-unlock:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(16, 185, 129, 0.4);
    }

    .btn-unlock:disabled {
        background: #d1d5db;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .unlock-banner {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 2px solid #fbbf24;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .unlock-banner h3 {
        color: #92400e;
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .unlock-banner p {
        color: #78350f;
        margin-bottom: 1rem;
    }

    .full-access-badge {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        border: 2px solid #10b981;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        text-align: center;
        color: #065f46;
        font-weight: 700;
    }

    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
</style>
@endpush

@section('content')
<div style="max-width: 1400px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('cabinet.items.index') }}" class="back-link">
            ← Назад к списку позиций
        </a>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <!-- Unlock Banner or Full Access Badge -->
        @if(!$hasPurchased)
            <div class="unlock-banner">
                <h3>🔒 Предпросмотр позиции</h3>
                <p>Для полного доступа к информации о поставщиках и всем ценам разблокируйте этот отчет</p>
                <form method="POST" action="{{ route('cabinet.items.purchase', $item->id) }}">
                    @csrf
                    <button type="submit" class="btn-unlock" {{ auth()->user()->balance < $unlockPrice ? 'disabled' : '' }}>
                        🔓 Получить полный доступ за {{ number_format($unlockPrice, 0) }} ₽
                    </button>
                    @if(auth()->user()->balance < $unlockPrice)
                        <p style="color: #991b1b; margin-top: 0.5rem; font-weight: 600;">
                            Недостаточно средств на балансе (доступно: {{ number_format(auth()->user()->balance, 2) }} ₽)
                        </p>
                    @endif
                </form>
            </div>
        @else
            <div class="full-access-badge">
                ✅ У вас есть полный доступ к этому отчету
            </div>
        @endif

        <div>
            <h1 style="font-size: 2rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;">
                {{ $item->name }}
            </h1>
            @if(!$hasPurchased)
                <p style="color: #9ca3af; font-size: 0.875rem; font-style: italic;">
                    Информация о заявке скрыта в режиме предпросмотра
                </p>
            @else
                <p style="color: #6b7280;">
                    Позиция #{{ $item->position_number }}
                    @if($item->request)
                        из заявки
                        <span style="font-weight: 600;">{{ $item->request->request_number }}</span>
                    @endif
                </p>
            @endif
        </div>
    </div>

    <!-- Item Details -->
    <div class="cabinet-card">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin-bottom: 1rem;">
            Информация о позиции
        </h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
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
                <div class="info-value">{{ rtrim(rtrim(number_format($item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit }}</div>
            </div>

            <div>
                <div class="info-label">Статус</div>
                <div class="info-value">
                    @if($item->status === 'pending')
                        Ожидает
                    @elseif($item->status === 'has_offers')
                        Есть предложения
                    @elseif($item->status === 'partial_offers')
                        Частично
                    @elseif($item->status === 'no_offers')
                        Нет предложений
                    @elseif($item->status === 'clarification_needed')
                        Требуется уточнение
                    @else
                        {{ $item->status }}
                    @endif
                </div>
            </div>
        </div>

        @if($item->characteristics)
        <div style="margin-top: 1.5rem;">
            <div class="info-label">Характеристики</div>
            <div style="color: #374151; line-height: 1.6; margin-top: 0.5rem;">
                {{ $item->characteristics }}
            </div>
        </div>
        @endif
    </div>

    <!-- Statistics -->
    @if($offers->isNotEmpty())
        @php
            $prices = $offers->pluck('price_per_unit_in_rub')->filter();
            $minPrice = $prices->min();
            $maxPrice = $prices->max();
            $avgPrice = $prices->avg();
        @endphp

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div class="stat-card">
                <div class="stat-label">Предложений</div>
                <div class="stat-value">{{ $offers->count() }}</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Мин. цена</div>
                <div class="stat-value stat-value-accent">
                    @if($hasPurchased || $maxPrice == $minPrice)
                        {{ number_format($minPrice, 2) }} ₽
                    @else
                        <span class="price-hidden">***</span>
                    @endif
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Средняя цена</div>
                <div class="stat-value">
                    @if($hasPurchased || $prices->count() == 1)
                        {{ number_format($avgPrice, 2) }} ₽
                    @else
                        <span class="price-hidden">***</span>
                    @endif
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Макс. цена</div>
                <div class="stat-value">{{ number_format($maxPrice, 2) }} ₽</div>
            </div>
        </div>
    @endif

    <!-- Offers Table -->
    <div class="cabinet-card">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin-bottom: 1rem;">
            Предложения поставщиков
        </h2>

        @if($offers->isEmpty())
            <div class="no-offers">
                По данной позиции пока нет предложений от поставщиков
            </div>
        @else
            <table class="offers-table">
                <thead>
                    <tr>
                        <th>Поставщик</th>
                        <th>Цена за ед.</th>
                        <th>Общая цена</th>
                        @if(!$hasPurchased)
                            <th>Срок поставки</th>
                        @else
                            <th>Срок поставки</th>
                            <th>Условия оплаты</th>
                            <th>Дата ответа</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($offers as $index => $offer)
                    <tr>
                        <td>
                            @if($hasPurchased && $offer->supplier)
                                <div style="font-weight: 600; color: #111827;">{{ $offer->supplier->name ?? 'Не указан' }}</div>
                                @if($offer->supplier->email)
                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                        {{ $offer->supplier->email }}
                                    </div>
                                @endif
                                @if($offer->supplier->phone)
                                    <div style="font-size: 0.75rem; color: #6b7280;">
                                        {{ $offer->supplier->phone }}
                                    </div>
                                @endif
                            @else
                                <span class="price-hidden">Скрыто</span>
                            @endif
                        </td>
                        <td>
                            @if($hasPurchased || $offer->price_per_unit_in_rub == $maxPrice)
                                @if($offer->price_per_unit)
                                    <div class="{{ $hasPurchased && $index === 0 ? 'price-best' : '' }}">
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
                            @else
                                <span class="price-hidden">***</span>
                            @endif
                        </td>
                        <td>
                            @if($hasPurchased || $offer->price_per_unit_in_rub == $maxPrice)
                                @if($offer->total_price)
                                    <span style="font-weight: 600;">{{ number_format($offer->total_price_in_rub, 2) }} ₽</span>
                                    @if($offer->currency !== 'RUB')
                                        <div style="color: #6b7280; font-size: 0.75rem;">{{ number_format($offer->total_price, 2) }} {{ $offer->currency }}</div>
                                    @endif
                                @else
                                    <span style="color: #6b7280;">—</span>
                                @endif
                            @else
                                <span class="price-hidden">***</span>
                            @endif
                        </td>
                        <td>
                            @if($hasPurchased || $offer->price_per_unit_in_rub == $maxPrice)
                                @if($offer->delivery_days)
                                    <span>{{ $offer->delivery_days }} дн.</span>
                                @else
                                    <span style="color: #6b7280;">—</span>
                                @endif
                            @else
                                <span class="price-hidden">***</span>
                            @endif
                        </td>
                        @if($hasPurchased)
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
                        @endif
                    </tr>
                    @if($hasPurchased && $offer->notes)
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
        @endif
    </div>

    <!-- Bottom Unlock Button -->
    @if(!$hasPurchased)
        <div class="unlock-banner">
            <h3>💡 Получите полный доступ к отчету</h3>
            <p>Узнайте всех поставщиков, их контакты и полную информацию о ценах</p>
            <form method="POST" action="{{ route('cabinet.items.purchase', $item->id) }}">
                @csrf
                <button type="submit" class="btn-unlock" {{ auth()->user()->balance < $unlockPrice ? 'disabled' : '' }}>
                    🔓 Разблокировать отчет за {{ number_format($unlockPrice, 0) }} ₽
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
