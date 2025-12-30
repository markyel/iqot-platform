@extends('layouts.cabinet')

@section('title', 'Мониторинг позиций')

@push('styles')
<style>
    /* Light theme for user cabinet */
    .cabinet-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .cabinet-table {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
    }

    .cabinet-table thead {
        background: #f9fafb;
    }

    .cabinet-table th {
        text-align: left;
        padding: 1rem 1.5rem;
        color: #6b7280;
        font-weight: 600;
        font-size: 0.875rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .cabinet-table td {
        padding: 1rem 1.5rem;
        border-top: 1px solid #f3f4f6;
    }

    .cabinet-table tbody tr:hover {
        background: #f9fafb;
    }

    .status-badge {
        display: inline-block;
        padding: 0.375rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-preview { background: #fef3c7; color: #92400e; }
    .status-full-access { background: #d1fae5; color: #065f46; }

    .form-input, .form-select {
        background: #ffffff;
        border: 1px solid #d1d5db;
        color: #111827;
        padding: 0.625rem 1rem;
        border-radius: 8px;
        outline: none;
    }

    .form-input:focus, .form-select:focus {
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

    .badge-success {
        background: #d1fae5;
        color: #065f46;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-gray {
        background: #f3f4f6;
        color: #6b7280;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .pagination {
        display: flex;
        gap: 0.5rem;
        margin-top: 1.5rem;
    }

    .pagination a, .pagination span {
        padding: 0.5rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        color: #374151;
        text-decoration: none;
        background: #ffffff;
    }

    .pagination .active {
        background: #10b981;
        color: white;
        border-color: #10b981;
    }

    .pagination a:hover:not(.active) {
        background: #f9fafb;
    }

    /* Mobile card layout */
    .mobile-card {
        display: none;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .cabinet-card {
            padding: 1rem;
        }

        .cabinet-table {
            display: none;
        }

        .mobile-card {
            display: block;
        }

        .item-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .item-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .item-card-title {
            font-weight: 600;
            color: #111827;
            font-size: 0.9375rem;
            flex: 1;
        }

        .item-card-body {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .item-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-card-label {
            color: #6b7280;
            font-size: 0.8125rem;
        }

        .item-card-value {
            color: #111827;
            font-weight: 600;
        }

        .filter-form-mobile {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .filter-form-mobile > div {
            width: 100%;
        }

        .pagination {
            flex-wrap: wrap;
            justify-content: center;
        }

        .pagination a, .pagination span {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
    }
</style>
@endpush

@section('content')
<div style="max-width: 1400px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; color: #111827;">
            📦 Мониторинг позиций
        </h1>
        <div style="color: #6b7280;">
            Баланс: <strong style="color: #10b981;">{{ number_format(auth()->user()->balance, 2) }} ₽</strong>
        </div>
    </div>

    <!-- Filters -->
    <div class="cabinet-card">
        <form method="GET" action="{{ route('cabinet.items.index') }}" style="display: grid; grid-template-columns: 2fr 1fr auto auto; gap: 1rem; align-items: end;" class="filter-form-mobile">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: #374151;">Поиск</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Название или артикул..." class="form-input" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: #374151;">Статус</label>
                <select name="status" class="form-select" style="width: 100%;">
                    <option value="">Все статусы</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Ожидает</option>
                    <option value="has_offers" {{ request('status') === 'has_offers' ? 'selected' : '' }}>Есть предложения</option>
                    <option value="partial_offers" {{ request('status') === 'partial_offers' ? 'selected' : '' }}>Частично</option>
                    <option value="no_offers" {{ request('status') === 'no_offers' ? 'selected' : '' }}>Нет предложений</option>
                    <option value="clarification_needed" {{ request('status') === 'clarification_needed' ? 'selected' : '' }}>Требуется уточнение</option>
                </select>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; padding-top: 1.75rem;">
                <input type="checkbox" name="has_offers" id="has_offers" value="1" {{ request('has_offers') ? 'checked' : '' }} style="width: 1.25rem; height: 1.25rem;">
                <label for="has_offers" style="font-size: 0.875rem; font-weight: 600; color: #374151; white-space: nowrap;">Только с предложениями</label>
            </div>

            <button type="submit" class="btn-green" style="width: 100%;">Применить</button>
        </form>
    </div>

    <!-- Items Table -->
    <table class="cabinet-table">
        <thead>
            <tr>
                <th style="width: 60px;">№</th>
                <th>Название / Характеристики</th>
                <th style="width: 120px;">Количество</th>
                <th style="width: 150px;">Доступ</th>
                <th style="width: 120px;">Предложения</th>
                <th style="width: 140px;">Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
            <tr>
                <td style="color: #6b7280; font-family: monospace;">{{ $item->id }}</td>
                <td>
                    <div style="font-weight: 600; color: #111827; margin-bottom: 0.25rem;">
                        {{ $item->name }}
                    </div>
                    @if($item->brand)
                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem;">
                            🏷️ {{ $item->brand }}
                        </div>
                    @endif
                    @if($item->article)
                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem;">
                            📋 {{ $item->article }}
                        </div>
                    @endif
                    @if($item->characteristics)
                        <div style="font-size: 0.875rem; color: #9ca3af; margin-top: 0.5rem; line-height: 1.5;">
                            {{ \Illuminate\Support\Str::limit($item->characteristics, 200) }}
                        </div>
                    @endif
                </td>
                <td style="font-weight: 600; color: #111827;">
                    {{ rtrim(rtrim(number_format($item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit }}
                </td>
                <td>
                    @php
                        $hasAccess = in_array($item->id, $purchasedItemIds) ||
                                    ($item->request && in_array($item->request->request_number, $userRequestNumbers));
                    @endphp
                    @if($hasAccess)
                        <span class="status-badge status-full-access">Полный доступ</span>
                    @else
                        <span class="status-badge status-preview">Предпросмотр</span>
                    @endif
                </td>
                <td>
                    @php
                        $receivedOffersCount = $item->offers()->whereIn('status', ['received', 'processed'])->count();
                    @endphp
                    @if($receivedOffersCount > 0)
                        <div class="badge-success">{{ $receivedOffersCount }} шт</div>
                    @else
                        <div class="badge-gray">Нет</div>
                    @endif
                </td>
                <td>
                    <a href="{{ route('cabinet.items.show', $item->id) }}" style="color: #10b981; text-decoration: none; font-weight: 600;">
                        Открыть
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: 3rem; color: #9ca3af;">
                    Позиции не найдены
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Mobile Cards -->
    <div class="mobile-card">
        @forelse($items as $item)
        <div class="item-card">
            <div class="item-card-header">
                <div class="item-card-title">{{ $item->name }}</div>
                @php
                    $hasAccess = in_array($item->id, $purchasedItemIds) ||
                                ($item->request && in_array($item->request->request_number, $userRequestNumbers));
                @endphp
                @if($hasAccess)
                    <span class="status-badge status-full-access">Полный доступ</span>
                @else
                    <span class="status-badge status-preview">Предпросмотр</span>
                @endif
            </div>
            <div class="item-card-body">
                @if($item->article)
                <div class="item-card-row">
                    <span class="item-card-label">Артикул:</span>
                    <span class="item-card-value">{{ $item->article }}</span>
                </div>
                @endif
                @if($item->brand)
                <div class="item-card-row">
                    <span class="item-card-label">Бренд:</span>
                    <span class="item-card-value">{{ $item->brand }}</span>
                </div>
                @endif
                <div class="item-card-row">
                    <span class="item-card-label">Количество:</span>
                    <span class="item-card-value">{{ rtrim(rtrim(number_format($item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit }}</span>
                </div>
                <div class="item-card-row">
                    <span class="item-card-label">Предложения:</span>
                    @php
                        $receivedOffersCount = $item->offers()->whereIn('status', ['received', 'processed'])->count();
                    @endphp
                    @if($receivedOffersCount > 0)
                        <div class="badge-success">{{ $receivedOffersCount }} шт</div>
                    @else
                        <div class="badge-gray">Нет</div>
                    @endif
                </div>
                <a href="{{ route('cabinet.items.show', $item->id) }}" class="btn-green" style="display: block; text-align: center; text-decoration: none; margin-top: 0.75rem;">
                    Открыть отчет
                </a>
            </div>
        </div>
        @empty
        <div style="text-align: center; padding: 3rem; color: #9ca3af;">
            Позиции не найдены
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($items->hasPages())
        <div class="pagination">
            @if ($items->onFirstPage())
                <span style="opacity: 0.5;">← Назад</span>
            @else
                <a href="{{ $items->previousPageUrl() }}">← Назад</a>
            @endif

            @foreach ($items->getUrlRange(1, $items->lastPage()) as $page => $url)
                @if ($page == $items->currentPage())
                    <span class="active">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($items->hasMorePages())
                <a href="{{ $items->nextPageUrl() }}">Вперёд →</a>
            @else
                <span style="opacity: 0.5;">Вперёд →</span>
            @endif
        </div>
    @endif
</div>
@endsection
