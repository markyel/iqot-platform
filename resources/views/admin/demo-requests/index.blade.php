@extends('layouts.cabinet')

@section('title', 'Заявки на демонстрацию')

@section('content')
<div style="max-width: 1400px; margin: 0 auto;">
    <x-page-header
        title="Заявки на демонстрацию"
        description="Управление входящими заявками на демонстрацию сервиса"
    />

    @if(session('success'))
        <div class="alert alert-success">
            <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
            {{ session('error') }}
        </div>
    @endif

    <!-- Фильтры -->
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-body">
            <form method="GET" style="display: flex; gap: var(--space-4); align-items: center;">
                <select name="status" class="select">
                    <option value="">Все статусы</option>
                    <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>Новые</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>В обработке</option>
                    <option value="contacted" {{ request('status') === 'contacted' ? 'selected' : '' }}>Связались</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Завершено</option>
                </select>
                <x-button type="submit">Применить</x-button>
            </form>
        </div>
    </div>

    <!-- Список заявок -->
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>ФИО / Организация</th>
                <th>Контакты</th>
                <th>Статус</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $request)
            <tr>
                <td data-label="ID" style="color: var(--neutral-900); font-family: monospace; font-weight: 600;">#{{ $request->id }}</td>
                <td data-label="ФИО / Организация">
                    <div style="color: var(--neutral-900); font-weight: 600; margin-bottom: var(--space-1);">{{ $request->full_name }}</div>
                    <div style="color: var(--neutral-600); font-size: 0.875rem;">{{ $request->organization }}</div>
                    <div style="color: var(--neutral-500); font-size: 0.75rem;">ИНН: {{ $request->inn }}</div>
                </td>
                <td data-label="Контакты">
                    <div style="color: var(--neutral-700); font-size: 0.875rem;">{{ $request->email }}</div>
                    <div style="color: var(--neutral-600); font-size: 0.875rem;">{{ $request->phone }}</div>
                </td>
                <td data-label="Статус">
                    @php
                        $statusLabel = match($request->status) {
                            'new' => 'Новая',
                            'processing' => 'В обработке',
                            'contacted' => 'Связались',
                            'completed' => 'Завершено',
                            default => $request->status
                        };
                        $statusVariant = match($request->status) {
                            'new' => 'info',
                            'processing' => 'warning',
                            'contacted' => 'success',
                            'completed' => 'neutral',
                            default => 'neutral'
                        };
                    @endphp
                    <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                </td>
                <td data-label="Дата" style="color: var(--neutral-600); font-size: 0.875rem;">
                    {{ $request->created_at->format('d.m.Y H:i') }}
                </td>
                <td data-label="Действия">
                    <x-button size="sm" href="{{ route('admin.demo-requests.show', $request) }}">
                        Подробнее
                    </x-button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: var(--space-12);">
                    <x-empty-state
                        icon="inbox"
                        title="Заявок не найдено"
                        description="Новых заявок на демонстрацию пока нет"
                    />
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Пагинация -->
    @if($requests->hasPages())
        <div style="margin-top: var(--space-6);">
            {{ $requests->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
@endsection
