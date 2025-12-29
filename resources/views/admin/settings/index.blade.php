@extends('layouts.cabinet')

@section('title', 'Настройки системы')

@push('styles')
<style>
    /* Light theme for admin */
    .admin-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
    }

    .form-help {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: #6b7280;
    }

    .form-input {
        background: #ffffff;
        border: 1px solid #d1d5db;
        color: #111827;
        padding: 0.625rem 1rem;
        border-radius: 8px;
        outline: none;
        width: 100%;
        max-width: 400px;
    }

    .form-input:focus {
        border-color: #10b981;
    }

    .btn-green {
        background: #10b981;
        color: white;
        padding: 0.625rem 1.5rem;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-green:hover {
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

    .info-box {
        background: #dbeafe;
        border: 1px solid #93c5fd;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .info-box-title {
        color: #1e40af;
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-box-text {
        color: #1e40af;
        font-size: 0.875rem;
        line-height: 1.5;
    }
</style>
@endpush

@section('content')
<div style="max-width: 900px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; color: #111827;">
            ⚙️ Настройки системы
        </h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="info-box">
        <div class="info-box-title">
            💡 Информация
        </div>
        <div class="info-box-text">
            Здесь вы можете настроить основные параметры работы системы мониторинга позиций.
            Изменения вступают в силу немедленно для всех пользователей.
        </div>
    </div>

    <div class="admin-card">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin-bottom: 1.5rem;">
            Настройки ценообразования
        </h2>

        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            <div class="form-group">
                <label class="form-label">
                    Стоимость разблокировки отчета по позиции (₽)
                </label>
                <input
                    type="number"
                    name="item_unlock_price"
                    value="{{ $unlockPrice }}"
                    step="0.01"
                    min="0"
                    class="form-input"
                    required
                >
                <span class="form-help">
                    Эта сумма будет списываться с баланса пользователя при получении полного доступа к отчету по позиции
                </span>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Стоимость мониторинга одной позиции в заявке (₽)
                </label>
                <input
                    type="number"
                    name="price_per_item"
                    value="{{ $pricePerItem }}"
                    step="0.01"
                    min="0"
                    class="form-input"
                    required
                >
                <span class="form-help">
                    Эта сумма замораживается на балансе при создании заявки (за каждую позицию). После обработки заявки средства списываются
                </span>
            </div>

            <button type="submit" class="btn-green">
                Сохранить настройки
            </button>
        </form>
    </div>

    <div class="admin-card">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin-bottom: 1rem;">
            📊 Статистика системы
        </h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div style="background: #f9fafb; padding: 1rem; border-radius: 8px;">
                <div style="color: #6b7280; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Цена разблокировки
                </div>
                <div style="color: #10b981; font-size: 1.5rem; font-weight: 700;">
                    {{ number_format($unlockPrice, 0) }} ₽
                </div>
            </div>

            <div style="background: #f9fafb; padding: 1rem; border-radius: 8px;">
                <div style="color: #6b7280; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Цена за позицию
                </div>
                <div style="color: #10b981; font-size: 1.5rem; font-weight: 700;">
                    {{ number_format($pricePerItem, 0) }} ₽
                </div>
            </div>

            <div style="background: #f9fafb; padding: 1rem; border-radius: 8px;">
                <div style="color: #6b7280; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Всего пользователей
                </div>
                <div style="color: #111827; font-size: 1.5rem; font-weight: 700;">
                    {{ \App\Models\User::count() }}
                </div>
            </div>

            <div style="background: #f9fafb; padding: 1rem; border-radius: 8px;">
                <div style="color: #6b7280; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Всего покупок
                </div>
                <div style="color: #111827; font-size: 1.5rem; font-weight: 700;">
                    {{ \App\Models\ItemPurchase::count() }}
                </div>
            </div>

            <div style="background: #f9fafb; padding: 1rem; border-radius: 8px;">
                <div style="color: #6b7280; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Общая выручка
                </div>
                <div style="color: #111827; font-size: 1.5rem; font-weight: 700;">
                    {{ number_format(\App\Models\ItemPurchase::sum('amount'), 2) }} ₽
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
