@extends('layouts.cabinet')

@section('title', 'Редактирование отправителя')

@section('content')
<x-page-header
    title="Редактирование отправителя: {{ $user->name }}"
    :breadcrumbs="[
        ['label' => 'Пользователи', 'url' => route('admin.users.index')],
        ['label' => $user->name, 'url' => route('admin.users.sender.show', $user)],
        ['label' => 'Редактирование']
    ]"
/>
<div style="max-width: 1200px; margin: 0 auto;">
    @if(session('error'))
        <div class="alert alert-error" style="margin-bottom: var(--space-4);">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('admin.users.sender.update', $user) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-8);">
                <!-- Левая колонка: Настройки отправителя -->
                <div>
                    <h3 style="margin: 0 0 var(--space-4) 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                        <i data-lucide="mail" class="icon-sm"></i>
                        Настройки отправителя
                    </h3>

                    <div class="info-box">
                        <small style="display: block; margin-bottom: var(--space-1); color: var(--text-muted);">Email адрес (не изменяется)</small>
                        <strong style="color: var(--text-primary);">{{ $sender['email'] ?? '—' }}</strong>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Шаблон письма</label>
                        <select name="template_id" class="select">
                            <option value="">По умолчанию</option>
                            @foreach($templates as $template)
                                <option value="{{ $template['id'] }}"
                                    {{ (old('template_id', $sender['template_id'] ?? null) == $template['id']) ? 'selected' : '' }}>
                                    {{ $template['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Имя отправителя <span class="text-danger">*</span></label>
                        <input type="text" name="sender_name" class="input @error('sender_name') is-invalid @enderror"
                               value="{{ old('sender_name', $sender['sender_name'] ?? '') }}" required>
                        @error('sender_name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Полное имя <span class="text-danger">*</span></label>
                        <input type="text" name="sender_full_name" class="input @error('sender_full_name') is-invalid @enderror"
                               value="{{ old('sender_full_name', $sender['sender_full_name'] ?? '') }}" required>
                        @error('sender_full_name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Телефон</label>
                        <input type="text" name="phone" class="input"
                               value="{{ old('phone', $sender['phone'] ?? '') }}">
                    </div>
                </div>

                <!-- Правая колонка: Данные организации -->
                <div>
                    <h3 style="margin: 0 0 var(--space-4) 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                        <i data-lucide="building-2" class="icon-sm"></i>
                        Данные организации
                    </h3>

                    <div class="form-group">
                        <label class="form-label">Название организации <span class="text-danger">*</span></label>
                        <input type="text" name="organization[name]" class="input @error('organization.name') is-invalid @enderror"
                               value="{{ old('organization.name', $organization['name'] ?? '') }}" required>
                        @error('organization.name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <div class="form-group">
                            <label class="form-label">ИНН</label>
                            <input type="text" name="organization[inn]" class="input"
                                   value="{{ old('organization.inn', $organization['inn'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">КПП</label>
                            <input type="text" name="organization[kpp]" class="input"
                                   value="{{ old('organization.kpp', $organization['kpp'] ?? '') }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Юридический адрес</label>
                        <textarea name="organization[legal_address]" class="input" rows="2">{{ old('organization.legal_address', $organization['legal_address'] ?? '') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Контактное лицо</label>
                        <input type="text" name="organization[contact_person]" class="input"
                               value="{{ old('organization.contact_person', $organization['contact_person'] ?? '') }}">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <div class="form-group">
                            <label class="form-label">Телефон организации</label>
                            <input type="text" name="organization[phone]" class="input"
                                   value="{{ old('organization.phone', $organization['phone'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email организации</label>
                            <input type="email" name="organization[email]" class="input"
                                   value="{{ old('organization.email', $organization['email'] ?? '') }}">
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <div style="display: flex; gap: var(--space-4); margin-top: var(--space-6);">
            <x-button variant="accent" type="submit" icon="check">
                Сохранить изменения
            </x-button>
            <x-button variant="secondary" :href="route('admin.users.sender.show', $user)" icon="x">
                Отмена
            </x-button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
