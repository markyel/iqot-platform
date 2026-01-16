@extends('layouts.cabinet')

@section('title', 'Мои заявки')

@section('content')
<x-page-header
    title="Мои заявки"
    subtitle="Управление запросами на подбор запчастей"
>
    <x-slot name="actions">
        <x-button
            href="{{ route('cabinet.my.requests.create') }}"
            variant="primary"
            icon="plus"
        >
            Создать заявку
        </x-button>
    </x-slot>
</x-page-header>

<!-- Фильтры -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: var(--space-3); align-items: end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Поиск</label>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Код или название заявки"
                    class="input"
                >
            </div>

            <div style="width: 200px;">
                <label class="form-label">Статус</label>
                <select name="status" class="select">
                    <option value="">Все</option>
                    @foreach(\App\Models\Request::statuses() as $key => $label)
                        <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <x-button type="submit" variant="primary" icon="filter">
                Применить
            </x-button>

            @if(request('search') || request('status'))
                <x-button
                    href="{{ route('cabinet.requests') }}"
                    variant="danger-secondary"
                >
                    Сбросить
                </x-button>
            @endif
        </form>
    </div>
</div>

<!-- Список заявок -->
@if($requests->count() > 0)
    <div style="display: grid; gap: var(--space-4); margin-bottom: var(--space-6);">
        @foreach($requests as $request)
        @php
            $externalRequest = ($request->synced_to_main_db && $request->main_db_request_id && isset($externalRequests[$request->main_db_request_id]))
                ? $externalRequests[$request->main_db_request_id]
                : null;
            $displayStatus = $externalRequest ? $externalRequest->status : $request->status;
            $displayStatusLabel = $externalRequest
                ? (\App\Models\ExternalRequest::getStatusLabels()[$displayStatus] ?? $displayStatus)
                : (\App\Models\Request::statuses()[$displayStatus] ?? $displayStatus);

            // Карта статусов на варианты бейджей и визуал
            $statusConfig = match($displayStatus) {
                'draft' => [
                    'icon' => 'file-text',
                    'variant' => 'secondary',
                    'bg' => 'var(--neutral-50)',
                    'border' => 'var(--neutral-300)'
                ],
                'pending', 'queued_for_sending' => [
                    'icon' => 'clock',
                    'variant' => 'warning',
                    'bg' => 'var(--warning-50)',
                    'border' => 'var(--warning-400)'
                ],
                'sending', 'new', 'emails_sent' => [
                    'icon' => 'send',
                    'variant' => 'info',
                    'bg' => 'var(--info-50)',
                    'border' => 'var(--info-400)'
                ],
                'collecting', 'active', 'responses_received' => [
                    'icon' => 'refresh-cw',
                    'variant' => 'primary',
                    'bg' => 'var(--primary-50)',
                    'border' => 'var(--primary-400)'
                ],
                'completed' => [
                    'icon' => 'check-circle',
                    'variant' => 'success',
                    'bg' => 'var(--success-50)',
                    'border' => 'var(--success-400)'
                ],
                'cancelled' => [
                    'icon' => 'x-circle',
                    'variant' => 'danger',
                    'bg' => 'var(--danger-50)',
                    'border' => 'var(--danger-400)'
                ],
                default => [
                    'icon' => 'file-text',
                    'variant' => 'secondary',
                    'bg' => 'var(--neutral-50)',
                    'border' => 'var(--neutral-300)'
                ]
            };
        @endphp

        <div class="card request-card" style="border-left: 4px solid {{ $statusConfig['border'] }}; background: linear-gradient(to right, {{ $statusConfig['bg'] }}, var(--neutral-0));">
            <div class="card-body">
                <div style="display: grid; grid-template-columns: auto 1fr auto; gap: var(--space-4); align-items: start;">
                    <!-- Иконка статуса -->
                    <div style="width: 56px; height: 56px; border-radius: var(--radius-lg); background: {{ $statusConfig['bg'] }}; border: 2px solid {{ $statusConfig['border'] }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i data-lucide="{{ $statusConfig['icon'] }}" style="width: 28px; height: 28px; color: {{ $statusConfig['border'] }};"></i>
                    </div>

                    <!-- Основная информация -->
                    <div style="min-width: 0;">
                        <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-2); flex-wrap: wrap;">
                            <a href="{{ route('cabinet.requests.show', $request) }}" style="font-size: var(--text-xl); font-weight: 600; color: var(--primary-600); text-decoration: none;">
                                {{ $externalRequest ? $externalRequest->request_number : $request->code }}
                            </a>
                            <x-badge :variant="$statusConfig['variant']" size="sm">
                                {{ $displayStatusLabel }}
                            </x-badge>
                        </div>

                        <h3 style="font-size: var(--text-base); font-weight: 500; margin-bottom: var(--space-3); color: var(--neutral-900);">
                            {{ $request->title ?? 'Без названия' }}
                        </h3>

                        <div style="display: flex; gap: var(--space-6); flex-wrap: wrap;">
                            @if($request->company_name)
                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                <i data-lucide="building-2" style="width: 16px; height: 16px; color: var(--neutral-500);"></i>
                                <span style="font-size: var(--text-sm); color: var(--neutral-600);">
                                    {{ $request->company_name }}
                                </span>
                            </div>
                            @endif

                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                <i data-lucide="layers" style="width: 16px; height: 16px; color: var(--neutral-500);"></i>
                                <span style="font-size: var(--text-sm); color: var(--neutral-600);">
                                    <strong>{{ $externalRequest ? $externalRequest->total_items : $request->items_count }}</strong> позиций
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
                    <div style="display: flex; gap: var(--space-2); align-items: center; flex-shrink: 0; flex-wrap: wrap;">
                        <x-button
                            :href="route('cabinet.requests.show', $request)"
                            variant="primary"
                            size="sm"
                            icon="eye"
                        >
                            Открыть
                        </x-button>

                        @if($externalRequest)
                        <x-button
                            :href="route('cabinet.my.requests.report', $request->id)"
                            variant="accent"
                            size="sm"
                            icon="bar-chart-2"
                        >
                            Отчет
                        </x-button>
                        @endif

                        <x-button
                            :href="route('cabinet.my.requests.questions', $request->id)"
                            variant="info"
                            size="sm"
                            icon="message-circle"
                        >
                            Вопросы
                        </x-button>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div style="display: flex; justify-content: center;">
        {{ $requests->links() }}
    </div>
@else
    <x-empty-state
        icon="clipboard"
        title="Заявок не найдено"
        :description="(request('search') || request('status')) ? 'Попробуйте изменить параметры поиска' : 'Создайте первую заявку для подбора запчастей'"
    >
        <x-button
            href="{{ route('cabinet.my.requests.create') }}"
            variant="primary"
        >
            Создать заявку
        </x-button>
    </x-empty-state>
@endif

@push('scripts')
<script>
    lucide.createIcons();
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
