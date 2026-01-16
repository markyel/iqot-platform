@extends('layouts.cabinet')

@section('title', 'Мои заявки')

@section('content')
<x-page-header title="Мои заявки" description="Список ваших заявок на мониторинг позиций">
    <x-slot name="actions">
        <x-button variant="primary" :href="route('cabinet.my.requests.create')" icon="plus">
            Создать заявку
        </x-button>
    </x-slot>
</x-page-header>

@if($requests->count() > 0)
    <div style="display: grid; gap: var(--space-4);">
        @foreach($requests as $request)
        @php
            $statusType = match($request->status) {
                'draft' => 'secondary',
                'pending' => 'warning',
                'sending' => 'info',
                'collecting' => 'info',
                'completed' => 'success',
                'cancelled' => 'danger',
                default => 'secondary'
            };
            $statusText = \App\Models\Request::statuses()[$request->status] ?? $request->status;

            // Иконки и цвета для статусов
            $statusConfig = match($request->status) {
                'draft' => ['icon' => 'file-text', 'bg' => 'var(--neutral-50)', 'border' => 'var(--neutral-200)'],
                'pending' => ['icon' => 'clock', 'bg' => 'var(--warning-50)', 'border' => 'var(--warning-200)'],
                'sending' => ['icon' => 'send', 'bg' => 'var(--info-50)', 'border' => 'var(--info-200)'],
                'collecting' => ['icon' => 'refresh-cw', 'bg' => 'var(--info-50)', 'border' => 'var(--info-200)'],
                'completed' => ['icon' => 'check-circle', 'bg' => 'var(--success-50)', 'border' => 'var(--success-200)'],
                'cancelled' => ['icon' => 'x-circle', 'bg' => 'var(--danger-50)', 'border' => 'var(--danger-200)'],
                default => ['icon' => 'file-text', 'bg' => 'var(--neutral-50)', 'border' => 'var(--neutral-200)']
            };
        @endphp

        <div class="card request-card" style="border-left: 4px solid {{ $statusConfig['border'] }}; background: linear-gradient(to right, {{ $statusConfig['bg'] }}, var(--neutral-0));">
            <div class="card-body">
                <div style="display: grid; grid-template-columns: auto 1fr auto; gap: var(--space-4); align-items: start;">
                    <!-- Иконка статуса -->
                    <div style="width: 48px; height: 48px; border-radius: var(--radius-lg); background: {{ $statusConfig['bg'] }}; border: 2px solid {{ $statusConfig['border'] }}; display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="{{ $statusConfig['icon'] }}" style="width: 24px; height: 24px; color: {{ $statusConfig['border'] }};"></i>
                    </div>

                    <!-- Основная информация -->
                    <div>
                        <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-2);">
                            <a href="{{ route('cabinet.my.requests.show', $request->id) }}" style="font-size: var(--text-lg); font-weight: 600; color: var(--primary-600); text-decoration: none;">
                                {{ $request->request_number ?? $request->code }}
                            </a>
                            <x-badge :type="$statusType" size="sm">
                                {{ $statusText }}
                            </x-badge>
                        </div>

                        <h3 style="font-size: var(--text-base); font-weight: 500; margin-bottom: var(--space-3); color: var(--neutral-900);">
                            {{ $request->title }}
                        </h3>

                        <div style="display: flex; gap: var(--space-6); flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                <i data-lucide="layers" style="width: 16px; height: 16px; color: var(--neutral-500);"></i>
                                <span style="font-size: var(--text-sm); color: var(--neutral-600);">
                                    <strong>{{ $request->items_count }}</strong> позиций
                                </span>
                            </div>

                            @if($request->balanceHold)
                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                <i data-lucide="wallet" style="width: 16px; height: 16px; color: var(--neutral-500);"></i>
                                <span style="font-size: var(--text-sm); color: var(--neutral-600);">
                                    @if($request->balanceHold->status === 'held')
                                        Заморожено: <strong>{{ number_format($request->balanceHold->amount, 2) }} ₽</strong>
                                    @elseif($request->balanceHold->status === 'charged')
                                        Списано: <strong>{{ number_format($request->balanceHold->charges->sum('amount'), 2) }} ₽</strong>
                                    @else
                                        <strong>{{ number_format($request->balanceHold->amount, 2) }} ₽</strong>
                                    @endif
                                </span>
                            </div>
                            @endif

                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                <i data-lucide="calendar" style="width: 16px; height: 16px; color: var(--neutral-500);"></i>
                                <span style="font-size: var(--text-sm); color: var(--neutral-600);">
                                    {{ $request->created_at->format('d.m.Y H:i') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Действия -->
                    <div style="display: flex; gap: var(--space-2); align-items: center;">
                        <x-button variant="secondary" size="sm" :href="route('cabinet.my.requests.show', $request->id)" icon="eye">
                            Подробнее
                        </x-button>

                        @if($request->synced_to_main_db && $request->main_db_request_id)
                        <x-button variant="accent" size="sm" :href="route('cabinet.my.requests.report', $request->id)" icon="bar-chart">
                            Отчет
                        </x-button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($requests->hasPages())
    <div style="margin-top: var(--space-6);">
        {{ $requests->links() }}
    </div>
    @endif

@else
    <x-empty-state
        icon="inbox"
        title="У вас пока нет заявок"
        description="Создайте первую заявку для мониторинга позиций"
    >
        <x-button variant="primary" :href="route('cabinet.my.requests.create')" icon="plus">
            Создать заявку
        </x-button>
    </x-empty-state>
@endif

@push('scripts')
<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush

@push('styles')
<style>
.request-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.request-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}
</style>
@endpush
@endsection
