@extends('layouts.cabinet')

@section('title', 'Заявки из базы отчетов')

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

    .admin-table {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
    }

    .admin-table thead {
        background: #f9fafb;
    }

    .admin-table th {
        text-align: left;
        padding: 1rem 1.5rem;
        color: #6b7280;
        font-weight: 600;
        font-size: 0.875rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .admin-table td {
        padding: 1rem 1.5rem;
        border-top: 1px solid #f3f4f6;
    }

    .admin-table tbody tr:hover {
        background: #f9fafb;
    }

    .status-badge {
        display: inline-block;
        padding: 0.375rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-draft { background: #f3f4f6; color: #6b7280; }
    .status-new { background: #dbeafe; color: #1e40af; }
    .status-active { background: #d1fae5; color: #065f46; }
    .status-collecting { background: #fef3c7; color: #92400e; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }
    .status-emails-sent { background: #e0e7ff; color: #3730a3; }
    .status-responses-received { background: #ddd6fe; color: #5b21b6; }
    .status-queued-for-sending { background: #fef3c7; color: #78350f; }

    .form-select {
        background: #ffffff;
        border: 1px solid #d1d5db;
        color: #111827;
        padding: 0.625rem 1rem;
        border-radius: 8px;
        outline: none;
    }

    .form-select:focus {
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

    .progress-bar {
        width: 120px;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 0.25rem;
    }

    .progress-fill {
        height: 100%;
        background: #10b981;
        transition: width 0.3s;
    }

    .status-progress-col {
        min-width: 180px;
    }
</style>
@endpush

@section('content')
<div style="max-width: 1600px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">Заявки</h1>
        <p style="color: #9ca3af;">Заявки из системы ценовых котировок</p>
    </div>

    <!-- Фильтры -->
    <div class="admin-card">
        <form method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <select name="status" class="form-select">
                <option value="">Все статусы</option>
                @foreach(\App\Models\ExternalRequest::getStatusLabels() as $value => $label)
                    <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            
            <select name="is_customer_request" class="form-select">
                <option value="">Все типы</option>
                <option value="1" {{ request('is_customer_request') === '1' ? 'selected' : '' }}>Клиентские заявки</option>
                <option value="0" {{ request('is_customer_request') === '0' ? 'selected' : '' }}>Внутренние заявки</option>
            </select>
            
            <button type="submit" class="btn-green">Применить</button>
            
            @if(request()->hasAny(['status', 'is_customer_request']))
                <a href="{{ route('admin.external-requests.index') }}" style="color: #9ca3af; text-decoration: none; padding: 0.625rem 1rem;">
                    Сбросить
                </a>
            @endif
        </form>
    </div>

    <!-- Список заявок -->
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width: 150px;">Номер</th>
                <th>Заголовок / Позиции</th>
                <th style="width: 200px;">Статус / Прогресс</th>
                <th style="width: 140px;">Дата создания</th>
                <th style="width: 120px;">Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $request)
            <tr>
                <td>
                    <div style="color: #111827; font-family: monospace; font-weight: 600;">{{ $request->request_number }}</div>
                    @if($request->is_customer_request)
                        <span style="color: #10b981; font-size: 0.75rem; display: block; margin-top: 0.25rem;">👤 Клиентская</span>
                    @endif
                </td>
                <td>
                    <div style="color: #111827; font-weight: 500; margin-bottom: 0.5rem;">{{ $request->title ?: '—' }}</div>
                    @if($request->items->count() > 0)
                        <div style="background: #f9fafb; border-radius: 4px; padding: 0.5rem; margin-top: 0.5rem;">
                            <div style="color: #6b7280; font-size: 0.75rem; margin-bottom: 0.25rem;">Позиции ({{ $request->total_items }}):</div>
                            @foreach($request->items as $item)
                                <div style="color: #374151; font-size: 0.75rem; padding: 0.125rem 0;">
                                    {{ $item->position_number }}. {{ Str::limit($item->name, 50) }}
                                    @if($item->offers_count > 0)
                                        <span style="color: #059669; font-weight: 600;">({{ $item->offers_count }})</span>
                                    @endif
                                </div>
                            @endforeach
                            @if($request->total_items > $request->items->count())
                                <div style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem; font-style: italic;">
                                    и ещё {{ $request->total_items - $request->items->count() }}...
                                </div>
                            @endif
                        </div>
                    @endif
                </td>
                <td class="status-progress-col">
                    @php
                        $statusClass = 'status-' . str_replace('_', '-', $request->status);
                        $statusLabel = \App\Models\ExternalRequest::getStatusLabels()[$request->status] ?? $request->status;
                    @endphp
                    <div style="margin-bottom: 0.75rem;">
                        <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                    <div style="color: #111827; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">
                        {{ number_format($request->completion_percentage, 0) }}% • {{ $request->items_with_offers }}/{{ $request->total_items }}
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: {{ $request->completion_percentage }}%"></div>
                    </div>
                </td>
                <td style="color: #6b7280; font-size: 0.875rem;">
                    <div style="color: #111827; font-weight: 500;">{{ $request->created_at ? $request->created_at->format('d.m.Y') : '—' }}</div>
                    @if($request->collection_deadline)
                        <div style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;">
                            До: {{ $request->collection_deadline->format('d.m.Y') }}
                        </div>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.external-requests.show', $request) }}" class="btn-green" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none; display: inline-block;">
                        Открыть
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; padding: 3rem; color: #9ca3af;">
                    Заявок не найдено
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Пагинация -->
    @if($requests->hasPages())
        <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="color: #6b7280; font-size: 0.875rem;">
                Показано {{ $requests->firstItem() }}–{{ $requests->lastItem() }} из {{ $requests->total() }}
            </div>
            <div style="display: flex; gap: 0.5rem;">
                {{-- Предыдущая страница --}}
                @if($requests->onFirstPage())
                    <span style="background: #f3f4f6; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #9ca3af; cursor: not-allowed;">
                        ← Назад
                    </span>
                @else
                    <a href="{{ $requests->previousPageUrl() }}" style="background: #ffffff; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #10b981; text-decoration: none; transition: all 0.2s;">
                        ← Назад
                    </a>
                @endif

                {{-- Номера страниц --}}
                @foreach($requests->getUrlRange(max(1, $requests->currentPage() - 2), min($requests->lastPage(), $requests->currentPage() + 2)) as $page => $url)
                    @if($page == $requests->currentPage())
                        <span style="background: #10b981; border: 1px solid #10b981; padding: 0.5rem 0.875rem; border-radius: 6px; color: #fff; font-weight: 600; min-width: 40px; text-align: center;">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}" style="background: #ffffff; border: 1px solid #e5e7eb; padding: 0.5rem 0.875rem; border-radius: 6px; color: #374151; text-decoration: none; transition: all 0.2s; min-width: 40px; text-align: center; display: inline-block;">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach

                {{-- Следующая страница --}}
                @if($requests->hasMorePages())
                    <a href="{{ $requests->nextPageUrl() }}" style="background: #ffffff; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #10b981; text-decoration: none; transition: all 0.2s;">
                        Вперёд →
                    </a>
                @else
                    <span style="background: #f3f4f6; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #9ca3af; cursor: not-allowed;">
                        Вперёд →
                    </span>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
