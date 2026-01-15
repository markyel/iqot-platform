@extends('layouts.cabinet')

@section('title', 'Управление заявками')

@section('content')
<x-page-header
    title="Управление заявками"
    description="Создание и управление заявками через n8n API"
>
    <x-slot:actions>
        <x-button variant="accent" icon="plus" :href="route('admin.manage.requests.create')">
            Создать заявку
        </x-button>
    </x-slot:actions>
</x-page-header>

<!-- Filters Card -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.manage.requests.index') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); align-items: end;">
            <div class="form-group">
                <label class="form-label">Статус</label>
                <select name="status" class="input select">
                    <option value="">Все</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Черновик</option>
                    <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>В очереди</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Активна</option>
                    <option value="queued_for_sending" {{ request('status') === 'queued_for_sending' ? 'selected' : '' }}>В очереди на отправку</option>
                    <option value="emails_sent" {{ request('status') === 'emails_sent' ? 'selected' : '' }}>Письма отправлены</option>
                    <option value="collecting" {{ request('status') === 'collecting' ? 'selected' : '' }}>Сбор ответов</option>
                    <option value="responses_received" {{ request('status') === 'responses_received' ? 'selected' : '' }}>Ответы получены</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Завершена</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Отменена</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Тип</label>
                <select name="type" class="input select">
                    <option value="">Все</option>
                    <option value="customer" {{ request('type') === 'customer' ? 'selected' : '' }}>Именные</option>
                    <option value="anonymous" {{ request('type') === 'anonymous' ? 'selected' : '' }}>Анонимные</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Вопросы</label>
                <select name="has_questions" class="input select">
                    <option value="">Все</option>
                    <option value="1" {{ request('has_questions') === '1' ? 'selected' : '' }}>С неотвеченными вопросами</option>
                </select>
            </div>

            <div class="form-group" style="grid-column: span 2;">
                <label class="form-label">Поиск</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Номер, название, клиент..." class="input">
            </div>

            <div class="form-group">
                <label class="form-label">Дата от</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="input">
            </div>

            <div class="form-group">
                <label class="form-label">Дата до</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="input">
            </div>

            <div style="display: flex; gap: var(--space-2);">
                <button type="submit" class="btn btn-primary btn-md">
                    <i data-lucide="filter" class="icon-sm"></i>
                    Применить
                </button>
                @if(request()->hasAny(['status', 'type', 'search', 'date_from', 'date_to', 'has_questions']))
                    <a href="{{ route('admin.manage.requests.index') }}" class="btn btn-secondary btn-md">
                        <i data-lucide="x" class="icon-sm"></i>
                        Сбросить
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Requests Table Card -->
<div class="card">
    @if(!empty($requests) && count($requests) > 0)
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Номер</th>
                        <th>Заголовок</th>
                        <th>Тип</th>
                        <th>Клиент</th>
                        <th>Позиций</th>
                        <th>Вопросы</th>
                        <th>Статус</th>
                        <th>Дата создания</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $request)
                    <tr>
                        <td data-label="Номер" class="table-cell-mono">
                            {{ $request['request_number'] }}
                        </td>
                        <td data-label="Заголовок">{{ $request['title'] ?? '—' }}</td>
                        <td data-label="Тип">
                            <x-badge :type="$request['is_customer_request'] ? 'customer' : 'anonymous'">
                                {{ $request['is_customer_request'] ? 'Именная' : 'Анонимная' }}
                            </x-badge>
                        </td>
                        <td data-label="Клиент">{{ $request['customer_company'] ?? '—' }}</td>
                        <td data-label="Позиций">{{ $request['total_items'] ?? 0 }}</td>
                        <td data-label="Вопросы">
                            @if(isset($questionsCounts[$request['id']]) && $questionsCounts[$request['id']] > 0)
                                <a href="{{ route('admin.manage.requests.questions', $request['id']) }}">
                                    <x-badge type="pending" dot>
                                        {{ $questionsCounts[$request['id']] }}
                                    </x-badge>
                                </a>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td data-label="Статус">
                            <x-badge :type="$request['status']">
                                @switch($request['status'])
                                    @case('draft') Черновик @break
                                    @case('new') В очереди @break
                                    @case('active') Активна @break
                                    @case('queued_for_sending') В очереди на отправку @break
                                    @case('emails_sent') Письма отправлены @break
                                    @case('collecting') Сбор ответов @break
                                    @case('responses_received') Ответы получены @break
                                    @case('completed') Завершена @break
                                    @case('cancelled') Отменена @break
                                    @default {{ $request['status'] }}
                                @endswitch
                            </x-badge>
                        </td>
                        <td data-label="Дата">{{ \Carbon\Carbon::parse($request['created_at'])->format('d.m.Y H:i') }}</td>
                        <td class="table-cell-actions">
                            <div style="display: flex; gap: var(--space-2);">
                                <x-button variant="primary" size="sm" :href="route('admin.manage.requests.show', $request['id'])">
                                    Открыть
                                </x-button>
                                @if(in_array($request['status'], ['draft', 'new']))
                                    <x-button variant="ghost" size="sm" icon="pencil" :href="route('admin.manage.requests.edit', $request['id'])">
                                    </x-button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if(($lastPage ?? 1) > 1)
        <div class="table-footer">
            <div class="pagination-info">
                Страница <strong>{{ $currentPage ?? 1 }}</strong> из <strong>{{ $lastPage ?? 1 }}</strong>
            </div>

            <div class="pagination">
                @if(($currentPage ?? 1) > 1)
                    <a href="{{ route('admin.manage.requests.index', array_merge(request()->all(), ['page' => ($currentPage ?? 1) - 1])) }}" class="pagination-nav-btn">
                        <i data-lucide="chevron-left" class="icon-sm"></i>
                        Назад
                    </a>
                @else
                    <button class="pagination-nav-btn" disabled>
                        <i data-lucide="chevron-left" class="icon-sm"></i>
                        Назад
                    </button>
                @endif

                @php
                    $start = max(1, ($currentPage ?? 1) - 2);
                    $end = min(($lastPage ?? 1), ($currentPage ?? 1) + 2);
                @endphp

                @if($start > 1)
                    <a href="{{ route('admin.manage.requests.index', array_merge(request()->all(), ['page' => 1])) }}" class="pagination-btn">1</a>
                    @if($start > 2)
                        <span class="pagination-ellipsis">...</span>
                    @endif
                @endif

                @for($i = $start; $i <= $end; $i++)
                    <a href="{{ route('admin.manage.requests.index', array_merge(request()->all(), ['page' => $i])) }}"
                       class="pagination-btn {{ $i === ($currentPage ?? 1) ? 'active' : '' }}">
                        {{ $i }}
                    </a>
                @endfor

                @if($end < ($lastPage ?? 1))
                    @if($end < ($lastPage ?? 1) - 1)
                        <span class="pagination-ellipsis">...</span>
                    @endif
                    <a href="{{ route('admin.manage.requests.index', array_merge(request()->all(), ['page' => ($lastPage ?? 1)])) }}" class="pagination-btn">{{ $lastPage ?? 1 }}</a>
                @endif

                @if(($currentPage ?? 1) < ($lastPage ?? 1))
                    <a href="{{ route('admin.manage.requests.index', array_merge(request()->all(), ['page' => ($currentPage ?? 1) + 1])) }}" class="pagination-nav-btn">
                        Вперёд
                        <i data-lucide="chevron-right" class="icon-sm"></i>
                    </a>
                @else
                    <button class="pagination-nav-btn" disabled>
                        Вперёд
                        <i data-lucide="chevron-right" class="icon-sm"></i>
                    </button>
                @endif
            </div>
        </div>
        @endif
    @else
        <x-empty-state
            icon="inbox"
            title="Заявок не найдено"
            description="Создайте первую заявку или измените фильтры поиска"
        >
            <x-slot:action>
                <x-button variant="accent" icon="plus" :href="route('admin.manage.requests.create')">
                    Создать заявку
                </x-button>
            </x-slot:action>
        </x-empty-state>
    @endif
</div>

@push('scripts')
<script>
    // Reinitialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
@endpush
@endsection
