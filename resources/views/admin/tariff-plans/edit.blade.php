@extends('layouts.cabinet')

@section('title', 'Редактировать тарифный план')

@section('content')
<x-page-header
    title="Редактировать тарифный план"
    description="Изменение параметров тарифа «{{ $tariffPlan->name }}»"
>
    <x-slot:actions>
        <x-button variant="secondary" icon="arrow-left" :href="route('admin.tariff-plans.index')">
            Назад к списку
        </x-button>
    </x-slot:actions>
</x-page-header>

<div style="max-width: 900px; margin: 0 auto;">
    @if($tariffPlan->active_subscriptions_count > 0)
    <div class="alert alert-warning" style="margin-bottom: var(--space-6);">
        <i data-lucide="alert-circle" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 8px;"></i>
        <strong>Внимание!</strong> У этого тарифа есть активные подписки ({{ $tariffPlan->active_subscriptions_count }}).
        Изменения повлияют на всех пользователей.
    </div>
    @endif

    <form action="{{ route('admin.tariff-plans.update', $tariffPlan->id) }}" method="POST">
        @csrf
        @method('PUT')

        <!-- Основная информация -->
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 class="card-title">Основная информация</h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="code" class="label required">Код тарифа</label>
                        <input
                            type="text"
                            id="code"
                            name="code"
                            class="input @error('code') error @enderror"
                            value="{{ old('code', $tariffPlan->code) }}"
                            required
                            placeholder="start, basic, extended, professional"
                        >
                        @error('code')
                        <span class="form-error">{{ $message }}</span>
                        @enderror
                        <small class="form-hint">Уникальный код тарифа (латиница, без пробелов)</small>
                    </div>

                    <div class="form-group">
                        <label for="name" class="label required">Название</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="input @error('name') error @enderror"
                            value="{{ old('name', $tariffPlan->name) }}"
                            required
                            placeholder="Базовый, Расширенный"
                        >
                        @error('name')
                        <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="description" class="label">Описание</label>
                    <textarea
                        id="description"
                        name="description"
                        class="textarea @error('description') error @enderror"
                        rows="3"
                        placeholder="Описание тарифного плана для пользователей"
                    >{{ old('description', $tariffPlan->description) }}</textarea>
                    @error('description')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="sort_order" class="label required">Порядок сортировки</label>
                        <input
                            type="number"
                            id="sort_order"
                            name="sort_order"
                            class="input @error('sort_order') error @enderror"
                            value="{{ old('sort_order', $tariffPlan->sort_order) }}"
                            required
                            min="0"
                        >
                        @error('sort_order')
                        <span class="form-error">{{ $message }}</span>
                        @enderror
                        <small class="form-hint">Чем меньше число, тем выше позиция</small>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input
                                type="checkbox"
                                name="is_active"
                                class="checkbox"
                                {{ old('is_active', $tariffPlan->is_active) ? 'checked' : '' }}
                            >
                            <span>Тариф активен</span>
                        </label>
                        <small class="form-hint">Неактивные тарифы не доступны для выбора пользователями</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Стоимость -->
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 class="card-title">Стоимость</h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-4);">
                    <div class="form-group">
                        <label for="monthly_price" class="label required">Ежемесячная плата (₽)</label>
                        <input
                            type="number"
                            id="monthly_price"
                            name="monthly_price"
                            class="input @error('monthly_price') error @enderror"
                            value="{{ old('monthly_price', $tariffPlan->monthly_price) }}"
                            required
                            min="0"
                            step="0.01"
                        >
                        @error('monthly_price')
                        <span class="form-error">{{ $message }}</span>
                        @enderror
                        <small class="form-hint">0 для бесплатного тарифа</small>
                    </div>

                    <div class="form-group">
                        <label for="price_per_item_over_limit" class="label required">Цена за позицию (₽)</label>
                        <input
                            type="number"
                            id="price_per_item_over_limit"
                            name="price_per_item_over_limit"
                            class="input @error('price_per_item_over_limit') error @enderror"
                            value="{{ old('price_per_item_over_limit', $tariffPlan->price_per_item_over_limit) }}"
                            required
                            min="0"
                            step="0.01"
                        >
                        @error('price_per_item_over_limit')
                        <span class="form-error">{{ $message }}</span>
                        @enderror
                        <small class="form-hint">Стоимость сверх лимита</small>
                    </div>

                    <div class="form-group">
                        <label for="price_per_report_over_limit" class="label required">Цена за отчет (₽)</label>
                        <input
                            type="number"
                            id="price_per_report_over_limit"
                            name="price_per_report_over_limit"
                            class="input @error('price_per_report_over_limit') error @enderror"
                            value="{{ old('price_per_report_over_limit', $tariffPlan->price_per_report_over_limit) }}"
                            required
                            min="0"
                            step="0.01"
                        >
                        @error('price_per_report_over_limit')
                        <span class="form-error">{{ $message }}</span>
                        @enderror
                        <small class="form-hint">Стоимость сверх лимита</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Лимиты -->
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 class="card-title">Лимиты в месяц</h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="items_limit" class="label">Лимит позиций</label>
                        <input
                            type="number"
                            id="items_limit"
                            name="items_limit"
                            class="input @error('items_limit') error @enderror"
                            value="{{ old('items_limit', $tariffPlan->items_limit) }}"
                            min="0"
                            placeholder="Оставьте пустым для безлимита"
                        >
                        @error('items_limit')
                        <span class="form-error">{{ $message }}</span>
                        @enderror
                        <small class="form-hint">Количество позиций в заявках в месяц</small>
                    </div>

                    <div class="form-group">
                        <label for="reports_limit" class="label">Лимит отчетов</label>
                        <input
                            type="number"
                            id="reports_limit"
                            name="reports_limit"
                            class="input @error('reports_limit') error @enderror"
                            value="{{ old('reports_limit', $tariffPlan->reports_limit) }}"
                            min="0"
                            placeholder="Оставьте пустым для безлимита"
                        >
                        @error('reports_limit')
                        <span class="form-error">{{ $message }}</span>
                        @enderror
                        <small class="form-hint">Количество открытых отчетов в месяц</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Кнопки действий -->
        <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
            <x-button variant="secondary" type="button" :href="route('admin.tariff-plans.index')">
                Отмена
            </x-button>
            <x-button variant="accent" type="submit" icon="check">
                Сохранить изменения
            </x-button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
@endpush

@push('styles')
<style>
.form-group {
    margin-bottom: var(--space-4);
}

.label {
    display: block;
    font-size: var(--text-sm);
    font-weight: 500;
    color: var(--color-text);
    margin-bottom: var(--space-2);
}

.label.required::after {
    content: '*';
    color: var(--color-error);
    margin-left: 4px;
}

.form-hint {
    display: block;
    font-size: var(--text-xs);
    color: var(--color-text-muted);
    margin-top: var(--space-2);
}

.form-error {
    display: block;
    font-size: var(--text-sm);
    color: var(--color-error);
    margin-top: var(--space-2);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    cursor: pointer;
    margin-top: var(--space-2);
}

.checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.alert-warning {
    padding: var(--space-4);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
    background: rgba(255, 159, 10, 0.1);
    color: #ff9f0a;
    border: 1px solid #ff9f0a;
}
</style>
@endpush
@endsection
