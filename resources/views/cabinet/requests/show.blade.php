@extends('layouts.cabinet')

@section('title', 'Заявка ' . $request->code)

@section('content')
<div style="margin-bottom: var(--space-4);">
    <a href="{{ route('cabinet.requests') }}" class="text-muted" style="text-decoration: none; display: inline-flex; align-items: center; gap: var(--space-2);">
        <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
        Назад к списку
    </a>
</div>

<div style="display: grid; gap: var(--space-4);">
    <!-- Основная информация -->
    <div class="card">
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-3);">
                <div>
                    <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: var(--space-2);">{{ $request->code }}</h1>
                    <p class="text-muted">{{ $request->title ?? 'Без названия' }}</p>
                </div>
                <div style="display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap;">
                    @if($externalRequest)
                        <x-button
                            href="{{ route('cabinet.my.requests.report', $request->id) }}"
                            variant="purple"
                            icon="bar-chart-2"
                        >
                            Отчет
                        </x-button>
                    @endif
                    <x-button
                        href="{{ route('cabinet.my.requests.questions', $request->id) }}"
                        variant="info"
                        icon="message-circle"
                    >
                        Вопросы
                    </x-button>
                    @php
                        $displayStatus = $externalRequest ? $externalRequest->status : $request->status;
                        $displayStatusLabel = $externalRequest
                            ? (\App\Models\ExternalRequest::getStatusLabels()[$displayStatus] ?? $displayStatus)
                            : (\App\Models\Request::statuses()[$displayStatus] ?? $displayStatus);

                        $statusVariant = match($displayStatus) {
                            'draft' => 'neutral',
                            'pending', 'queued_for_sending' => 'warning',
                            'sending', 'new', 'emails_sent' => 'info',
                            'collecting', 'active', 'responses_received' => 'primary',
                            'completed' => 'success',
                            'cancelled' => 'danger',
                            default => 'neutral'
                        };
                    @endphp
                    <x-badge :variant="$statusVariant" size="lg">
                        {{ $displayStatusLabel }}
                    </x-badge>
                </div>
            </div>

        @if($externalRequest)
            <div class="alert alert-success" style="margin-bottom: var(--space-4);">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                    <div>
                        <strong>Заявка отправлена поставщикам</strong>
                        <p style="margin: var(--space-1) 0 0 0;">Номер заявки в системе: {{ $externalRequest->request_number }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if($request->description)
        <div style="padding: var(--space-3); background: var(--neutral-50); border-radius: var(--radius-md); margin-bottom: var(--space-4);">
            <strong style="display: block; margin-bottom: var(--space-2);">Описание:</strong>
            <p style="color: var(--neutral-700); margin: 0;">{{ $request->description }}</p>
        </div>
        @endif

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-3);">
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Создана</div>
                    <div style="font-weight: 600;">{{ $request->created_at->format('d.m.Y H:i') }}</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Позиций</div>
                    <div style="font-weight: 600;">{{ $externalRequest ? $externalRequest->items->count() : $request->items_count }}</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Поставщиков</div>
                    <div style="font-weight: 600;">{{ $externalRequest ? $externalRequest->suppliers_count : $request->suppliers_count }}</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Предложений</div>
                    <div style="font-weight: 600;">{{ $externalRequest ? $externalRequest->offers_count : $request->offers_count }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Контактная информация -->
    <div class="card">
        <div class="card-body">
            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: var(--space-4);">Контактная информация</h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Организация</div>
                    <div style="font-weight: 500;">
                        @if($externalRequest && $externalRequest->clientOrganization)
                            {{ $externalRequest->clientOrganization->name }}
                        @else
                            {{ $request->company_name ?? '—' }}
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Контактное лицо</div>
                    <div style="font-weight: 500;">
                        {{ $externalRequest->contact_name ?? $request->contact_person ?? '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Email</div>
                    <div style="font-weight: 500;">
                        {{ $externalRequest->contact_email ?? '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Телефон</div>
                    <div style="font-weight: 500;">
                        {{ $externalRequest->contact_phone ?? $request->contact_phone ?? '—' }}
                    </div>
                </div>
            </div>

            @if(!$externalRequest && !$request->canBeSent())
                <div class="alert alert-warning" style="margin-top: var(--space-4);">
                    <strong>Заявка не готова к отправке</strong>
                    <ul style="margin-top: var(--space-2); padding-left: var(--space-5);">
                        @foreach($request->getMissingRequiredFields() as $field)
                            <li>{{ $field }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <!-- Позиции заявки -->
    <div class="card">
        <div class="card-body">
            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: var(--space-4);">Позиции заявки</h2>

            @php
                $itemsToDisplay = $externalRequest ? $externalRequest->items : $request->items;
            @endphp

            @if($itemsToDisplay->count() > 0)
            <div style="display: grid; gap: var(--space-3);">
                @foreach($itemsToDisplay as $item)
                <div style="padding: var(--space-4); background: var(--neutral-50); border-radius: var(--radius-md); border: 1px solid var(--neutral-200);">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-3); flex-wrap: wrap; gap: var(--space-2);">
                        <h3 style="font-weight: 600; font-size: 1.125rem; margin: 0;">{{ $item->name ?? $item->item_name }}</h3>
                        @if($externalRequest)
                            <x-badge variant="success">
                                <i data-lucide="check" style="width: 12px; height: 12px;"></i>
                                В работе
                            </x-badge>
                        @elseif(method_exists($item, 'isValid') && !$item->isValid())
                            <x-badge variant="danger">
                                Неполные данные
                            </x-badge>
                        @endif
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-3);">
                        @if($externalRequest)
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Артикул</div>
                                <div style="font-weight: 500; font-family: 'JetBrains Mono', monospace;">{{ $item->article ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Бренд</div>
                                <div style="font-weight: 500;">{{ $item->brand ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Количество</div>
                                <div style="font-weight: 500;">{{ $item->quantity ?? '—' }}</div>
                            </div>
                        @else
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Тип оборудования</div>
                                <div style="font-weight: 500;">
                                    {{ $item->equipment_type ? \App\Models\RequestItem::equipmentTypes()[$item->equipment_type] : '—' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Марка оборудования</div>
                                <div style="font-weight: 500;">{{ $item->equipment_brand ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Артикул производителя</div>
                                <div style="font-weight: 500; font-family: 'JetBrains Mono', monospace;">{{ $item->manufacturer_article ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Количество</div>
                                <div style="font-weight: 500;">{{ $item->quantity ?? '—' }}</div>
                            </div>
                        @endif
                    </div>

                    @if(!$externalRequest && method_exists($item, 'isValid') && !$item->isValid())
                        <div style="margin-top: var(--space-3); padding: var(--space-3); background: var(--red-50); border-radius: var(--radius-sm);">
                            <div style="color: var(--red-900); font-size: 0.875rem; font-weight: 600; margin-bottom: var(--space-1);">Не заполнены обязательные поля:</div>
                            <ul style="margin: 0; padding-left: var(--space-5); color: var(--red-900); font-size: 0.875rem;">
                                @foreach($item->getMissingRequiredFields() as $field)
                                    <li>{{ $field }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                @endforeach
            </div>
            @else
                <x-empty-state
                    icon="package"
                    title="Нет позиций"
                    description=""
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
