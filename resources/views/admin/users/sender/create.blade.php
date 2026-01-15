@extends('layouts.cabinet')

@section('title', 'Создание отправителя')

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <x-page-header
        title="Создание отправителя"
        :description="'Для пользователя: ' . $user->name"
    />

    @if(session('error'))
        <div class="alert alert-danger">
            <i data-lucide="alert-circle"></i>
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('admin.users.sender.store', $user) }}" method="POST">
        @csrf
        <div class="card">
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem;">
                    <!-- Левая колонка: Настройки отправителя -->
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--border);">
                            <i data-lucide="mail"></i>
                            Настройки отправителя
                        </h3>

                        <div class="form-group">
                            <label class="form-label">Email адрес <span class="required">*</span></label>
                            <select name="reserved_email_id" class="select @error('reserved_email_id') is-invalid @enderror" required>
                                <option value="">Выберите email...</option>
                                @foreach($availableEmails as $email)
                                    <option value="{{ $email['id'] }}" {{ old('reserved_email_id') == $email['id'] ? 'selected' : '' }}>
                                        {{ $email['email'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('reserved_email_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
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
                        </div>

                        <div class="form-group">
                            <label class="form-label">Имя отправителя <span class="required">*</span></label>
                            <input type="text" name="sender_name" class="input @error('sender_name') is-invalid @enderror"
                                   value="{{ old('sender_name', $user->name) }}" required>
                            @error('sender_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Полное имя <span class="required">*</span></label>
                            <input type="text" name="sender_full_name" class="input @error('sender_full_name') is-invalid @enderror"
                                   value="{{ old('sender_full_name', $user->full_name ?? $user->name) }}" required>
                            @error('sender_full_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Телефон</label>
                            <input type="text" name="phone" class="input"
                                   value="{{ old('phone', $user->phone) }}">
                        </div>
                    </div>

                    <!-- Правая колонка: Данные организации -->
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--border);">
                            <i data-lucide="building"></i>
                            Данные организации
                        </h3>

                        <div class="form-group">
                            <label class="form-label">Название организации <span class="required">*</span></label>
                            <input type="text" name="organization[name]" class="input @error('organization.name') is-invalid @enderror"
                                   value="{{ old('organization.name', $user->company ?? $user->organization) }}" required>
                            @error('organization.name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">ИНН</label>
                                <input type="text" name="organization[inn]" class="input"
                                       value="{{ old('organization.inn', $user->inn) }}">
                            </div>
                            <div class="form-group">
                                <label class="form-label">КПП</label>
                                <input type="text" name="organization[kpp]" class="input"
                                       value="{{ old('organization.kpp', $user->kpp) }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Юридический адрес</label>
                            <textarea name="organization[legal_address]" class="input" rows="2">{{ old('organization.legal_address', $user->legal_address) }}</textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Контактное лицо</label>
                            <input type="text" name="organization[contact_person]" class="input"
                                   value="{{ old('organization.contact_person', $user->full_name ?? $user->name) }}">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Телефон организации</label>
                                <input type="text" name="organization[phone]" class="input"
                                       value="{{ old('organization.phone', $user->phone) }}">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email организации</label>
                                <input type="email" name="organization[email]" class="input"
                                       value="{{ old('organization.email', $user->email) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <x-button type="submit" variant="success">
                <i data-lucide="check"></i>
                Создать отправителя
            </x-button>
            <a href="{{ route('admin.users.sender.show', $user) }}" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>
@endpush
@endsection
