@extends('layouts.cabinet')

@section('title', 'Заявка ' . ($request['request_number'] ?? 'N/A'))

@push('styles')
<style>
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: var(--space-6);
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    .info-label {
        font-size: var(--text-xs);
        text-transform: uppercase;
        color: var(--neutral-500);
        font-weight: 600;
        letter-spacing: 0.05em;
    }

    .info-value {
        color: var(--neutral-900);
        font-size: var(--text-base);
        font-weight: 500;
    }
</style>
@endpush

@section('content')
<x-page-header
    title="Заявка {{ $request['request_number'] ?? 'N/A' }}"
    description="Детальная информация о заявке"
>
    <x-slot:actions>
        <a href="{{ route('admin.manage.requests.index') }}" class="btn btn-secondary btn-md">
            <i data-lucide="arrow-left" class="icon-sm"></i>
            Назад к списку
        </a>

        @php
            $externalRequest = \App\Models\ExternalRequest::where('request_number', $request['request_number'] ?? '')->first();
        @endphp

        @if($externalRequest)
            <a href="{{ route('admin.manage.requests.report', $request['id']) }}" class="btn btn-accent btn-md">
                <i data-lucide="bar-chart-3" class="icon-sm"></i>
                Просмотреть отчет
            </a>
        @endif

        @if(in_array($request['status'] ?? '', ['draft', 'new']))
            <a href="{{ route('admin.manage.requests.edit', $request['id']) }}" class="btn btn-primary btn-md">
                <i data-lucide="edit" class="icon-sm"></i>
                Редактировать
            </a>

            <button type="button" class="btn btn-danger btn-md" onclick="if(confirm('Вы уверены, что хотите отменить заявку?')) { document.getElementById('cancel-form').submit(); }">
                <i data-lucide="x-circle" class="icon-sm"></i>
                Отменить заявку
            </button>

            <form id="cancel-form" action="{{ route('admin.manage.requests.cancel', $request['id']) }}" method="POST" style="display: none;">
                @csrf
                <input type="hidden" name="reason" value="Отменена администратором">
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom: var(--space-6);">
    <i data-lucide="check-circle" class="icon-sm"></i>
    <strong>{{ session('success') }}</strong>
</div>
@endif

@if(session('error'))
<div class="alert alert-error" style="margin-bottom: var(--space-6);">
    <i data-lucide="alert-circle" class="icon-sm"></i>
    <strong>{{ session('error') }}</strong>
</div>
@endif

<!-- Основная информация -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
        <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Основная информация</h3>
        <x-badge :type="$request['status'] ?? 'draft'">
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
        </x-badge>
    </div>
    <div class="card-body">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Номер заявки</div>
                <div class="info-value">
                    <span class="text-code">{{ $request['request_number'] ?? '-' }}</span>
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Тип заявки</div>
                <div class="info-value">
                    @if($request['is_customer_request'] ?? false)
                        <x-badge type="primary">Именная</x-badge>
                    @else
                        <x-badge type="default">Анонимная</x-badge>
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
        <div style="margin-top: var(--space-6); padding-top: var(--space-6); border-top: 1px solid var(--neutral-200);">
            <div class="info-label">Заметки</div>
            <div class="info-value" style="white-space: pre-wrap; margin-top: var(--space-2);">{{ $request['notes'] }}</div>
        </div>
        @endif
    </div>
</div>

<!-- Данные клиента -->
@if($request['is_customer_request'] ?? false)
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">
            <i data-lucide="user" class="icon-sm" style="display: inline-block; vertical-align: middle; margin-right: var(--space-2);"></i>
            Данные клиента
        </h3>
    </div>
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
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">
            <i data-lucide="package" class="icon-sm" style="display: inline-block; vertical-align: middle; margin-right: var(--space-2);"></i>
            Позиции заявки ({{ count($request['items'] ?? []) }})
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        @if(!empty($request['items']) && count($request['items']) > 0)
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Название</th>
                        <th style="width: 150px;">Бренд</th>
                        <th style="width: 150px;">Артикул</th>
                        <th style="width: 100px;">Кол-во</th>
                        <th style="width: 100px;">Ед. изм.</th>
                        <th style="width: 180px;">Категория</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($request['items'] as $index => $item)
                    <tr>
                        <td data-label="#">{{ $index + 1 }}</td>
                        <td data-label="Название">{{ $item['name'] ?? '-' }}</td>
                        <td data-label="Бренд">{{ $item['brand'] ?? '-' }}</td>
                        <td data-label="Артикул"><span class="text-code">{{ $item['article'] ?? '-' }}</span></td>
                        <td data-label="Кол-во">{{ $item['quantity'] ?? 1 }}</td>
                        <td data-label="Ед. изм.">{{ $item['unit'] ?? 'шт' }}</td>
                        <td data-label="Категория">{{ $item['category'] ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <x-empty-state
            icon="package"
            title="Нет позиций"
            description="В заявке пока нет ни одной позиции"
        />
        @endif
    </div>
</div>

<!-- Информация о рассылке -->
@if(!in_array($request['status'] ?? '', ['draft', 'new']))
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">
            <i data-lucide="send" class="icon-sm" style="display: inline-block; vertical-align: middle; margin-right: var(--space-2);"></i>
            Информация о рассылке
        </h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i data-lucide="info" class="icon-sm"></i>
            <div>
                <strong>Статус:</strong>
                @switch($request['status'] ?? '')
                    @case('active') Активна @break
                    @case('queued_for_sending') В очереди на отправку @break
                    @case('emails_sent') Письма отправлены @break
                    @case('collecting') Сбор ответов @break
                    @case('responses_received') Ответы получены @break
                    @case('completed') Завершена @break
                    @case('cancelled') Отменена @break
                    @default {{ $request['status'] ?? '-' }}
                @endswitch
                <br><br>
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
</div>
@endif

@push('scripts')
<script>
// Reinitialize Lucide icons
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush
@endsection
