@extends('layouts.cabinet')

@section('title', 'Настройки профиля')
@section('header', 'Настройки профиля')

@push('styles')
<style>
    .settings-card {
        background: white;
        padding: 2rem;
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
    }

    .section-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-input, .form-textarea {
        width: 100%;
        background: #ffffff;
        border: 1px solid #d1d5db;
        color: #111827;
        padding: 0.625rem 1rem;
        border-radius: 8px;
        outline: none;
        font-size: 0.9375rem;
    }

    .form-input:focus, .form-textarea:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .form-input.is-invalid {
        border-color: #ef4444;
    }

    .invalid-feedback {
        color: #ef4444;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 1rem;
    }

    .btn-success {
        background: #10b981;
        color: white;
    }

    .btn-success:hover {
        background: #059669;
    }

    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div style="max-width: 900px; margin: 0 auto;">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('cabinet.settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <!-- Личная информация -->
        <div class="settings-card">
            <div class="section-title">👤 Личная информация</div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Имя <span class="required">*</span></label>
                    <input type="text" name="name" class="form-input @error('name') is-invalid @enderror"
                           value="{{ old('name', $user->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Полное имя</label>
                    <input type="text" name="full_name" class="form-input @error('full_name') is-invalid @enderror"
                           value="{{ old('full_name', $user->full_name) }}">
                    @error('full_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-input @error('email') is-invalid @enderror"
                           value="{{ old('email', $user->email) }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Телефон</label>
                    <input type="text" name="phone" class="form-input @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $user->phone) }}" placeholder="+7 (999) 123-45-67">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Данные организации -->
        <div class="settings-card">
            <div class="section-title">🏢 Данные организации</div>

            <div class="form-group">
                <label class="form-label">Название организации</label>
                <input type="text" name="company" class="form-input @error('company') is-invalid @enderror"
                       value="{{ old('company', $user->company) }}">
                @error('company')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ИНН</label>
                    <input type="text" name="inn" class="form-input @error('inn') is-invalid @enderror"
                           value="{{ old('inn', $user->inn) }}" maxlength="12">
                    @error('inn')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">КПП</label>
                    <input type="text" name="kpp" class="form-input @error('kpp') is-invalid @enderror"
                           value="{{ old('kpp', $user->kpp) }}" maxlength="9">
                    @error('kpp')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Юридический адрес</label>
                <textarea name="legal_address" class="form-textarea @error('legal_address') is-invalid @enderror" rows="2">{{ old('legal_address', $user->legal_address) }}</textarea>
                @error('legal_address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Контактное лицо</label>
                <input type="text" name="contact_person" class="form-input @error('contact_person') is-invalid @enderror"
                       value="{{ old('contact_person', $user->contact_person) }}">
                @error('contact_person')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Телефон организации</label>
                <input type="text" name="company_phone" class="form-input @error('company_phone') is-invalid @enderror"
                       value="{{ old('company_phone', $user->company_phone) }}" placeholder="+7 (999) 123-45-67">
                @error('company_phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Реквизиты организации</label>
                <textarea name="company_details" class="form-textarea @error('company_details') is-invalid @enderror" rows="6" placeholder="Банковские реквизиты, БИК, корр. счёт, расчётный счёт и другая информация">{{ old('company_details', $user->company_details) }}</textarea>
                @error('company_details')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn-success">Сохранить изменения</button>
        </div>
    </form>
</div>
@endsection
