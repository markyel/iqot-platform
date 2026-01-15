@extends('layouts.cabinet')

@section('title', 'Заявка ' . $externalRequest->request_number)

@section('content')
<div style="max-width: 1600px; margin: 0 auto;">
    <div style="margin-bottom: var(--space-6);">
        <a href="{{ route('admin.manage.requests.show', $externalRequest->id) }}" style="color: var(--primary-600); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-4);">
            <i data-lucide="arrow-left" style="width: 1rem; height: 1rem;"></i>
            Назад к заявке
        </a>
        <div style="display: flex; justify-content: space-between; align-items: start; margin-top: var(--space-4);">
            <div>
                <h1 style="font-size: 2rem; font-weight: 700; color: var(--neutral-900); margin-bottom: var(--space-2);">
                    Заявка {{ $externalRequest->request_number }}
                </h1>
                <p style="color: var(--neutral-600);">
                    Создана {{ $externalRequest->created_at ? $externalRequest->created_at->format('d.m.Y в H:i') : '—' }}
                </p>
            </div>
            <div>
                @php
                    $statusMap = [
                        'draft' => 'secondary',
                        'new' => 'info',
                        'active' => 'success',
                        'collecting' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'emails_sent' => 'info',
                        'responses_received' => 'primary',
                        'queued_for_sending' => 'warning'
                    ];
                    $statusType = $statusMap[$externalRequest->status] ?? 'secondary';
                    $statusLabel = \App\Models\ExternalRequest::getStatusLabels()[$externalRequest->status] ?? $externalRequest->status;
                @endphp
                <x-badge type="{{ $statusType }}">{{ $statusLabel }}</x-badge>
            </div>
        </div>
    </div>

    <!-- Основная информация о заявке -->
    <div class="card">
        <div class="card-header">
            <h2>Информация о заявке</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">
                @if($externalRequest->title)
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Название</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">{{ $externalRequest->title }}</div>
                </div>
                @endif

                @if($externalRequest->customer_company)
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Компания клиента</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">{{ $externalRequest->customer_company }}</div>
                </div>
                @endif

                @if($externalRequest->customer_contact_person)
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Контактное лицо</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">{{ $externalRequest->customer_contact_person }}</div>
                </div>
                @endif

                @if($externalRequest->customer_email)
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Email клиента</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">
                        <a href="mailto:{{ $externalRequest->customer_email }}" style="color: var(--primary-600); text-decoration: none;">
                            {{ $externalRequest->customer_email }}
                        </a>
                    </div>
                </div>
                @endif

                @if($externalRequest->customer_phone)
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Телефон клиента</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">{{ $externalRequest->customer_phone }}</div>
                </div>
                @endif

                @if($externalRequest->collection_deadline)
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Срок сбора</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">{{ $externalRequest->collection_deadline->format('d.m.Y H:i') }}</div>
                </div>
                @endif
            </div>

            <!-- Прогресс выполнения -->
            <div>
                <div class="form-label" style="margin-bottom: var(--space-2);">Прогресс выполнения</div>
                <div style="display: flex; align-items: center; gap: var(--space-4);">
                    <div style="flex: 1;">
                        <div style="width: 100%; height: 8px; background: var(--neutral-200); border-radius: var(--radius-sm); overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(90deg, var(--primary-500), var(--primary-600)); width: {{ $externalRequest->completion_percentage }}%; transition: width 0.3s;"></div>
                        </div>
                    </div>
                    <div style="color: var(--neutral-900); font-weight: 700; font-size: 1.125rem;">
                        {{ number_format($externalRequest->completion_percentage, 0) }}%
                    </div>
                </div>
            </div>

            @if($externalRequest->notes)
            <div style="margin-top: var(--space-6); padding-top: var(--space-6); border-top: 1px solid var(--neutral-200);">
                <div class="form-label" style="margin-bottom: var(--space-2);">Заметки</div>
                <div style="color: var(--neutral-700); white-space: pre-wrap;">{{ $externalRequest->notes }}</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Статистика -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
        <x-stat-card
            label="Всего позиций"
            :value="$externalRequest->total_items"
        />
        <x-stat-card
            label="С предложениями"
            :value="$externalRequest->items_with_offers"
            variant="success"
        />
        <x-stat-card
            label="Процент закрытия"
            :value="($externalRequest->total_items > 0 ? number_format(($externalRequest->items_with_offers / $externalRequest->total_items) * 100, 0) : 0) . '%'"
            variant="success"
        />
    </div>

    <!-- Товарные позиции с предложениями -->
    <div class="card">
        <div class="card-header">
            <h2>Товарные позиции ({{ $externalRequest->items->count() }})</h2>
        </div>
        <div class="card-body">
            @forelse($externalRequest->items as $item)
            <div style="background: var(--neutral-50); border: 1px solid var(--neutral-200); border-radius: var(--radius-md); padding: var(--space-6); margin-bottom: var(--space-6);">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-4); padding-bottom: var(--space-4); border-bottom: 1px solid var(--neutral-200);">
                    <div style="flex: 1;">
                        <div style="color: var(--neutral-600); font-size: 0.875rem; margin-bottom: var(--space-1);">
                            Позиция #{{ $item->position_number }}
                        </div>
                        <div style="color: var(--neutral-900); font-weight: 600; font-size: 1.125rem; margin-bottom: var(--space-2);">{{ $item->name }}</div>
                        <div style="color: var(--neutral-600); font-size: 0.875rem;">
                            @if($item->brand)
                                <span>Бренд: <strong style="color: var(--neutral-700);">{{ $item->brand }}</strong></span> •
                            @endif
                            @if($item->article)
                                <span>Артикул: <strong style="color: var(--neutral-700);">{{ $item->article }}</strong></span> •
                            @endif
                            <span>Количество: <strong style="color: var(--neutral-700);">{{ $item->quantity }} {{ $item->unit }}</strong></span>
                        </div>
                        @if($item->description)
                        <div style="color: var(--neutral-600); font-size: 0.875rem; margin-top: var(--space-2);">
                            {{ $item->description }}
                        </div>
                        @endif
                    </div>
                    <div>
                        @php
                            $itemStatusMap = [
                                'draft' => 'secondary',
                                'new' => 'info',
                                'sent' => 'warning',
                                'responses_received' => 'primary',
                                'completed' => 'success',
                                'cancelled' => 'danger'
                            ];
                            $itemStatusType = $itemStatusMap[$item->status] ?? 'secondary';
                            $itemStatusLabel = \App\Models\ExternalRequestItem::getStatusLabels()[$item->status] ?? $item->status;
                        @endphp
                        <x-badge type="{{ $itemStatusType }}" size="sm">{{ $itemStatusLabel }}</x-badge>
                    </div>
                </div>

                @if($item->offers->count() > 0)
                <!-- Статистика по позиции -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-4); margin-bottom: var(--space-4); padding: var(--space-4); background: white; border: 1px solid var(--neutral-200); border-radius: var(--radius-md);">
                    <div>
                        <div style="color: var(--neutral-600); font-size: 0.75rem;">Предложений</div>
                        <div style="color: var(--success-600); font-weight: 700; font-size: 1.125rem;">{{ $item->offers->count() }}</div>
                    </div>
                    @if($item->min_price)
                    <div>
                        <div style="color: var(--neutral-600); font-size: 0.75rem;">Мин. цена</div>
                        <div style="color: var(--neutral-900); font-weight: 700; font-size: 1.125rem;">{{ number_format($item->min_price, 2) }} ₽</div>
                    </div>
                    @endif
                    @if($item->max_price)
                    <div>
                        <div style="color: var(--neutral-600); font-size: 0.75rem;">Макс. цена</div>
                        <div style="color: var(--neutral-900); font-weight: 700; font-size: 1.125rem;">{{ number_format($item->max_price, 2) }} ₽</div>
                    </div>
                    @endif
                </div>

                <!-- Таблица предложений -->
                <table class="table">
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
                            <td data-label="Поставщик">
                                <div style="font-weight: 600; color: var(--neutral-900);">{{ $offer->supplier->name ?? 'Не указан' }}</div>
                                @if($offer->supplier && $offer->supplier->email)
                                <div style="color: var(--neutral-500); font-size: 0.75rem; margin-top: var(--space-1);">
                                    {{ $offer->supplier->email }}
                                </div>
                                @endif
                            </td>
                            <td data-label="Цена за ед.">
                                @if($offer->price_per_unit)
                                <div @if($index === 0) style="background: var(--success-50); border: 1px solid var(--success-200); border-radius: var(--radius-sm); padding: var(--space-2);" @endif>
                                    <span style="color: var(--success-600); font-weight: 700;">{{ number_format($offer->price_per_unit_in_rub, 2) }} ₽</span>
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
                                <span style="color: var(--neutral-400);">—</span>
                                @endif
                            </td>
                            <td data-label="Общая цена">
                                @if($offer->total_price)
                                <span style="font-weight: 600;">{{ number_format($offer->total_price_in_rub, 2) }} ₽</span>
                                @if($offer->currency !== 'RUB')
                                    <div style="color: var(--neutral-600); font-size: 0.75rem;">{{ number_format($offer->total_price, 2) }} {{ $offer->currency }}</div>
                                @endif
                                @else
                                <span style="color: var(--neutral-400);">—</span>
                                @endif
                            </td>
                            <td data-label="Срок поставки">
                                @if($offer->delivery_days)
                                <span>{{ $offer->delivery_days }} дн.</span>
                                @else
                                <span style="color: var(--neutral-400);">—</span>
                                @endif
                            </td>
                            <td data-label="Условия оплаты">
                                @if($offer->payment_terms)
                                <span>{{ $offer->payment_terms }}</span>
                                @else
                                <span style="color: var(--neutral-400);">—</span>
                                @endif
                            </td>
                            <td data-label="Статус">
                                @php
                                    $offerStatusMap = [
                                        'pending' => 'warning',
                                        'accepted' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled' => 'secondary'
                                    ];
                                    $offerStatusType = $offerStatusMap[$offer->status] ?? 'secondary';
                                    $offerStatusLabel = \App\Models\ExternalOffer::getStatusLabels()[$offer->status] ?? $offer->status;
                                @endphp
                                <x-badge type="{{ $offerStatusType }}" size="sm">{{ $offerStatusLabel }}</x-badge>
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
                    description="По данной позиции предложения от поставщиков отсутствуют"
                />
                @endif
            </div>
            @empty
            <x-empty-state
                icon="inbox"
                title="Нет позиций"
                description="Товарные позиции в заявке отсутствуют"
            />
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush
@endsection
