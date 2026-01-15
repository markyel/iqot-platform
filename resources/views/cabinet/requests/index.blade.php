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
<div class="card">
    @if($requests->count() > 0)
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Код</th>
                        <th>Название</th>
                        <th>Статус</th>
                        <th>Организация</th>
                        <th>Позиций</th>
                        <th>Создана</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $request)
                    @php
                        $externalRequest = ($request->synced_to_main_db && $request->main_db_request_id && isset($externalRequests[$request->main_db_request_id]))
                            ? $externalRequests[$request->main_db_request_id]
                            : null;
                        $displayStatus = $externalRequest ? $externalRequest->status : $request->status;
                        $displayStatusLabel = $externalRequest
                            ? (\App\Models\ExternalRequest::getStatusLabels()[$displayStatus] ?? $displayStatus)
                            : (\App\Models\Request::statuses()[$displayStatus] ?? $displayStatus);

                        // Карта статусов на варианты бейджей
                        $statusVariant = match($displayStatus) {
                            'draft' => 'neutral',
                            'pending', 'queued_for_sending' => 'warning',
                            'sending', 'new', 'emails_sent' => 'info',
                            'collecting', 'active', 'responses_received' => 'primary',
                            'completed' => 'success',
                            'cancelled' => 'danger',
                            default => 'neutral'
                        };
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('cabinet.requests.show', $request) }}" class="text-primary" style="font-weight: 600;">
                                {{ $externalRequest ? $externalRequest->request_number : $request->code }}
                            </a>
                        </td>
                        <td>{{ $request->title ?? '—' }}</td>
                        <td>
                            <x-badge :variant="$statusVariant">
                                {{ $displayStatusLabel }}
                            </x-badge>
                        </td>
                        <td>{{ $request->company_name ?? '—' }}</td>
                        <td>{{ $externalRequest ? $externalRequest->total_items : $request->items_count }}</td>
                        <td class="text-muted" style="font-size: 0.875rem;">{{ $request->created_at->format('d.m.Y H:i') }}</td>
                        <td>
                            <div style="display: flex; gap: var(--space-2); align-items: center;">
                                <x-button
                                    :href="route('cabinet.requests.show', $request)"
                                    variant="primary"
                                    size="sm"
                                    icon="arrow-right"
                                >
                                    Открыть
                                </x-button>
                                @if($externalRequest)
                                    <x-button
                                        :href="route('cabinet.my.requests.report', $request->id)"
                                        variant="purple"
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
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="padding: var(--space-4); border-top: 1px solid var(--neutral-200);">
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
</div>

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
@endsection
