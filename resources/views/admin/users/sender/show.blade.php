@extends('layouts.cabinet')

@section('title', 'Отправитель пользователя')

@section('content')
<x-page-header
    title="Отправитель пользователя: {{ $user->name }}"
    :breadcrumbs="[
        ['label' => 'Пользователи', 'url' => route('admin.users.index')],
        ['label' => $user->name],
        ['label' => 'Отправитель']
    ]"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('admin.users.index')" icon="arrow-left">
            Назад к пользователям
        </x-button>
    </x-slot>
</x-page-header>
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


    @if($sender)
        {{-- Sender существует --}}
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6);">
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                        <i data-lucide="user" class="icon-md"></i>
                        Данные отправителя
                    </h3>
                </div>
                <div class="card-body">
                <table class="table">
                    <tr>
                        <th>Email</th>
                        <td><strong>{{ $sender['email'] ?? '—' }}</strong></td>
                    </tr>
                    <tr>
                        <th>Имя</th>
                        <td>{{ $sender['sender_name'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Полное имя</th>
                        <td>{{ $sender['sender_full_name'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Телефон</th>
                        <td>{{ $sender['phone'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Статус</th>
                        <td>
                            @if($sender['is_active'] ?? false)
                                <x-badge type="completed">Активен</x-badge>
                            @else
                                <x-badge type="draft">Неактивен</x-badge>
                            @endif
                            @if($sender['is_verified'] ?? false)
                                <x-badge type="in-progress">Верифицирован</x-badge>
                            @endif
                        </td>
                    </tr>
                </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                        <i data-lucide="building-2" class="icon-md"></i>
                        Организация
                    </h3>
                </div>
                <div class="card-body">
                @if($organization)
                    <table class="table">
                        <tr>
                            <th>Название</th>
                            <td><strong>{{ $organization['name'] ?? '—' }}</strong></td>
                        </tr>
                        <tr>
                            <th>ИНН / КПП</th>
                            <td>{{ $organization['inn'] ?? '—' }} / {{ $organization['kpp'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Адрес</th>
                            <td>{{ $organization['legal_address'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Контактное лицо</th>
                            <td>{{ $organization['contact_person'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Телефон</th>
                            <td>{{ $organization['phone'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>{{ $organization['email'] ?? '—' }}</td>
                        </tr>
                    </table>
                @else
                    <p style="color: var(--text-muted);">Организация не привязана</p>
                @endif
                </div>
            </div>
        </div>

        <div style="margin-top: var(--space-6); display: flex; gap: var(--space-4);">
            <x-button variant="primary" :href="route('admin.users.sender.edit', $user)" icon="edit">
                Редактировать
            </x-button>

            @if($sender['is_active'] ?? false)
                <form action="{{ route('admin.users.sender.deactivate', $user) }}"
                      method="POST"
                      style="display: inline-block;"
                      onsubmit="return confirm('Вы уверены? Это деактивирует отправителя.')">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" type="submit" icon="x-circle">
                        Деактивировать
                    </x-button>
                </form>
            @endif
        </div>

    @else
        {{-- Sender не существует --}}
        <x-empty-state
            icon="mail"
            title="Отправитель не создан"
            description="У пользователя ещё нет персонального отправителя."
        >
            <x-slot name="action">
                <x-button variant="success" :href="route('admin.users.sender.create', $user)" icon="plus">
                    Создать отправителя
                </x-button>
            </x-slot>
        </x-empty-state>
    @endif
</div>
@endsection

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
