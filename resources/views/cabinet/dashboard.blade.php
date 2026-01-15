@extends('layouts.cabinet')

@section('title', 'Главная')

@section('content')
<!-- Page Header -->
<x-page-header title="Главная" description="Обзор ваших заявок и активности">
    <x-slot:actions>
        <x-button variant="accent" icon="plus" :href="route('cabinet.my.requests.create')">
            Создать заявку
        </x-button>
    </x-slot:actions>
</x-page-header>

<!-- Stats Cards -->
<div class="stats-grid" style="margin-bottom: var(--space-8);">
    <x-stat-card
        value="{{ $stats['total_requests'] }}"
        label="Всего заявок"
        icon="file-text"
        icon-type="primary"
    />

    <x-stat-card
        value="{{ $stats['active_requests'] }}"
        label="Активные"
        icon="clock"
        icon-type="accent"
    />

    <x-stat-card
        value="{{ $stats['completed_requests'] }}"
        label="Завершённые"
        icon="check-circle"
        icon-type="success"
    />

    <x-stat-card
        value="{{ $stats['total_reports'] }}"
        label="Отчёты"
        icon="bar-chart-3"
        icon-type="primary"
    />
</div>

<!-- Preview Items Card -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title">Доступные для просмотра позиции</h2>
        <x-button
            :href="route('cabinet.items.index')"
            variant="ghost"
            size="sm"
            icon="arrow-right"
        >
            Все позиции
        </x-button>
    </div>

    @if(isset($previewItems) && $previewItems->count() > 0)
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Артикул</th>
                        <th>Бренд</th>
                        <th>Заявка</th>
                        <th>Доступ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($previewItems as $item)
                    @php
                        // Check if user has purchased this item or owns the request
                        $hasPurchased = \App\Models\ItemPurchase::where('user_id', auth()->id())
                            ->where('item_id', $item->id)
                            ->exists();

                        $ownsRequest = $item->request &&
                            auth()->user()->requests()
                                ->where('synced_to_main_db', true)
                                ->where('request_number', $item->request->request_number)
                                ->exists();

                        $hasAccess = $hasPurchased || $ownsRequest;
                    @endphp
                    <tr>
                        <td data-label="Название">{{ $item->name }}</td>
                        <td data-label="Артикул" class="table-cell-mono">{{ $item->article ?? '—' }}</td>
                        <td data-label="Бренд">{{ $item->brand ?? '—' }}</td>
                        <td data-label="Заявка">
                            @if($item->request)
                                {{ $item->request->request_number }}
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Доступ">
                            @if($hasAccess)
                                <x-badge type="completed">
                                    <i data-lucide="unlock" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i>
                                    Полный доступ
                                </x-badge>
                            @else
                                <x-badge type="draft">
                                    <i data-lucide="lock" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i>
                                    Предпросмотр
                                </x-badge>
                            @endif
                        </td>
                        <td>
                            <x-button
                                :href="route('cabinet.items.show', $item->id)"
                                variant="primary"
                                size="sm"
                                icon="arrow-right"
                            >
                                Открыть
                            </x-button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-empty-state
            icon="package"
            title="Нет доступных позиций"
            description="Позиции для просмотра появятся здесь"
        />
    @endif
</div>

<!-- Recent Requests Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Последние заявки</h2>
        <x-button
            :href="route('cabinet.requests')"
            variant="ghost"
            size="sm"
            icon="arrow-right"
        >
            Все заявки
        </x-button>
    </div>

    @if($recentRequests->count() > 0)
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Код</th>
                        <th>Название</th>
                        <th>Статус</th>
                        <th>Позиций</th>
                        <th>Дата</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentRequests as $request)
                    <tr>
                        <td data-label="Код" class="table-cell-mono">
                            {{ $request->code }}
                        </td>
                        <td data-label="Название">{{ $request->title ?? '—' }}</td>
                        <td data-label="Статус">
                            <x-badge :type="$request->status">
                                {{ \App\Models\Request::statuses()[$request->status] ?? $request->status }}
                            </x-badge>
                        </td>
                        <td data-label="Позиций">{{ $request->items_count }}</td>
                        <td data-label="Дата">{{ $request->created_at->format('d.m.Y H:i') }}</td>
                        <td>
                            <x-button
                                :href="route('cabinet.requests.show', $request)"
                                variant="ghost"
                                size="sm"
                                icon="arrow-right"
                            >
                                Открыть
                            </x-button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-empty-state
            icon="inbox"
            title="Нет заявок"
            description="Создайте первую заявку для начала работы с системой"
        >
            <x-slot:action>
                <x-button variant="primary" icon="plus" :href="route('cabinet.my.requests.create')">
                    Создать заявку
                </x-button>
            </x-slot:action>
        </x-empty-state>
    @endif
</div>

@push('scripts')
<script>
    // Reinitialize Lucide icons after content is loaded
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
@endpush
@endsection
