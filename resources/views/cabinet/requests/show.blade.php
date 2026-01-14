@extends('layouts.cabinet')

@section('title', 'Заявка ' . $request->code)
@section('header', 'Заявка ' . $request->code)

@section('content')
<div style="margin-bottom: 1.5rem;">
    <a href="{{ route('cabinet.requests') }}" style="color: #6b7280; text-decoration: none;">
        ← Назад к списку
    </a>
</div>

<div style="display: grid; gap: 1.5rem;">
    <!-- Основная информация -->
    <div style="background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1.5rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">{{ $request->code }}</h1>
                <p style="color: #6b7280;">{{ $request->title ?? 'Без названия' }}</p>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                @if($externalRequest)
                    <a href="{{ route('cabinet.my.requests.report', $request->id) }}" class="btn" style="background: #8b5cf6; color: white; text-decoration: none; padding: 0.625rem 1.25rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                        📊 Отчет
                    </a>
                @endif
                <a href="{{ route('cabinet.my.requests.questions', $request->id) }}" class="btn" style="background: #3b82f6; color: white; text-decoration: none; padding: 0.625rem 1.25rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    💬 Вопросы
                </a>
                @php
                    $statusColors = [
                        'draft' => 'background: #f3f4f6; color: #374151;',
                        'pending' => 'background: #fef3c7; color: #92400e;',
                        'sending' => 'background: #dbeafe; color: #1e40af;',
                        'collecting' => 'background: #e0e7ff; color: #3730a3;',
                        'completed' => 'background: #d1fae5; color: #065f46;',
                        'cancelled' => 'background: #fee2e2; color: #991b1b;',
                    ];

                    // Используем статус из ExternalRequest, если заявка синхронизирована
                    $displayStatus = $externalRequest ? $externalRequest->status : $request->status;
                    $displayStatusLabel = $externalRequest
                        ? (\App\Models\ExternalRequest::getStatusLabels()[$displayStatus] ?? $displayStatus)
                        : (\App\Models\Request::statuses()[$displayStatus] ?? $displayStatus);
                @endphp
                <span style="display: inline-block; padding: 0.5rem 1rem; border-radius: 9999px; font-weight: 600; {{ $statusColors[$displayStatus] ?? '' }}">
                    {{ $displayStatusLabel }}
                </span>
            </div>
        </div>

        @if($externalRequest)
            <div style="padding: 1rem; background: #d1fae5; border-left: 4px solid #10b981; border-radius: 0.5rem; margin-bottom: 1.5rem;">
                <strong style="color: #065f46;">✓ Заявка отправлена поставщикам</strong>
                <p style="margin: 0.5rem 0 0 0; color: #065f46;">Номер заявки в системе: {{ $externalRequest->request_number }}</p>
            </div>
        @endif

        @if($request->description)
        <div style="padding: 1rem; background: #f9fafb; border-radius: 0.5rem; margin-bottom: 1.5rem;">
            <strong style="display: block; margin-bottom: 0.5rem;">Описание:</strong>
            <p style="color: #374151;">{{ $request->description }}</p>
        </div>
        @endif

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
            <div>
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Создана</div>
                <div style="font-weight: 600;">{{ $request->created_at->format('d.m.Y H:i') }}</div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Позиций</div>
                <div style="font-weight: 600;">{{ $externalRequest ? $externalRequest->items->count() : $request->items_count }}</div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Поставщиков</div>
                <div style="font-weight: 600;">{{ $externalRequest ? $externalRequest->suppliers_count : $request->suppliers_count }}</div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Предложений</div>
                <div style="font-weight: 600;">{{ $externalRequest ? $externalRequest->offers_count : $request->offers_count }}</div>
            </div>
        </div>
    </div>

    <!-- Контактная информация -->
    <div style="background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Контактная информация</h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
            <div>
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Организация</div>
                <div style="font-weight: 500;">
                    @if($externalRequest && $externalRequest->clientOrganization)
                        {{ $externalRequest->clientOrganization->name }}
                    @else
                        {{ $request->company_name ?? '—' }}
                    @endif
                </div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Контактное лицо</div>
                <div style="font-weight: 500;">
                    {{ $externalRequest->contact_name ?? $request->contact_person ?? '—' }}
                </div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Email</div>
                <div style="font-weight: 500;">
                    {{ $externalRequest->contact_email ?? '—' }}
                </div>
            </div>
            <div>
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Телефон</div>
                <div style="font-weight: 500;">
                    {{ $externalRequest->contact_phone ?? $request->contact_phone ?? '—' }}
                </div>
            </div>
        </div>

        @if(!$externalRequest && !$request->canBeSent())
            <div style="margin-top: 1.5rem; padding: 1rem; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 0.375rem;">
                <strong style="color: #92400e;">Заявка не готова к отправке</strong>
                <ul style="margin-top: 0.5rem; padding-left: 1.25rem; color: #92400e;">
                    @foreach($request->getMissingRequiredFields() as $field)
                        <li>{{ $field }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <!-- Позиции заявки -->
    <div style="background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Позиции заявки</h2>

        @php
            $itemsToDisplay = $externalRequest ? $externalRequest->items : $request->items;
        @endphp

        @if($itemsToDisplay->count() > 0)
            <div style="display: grid; gap: 1rem;">
                @foreach($itemsToDisplay as $item)
                <div style="padding: 1.5rem; background: #f9fafb; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <h3 style="font-weight: 600; font-size: 1.125rem;">{{ $item->name ?? $item->item_name }}</h3>
                        @if($externalRequest)
                            <span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                                ✓ В работе
                            </span>
                        @elseif(method_exists($item, 'isValid') && !$item->isValid())
                            <span style="background: #fee2e2; color: #991b1b; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                                Неполные данные
                            </span>
                        @endif
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        @if($externalRequest)
                            <div>
                                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Артикул</div>
                                <div style="font-weight: 500; font-family: 'JetBrains Mono', monospace;">{{ $item->article ?? '—' }}</div>
                            </div>
                            <div>
                                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Бренд</div>
                                <div style="font-weight: 500;">{{ $item->brand ?? '—' }}</div>
                            </div>
                            <div>
                                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Количество</div>
                                <div style="font-weight: 500;">{{ $item->quantity ?? '—' }}</div>
                            </div>
                        @else
                            <div>
                                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Тип оборудования</div>
                                <div style="font-weight: 500;">
                                    {{ $item->equipment_type ? \App\Models\RequestItem::equipmentTypes()[$item->equipment_type] : '—' }}
                                </div>
                            </div>
                            <div>
                                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Марка оборудования</div>
                                <div style="font-weight: 500;">{{ $item->equipment_brand ?? '—' }}</div>
                            </div>
                            <div>
                                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Артикул производителя</div>
                                <div style="font-weight: 500; font-family: 'JetBrains Mono', monospace;">{{ $item->manufacturer_article ?? '—' }}</div>
                            </div>
                            <div>
                                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Количество</div>
                                <div style="font-weight: 500;">{{ $item->quantity ?? '—' }}</div>
                            </div>
                        @endif
                    </div>

                    @if(!$externalRequest && method_exists($item, 'isValid') && !$item->isValid())
                        <div style="margin-top: 1rem; padding: 0.75rem; background: #fee2e2; border-radius: 0.375rem;">
                            <div style="color: #991b1b; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">Не заполнены обязательные поля:</div>
                            <ul style="margin: 0; padding-left: 1.25rem; color: #991b1b; font-size: 0.875rem;">
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
            <div style="text-align: center; padding: 2rem; color: #6b7280;">
                Нет позиций
            </div>
        @endif
    </div>
</div>
@endsection
