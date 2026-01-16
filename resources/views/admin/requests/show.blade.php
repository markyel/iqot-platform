@extends('layouts.cabinet')

@section('title', 'Заявка ' . ($request->request_number ?? $request->code))

@section('content')
<x-page-header
    :title="'Заявка ' . ($request->request_number ?? $request->code)"
    :breadcrumbs="[
        ['label' => 'Заявки', 'url' => route('admin.requests.index')],
        ['label' => $request->request_number ?? $request->code]
    ]"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('admin.requests.index')" icon="arrow-left">
            Назад к списку
        </x-button>

        @if(!$request->synced_to_main_db)
            <x-button variant="primary" :href="route('admin.requests.edit', $request->id)" icon="edit">
                Редактировать
            </x-button>
        @endif
    </x-slot>
</x-page-header>

@if(session('success'))
<div class="alert alert-success">
    <i data-lucide="check-circle" class="alert-icon"></i>
    <div class="alert-content">
        {{ session('success') }}
    </div>
</div>
@endif

@if(session('error'))
<div class="alert alert-error">
    <i data-lucide="x-circle" class="alert-icon"></i>
    <div class="alert-content">
        {{ session('error') }}
    </div>
</div>
@endif

<!-- Основная информация -->
<div class="card">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
            <h2 style="margin: 0;">{{ $request->title }}</h2>
            <div style="display: flex; gap: var(--space-3); align-items: center;">
                <x-badge :type="$request->status">
                    {{ \App\Models\Request::statuses()[$request->status] ?? $request->status }}
                </x-badge>
                @if($request->synced_to_main_db)
                    <x-badge type="completed">
                        <i data-lucide="check" style="width: 0.875rem; height: 0.875rem;"></i>
                        Отправлено в работу
                    </x-badge>
                @else
                    <x-badge type="pending">Не отправлено</x-badge>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Номер заявки
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    {{ $request->request_number ?? $request->code }}
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Создана
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    {{ $request->created_at->format('d.m.Y в H:i') }}
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Позиций
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    {{ $request->items_count }}
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Стоимость
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    @if($request->balanceHold)
                        {{ number_format($request->balanceHold->amount, 2) }} ₽
                        @if($request->balanceHold->status === 'held')
                            <x-badge type="pending" size="sm">заморожено</x-badge>
                        @elseif($request->balanceHold->status === 'charged')
                            <x-badge type="completed" size="sm">списано</x-badge>
                        @elseif($request->balanceHold->status === 'released')
                            <x-badge type="draft" size="sm">возвращено</x-badge>
                        @endif
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>

        @if($request->synced_to_main_db)
        <div class="alert alert-info">
            <i data-lucide="database" class="alert-icon"></i>
            <div class="alert-content">
                <strong>Синхронизация:</strong> ID в основной БД: <strong>{{ $request->main_db_request_id }}</strong><br>
                Дата синхронизации: {{ $request->synced_at->format('d.m.Y в H:i') }}
            </div>
        </div>
        @endif

        @if($request->notes)
        <div class="alert alert-info">
            <i data-lucide="file-text" class="alert-icon"></i>
            <div class="alert-content">
                <strong>Примечание:</strong>
                <div style="white-space: pre-line; margin-top: var(--space-2);">{{ $request->notes }}</div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Информация о заказчике -->
<div class="card">
    <div class="card-header">
        <i data-lucide="user" style="width: 1.25rem; height: 1.25rem;"></i>
        Информация о заказчике
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4);">
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Пользователь
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    <a href="{{ route('admin.users.show', $request->user->id) }}" style="color: var(--primary-600); text-decoration: none;">
                        {{ $request->user->name }}
                    </a>
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Email
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    {{ $request->user->email }}
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Телефон
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    {{ $request->user->phone ?? $request->user->company_phone ?? '—' }}
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Компания
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    {{ $request->user->company ?? $request->user->organization ?? '—' }}
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Контактное лицо
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    {{ $request->user->contact_person ?? $request->user->full_name ?? '—' }}
                </div>
            </div>
            <div>
                <div style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Sender Email
                </div>
                <div style="font-size: var(--text-base); font-weight: 500;">
                    @if($senderEmail ?? null)
                        <a href="mailto:{{ $senderEmail }}" style="color: var(--primary-600); text-decoration: none;">
                            {{ $senderEmail }}
                        </a>
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Позиции заявки -->
<div class="card">
    <div class="card-header">
        <i data-lucide="list" style="width: 1.25rem; height: 1.25rem;"></i>
        Позиции заявки
    </div>
    @if($request->items->count() > 0)
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 60px;">№</th>
                        <th>Название</th>
                        <th>Бренд</th>
                        <th>Артикул</th>
                        <th style="width: 100px;">Количество</th>
                        <th style="width: 100px;">Ед. изм.</th>
                        <th>Категория</th>
                        <th>Тип оборудования</th>
                        <th>Область применения</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($request->items as $item)
                    <tr>
                        <td data-label="№">{{ $item->position_number }}</td>
                        <td data-label="Название">
                            <div style="font-weight: 500;">{{ $item->name }}</div>
                            @if($item->description)
                            <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-top: var(--space-1);">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td data-label="Бренд">{{ $item->brand ?? '—' }}</td>
                        <td data-label="Артикул" style="font-family: var(--font-mono); font-size: var(--text-sm);">{{ $item->article ?? '—' }}</td>
                        <td data-label="Количество" style="font-weight: 600;">{{ $item->quantity }}</td>
                        <td data-label="Ед. изм.">{{ $item->unit ?? 'шт.' }}</td>
                        <td data-label="Категория">
                            @if($item->category)
                                <x-badge type="draft" size="sm">{{ $item->category }}</x-badge>
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Тип оборудования">
                            @if($item->product_type_id)
                                {{ \App\Models\ProductType::find($item->product_type_id)?->name ?? '—' }}
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Область применения">
                            @if($item->domain_id)
                                {{ \App\Models\ApplicationDomain::find($item->domain_id)?->name ?? '—' }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="card-body">
        <x-empty-state
            icon="package"
            title="Позиции не найдены"
            description="В этой заявке нет позиций"
        />
    </div>
    @endif
</div>

<!-- Действия модерации -->
@if(!$request->synced_to_main_db && $request->status === \App\Models\Request::STATUS_PENDING)
<div class="card">
    <div class="card-header">
        <i data-lucide="settings" style="width: 1.25rem; height: 1.25rem;"></i>
        Модерация
    </div>
    <div class="card-body">
        <p style="color: var(--neutral-600); margin-bottom: var(--space-4);">
            Эта заявка ожидает модерации. Вы можете отправить её в работу или отклонить.
        </p>
        <div style="display: flex; gap: var(--space-4); flex-wrap: wrap;">
            <form method="POST" action="{{ route('admin.requests.approve', $request->id) }}" style="display: inline;">
                @csrf
                <x-button variant="success" type="submit" icon="check">
                    Отправить в работу
                </x-button>
            </form>
            <button type="button" class="btn btn-danger btn-md" onclick="showRejectModal()">
                <i data-lucide="x" class="icon-sm"></i>
                Отклонить заявку
            </button>
        </div>
    </div>
</div>
@endif

<!-- Модальное окно отклонения -->
<div id="rejectModal" class="modal-overlay" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-container">
            <!-- Заголовок модального окна -->
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <div style="width: 40px; height: 40px; border-radius: var(--radius-lg); background: var(--danger-100); display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="alert-circle" style="width: 1.25rem; height: 1.25rem; color: var(--danger-600);"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Отклонение заявки</h3>
                        <p style="margin: 0; font-size: var(--text-sm); color: var(--neutral-600);">Укажите причину отклонения</p>
                    </div>
                </div>
                <button type="button" onclick="hideRejectModal()" style="background: none; border: none; cursor: pointer; padding: var(--space-2); margin: calc(var(--space-2) * -1); color: var(--neutral-600); display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="x" style="width: 1.25rem; height: 1.25rem;"></i>
                </button>
            </div>

            <!-- Тело модального окна -->
            <form method="POST" action="{{ route('admin.requests.reject', $request->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="reject-reason" class="form-label">
                            <span style="color: var(--danger-600);">*</span> Причина отклонения
                        </label>
                        <textarea
                            id="reject-reason"
                            name="reason"
                            required
                            class="input"
                            rows="5"
                            placeholder="Опишите причину, по которой заявка отклоняется..."
                            style="resize: vertical; min-height: 120px;"
                        ></textarea>
                        <p class="form-hint">Эта информация будет отправлена пользователю</p>
                    </div>
                </div>

                <!-- Футер модального окна -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-md" onclick="hideRejectModal()">
                        <i data-lucide="x" class="icon-sm"></i>
                        Отмена
                    </button>
                    <button type="submit" class="btn btn-danger btn-md">
                        <i data-lucide="x-circle" class="icon-sm"></i>
                        Отклонить заявку
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(2px);
    z-index: 9999;
    overflow-y: auto;
}

.modal-dialog {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-6);
}

.modal-container {
    background: var(--neutral-0);
    border-radius: var(--radius-xl);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 540px;
    width: 100%;
    position: relative;
    animation: modalSlideIn 0.2s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: var(--space-6);
    border-bottom: 1px solid var(--neutral-200);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

.modal-body {
    padding: var(--space-6);
}

.modal-footer {
    padding: var(--space-6);
    border-top: 1px solid var(--neutral-200);
    display: flex;
    justify-content: flex-end;
    gap: var(--space-3);
    background: var(--neutral-50);
    border-bottom-left-radius: var(--radius-xl);
    border-bottom-right-radius: var(--radius-xl);
}
</style>
@endpush

@push('scripts')
<script>
function showRejectModal() {
    document.getElementById('rejectModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }, 100);
}

function hideRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('reject-reason').value = '';
}

// Закрытие по клику на overlay
document.addEventListener('click', function(event) {
    const modal = document.getElementById('rejectModal');
    if (event.target === modal) {
        hideRejectModal();
    }
});

// Закрытие по Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('rejectModal');
        if (modal && modal.style.display === 'block') {
            hideRejectModal();
        }
    }
});

if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush
