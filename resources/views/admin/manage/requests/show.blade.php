@extends('layouts.cabinet')

@section('title', 'Заявка ' . ($request['request_number'] ?? 'N/A'))
@section('header', 'Заявка ' . ($request['request_number'] ?? 'N/A'))

@push('styles')
<style>
    .container { max-width: 1200px; margin: 0 auto; }
    .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
    .card-header { padding: 1.25rem; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #111827; display: flex; justify-content: space-between; align-items: center; }
    .card-body { padding: 1.5rem; }
    .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
    .info-item { }
    .info-label { font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem; font-weight: 600; }
    .info-value { color: #111827; font-size: 0.9375rem; }
    .badge { padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 600; white-space: nowrap; display: inline-block; }
    .badge-draft { background: #f3f4f6; color: #6b7280; }
    .badge-new { background: #dbeafe; color: #1e40af; }
    .badge-active { background: #d1fae5; color: #065f46; }
    .badge-queued-for-sending { background: #fef3c7; color: #92400e; }
    .badge-emails-sent { background: #e0e7ff; color: #3730a3; }
    .badge-collecting { background: #ddd6fe; color: #5b21b6; }
    .badge-responses-received { background: #e0e7ff; color: #4338ca; }
    .badge-completed { background: #d1fae5; color: #065f46; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .items-table { width: 100%; border-collapse: collapse; }
    .items-table th, .items-table td { padding: 0.875rem; border: 1px solid #e5e7eb; text-align: left; font-size: 0.875rem; }
    .items-table th { background: #f9fafb; font-weight: 600; color: #6b7280; }
    .btn { padding: 0.625rem 1.25rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; font-size: 0.875rem; display: inline-block; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .btn-secondary { background: #6b7280; color: white; }
    .btn-secondary:hover { background: #4b5563; }
    .btn-danger { background: #ef4444; color: white; }
    .btn-danger:hover { background: #dc2626; }
    .actions { display: flex; gap: 1rem; flex-wrap: wrap; }
    .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; }
    .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
    .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
    .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }
</style>
@endpush

@section('content')
<div class="container">

    @if(session('success'))
    <div class="alert alert-success">
        <strong>{{ session('success') }}</strong>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-error">
        <strong>{{ session('error') }}</strong>
    </div>
    @endif

    <!-- Действия -->
    <div style="margin-bottom: 1.5rem;">
        <div class="actions">
            <a href="{{ route('admin.manage.requests.index') }}" class="btn btn-secondary">← Назад к списку</a>

            @php
                $externalRequest = \App\Models\ExternalRequest::where('request_number', $request['request_number'] ?? '')->first();
            @endphp

            @if($externalRequest)
                <a href="{{ route('admin.manage.requests.report', $request['id']) }}" class="btn btn-primary" style="background: #10b981;">
                    📊 Просмотреть отчет
                </a>
            @endif

            @if(in_array($request['status'] ?? '', ['draft', 'new']))
                <a href="{{ route('admin.manage.requests.edit', $request['id']) }}" class="btn btn-primary">Редактировать</a>

                <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены, что хотите отменить заявку?')) { document.getElementById('cancel-form').submit(); }">
                    Отменить заявку
                </button>

                <form id="cancel-form" action="{{ route('admin.manage.requests.cancel', $request['id']) }}" method="POST" style="display: none;">
                    @csrf
                    <input type="hidden" name="reason" value="Отменена администратором">
                </form>
            @endif
        </div>
    </div>

    <!-- Основная информация -->
    <div class="card">
        <div class="card-header">
            <span>Основная информация</span>
            <span class="badge badge-{{ $request['status'] ?? 'draft' }}">
                @switch($request['status'] ?? 'draft')
                    @case('draft') Черновик @break
                    @case('new') В очереди @break
                    @case('active') Активна @break
                    @case('queued_for_sending') В очереди на отправку @break
                    @case('emails_sent') Письма отправлены @break
                    @case('collecting') Сбор ответов @break
                    @case('responses_received') Ответы получены @break
                    @case('completed') Завершена @break
                    @case('cancelled') Отменена @break
                    @default {{ $request['status'] ?? '-' }}
                @endswitch
            </span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Номер заявки</div>
                    <div class="info-value">{{ $request['request_number'] ?? '-' }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Тип заявки</div>
                    <div class="info-value">
                        @if($request['is_customer_request'] ?? false)
                            <span class="badge" style="background: #dbeafe; color: #1e40af;">Именная</span>
                        @else
                            <span class="badge" style="background: #f3f4f6; color: #6b7280;">Анонимная</span>
                        @endif
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Дата создания</div>
                    <div class="info-value">{{ isset($request['created_at']) ? \Carbon\Carbon::parse($request['created_at'])->format('d.m.Y H:i') : '-' }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Последнее обновление</div>
                    <div class="info-value">{{ isset($request['updated_at']) ? \Carbon\Carbon::parse($request['updated_at'])->format('d.m.Y H:i') : '-' }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Заголовок</div>
                    <div class="info-value">{{ $request['title'] ?? '-' }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Всего позиций</div>
                    <div class="info-value">{{ $request['total_items'] ?? 0 }}</div>
                </div>
            </div>

            @if(!empty($request['notes']))
            <div style="margin-top: 1.5rem;">
                <div class="info-label">Заметки</div>
                <div class="info-value" style="white-space: pre-wrap;">{{ $request['notes'] }}</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Данные клиента -->
    @if($request['is_customer_request'] ?? false)
    <div class="card">
        <div class="card-header">Данные клиента</div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Компания</div>
                    <div class="info-value">{{ $request['customer_company'] ?? '-' }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Контактное лицо</div>
                    <div class="info-value">{{ $request['customer_contact_person'] ?? '-' }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value">{{ $request['customer_email'] ?? '-' }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Телефон</div>
                    <div class="info-value">{{ $request['customer_phone'] ?? '-' }}</div>
                </div>

                @if(!empty($request['client_organization_id']))
                <div class="info-item">
                    <div class="info-label">ID организации</div>
                    <div class="info-value">{{ $request['client_organization_id'] }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Позиции заявки -->
    <div class="card">
        <div class="card-header">
            <span>Позиции заявки ({{ count($request['items'] ?? []) }})</span>
        </div>
        <div class="card-body">
            @if(!empty($request['items']) && count($request['items']) > 0)
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Название</th>
                        <th style="width: 120px;">Бренд</th>
                        <th style="width: 120px;">Артикул</th>
                        <th style="width: 80px;">Кол-во</th>
                        <th style="width: 80px;">Ед. изм.</th>
                        <th style="width: 140px;">Категория</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($request['items'] as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item['name'] ?? '-' }}</td>
                        <td>{{ $item['brand'] ?? '-' }}</td>
                        <td>{{ $item['article'] ?? '-' }}</td>
                        <td>{{ $item['quantity'] ?? 1 }}</td>
                        <td>{{ $item['unit'] ?? 'шт' }}</td>
                        <td>{{ $item['category'] ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div style="text-align: center; padding: 2rem; color: #6b7280;">
                Нет позиций в заявке
            </div>
            @endif
        </div>
    </div>

    <!-- Информация о рассылке -->
    @if(!in_array($request['status'] ?? '', ['draft', 'new']))
    <div class="card">
        <div class="card-header">Информация о рассылке</div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>Статус:</strong> {{ $request['status'] ?? '-' }}<br>
                @if(in_array($request['status'] ?? '', ['active', 'queued_for_sending', 'emails_sent', 'collecting']))
                    Заявка находится в процессе обработки. Рассылка поставщикам выполняется автоматически каждые 60 минут.
                @elseif($request['status'] === 'completed')
                    Заявка завершена. Все ответы собраны.
                @elseif($request['status'] === 'cancelled')
                    Заявка отменена.
                @endif
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
