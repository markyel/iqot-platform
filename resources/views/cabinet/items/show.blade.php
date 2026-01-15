@extends('layouts.cabinet')

@section('title', 'Позиция')

@push('styles')
<style>
    .price-hidden {
        color: var(--neutral-500);
        font-style: italic;
    }

    .price-best {
        background: var(--green-50);
        border: 1px solid var(--green-200);
        border-radius: var(--radius-sm);
        padding: var(--space-2) var(--space-3);
    }

    .price-highlight {
        color: var(--green-700);
        font-weight: 700;
        font-size: 1rem;
    }

    .unlock-banner {
        background: linear-gradient(135deg, var(--yellow-50) 0%, var(--yellow-100) 100%);
        border: 2px solid var(--yellow-400);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-4);
        text-align: center;
    }

    .unlock-banner h3 {
        color: var(--yellow-900);
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: var(--space-2);
    }

    .unlock-banner p {
        color: var(--yellow-800);
        margin-bottom: var(--space-3);
    }

    .btn-unlock {
        background: linear-gradient(135deg, var(--green-600) 0%, var(--green-700) 100%);
        color: white;
        padding: var(--space-3) var(--space-5);
        border-radius: var(--radius-md);
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
        background: var(--neutral-300);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .full-access-badge {
        background: linear-gradient(135deg, var(--green-50) 0%, var(--green-100) 100%);
        border: 2px solid var(--green-600);
        border-radius: var(--radius-lg);
        padding: var(--space-3);
        margin-bottom: var(--space-4);
        text-align: center;
        color: var(--green-900);
        font-weight: 700;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .offers-table {
            display: none;
        }

        .mobile-offer-card {
            display: block;
        }

        .offer-card {
            background: var(--neutral-0);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-md);
            padding: var(--space-3);
            margin-bottom: var(--space-3);
        }

        .offer-card-header {
            font-weight: 700;
            color: var(--neutral-900);
            margin-bottom: var(--space-2);
            padding-bottom: var(--space-2);
            border-bottom: 1px solid var(--neutral-100);
            font-size: 0.9375rem;
        }

        .offer-card-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: var(--space-2);
            font-size: 0.875rem;
        }

        .offer-card-label {
            color: var(--neutral-600);
            font-size: 0.8125rem;
        }

        .offer-card-value {
            color: var(--neutral-900);
            font-weight: 600;
            text-align: right;
        }

        .offer-notes {
            background: var(--neutral-50);
            padding: var(--space-3);
            border-radius: var(--radius-sm);
            margin-top: var(--space-2);
        }

        .offer-notes-label {
            color: var(--neutral-600);
            font-size: 0.75rem;
            margin-bottom: var(--space-1);
        }

        .offer-notes-text {
            color: var(--neutral-900);
            font-size: 0.8125rem;
            line-height: 1.5;
        }
    }

    @media (min-width: 769px) {
        .mobile-offer-card {
            display: none;
        }
    }
</style>
@endpush

@section('content')
<div style="max-width: 1400px; margin: 0 auto;">
    <div style="margin-bottom: var(--space-4);">
        <a href="{{ route('cabinet.items.index') }}" class="text-muted" style="text-decoration: none; display: inline-flex; align-items: center; gap: var(--space-2); font-weight: 600;">
            <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
            Назад к списку позиций
        </a>

        @if(session('success'))
            <div class="alert alert-success" style="margin-top: var(--space-3);">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger" style="margin-top: var(--space-3);">{{ session('error') }}</div>
        @endif

        <!-- Unlock Banner or Full Access Badge -->
        @if(!$hasPurchased)
            <div class="unlock-banner" style="margin-top: var(--space-3);">
                <h3 style="display: flex; align-items: center; justify-content: center; gap: var(--space-2);">
                    <i data-lucide="lock" style="width: 24px; height: 24px;"></i>
                    Предпросмотр позиции
                </h3>
                <p>Для полного доступа к информации о поставщиках и всем ценам разблокируйте этот отчет</p>
                <form method="POST" action="{{ route('cabinet.items.purchase', $item->id) }}">
                    @csrf
                    <x-button
                        type="submit"
                        variant="accent"
                        size="lg"
                        icon="unlock"
                        :disabled="auth()->user()->balance < $unlockPrice"
                        style="width: 100%; justify-content: center;"
                    >
                        Получить полный доступ за {{ number_format($unlockPrice, 0) }} ₽
                    </x-button>
                    @if(auth()->user()->balance < $unlockPrice)
                        <p style="color: var(--danger-700); margin-top: var(--space-2); font-weight: 600; font-size: var(--text-sm);">
                            Недостаточно средств на балансе (доступно: {{ number_format(auth()->user()->balance, 2) }} ₽)
                        </p>
                    @endif
                </form>
            </div>
        @else
            <div class="full-access-badge" style="margin-top: var(--space-3); display: flex; align-items: center; justify-content: center; gap: var(--space-2);">
                <i data-lucide="check-circle" style="width: 24px; height: 24px;"></i>
                У вас есть полный доступ к этому отчету
            </div>
        @endif

        <div>
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: var(--space-2);">
                {{ $item->name }}
            </h1>
            @if(!$hasPurchased)
                <p class="text-muted" style="font-style: italic;">
                    Информация о заявке скрыта в режиме предпросмотра
                </p>
            @else
                <p class="text-muted">
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
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">
                <i data-lucide="package" class="icon-sm" style="display: inline-block; vertical-align: middle; margin-right: var(--space-2);"></i>
                Информация о позиции
            </h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-6);">
                @if($item->brand)
                <div>
                    <div class="text-muted" style="font-size: var(--text-xs); margin-bottom: var(--space-1); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Бренд</div>
                    <div style="font-weight: 600; font-size: var(--text-base);">{{ $item->brand }}</div>
                </div>
                @endif

                @if($item->article)
                <div>
                    <div class="text-muted" style="font-size: var(--text-xs); margin-bottom: var(--space-1); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Артикул</div>
                    <div style="font-weight: 600; font-size: var(--text-base);">{{ $item->article }}</div>
                </div>
                @endif

                <div>
                    <div class="text-muted" style="font-size: var(--text-xs); margin-bottom: var(--space-1); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Количество</div>
                    <div style="font-weight: 600; font-size: var(--text-base);">{{ rtrim(rtrim(number_format($item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit }}</div>
                </div>

                <div>
                    <div class="text-muted" style="font-size: var(--text-xs); margin-bottom: var(--space-1); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Статус</div>
                    <div style="font-weight: 600; font-size: var(--text-base);">
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
            <div style="margin-top: var(--space-6); padding-top: var(--space-6); border-top: 1px solid var(--neutral-200);">
                <div class="text-muted" style="font-size: var(--text-xs); margin-bottom: var(--space-2); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Характеристики</div>
                <div style="line-height: 1.6; font-size: var(--text-sm);">
                    {{ $item->characteristics }}
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Statistics -->
    @if($offers->isNotEmpty())
        @php
            $prices = $offers->pluck('price_per_unit_in_rub')->filter();
            $minPrice = $prices->min();
            $maxPrice = $prices->max();
            $avgPrice = $prices->avg();
        @endphp

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-3); margin-bottom: var(--space-4);">
            <x-stat-card
                label="Предложений"
                :value="$offers->count()"
            />

            <x-stat-card
                label="Мин. цена"
                :value="($hasPurchased || $maxPrice == $minPrice) ? number_format($minPrice, 2) . ' ₽' : '***'"
                variant="success"
            />

            <x-stat-card
                label="Средняя цена"
                :value="($hasPurchased || $prices->count() == 1) ? number_format($avgPrice, 2) . ' ₽' : '***'"
            />

            <x-stat-card
                label="Макс. цена"
                :value="number_format($maxPrice, 2) . ' ₽'"
            />
        </div>
    @endif

    <!-- Offers Table -->
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: var(--space-3);">
            Предложения поставщиков
        </h2>

        @if($offers->isEmpty())
            <x-empty-state
                icon="package-open"
                title="Нет предложений"
                description="По данной позиции пока нет предложений от поставщиков"
            />
        @else
            <table class="table offers-table">
                <thead>
                    <tr>
                        <th>Поставщик</th>
                        <th>Цена за ед.</th>
                        <th>Общая цена</th>
                        <th>Срок поставки</th>
                        @if($hasPurchased)
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
                                <div style="font-weight: 600;">{{ $offer->supplier->name ?? 'Не указан' }}</div>
                                @if($offer->supplier->email)
                                    <div class="text-muted" style="font-size: 0.75rem; margin-top: var(--space-1);">
                                        {{ $offer->supplier->email }}
                                    </div>
                                @endif
                                @if($offer->supplier->phone)
                                    <div class="text-muted" style="font-size: 0.75rem;">
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
                                            <div class="text-muted" style="font-size: 0.75rem;">{{ number_format($offer->price_per_unit, 2) }} {{ $offer->currency }}</div>
                                        @endif
                                        @if($offer->price_includes_vat)
                                            <div class="text-muted" style="font-size: 0.75rem;">с НДС</div>
                                        @else
                                            <div class="text-muted" style="font-size: 0.75rem;">без НДС</div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
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
                                        <div class="text-muted" style="font-size: 0.75rem;">{{ number_format($offer->total_price, 2) }} {{ $offer->currency }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
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
                                    <span class="text-muted">—</span>
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
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted" style="font-size: 0.875rem;">
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
                        <td colspan="6" style="background: var(--neutral-50); padding: var(--space-3);">
                            <div class="text-muted" style="font-size: 0.75rem; margin-bottom: var(--space-1);">Примечание:</div>
                            <div style="font-size: 0.875rem;">{{ $offer->notes }}</div>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>

            <!-- Mobile Offer Cards -->
            <div class="mobile-offer-card">
                @foreach($offers as $index => $offer)
                <div class="offer-card">
                    <div class="offer-card-header">
                        @if($hasPurchased && $offer->supplier)
                            {{ $offer->supplier->name ?? 'Не указан' }}
                            @if($index === 0)
                                <x-badge variant="success" style="margin-left: var(--space-2);">Лучшая</x-badge>
                            @endif
                        @else
                            Поставщик скрыт
                        @endif
                    </div>

                    @if($hasPurchased && $offer->supplier)
                        @if($offer->supplier->email)
                        <div class="offer-card-row">
                            <span class="offer-card-label">Email:</span>
                            <span class="offer-card-value">{{ $offer->supplier->email }}</span>
                        </div>
                        @endif
                        @if($offer->supplier->phone)
                        <div class="offer-card-row">
                            <span class="offer-card-label">Телефон:</span>
                            <span class="offer-card-value">{{ $offer->supplier->phone }}</span>
                        </div>
                        @endif
                    @endif

                    <div class="offer-card-row">
                        <span class="offer-card-label">Цена за ед.:</span>
                        <div class="offer-card-value">
                            @if($hasPurchased || $offer->price_per_unit_in_rub == $maxPrice)
                                @if($offer->price_per_unit)
                                    <div style="font-weight: 700; color: var(--green-700);">{{ number_format($offer->price_per_unit_in_rub, 2) }} ₽</div>
                                    @if($offer->currency !== 'RUB')
                                        <div class="text-muted" style="font-size: 0.75rem;">{{ number_format($offer->price_per_unit, 2) }} {{ $offer->currency }}</div>
                                    @endif
                                @else
                                    —
                                @endif
                            @else
                                <span class="price-hidden">***</span>
                            @endif
                        </div>
                    </div>

                    <div class="offer-card-row">
                        <span class="offer-card-label">Общая цена:</span>
                        <div class="offer-card-value">
                            @if($hasPurchased || $offer->price_per_unit_in_rub == $maxPrice)
                                @if($offer->total_price)
                                    <div style="font-weight: 700;">{{ number_format($offer->total_price_in_rub, 2) }} ₽</div>
                                    @if($offer->currency !== 'RUB')
                                        <div class="text-muted" style="font-size: 0.75rem;">{{ number_format($offer->total_price, 2) }} {{ $offer->currency }}</div>
                                    @endif
                                @else
                                    —
                                @endif
                            @else
                                <span class="price-hidden">***</span>
                            @endif
                        </div>
                    </div>

                    <div class="offer-card-row">
                        <span class="offer-card-label">Срок поставки:</span>
                        <span class="offer-card-value">
                            @if($hasPurchased || $offer->price_per_unit_in_rub == $maxPrice)
                                @if($offer->delivery_days)
                                    {{ $offer->delivery_days }} дн.
                                @else
                                    —
                                @endif
                            @else
                                <span class="price-hidden">***</span>
                            @endif
                        </span>
                    </div>

                    @if($hasPurchased)
                        @if($offer->payment_terms)
                        <div class="offer-card-row">
                            <span class="offer-card-label">Условия оплаты:</span>
                            <span class="offer-card-value">{{ $offer->payment_terms }}</span>
                        </div>
                        @endif

                        @if($offer->response_received_at)
                        <div class="offer-card-row">
                            <span class="offer-card-label">Дата ответа:</span>
                            <span class="offer-card-value">{{ $offer->response_received_at->format('d.m.Y H:i') }}</span>
                        </div>
                        @endif

                        @if($offer->notes)
                        <div class="offer-notes">
                            <div class="offer-notes-label">Примечание:</div>
                            <div class="offer-notes-text">{{ $offer->notes }}</div>
                        </div>
                        @endif
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Bottom Unlock Button -->
    @if(!$hasPurchased)
        <div class="unlock-banner">
            <h3 style="display: flex; align-items: center; justify-content: center; gap: var(--space-2);">
                <i data-lucide="lightbulb" style="width: 24px; height: 24px;"></i>
                Получите полный доступ к отчету
            </h3>
            <p>Узнайте всех поставщиков, их контакты и полную информацию о ценах</p>
            <form method="POST" action="{{ route('cabinet.items.purchase', $item->id) }}">
                @csrf
                <x-button
                    type="submit"
                    variant="accent"
                    size="lg"
                    icon="unlock"
                    :disabled="auth()->user()->balance < $unlockPrice"
                    style="width: 100%; justify-content: center;"
                >
                    Разблокировать отчет за {{ number_format($unlockPrice, 0) }} ₽
                </x-button>
            </form>
        </div>
    @endif
</div>

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
@endsection
