@extends('layouts.cabinet')

@section('title', 'Позиция #' . $item->position_number)

@section('content')
<div style="max-width: 1400px; margin: 0 auto;">
    <div style="margin-bottom: var(--space-6);">
        <a href="{{ route('admin.items.index') }}" style="color: var(--primary-600); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-4);">
            <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
            Назад к списку позиций
        </a>
        <div style="display: flex; justify-content: space-between; align-items: start; margin-top: var(--space-4);">
            <div>
                <h1 style="font-size: 2rem; font-weight: 700; color: var(--neutral-900); margin-bottom: var(--space-2);">
                    Позиция #{{ $item->position_number }}
                </h1>
                <p style="color: var(--neutral-600);">
                    Из заявки
                    @if($item->request)
                        <span style="color: var(--primary-600); font-weight: 600;">
                            {{ $item->request->request_number }}
                        </span>
                    @endif
                </p>
            </div>
            <div>
                @php
                    $statusLabel = \App\Models\ExternalRequestItem::getStatusLabels()[$item->status] ?? $item->status;
                @endphp
                <x-badge :variant="match($item->status) {
                    'has_offers' => 'success',
                    'partial_offers' => 'warning',
                    'no_offers' => 'danger',
                    'clarification_needed' => 'info',
                    default => 'neutral'
                }">{{ $statusLabel }}</x-badge>
            </div>
        </div>
    </div>

    <!-- Информация о позиции -->
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <h2 class="card-title">Информация о позиции</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-6);">
                <div>
                    <div class="form-label">Название</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">{{ $item->name }}</div>
                </div>

                @if($item->brand)
                <div>
                    <div class="form-label">Бренд</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">{{ $item->brand }}</div>
                </div>
                @endif

                @if($item->article)
                <div>
                    <div class="form-label">Артикул</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">{{ $item->article }}</div>
                </div>
                @endif

                <div>
                    <div class="form-label">Количество</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">{{ $item->quantity }} {{ $item->unit }}</div>
                </div>

                @if($item->category)
                <div>
                    <div class="form-label">Категория</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">{{ $item->category }}</div>
                </div>
                @endif
            </div>

            @if($item->description)
            <div style="margin-top: var(--space-6); padding-top: var(--space-6); border-top: 1px solid var(--neutral-200);">
                <div class="form-label">Описание</div>
                <div style="color: var(--neutral-700); margin-top: var(--space-2);">{{ $item->description }}</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Статистика -->
    @if($item->offers->count() > 0)
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
        <x-stat-card
            label="Всего предложений"
            :value="$item->offers->count()"
            variant="success"
        />
        @if($item->min_price)
        <x-stat-card
            label="Минимальная цена"
            :value="number_format($item->min_price, 2) . ' ₽'"
        />
        @endif
        @if($item->max_price)
        <x-stat-card
            label="Максимальная цена"
            :value="number_format($item->max_price, 2) . ' ₽'"
        />
        @endif
        @if($item->avg_price)
        <x-stat-card
            label="Средняя цена"
            :value="number_format($item->avg_price, 2) . ' ₽'"
        />
        @endif
    </div>
    @endif

    <!-- Предложения от поставщиков -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Предложения от поставщиков ({{ $item->offers->count() }})</h2>
        </div>
        <div class="card-body">
            @if($item->offers->count() > 0)
            <table class="table">
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
                        <td data-label="Поставщик">
                            <div style="font-weight: 600; color: var(--neutral-900);">{{ $offer->supplier->name ?? 'Не указан' }}</div>
                            @if($offer->supplier && $offer->supplier->email)
                            <div style="color: var(--neutral-600); font-size: 0.75rem; margin-top: var(--space-1);">
                                {{ $offer->supplier->email }}
                            </div>
                            @endif
                            @if($offer->supplier && $offer->supplier->phone)
                            <div style="color: var(--neutral-600); font-size: 0.75rem;">
                                {{ $offer->supplier->phone }}
                            </div>
                            @endif
                        </td>
                        <td data-label="Цена за ед.">
                            @if($offer->price_per_unit)
                            <div style="{{ $index === 0 ? 'background: var(--success-50); border: 1px solid var(--success-200); border-radius: 4px; padding: var(--space-2) var(--space-3);' : '' }}">
                                <span style="color: var(--primary-600); font-weight: 700; font-size: 1rem;">{{ number_format($offer->price_per_unit_in_rub, 2) }} ₽</span>
                                @if($offer->currency !== 'RUB')
                                    <div style="color: var(--neutral-600); font-size: 0.75rem;">{{ number_format($offer->price_per_unit, 2) }} {{ $offer->currency }}</div>
                                @endif
                                @if($offer->price_includes_vat)
                                <div style="color: var(--neutral-600); font-size: 0.75rem;">с НДС</div>
                                @else
                                <div style="color: var(--neutral-600); font-size: 0.75rem;">без НДС</div>
                                @endif
                            </div>
                            @else
                            <span style="color: var(--neutral-500);">—</span>
                            @endif
                        </td>
                        <td data-label="Общая цена">
                            @if($offer->total_price)
                            <span style="color: var(--neutral-900); font-weight: 600;">{{ number_format($offer->total_price_in_rub, 2) }} ₽</span>
                            @if($offer->currency !== 'RUB')
                                <div style="color: var(--neutral-600); font-size: 0.75rem;">{{ number_format($offer->total_price, 2) }} {{ $offer->currency }}</div>
                            @endif
                            @else
                            <span style="color: var(--neutral-500);">—</span>
                            @endif
                        </td>
                        <td data-label="Срок поставки">
                            @if($offer->delivery_days)
                            <span>{{ $offer->delivery_days }} дн.</span>
                            @else
                            <span style="color: var(--neutral-500);">—</span>
                            @endif
                        </td>
                        <td data-label="Условия оплаты">
                            @if($offer->payment_terms)
                            <span>{{ $offer->payment_terms }}</span>
                            @else
                            <span style="color: var(--neutral-500);">—</span>
                            @endif
                        </td>
                        <td data-label="Дата ответа" style="color: var(--neutral-600); font-size: 0.875rem;">
                            @if($offer->response_received_at)
                                {{ $offer->response_received_at->format('d.m.Y H:i') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @if($offer->notes)
                    <tr>
                        <td colspan="6" style="background: var(--neutral-50); padding: var(--space-3);">
                            <div style="color: var(--neutral-600); font-size: 0.75rem; margin-bottom: var(--space-1);">Примечание:</div>
                            <div style="color: var(--neutral-900); font-size: 0.875rem;">{{ $offer->notes }}</div>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
            @else
            <x-empty-state
                icon="package-x"
                title="Нет предложений"
                description="По данной позиции нет предложений от поставщиков"
            />
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
@endsection
