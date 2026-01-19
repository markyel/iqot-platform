@extends('layouts.cabinet')

@section('title', 'Тарифные планы')

@section('content')
<x-page-header
    title="Тарифные планы"
    description="Управление тарифными планами платформы"
>
    <x-slot:actions>
        <x-button variant="accent" icon="plus" :href="route('admin.tariff-plans.create')">
            Создать тариф
        </x-button>
    </x-slot:actions>
</x-page-header>

<div style="max-width: 1400px; margin: 0 auto;">
    @if(session('success'))
    <div class="alert alert-success" style="margin-bottom: var(--space-4);">
        <i data-lucide="check-circle" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 8px;"></i>
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-error" style="margin-bottom: var(--space-4);">
        <i data-lucide="alert-circle" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 8px;"></i>
        {{ session('error') }}
    </div>
    @endif

    <!-- Статистика -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-4); margin-bottom: var(--space-6);">
        <x-stat-card
            icon="credit-card"
            icon-type="primary"
            :value="$tariffPlans->count()"
            label="Всего тарифов"
        />
        <x-stat-card
            icon="check-circle"
            icon-type="success"
            :value="$tariffPlans->where('is_active', true)->count()"
            label="Активных"
        />
        <x-stat-card
            icon="users"
            icon-type="accent"
            :value="$tariffPlans->sum('active_subscriptions_count')"
            label="Всего подписок"
        />
        <x-stat-card
            icon="trending-up"
            icon-type="success"
            :value="number_format($tariffPlans->where('monthly_price', '>', 0)->sum('monthly_price'), 0, ',', ' ') . ' ₽'"
            label="Потенциальная выручка"
        />
    </div>

    <!-- Список тарифов -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Тарифные планы</h2>
        </div>

        @if($tariffPlans->count() > 0)
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Порядок</th>
                        <th>Код</th>
                        <th>Название</th>
                        <th>Цена/мес</th>
                        <th>Лимиты</th>
                        <th>Сверх лимита</th>
                        <th>Подписчиков</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tariffPlans as $tariff)
                    <tr>
                        <td data-label="Порядок">
                            <span style="font-weight: 600;">{{ $tariff->sort_order }}</span>
                        </td>
                        <td data-label="Код">
                            <x-badge type="draft">{{ $tariff->code }}</x-badge>
                        </td>
                        <td data-label="Название">
                            <div style="font-weight: 600;">{{ $tariff->name }}</div>
                            @if($tariff->description)
                            <div class="text-muted" style="font-size: var(--text-sm); margin-top: 4px;">
                                {{ Str::limit($tariff->description, 60) }}
                            </div>
                            @endif
                        </td>
                        <td data-label="Цена/мес">
                            @if($tariff->monthly_price > 0)
                                <strong style="color: var(--color-primary);">{{ number_format($tariff->monthly_price, 0, ',', ' ') }} ₽</strong>
                            @else
                                <x-badge type="success">Бесплатно</x-badge>
                            @endif
                        </td>
                        <td data-label="Лимиты">
                            <div style="font-size: var(--text-sm);">
                                <div>
                                    <i data-lucide="file-text" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle;"></i>
                                    Позиций: <strong>{{ $tariff->items_limit ?? '∞' }}</strong>
                                </div>
                                <div style="margin-top: 4px;">
                                    <i data-lucide="bar-chart-3" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle;"></i>
                                    Отчетов: <strong>{{ $tariff->reports_limit ?? '∞' }}</strong>
                                </div>
                                @if($tariff->pdf_reports_enabled)
                                <div style="margin-top: 4px; color: var(--color-success);">
                                    <i data-lucide="file-down" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle;"></i>
                                    PDF отчеты
                                </div>
                                @endif
                            </div>
                        </td>
                        <td data-label="Сверх лимита">
                            <div style="font-size: var(--text-sm); color: var(--color-text-muted);">
                                <div>{{ $tariff->price_per_item_over_limit }} ₽/поз</div>
                                <div style="margin-top: 4px;">{{ $tariff->price_per_report_over_limit }} ₽/отч</div>
                            </div>
                        </td>
                        <td data-label="Подписчиков">
                            @if($tariff->active_subscriptions_count > 0)
                                <x-badge type="primary">{{ $tariff->active_subscriptions_count }}</x-badge>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td data-label="Статус">
                            @if($tariff->is_active)
                                <x-badge type="completed">
                                    <i data-lucide="check-circle" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i>
                                    Активен
                                </x-badge>
                            @else
                                <x-badge type="draft">
                                    <i data-lucide="x-circle" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i>
                                    Неактивен
                                </x-badge>
                            @endif
                        </td>
                        <td>
                            <div style="display: flex; gap: var(--space-2);">
                                <x-button
                                    :href="route('admin.tariff-plans.edit', $tariff->id)"
                                    variant="ghost"
                                    size="sm"
                                    icon="edit"
                                >
                                    Изменить
                                </x-button>
                                @if($tariff->active_subscriptions_count == 0)
                                <form action="{{ route('admin.tariff-plans.destroy', $tariff->id) }}" method="POST"
                                      onsubmit="return confirm('Вы уверены, что хотите удалить этот тариф?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                        icon="trash-2"
                                        style="color: var(--color-error);"
                                    >
                                        Удалить
                                    </x-button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <x-empty-state
            icon="credit-card"
            title="Нет тарифных планов"
            description="Создайте первый тарифный план для начала работы"
        >
            <x-slot:action>
                <x-button variant="accent" icon="plus" :href="route('admin.tariff-plans.create')">
                    Создать тариф
                </x-button>
            </x-slot:action>
        </x-empty-state>
        @endif
    </div>
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
.alert {
    padding: var(--space-4);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
}

.alert-success {
    background: var(--color-success-alpha);
    color: var(--color-success);
    border: 1px solid var(--color-success);
}

.alert-error {
    background: var(--color-error-alpha);
    color: var(--color-error);
    border: 1px solid var(--color-error);
}
</style>
@endpush
@endsection
