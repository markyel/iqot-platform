@extends('layouts.cabinet')

@section('title', 'Создание отправителя')

@section('content')
<x-page-header title="Создание отправителя" :description="'Для пользователя: ' . $user->name">
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('admin.users.show', $user)" icon="arrow-left">
            Назад к пользователю
        </x-button>
    </x-slot>
</x-page-header>

@if(session('error'))
<div class="alert alert-error" style="margin-bottom: var(--space-6);">
    <i data-lucide="alert-circle" class="alert-icon"></i>
    <div class="alert-content">{{ session('error') }}</div>
</div>
@endif

<form action="{{ route('admin.users.sender.store', $user) }}" method="POST">
    @csrf

    <!-- Информация о пользователе -->
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <i data-lucide="user" style="width: 1.25rem; height: 1.25rem;"></i>
            Информация о пользователе
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-4);">
                <div>
                    <div style="font-size: var(--text-xs); color: var(--neutral-500); margin-bottom: var(--space-1);">Имя</div>
                    <div style="font-weight: 600;">{{ $user->name }}</div>
                </div>
                <div>
                    <div style="font-size: var(--text-xs); color: var(--neutral-500); margin-bottom: var(--space-1);">Email</div>
                    <div>{{ $user->email }}</div>
                </div>
                <div>
                    <div style="font-size: var(--text-xs); color: var(--neutral-500); margin-bottom: var(--space-1);">Компания</div>
                    <div>{{ $user->company ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">
        <!-- Настройки отправителя -->
        <div class="card">
            <div class="card-header">
                <i data-lucide="mail" style="width: 1.25rem; height: 1.25rem;"></i>
                Настройки отправителя
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">
                        <span style="color: var(--danger-600);">*</span> Email адрес
                    </label>
                    <select name="reserved_email_id" class="select @error('reserved_email_id') is-invalid @enderror" required>
                        <option value="">Выберите email...</option>
                        @foreach($availableEmails as $email)
                            <option value="{{ $email['id'] }}" {{ old('reserved_email_id') == $email['id'] ? 'selected' : '' }}>
                                {{ $email['email'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('reserved_email_id')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                    <p class="form-hint">Выберите зарезервированный email адрес для отправки писем</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Шаблон письма</label>
                    <select name="template_id" class="select">
                        <option value="">По умолчанию</option>
                        @foreach($templates as $template)
                            <option value="{{ $template['id'] }}" {{ old('template_id') == $template['id'] ? 'selected' : '' }}>
                                {{ $template['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <p class="form-hint">Шаблон оформления писем для поставщиков</p>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <span style="color: var(--danger-600);">*</span> Имя отправителя
                    </label>
                    <input type="text" name="sender_name" class="input @error('sender_name') is-invalid @enderror"
                           value="{{ old('sender_name', $user->name) }}" required placeholder="Иван Иванов">
                    @error('sender_name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <span style="color: var(--danger-600);">*</span> Полное имя
                    </label>
                    <input type="text" name="sender_full_name" class="input @error('sender_full_name') is-invalid @enderror"
                           value="{{ old('sender_full_name', $user->full_name ?? $user->name) }}" required placeholder="Иванов Иван Иванович">
                    @error('sender_full_name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Телефон</label>
                    <input type="text" name="phone" class="input"
                           value="{{ old('phone', $user->phone) }}" placeholder="+7 (999) 123-45-67">
                    <p class="form-hint">Контактный телефон для связи с поставщиками</p>
                </div>
            </div>
        </div>

        <!-- Данные организации -->
        <div class="card">
            <div class="card-header">
                <i data-lucide="building-2" style="width: 1.25rem; height: 1.25rem;"></i>
                Данные организации
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">
                        <span style="color: var(--danger-600);">*</span> Название организации
                    </label>
                    <input type="text" name="organization[name]" class="input @error('organization.name') is-invalid @enderror"
                           value="{{ old('organization.name', $user->company ?? $user->organization) }}" required placeholder="ООО 'Компания'">
                    @error('organization.name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">ИНН</label>
                        <input type="text" name="organization[inn]" class="input"
                               value="{{ old('organization.inn', $user->inn) }}" placeholder="1234567890">
                    </div>
                    <div class="form-group">
                        <label class="form-label">КПП</label>
                        <input type="text" name="organization[kpp]" class="input"
                               value="{{ old('organization.kpp', $user->kpp) }}" placeholder="123456789">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Юридический адрес</label>
                    <textarea name="organization[legal_address]" class="input" rows="3" placeholder="г. Москва, ул. Примерная, д. 1">{{ old('organization.legal_address', $user->legal_address) }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Контактное лицо</label>
                    <input type="text" name="organization[contact_person]" class="input"
                           value="{{ old('organization.contact_person', $user->full_name ?? $user->name) }}" placeholder="Иванов Иван Иванович">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Телефон организации</label>
                        <input type="text" name="organization[phone]" class="input"
                               value="{{ old('organization.phone', $user->phone) }}" placeholder="+7 (999) 123-45-67">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email организации</label>
                        <input type="email" name="organization[email]" class="input"
                               value="{{ old('organization.email', $user->email) }}" placeholder="company@example.com">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Действия -->
    <div class="card">
        <div class="card-body">
            <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
                <x-button type="button" variant="secondary" :href="route('admin.users.show', $user)" icon="x">
                    Отмена
                </x-button>
                <x-button type="submit" variant="success" icon="check">
                    Создать отправителя
                </x-button>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
lucide.createIcons();
</script>
@endpush
@endsection
