@extends('layouts.cabinet')

@section('title', 'Заявка ' . ($request->request_number ?? $request->code))

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
    </x-slot>
</x-page-header>

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">

    @if(session('success'))
    <div class="alert alert-success" style="margin-bottom: var(--space-4);">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-error" style="margin-bottom: var(--space-4);">
        {{ session('error') }}
    </div>
    @endif

    <!-- Заголовок заявки -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-6);">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: var(--space-2);">
                    {{ $request->title }}
                </h1>
                <p style="color: var(--text-muted); font-size: 0.875rem;">
                    <i data-lucide="calendar" class="icon-xs"></i>
                    Создана {{ $request->created_at->format('d.m.Y в H:i') }}
                </p>
            </div>
            <div style="display: flex; gap: var(--space-3); align-items: center;">
                <x-badge :type="$request->status">
                    {{ \App\Models\Request::statuses()[$request->status] ?? $request->status }}
                </x-badge>
                @if($request->synced_to_main_db)
                    <x-badge type="completed">
                        <i data-lucide="check" class="icon-xs"></i>
                        Отправлено в работу
                    </x-badge>
                @else
                    <x-badge type="pending">Не отправлено</x-badge>
                @endif
            </div>
        </div>

        <!-- Основная информация -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-4);">
            <div class="info-box">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Номер заявки
                </div>
                <div style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                    {{ $request->request_number ?? $request->code }}
                </div>
            </div>
            <div class="info-box">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    <i data-lucide="package" class="icon-xs"></i>
                    Позиций в заявке
                </div>
                <div style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                    {{ $request->items_count }}
                </div>
            </div>
            <div class="info-box">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    <i data-lucide="wallet" class="icon-xs"></i>
                    Стоимость
                </div>
                <div style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
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
        <div class="alert alert-info" style="margin-top: var(--space-4);">
            <div style="display: flex; align-items: start; gap: var(--space-3);">
                <i data-lucide="database" class="icon-md"></i>
                <div>
                    <div style="font-size: 0.75rem; font-weight: 600; margin-bottom: var(--space-1);">СИНХРОНИЗАЦИЯ</div>
                    <div style="font-size: 0.875rem;">
                        ID в основной БД: <strong>{{ $request->main_db_request_id }}</strong><br>
                        Дата синхронизации: {{ $request->synced_at->format('d.m.Y в H:i') }}
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($request->notes)
        <div class="alert alert-info" style="margin-top: var(--space-4);">
            <div style="display: flex; align-items: start; gap: var(--space-3);">
                <i data-lucide="file-text" class="icon-md"></i>
                <div>
                    <div style="font-size: 0.75rem; font-weight: 600; margin-bottom: var(--space-1);">ПРИМЕЧАНИЕ</div>
                    <div style="font-size: 0.875rem; white-space: pre-line;">{{ $request->notes }}</div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Информация о пользователе -->
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
            <i data-lucide="user" class="icon-md"></i>
            Информация о заказчике
        </h2>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-4);">
            <div class="info-box">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Пользователь
                </div>
                <div style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                    <a href="{{ route('admin.users.show', $request->user->id) }}" style="color: var(--primary); text-decoration: none;">
                        {{ $request->user->name }}
                    </a>
                </div>
            </div>
            <div class="info-box">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Email
                </div>
                <div style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                    {{ $request->user->email }}
                </div>
            </div>
            <div class="info-box">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Телефон
                </div>
                <div style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                    {{ $request->user->phone ?? $request->user->company_phone ?? '—' }}
                </div>
            </div>
            <div class="info-box">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Компания
                </div>
                <div style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                    {{ $request->user->company ?? $request->user->organization ?? '—' }}
                </div>
            </div>
            <div class="info-box">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Контактное лицо
                </div>
                <div style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                    {{ $request->user->contact_person ?? $request->user->full_name ?? '—' }}
                </div>
            </div>
            <div class="info-box">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: var(--space-1);">
                    Sender Email
                </div>
                <div style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                    @if($senderEmail ?? null)
                        <a href="mailto:{{ $senderEmail }}" style="color: var(--primary); text-decoration: none;">
                            {{ $senderEmail }}
                        </a>
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Позиции заявки -->
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
            <i data-lucide="list" class="icon-md"></i>
            Позиции заявки
        </h2>

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
                        <td style="color: var(--text-muted); font-weight: 600;">{{ $item->position_number }}</td>
                        <td>
                            <div style="font-weight: 500;">{{ $item->name }}</div>
                            @if($item->description)
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: var(--space-1);">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td>{{ $item->brand ?? '—' }}</td>
                        <td style="font-family: monospace; font-size: 0.875rem;">{{ $item->article ?? '—' }}</td>
                        <td style="text-align: center; font-weight: 600;">{{ $item->quantity }}</td>
                        <td style="color: var(--text-muted);">{{ $item->unit ?? 'шт.' }}</td>
                        <td>
                            <x-badge type="draft" size="sm">
                                {{ $item->category ?? 'Другое' }}
                            </x-badge>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <x-empty-state
            icon="package"
            title="Позиции не найдены"
            description="В этой заявке нет позиций"
        />
        @endif
    </div>

    <!-- Действия -->
    @if(!$request->synced_to_main_db && $request->status === \App\Models\Request::STATUS_PENDING)
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
            <i data-lucide="settings" class="icon-md"></i>
            Модерация
        </h2>
        <p style="color: var(--text-muted); margin-bottom: var(--space-4);">
            Эта заявка ожидает модерации. Вы можете отправить её в работу или отклонить.
        </p>
        <div style="display: flex; gap: var(--space-4);">
            <form method="POST" action="{{ route('admin.requests.approve', $request->id) }}" style="display: inline;">
                @csrf
                <x-button variant="success" type="submit" icon="check">
                    Отправить в работу
                </x-button>
            </form>
            <x-button variant="danger" type="button" icon="x" onclick="showRejectModal()">
                Отклонить заявку
            </x-button>
        </div>
    </div>
    @endif
</div>

<!-- Модальное окно отклонения -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <i data-lucide="alert-circle" class="icon-md"></i>
            Отклонение заявки
        </div>
        <form method="POST" action="{{ route('admin.requests.reject', $request->id) }}">
            @csrf
            <div class="modal-body">
                <label class="form-label">Причина отклонения:</label>
                <textarea name="reason" required class="input" rows="4"></textarea>
            </div>
            <div class="modal-footer">
                <x-button variant="secondary" type="button" onclick="hideRejectModal()" icon="x">
                    Отмена
                </x-button>
                <x-button variant="danger" type="submit" icon="check">
                    Отклонить
                </x-button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function showRejectModal() {
    document.getElementById('rejectModal').style.display = 'block';
    lucide.createIcons();
}

function hideRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

// Закрытие по клику вне модального окна
window.onclick = function(event) {
    const modal = document.getElementById('rejectModal');
    if (event.target === modal) {
        hideRejectModal();
    }
}

lucide.createIcons();
</script>
@endpush
