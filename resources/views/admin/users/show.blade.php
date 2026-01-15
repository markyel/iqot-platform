@extends('layouts.cabinet')

@section('title', 'Пользователь: ' . $user->name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">

    @if(session('success'))
    <div class="alert alert-success">
        <i data-lucide="check-circle"></i>
        {{ session('success') }}
    </div>
    @endif

    <!-- Кнопка назад -->
    <div style="margin-bottom: 1.5rem;">
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
            <i data-lucide="arrow-left"></i>
            Назад к списку
        </a>
    </div>

    <!-- Основная информация -->
    <div class="card">
        <div class="card-header">
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">{{ $user->name }}</h1>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <x-stat-card label="Email" :value="$user->email" />
                <x-stat-card label="Баланс" value="{{ number_format($user->balance, 2) }} ₽" />
                <x-stat-card label="Доступный баланс" value="{{ number_format($user->available_balance, 2) }} ₽" />
                <x-stat-card label="Компания" :value="$user->company_name ?? '—'" />
                <x-stat-card label="Телефон" :value="$user->phone ?? $user->company_phone ?? '—'" />
                <x-stat-card label="Контактное лицо" :value="$user->contact_person ?? '—'" />
                <x-stat-card label="Sender ID" :value="$user->sender_id ?? '—'" />
                <x-stat-card label="Organization ID" :value="$user->client_organization_id ?? '—'" />
                <x-stat-card label="Зарегистрирован" :value="$user->created_at->format('d.m.Y H:i')" />
                <x-stat-card label="Роль">
                    <x-slot name="value">
                        @if($user->is_admin)
                            <x-badge variant="info">Администратор</x-badge>
                        @else
                            <x-badge variant="secondary">Пользователь</x-badge>
                        @endif
                    </x-slot>
                </x-stat-card>
            </div>

            @if($user->sender_id)
            <div style="margin-top: 1rem;">
                <a href="{{ route('admin.users.sender.show', $user) }}" class="btn btn-primary">
                    <i data-lucide="mail"></i>
                    Управление Sender
                </a>
            </div>
            @endif
        </div>
    </div>

    <!-- Статистика заявок -->
    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">Статистика</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <x-stat-card label="Всего заявок" :value="$user->requests->count()" />
                <x-stat-card label="Покупок" :value="$user->purchases_count" />
                <x-stat-card label="Заморожено средств" value="{{ number_format($user->balanceHolds()->where('status', 'held')->sum('amount'), 2) }} ₽" />
                <x-stat-card label="Потрачено" value="{{ number_format($user->purchases_sum, 2) }} ₽" />
            </div>
        </div>
    </div>

    <!-- Последние заявки -->
    @if($user->requests->count() > 0)
    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">Последние заявки</h2>
        </div>
        <div class="card-body">
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Номер</th>
                            <th>Название</th>
                            <th>Статус</th>
                            <th>Дата</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($user->requests->take(10) as $request)
                        <tr>
                            <td data-label="Номер" style="font-family: var(--font-mono);">{{ $request->request_number }}</td>
                            <td data-label="Название">{{ $request->title }}</td>
                            <td data-label="Статус">{{ $request->status }}</td>
                            <td data-label="Дата" style="color: var(--text-secondary); font-size: 0.875rem;">
                                {{ $request->created_at->format('d.m.Y H:i') }}
                            </td>
                            <td data-label="">
                                <a href="{{ route('admin.requests.show', $request->id) }}" class="btn btn-sm btn-primary">
                                    Открыть
                                    <i data-lucide="arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>
@endpush
@endsection
