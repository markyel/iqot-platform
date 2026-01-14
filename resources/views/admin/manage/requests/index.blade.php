@extends('layouts.cabinet')

@section('title', 'Управление заявками (n8n)')
@section('header', 'Управление заявками (n8n)')

@push('styles')
<style>
    .filters { background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
    .filters form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
    .filter-group { display: flex; flex-direction: column; gap: 0.25rem; }
    .filter-group label { font-size: 0.875rem; font-weight: 600; color: #374151; }
    .filters input, .filters select { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
    .filters button { padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; }
    .filters button:hover { background: #2563eb; }
    .table-container { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 0.875rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
    .table th { background: #f9fafb; font-weight: 600; color: #6b7280; font-size: 0.875rem; }
    .table tbody tr:hover { background: #f9fafb; }
    .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; display: inline-block; }
    .badge-draft { background: #f3f4f6; color: #6b7280; }
    .badge-new { background: #dbeafe; color: #1e40af; }
    .badge-active { background: #d1fae5; color: #065f46; }
    .badge-queued-for-sending { background: #fef3c7; color: #92400e; }
    .badge-emails-sent { background: #e0e7ff; color: #3730a3; }
    .badge-collecting { background: #ddd6fe; color: #5b21b6; }
    .badge-responses-received { background: #e0e7ff; color: #4338ca; }
    .badge-completed { background: #d1fae5; color: #065f46; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .badge-customer { background: #dbeafe; color: #1e40af; }
    .badge-anonymous { background: #f3f4f6; color: #6b7280; }
    .badge-questions { background: #fef3c7; color: #92400e; }
    .btn { padding: 0.5rem 0.875rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; display: inline-block; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .btn-secondary { background: #6b7280; color: white; }
    .btn-secondary:hover { background: #4b5563; }
    .btn-success { background: #10b981; color: white; }
    .btn-success:hover { background: #059669; }
    .actions { display: flex; gap: 0.5rem; }
    .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
    .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; }
    .pagination a, .pagination span { padding: 0.5rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.375rem; text-decoration: none; color: #374151; }
    .pagination .active { background: #3b82f6; color: white; border-color: #3b82f6; }
    .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
</style>
@endpush

@section('content')
<div style="max-width: 1400px; margin: 0 auto;">

    @if(session('success'))
    <div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem;">
        <p style="color: #065f46; font-weight: 600;">{{ session('success') }}</p>
    </div>
    @endif

    @if(session('error') ?? $error ?? null)
    <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem;">
        <p style="color: #991b1b; font-weight: 600;">{{ session('error') ?? $error }}</p>
    </div>
    @endif

    <!-- Заголовок и кнопка создания -->
    <div class="header-actions">
        <div>
            <p style="color: #6b7280; margin-top: 0.5rem;">Создание и управление заявками через n8n API</p>
        </div>
        <a href="{{ route('admin.manage.requests.create') }}" class="btn btn-success">+ Создать заявку</a>
    </div>

    <!-- Фильтры -->
    <div class="filters">
        <form method="GET" action="{{ route('admin.manage.requests.index') }}">
            <div class="filter-group">
                <label>Статус</label>
                <select name="status">
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

            <div class="filter-group">
                <label>Тип</label>
                <select name="type">
                    <option value="">Все</option>
                    <option value="customer" {{ request('type') === 'customer' ? 'selected' : '' }}>Именные</option>
                    <option value="anonymous" {{ request('type') === 'anonymous' ? 'selected' : '' }}>Анонимные</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Вопросы</label>
                <select name="has_questions">
                    <option value="">Все</option>
                    <option value="1" {{ request('has_questions') === '1' ? 'selected' : '' }}>С неотвеченными вопросами</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Поиск</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Номер, название, клиент..." style="min-width: 250px;">
            </div>

            <div class="filter-group">
                <label>Дата от</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}">
            </div>

            <div class="filter-group">
                <label>Дата до</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}">
            </div>

            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit">Применить</button>
            </div>

            @if(request()->hasAny(['status', 'type', 'search', 'date_from', 'date_to', 'has_questions']))
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="{{ route('admin.manage.requests.index') }}" class="btn btn-secondary">Сбросить</a>
            </div>
            @endif
        </form>
    </div>

    <!-- Таблица заявок -->
    <div class="table-container">
        @if(!empty($requests) && count($requests) > 0)
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
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requests as $request)
                <tr>
                    <td>
                        <a href="{{ route('admin.manage.requests.show', $request['id']) }}" style="color: #3b82f6; text-decoration: none; font-weight: 600;">
                            {{ $request['request_number'] }}
                        </a>
                    </td>
                    <td>{{ $request['title'] ?? '-' }}</td>
                    <td>
                        @if($request['is_customer_request'])
                            <span class="badge badge-customer">Именная</span>
                        @else
                            <span class="badge badge-anonymous">Анонимная</span>
                        @endif
                    </td>
                    <td>{{ $request['customer_company'] ?? '-' }}</td>
                    <td>{{ $request['total_items'] ?? 0 }}</td>
                    <td>
                        @if(isset($questionsCounts[$request['id']]) && $questionsCounts[$request['id']] > 0)
                            <a href="{{ route('admin.manage.requests.questions', $request['id']) }}" style="text-decoration: none;">
                                <span class="badge badge-questions">❓ {{ $questionsCounts[$request['id']] }}</span>
                            </a>
                        @else
                            <span style="color: #9ca3af;">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $request['status'] }}">
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
                        </span>
                    </td>
                    <td>{{ \Carbon\Carbon::parse($request['created_at'])->format('d.m.Y H:i') }}</td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('admin.manage.requests.show', $request['id']) }}" class="btn btn-primary">Просмотр</a>
                            @if(in_array($request['status'], ['draft', 'new']))
                                <a href="{{ route('admin.manage.requests.edit', $request['id']) }}" class="btn btn-secondary">Редактировать</a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Пагинация -->
        @if(($lastPage ?? 1) > 1)
        <div class="pagination">
            @if(($currentPage ?? 1) > 1)
                <a href="{{ route('admin.manage.requests.index', array_merge(request()->all(), ['page' => ($currentPage ?? 1) - 1])) }}">« Предыдущая</a>
            @endif

            @for($i = 1; $i <= ($lastPage ?? 1); $i++)
                @if($i === ($currentPage ?? 1))
                    <span class="active">{{ $i }}</span>
                @else
                    <a href="{{ route('admin.manage.requests.index', array_merge(request()->all(), ['page' => $i])) }}">{{ $i }}</a>
                @endif
            @endfor

            @if(($currentPage ?? 1) < ($lastPage ?? 1))
                <a href="{{ route('admin.manage.requests.index', array_merge(request()->all(), ['page' => ($currentPage ?? 1) + 1])) }}">Следующая »</a>
            @endif
        </div>
        @endif
        @else
        <div class="empty-state">
            <svg style="width: 64px; height: 64px; margin: 0 auto 1rem; opacity: 0.3;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p style="font-size: 1.125rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Заявок не найдено</p>
            <p>Создайте первую заявку или измените фильтры поиска</p>
        </div>
        @endif
    </div>

</div>
@endsection
