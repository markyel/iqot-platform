@extends('layouts.cabinet')

@section('title', 'Мониторинг позиций')

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

    .status-pending { background: #f3f4f6; color: #6b7280; }
    .status-has-offers { background: #d1fae5; color: #065f46; }
    .status-partial-offers { background: #fef3c7; color: #92400e; }
    .status-no-offers { background: #fee2e2; color: #991b1b; }
    .status-clarification-needed { background: #dbeafe; color: #1e40af; }

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
</style>
@endpush

@section('content')
<div style="max-width: 1800px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;">Мониторинг позиций</h1>
        <p style="color: #6b7280;">Все товарные позиции из заявок</p>
    </div>

    <!-- Фильтры -->
    <div class="admin-card">
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div>
                <label style="display: block; color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Поиск по названию</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Введите название..." class="form-input" style="width: 100%;">
            </div>
            
            <div>
                <label style="display: block; color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Бренд</label>
                <input type="text" name="brand" value="{{ request('brand') }}" placeholder="Введите бренд..." class="form-input" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Статус</label>
                <select name="status" class="form-select" style="width: 100%;">
                    <option value="">Все статусы</option>
                    @foreach(\App\Models\ExternalRequestItem::getStatusLabels() as $value => $label)
                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display: block; color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">&nbsp;</label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="has_offers" value="1" {{ request('has_offers') ? 'checked' : '' }} style="width: 16px; height: 16px;">
                    <span style="color: #111827; font-size: 0.875rem;">Только с предложениями</span>
                </label>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn-green">Применить</button>
                @if(request()->hasAny(['search', 'brand', 'status', 'has_offers']))
                    <a href="{{ route('admin.items.index') }}" style="padding: 0.625rem 1rem; border-radius: 8px; background: #f3f4f6; color: #374151; text-decoration: none; font-weight: 600;">
                        Сбросить
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Список позиций -->
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width: 80px;">№</th>
                <th>Название / Характеристики</th>
                <th style="width: 150px;">Заявка</th>
                <th style="width: 120px;">Количество</th>
                <th style="width: 150px;">Предложения</th>
                <th style="width: 120px;">Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
            <tr>
                <td style="color: #6b7280; font-weight: 600;">{{ $item->position_number }}</td>
                <td>
                    <div style="color: #111827; font-weight: 500; margin-bottom: 0.25rem;">{{ $item->name }}</div>
                    <div style="color: #6b7280; font-size: 0.75rem;">
                        @if($item->brand)
                            <span>Бренд: <strong>{{ $item->brand }}</strong></span>
                        @endif
                        @if($item->article)
                            • <span>Артикул: <strong>{{ $item->article }}</strong></span>
                        @endif
                        @if($item->category)
                            • <span>Категория: {{ $item->category }}</span>
                        @endif
                    </div>
                </td>
                <td>
                    @if($item->request)
                        <a href="{{ route('admin.external-requests.show', $item->request) }}" style="color: #10b981; text-decoration: none; font-size: 0.875rem; font-weight: 500;">
                            {{ $item->request->request_number }}
                        </a>
                    @else
                        <span style="color: #6b7280;">—</span>
                    @endif
                </td>
                <td style="color: #111827; font-weight: 600;">
                    {{ rtrim(rtrim(number_format($item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit }}
                </td>
                <td>
                    @php
                        $receivedOffersCount = $item->offers()->whereIn('status', ['received', 'processed'])->count();
                    @endphp
                    @if($receivedOffersCount > 0)
                        <div class="badge-success">{{ $receivedOffersCount }} шт</div>
                        @if($item->min_price)
                            <div style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;">
                                от {{ number_format($item->min_price, 2) }} ₽
                            </div>
                        @endif
                    @else
                        <span class="badge-gray">Нет</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.items.show', $item) }}" class="btn-green" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none; display: inline-block;">
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

    <!-- Пагинация -->
    @if($items->hasPages())
        <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="color: #6b7280; font-size: 0.875rem;">
                Показано {{ $items->firstItem() }}–{{ $items->lastItem() }} из {{ $items->total() }}
            </div>
            <div style="display: flex; gap: 0.5rem;">
                @if($items->onFirstPage())
                    <span style="background: #f3f4f6; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #9ca3af; cursor: not-allowed;">
                        ← Назад
                    </span>
                @else
                    <a href="{{ $items->previousPageUrl() }}" style="background: #ffffff; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #10b981; text-decoration: none;">
                        ← Назад
                    </a>
                @endif

                @foreach($items->getUrlRange(max(1, $items->currentPage() - 2), min($items->lastPage(), $items->currentPage() + 2)) as $page => $url)
                    @if($page == $items->currentPage())
                        <span style="background: #10b981; border: 1px solid #10b981; padding: 0.5rem 0.875rem; border-radius: 6px; color: #fff; font-weight: 600; min-width: 40px; text-align: center;">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}" style="background: #ffffff; border: 1px solid #e5e7eb; padding: 0.5rem 0.875rem; border-radius: 6px; color: #374151; text-decoration: none; min-width: 40px; text-align: center; display: inline-block;">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach

                @if($items->hasMorePages())
                    <a href="{{ $items->nextPageUrl() }}" style="background: #ffffff; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #10b981; text-decoration: none;">
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
