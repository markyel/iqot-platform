@extends('layouts.cabinet')

@section('title', 'Заявки пользователей')

@section('content')
<x-page-header
    title="Заявки пользователей"
    description="Управление заявками от клиентов системы"
/>
<div style="max-width: 1400px; margin: 0 auto;">

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

    <!-- Статистика -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-4); margin-bottom: var(--space-6);">
        <x-stat-card
            icon="file-text"
            :value="\App\Models\Request::where('is_customer_request', 1)->count()"
            label="Всего заявок"
        />
        <x-stat-card
            icon="clock"
            icon-type="warning"
            :value="\App\Models\Request::where('is_customer_request', 1)->where('status', 'pending')->where('synced_to_main_db', false)->count()"
            label="Ожидают модерации"
        />
        <x-stat-card
            icon="loader"
            icon-type="primary"
            :value="\App\Models\Request::where('is_customer_request', 1)->whereIn('status', ['sending', 'collecting'])->count()"
            label="В работе"
        />
        <x-stat-card
            icon="check-circle"
            icon-type="success"
            :value="\App\Models\Request::where('is_customer_request', 1)->where('status', 'completed')->count()"
            label="Завершено"
        />
    </div>

    <!-- Фильтры -->
    <div class="card" style="margin-bottom: var(--space-6);">
        <form method="GET" action="{{ route('admin.requests.index') }}" style="display: flex; gap: var(--space-4); flex-wrap: wrap;">
            <input type="text" name="search" class="input" placeholder="Поиск по номеру, названию, пользователю..."
                   value="{{ request('search') }}" style="flex: 1; min-width: 300px;">

            <select name="status" class="select" style="min-width: 200px;">
                <option value="">Все статусы</option>
                @foreach(\App\Models\Request::statuses() as $value => $label)
                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
                @endforeach
            </select>

            <select name="synced" class="select" style="min-width: 200px;">
                <option value="">Синхронизация</option>
                <option value="yes" {{ request('synced') === 'yes' ? 'selected' : '' }}>Отправлено в работу</option>
                <option value="no" {{ request('synced') === 'no' ? 'selected' : '' }}>Не отправлено</option>
            </select>

            <x-button variant="primary" type="submit" icon="filter">
                Применить
            </x-button>
            <x-button variant="secondary" :href="route('admin.requests.index')" icon="x">
                Сбросить
            </x-button>
        </form>
    </div>

    <!-- Таблица заявок -->
    <div class="card">
        @if($requests->count() > 0)
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 120px;">Номер</th>
                        <th>Название</th>
                        <th style="width: 180px;">Пользователь</th>
                        <th style="width: 100px; text-align: center;">
                            <i data-lucide="package" class="icon-sm"></i>
                            Позиций
                        </th>
                        <th style="width: 100px; text-align: center;">
                            <i data-lucide="message-circle" class="icon-sm"></i>
                            Вопросы
                        </th>
                        <th style="width: 120px;">Стоимость</th>
                        <th style="width: 120px;">Статус</th>
                        <th style="width: 100px; text-align: center;">Отправлено</th>
                        <th style="width: 140px;">Создана</th>
                        <th style="width: 100px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $request)
                    <tr>
                        <td style="font-family: monospace; font-weight: 600;">
                            {{ $request->request_number ?? $request->code }}
                        </td>
                        <td>
                            <div style="font-weight: 500;">{{ $request->title }}</div>
                        </td>
                        <td>
                            <div style="font-weight: 500;">{{ $request->user->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $request->user->email }}</div>
                        </td>
                        <td style="text-align: center; font-weight: 600;">
                            {{ $request->items_count }}
                        </td>
                        <td style="text-align: center;">
                            @php
                                $summary = is_array($request->questions_summary) ? (object)$request->questions_summary : $request->questions_summary;
                                $unanswered = $summary->unanswered ?? 0;
                            @endphp
                            @if($unanswered > 0)
                                <x-badge type="pending">{{ $unanswered }}</x-badge>
                            @else
                                <span style="color: var(--text-muted);">—</span>
                            @endif
                        </td>
                        <td style="font-weight: 600;">
                            @if($request->balanceHold)
                                {{ number_format($request->balanceHold->amount, 0) }} ₽
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <x-badge :type="$request->status">
                                {{ \App\Models\Request::statuses()[$request->status] ?? $request->status }}
                            </x-badge>
                        </td>
                        <td style="text-align: center;">
                            @if($request->synced_to_main_db)
                                <x-badge type="completed">
                                    <i data-lucide="check" class="icon-xs"></i>
                                    Да
                                </x-badge>
                            @else
                                <x-badge type="pending">Нет</x-badge>
                            @endif
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.875rem;">
                            {{ $request->created_at->format('d.m.Y H:i') }}
                        </td>
                        <td>
                            <x-button variant="primary" size="sm" :href="route('admin.requests.show', $request->id)" icon="arrow-right">
                                Открыть
                            </x-button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="padding: var(--space-4); border-top: 1px solid var(--border-color);">
            {{ $requests->links() }}
        </div>
        @else
        <x-empty-state
            icon="inbox"
            title="Заявок не найдено"
            description="Попробуйте изменить параметры фильтрации"
        />
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
