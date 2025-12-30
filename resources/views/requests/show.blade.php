@extends('layouts.cabinet')

@section('title', 'Заявка ' . ($request->request_number ?? $request->code))
@section('header', 'Заявка ' . ($request->request_number ?? $request->code))

@push('styles')
<style>
    .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; padding: 1.5rem; }
    .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-draft { background: #f3f4f6; color: #6b7280; }
    .badge-sending { background: #dbeafe; color: #1e40af; }
    .badge-collecting { background: #e0e7ff; color: #3730a3; }
    .badge-completed { background: #d1fae5; color: #065f46; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .btn { padding: 0.625rem 1.25rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-secondary { background: #6b7280; color: white; }
    .btn-secondary:hover { background: #4b5563; }
    .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    .info-item { padding: 0.75rem; background: #f9fafb; border-radius: 0.5rem; }
    .info-label { font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem; }
    .info-value { font-size: 0.875rem; color: #111827; font-weight: 500; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
    .table th { background: #f9fafb; font-weight: 600; color: #6b7280; font-size: 0.875rem; }
    .table tbody tr:hover { background: #f9fafb; }
</style>
@endpush

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <!-- Кнопка назад -->
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <a href="{{ route('cabinet.my.requests.index') }}" class="btn btn-secondary">← Назад к списку</a>

        @if($request->synced_to_main_db && $request->main_db_request_id)
        <a href="{{ route('cabinet.my.requests.report', $request->id) }}" class="btn" style="background: #10b981; color: white;">
            📊 Просмотреть отчет
        </a>
        @endif
    </div>

    <!-- Заголовок заявки -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1.5rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                    {{ $request->title }}
                </h1>
                <p style="color: #6b7280; font-size: 0.875rem;">
                    Создана {{ $request->created_at->format('d.m.Y в H:i') }}
                </p>
            </div>
            <div>
                @php
                    $statusClass = match($request->status) {
                        'draft' => 'badge-draft',
                        'pending' => 'badge-pending',
                        'sending' => 'badge-sending',
                        'collecting' => 'badge-collecting',
                        'completed' => 'badge-completed',
                        'cancelled' => 'badge-cancelled',
                        default => 'badge-draft'
                    };
                    $statusText = \App\Models\Request::statuses()[$request->status] ?? $request->status;
                @endphp
                <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
            </div>
        </div>

        <!-- Основная информация -->
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Номер заявки</div>
                <div class="info-value">{{ $request->request_number ?? $request->code }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Позиций в заявке</div>
                <div class="info-value">{{ $request->items_count }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Стоимость</div>
                <div class="info-value">
                    @if($request->balanceHold)
                        {{ number_format($request->balanceHold->amount, 2) }} ₽
                        @if($request->balanceHold->status === 'held')
                            <span style="color: #d97706; font-size: 0.75rem;">(заморожено)</span>
                        @elseif($request->balanceHold->status === 'charged')
                            <span style="color: #059669; font-size: 0.75rem;">(списано)</span>
                        @elseif($request->balanceHold->status === 'released')
                            <span style="color: #6b7280; font-size: 0.75rem;">(возвращено)</span>
                        @endif
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Отправка</div>
                <div class="info-value">
                    @if($request->synced_to_main_db)
                        <span style="color: #059669; font-weight: 600;">✓ Отправлено</span>
                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                            {{ $request->synced_at->format('d.m.Y H:i') }}
                        </div>
                    @else
                        <span style="color: #d97706;">Ожидает модерации</span>
                    @endif
                </div>
            </div>
        </div>

        @if($request->notes)
        <div style="margin-top: 1rem; padding: 0.75rem; background: #f0f9ff; border-left: 3px solid #3b82f6; border-radius: 0.5rem;">
            <div style="font-size: 0.75rem; color: #1e40af; font-weight: 600; margin-bottom: 0.25rem;">ПРИМЕЧАНИЕ</div>
            <div style="font-size: 0.875rem; color: #1e3a8a;">{{ $request->notes }}</div>
        </div>
        @endif
    </div>

    <!-- Позиции заявки -->
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Позиции заявки</h2>

        @if($request->items->count() > 0)
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 60px;">№</th>
                        <th>Название</th>
                        <th style="width: 150px;">Бренд</th>
                        <th style="width: 150px;">Артикул</th>
                        <th style="width: 100px; text-align: center;">Количество</th>
                        <th style="width: 80px;">Ед. изм.</th>
                        <th style="width: 120px;">Категория</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($request->items as $item)
                    <tr>
                        <td style="color: #6b7280; font-weight: 600;">{{ $item->position_number }}</td>
                        <td>
                            <div style="font-weight: 500;">{{ $item->name }}</div>
                            @if($item->description)
                            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td>{{ $item->brand ?? '—' }}</td>
                        <td style="font-family: monospace; font-size: 0.875rem;">{{ $item->article ?? '—' }}</td>
                        <td style="text-align: center; font-weight: 600;">{{ $item->quantity }}</td>
                        <td style="color: #6b7280;">{{ $item->unit ?? 'шт.' }}</td>
                        <td>
                            <span style="padding: 0.25rem 0.5rem; background: #f3f4f6; border-radius: 0.375rem; font-size: 0.75rem;">
                                {{ $item->category ?? 'Другое' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p style="text-align: center; color: #6b7280; padding: 2rem;">Позиции не найдены</p>
        @endif
    </div>

    <!-- Информация о балансе -->
    @if($request->balanceHold)
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Информация об оплате</h2>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Сумма</div>
                <div class="info-value">{{ number_format($request->balanceHold->amount, 2) }} ₽</div>
            </div>
            <div class="info-item">
                <div class="info-label">Статус платежа</div>
                <div class="info-value">
                    @if($request->balanceHold->status === 'held')
                        <span style="color: #d97706;">Средства заморожены</span>
                    @elseif($request->balanceHold->status === 'charged')
                        <span style="color: #059669;">Средства списаны</span>
                    @elseif($request->balanceHold->status === 'released')
                        <span style="color: #6b7280;">Средства возвращены</span>
                    @endif
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Дата заморозки</div>
                <div class="info-value">{{ $request->balanceHold->created_at->format('d.m.Y H:i') }}</div>
            </div>
            @if($request->balanceHold->released_at || $request->balanceHold->charged_at)
            <div class="info-item">
                <div class="info-label">Дата {{ $request->balanceHold->status === 'charged' ? 'списания' : 'возврата' }}</div>
                <div class="info-value">
                    {{ ($request->balanceHold->charged_at ?? $request->balanceHold->released_at)->format('d.m.Y H:i') }}
                </div>
            </div>
            @endif
        </div>

        @if($request->balanceHold->description)
        <div style="margin-top: 1rem; padding: 0.75rem; background: #f9fafb; border-radius: 0.5rem;">
            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; margin-bottom: 0.25rem;">ОПИСАНИЕ</div>
            <div style="font-size: 0.875rem; color: #111827;">{{ $request->balanceHold->description }}</div>
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
