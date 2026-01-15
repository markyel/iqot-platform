@extends('layouts.cabinet')

@section('title', 'Настройки профиля')

@section('content')
<x-page-header
    title="Настройки профиля"
    subtitle=""
/>

<div style="max-width: 900px; margin: 0 auto;">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('success') }}</div>
    @endif

    <form action="{{ route('cabinet.settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <!-- Личная информация -->
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="user" style="width: 20px; height: 20px;"></i>
                    Личная информация
                </h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                <div class="form-group">
                    <label class="form-label">Имя <span style="color: var(--red-600);">*</span></label>
                    <input
                        type="text"
                        name="name"
                        class="input @error('name') is-invalid @enderror"
                        value="{{ old('name', $user->name) }}"
                        required
                    >
                    @error('name')
                        <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Полное имя</label>
                    <input
                        type="text"
                        name="full_name"
                        class="input @error('full_name') is-invalid @enderror"
                        value="{{ old('full_name', $user->full_name) }}"
                    >
                    @error('full_name')
                        <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                <div class="form-group">
                    <label class="form-label">Email <span style="color: var(--red-600);">*</span></label>
                    <input
                        type="email"
                        name="email"
                        class="input @error('email') is-invalid @enderror"
                        value="{{ old('email', $user->email) }}"
                        required
                    >
                    @error('email')
                        <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Телефон</label>
                    <input
                        type="text"
                        name="phone"
                        class="input @error('phone') is-invalid @enderror"
                        value="{{ old('phone', $user->phone) }}"
                        placeholder="+7 (999) 123-45-67"
                    >
                    @error('phone')
                        <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            </div>
        </div>

        <!-- Данные организации -->
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="building-2" style="width: 20px; height: 20px;"></i>
                    Данные организации
                </h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                <label class="form-label">Название организации</label>
                <input
                    type="text"
                    name="company"
                    class="input @error('company') is-invalid @enderror"
                    value="{{ old('company', $user->company) }}"
                >
                @error('company')
                    <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                @enderror
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                <div class="form-group">
                    <label class="form-label">ИНН</label>
                    <input
                        type="text"
                        name="inn"
                        class="input @error('inn') is-invalid @enderror"
                        value="{{ old('inn', $user->inn) }}"
                        maxlength="12"
                    >
                    @error('inn')
                        <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">КПП</label>
                    <input
                        type="text"
                        name="kpp"
                        class="input @error('kpp') is-invalid @enderror"
                        value="{{ old('kpp', $user->kpp) }}"
                        maxlength="9"
                    >
                    @error('kpp')
                        <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Юридический адрес</label>
                <textarea
                    name="legal_address"
                    class="input @error('legal_address') is-invalid @enderror"
                    rows="2"
                    style="resize: vertical;"
                >{{ old('legal_address', $user->legal_address) }}</textarea>
                @error('legal_address')
                    <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Контактное лицо</label>
                <input
                    type="text"
                    name="contact_person"
                    class="input @error('contact_person') is-invalid @enderror"
                    value="{{ old('contact_person', $user->contact_person) }}"
                >
                @error('contact_person')
                    <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Телефон организации</label>
                <input
                    type="text"
                    name="company_phone"
                    class="input @error('company_phone') is-invalid @enderror"
                    value="{{ old('company_phone', $user->company_phone) }}"
                    placeholder="+7 (999) 123-45-67"
                >
                @error('company_phone')
                    <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Реквизиты организации</label>
                <textarea
                    name="company_details"
                    class="input @error('company_details') is-invalid @enderror"
                    rows="6"
                    placeholder="Банковские реквизиты, БИК, корр. счёт, расчётный счёт и другая информация"
                    style="resize: vertical;"
                >{{ old('company_details', $user->company_details) }}</textarea>
                @error('company_details')
                    <div style="color: var(--red-600); font-size: 0.875rem; margin-top: var(--space-1);">{{ $message }}</div>
                @enderror
            </div>
            </div>
        </div>

        <div>
            <x-button type="submit" variant="accent" icon="save">
                Сохранить изменения
            </x-button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
@endsection
