@extends('layouts.cabinet')

@section('title', 'Сформировать акт')

@section('content')
<x-page-header title="Сформировать акт" description="Генерация акта оказанных услуг за период">
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('admin.billing.acts.index')" icon="arrow-left">
            К списку актов
        </x-button>
    </x-slot>
</x-page-header>

@if(session('error'))
    <div class="alert alert-danger" style="margin-bottom: var(--space-6);">
        {{ session('error') }}
    </div>
@endif

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Параметры генерации</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.billing.acts.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="user_id">Пользователь <span style="color: var(--danger-600);">*</span></label>
                <select name="user_id" id="user_id" class="select" required>
                    <option value="">Выберите пользователя</option>
                    @foreach(\App\Models\User::orderBy('name')->get() as $user)
                        <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
                @error('user_id')
                    <div style="color: var(--danger-600); font-size: var(--text-sm); margin-top: var(--space-2);">{{ $message }}</div>
                @enderror
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                <div class="form-group">
                    <label class="form-label" for="year">Год <span style="color: var(--danger-600);">*</span></label>
                    <select name="year" id="year" class="select" required>
                        @for($y = date('Y'); $y >= 2020; $y--)
                            <option value="{{ $y }}" {{ old('year', date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    @error('year')
                        <div style="color: var(--danger-600); font-size: var(--text-sm); margin-top: var(--space-2);">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="month">Месяц <span style="color: var(--danger-600);">*</span></label>
                    <select name="month" id="month" class="select" required>
                        @foreach(['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'] as $m => $monthName)
                            <option value="{{ $m + 1 }}" {{ old('month', date('n')) == ($m + 1) ? 'selected' : '' }}>{{ $monthName }}</option>
                        @endforeach
                    </select>
                    @error('month')
                        <div style="color: var(--danger-600); font-size: var(--text-sm); margin-top: var(--space-2);">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div style="padding: var(--space-4); background: var(--info-50); border: 1px solid var(--info-200); border-radius: var(--radius-md); margin-bottom: var(--space-6);">
                <div style="display: flex; gap: var(--space-3); align-items: start;">
                    <i data-lucide="info" style="width: 1.25rem; height: 1.25rem; color: var(--info-600); flex-shrink: 0; margin-top: 2px;"></i>
                    <div style="font-size: var(--text-sm); color: var(--info-900);">
                        <strong>Примечание:</strong><br>
                        Акт будет сформирован на основе всех списаний пользователя за указанный период:
                        <ul style="margin-top: var(--space-2); margin-left: var(--space-4);">
                            <li>Абонентская плата за тарифы</li>
                            <li>Услуги ценового мониторинга (обработка заявок)</li>
                            <li>Доступ к отчетам каталога</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
                <x-button type="button" variant="secondary" onclick="window.location.href='{{ route('admin.billing.acts.index') }}'">
                    Отмена
                </x-button>
                <x-button type="submit" variant="primary" icon="file-plus">
                    Сформировать акт
                </x-button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
lucide.createIcons();
</script>
@endpush
@endsection
