@extends('layouts.cabinet')

@section('title', 'Мониторинг позиций')

@push('styles')
<style>
    /* Mobile card layout */
    .mobile-card {
        display: none;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .table {
            display: none;
        }

        .mobile-card {
            display: block;
        }

        .item-card {
            background: var(--neutral-0);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-lg);
            padding: var(--space-3);
            margin-bottom: var(--space-3);
        }

        .item-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: var(--space-2);
            padding-bottom: var(--space-2);
            border-bottom: 1px solid var(--neutral-100);
        }

        .item-card-title {
            font-weight: 600;
            color: var(--neutral-900);
            font-size: 0.9375rem;
            flex: 1;
        }

        .item-card-body {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
            font-size: 0.875rem;
        }

        .item-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-card-label {
            color: var(--neutral-600);
            font-size: 0.8125rem;
        }

        .item-card-value {
            color: var(--neutral-900);
            font-weight: 600;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--space-3);
        }

        .filter-form button {
            width: 100%;
        }
    }

    @media (min-width: 769px) {
        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1.5fr 2fr auto;
            gap: var(--space-3);
            align-items: end;
        }

        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
    }
</style>
@endpush

@section('content')
<div style="max-width: 1400px; margin: 0 auto;">
    <x-page-header
        title="Мониторинг позиций"
        subtitle=""
    >
        <x-slot name="actions">
            <div class="text-muted">
                Баланс: <strong class="text-success">{{ number_format(auth()->user()->balance, 2) }} ₽</strong>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Filters -->
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-body">
            <form method="GET" action="{{ route('cabinet.items.index') }}" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Название или артикул..." class="input">
                </div>

                <div class="form-group">
                    <label class="form-label">Статус</label>
                    <select name="status" class="select">
                        <option value="">Все статусы</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Ожидает</option>
                        <option value="has_offers" {{ request('status') === 'has_offers' ? 'selected' : '' }}>Есть предложения</option>
                        <option value="partial_offers" {{ request('status') === 'partial_offers' ? 'selected' : '' }}>Частично</option>
                        <option value="no_offers" {{ request('status') === 'no_offers' ? 'selected' : '' }}>Нет предложений</option>
                        <option value="clarification_needed" {{ request('status') === 'clarification_needed' ? 'selected' : '' }}>Требуется уточнение</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="visibility: hidden; user-select: none;">.</label>
                    <div class="filter-checkbox">
                        <input type="checkbox" name="has_offers" id="has_offers" value="1" {{ request('has_offers') ? 'checked' : '' }} style="width: 1.25rem; height: 1.25rem;">
                        <label for="has_offers" style="font-size: var(--text-sm); font-weight: 600; white-space: nowrap; margin: 0;">Только с предложениями</label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="visibility: hidden; user-select: none;">.</label>
                    <x-button type="submit" variant="primary" icon="filter">Применить</x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card">
        <table class="table">
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
                    <td class="text-muted" style="font-family: monospace;">{{ $item->id }}</td>
                    <td>
                        <div style="font-weight: 600; color: var(--neutral-900); margin-bottom: var(--space-1);">
                            {{ $item->name }}
                        </div>
                        @if($item->brand)
                            <div style="font-size: 0.875rem; color: var(--neutral-600); margin-bottom: var(--space-1); display: flex; align-items: center; gap: var(--space-1);">
                                <i data-lucide="tag" style="width: 14px; height: 14px;"></i>
                                {{ $item->brand }}
                            </div>
                        @endif
                        @if($item->article)
                            <div style="font-size: 0.875rem; color: var(--neutral-600); margin-bottom: var(--space-1); display: flex; align-items: center; gap: var(--space-1);">
                                <i data-lucide="hash" style="width: 14px; height: 14px;"></i>
                                {{ $item->article }}
                            </div>
                        @endif
                        @if($item->characteristics)
                            <div style="font-size: 0.875rem; color: var(--neutral-500); margin-top: var(--space-2); line-height: 1.5;">
                                {{ \Illuminate\Support\Str::limit($item->characteristics, 200) }}
                            </div>
                        @endif
                    </td>
                    <td style="font-weight: 600;">
                        {{ rtrim(rtrim(number_format($item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit }}
                    </td>
                    <td>
                        @php
                            $hasAccess = in_array($item->id, $purchasedItemIds) ||
                                        ($item->request && in_array($item->request->request_number, $userRequestNumbers));
                        @endphp
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
                        @php
                            $receivedOffersCount = $item->offers()->whereIn('status', ['received', 'processed'])->count();
                        @endphp
                        @if($receivedOffersCount > 0)
                            <x-badge variant="success">{{ $receivedOffersCount }} шт</x-badge>
                        @else
                            <x-badge variant="neutral">Нет</x-badge>
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
                @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state
                            icon="package"
                            title="Позиции не найдены"
                            description=""
                        />
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
                            <x-badge variant="success">{{ $receivedOffersCount }} шт</x-badge>
                        @else
                            <x-badge variant="neutral">Нет</x-badge>
                        @endif
                    </div>
                    <x-button
                        href="{{ route('cabinet.items.show', $item->id) }}"
                        variant="primary"
                        style="margin-top: var(--space-2);"
                    >
                        Открыть отчет
                    </x-button>
                </div>
            </div>
            @empty
            <x-empty-state
                icon="package"
                title="Позиции не найдены"
                description=""
            />
            @endforelse
        </div>

        <!-- Pagination -->
        @if($items->hasPages())
            <div style="margin-top: var(--space-6); padding-top: var(--space-6); border-top: 1px solid var(--neutral-200);">
                <div class="pagination">
                    @if($items->onFirstPage())
                        <button class="pagination-nav-btn" disabled>
                            <i data-lucide="chevron-left" class="icon-sm"></i>
                            Назад
                        </button>
                    @else
                        <a href="{{ $items->previousPageUrl() }}" class="pagination-nav-btn">
                            <i data-lucide="chevron-left" class="icon-sm"></i>
                            Назад
                        </a>
                    @endif

                    @php
                        $start = max(1, $items->currentPage() - 2);
                        $end = min($items->lastPage(), $items->currentPage() + 2);
                    @endphp

                    @if($start > 1)
                        <a href="{{ $items->url(1) }}" class="pagination-btn">1</a>
                        @if($start > 2)
                            <span class="pagination-ellipsis">...</span>
                        @endif
                    @endif

                    @for($i = $start; $i <= $end; $i++)
                        <a href="{{ $items->url($i) }}" class="pagination-btn {{ $i === $items->currentPage() ? 'active' : '' }}">{{ $i }}</a>
                    @endfor

                    @if($end < $items->lastPage())
                        @if($end < $items->lastPage() - 1)
                            <span class="pagination-ellipsis">...</span>
                        @endif
                        <a href="{{ $items->url($items->lastPage()) }}" class="pagination-btn">{{ $items->lastPage() }}</a>
                    @endif

                    @if($items->hasMorePages())
                        <a href="{{ $items->nextPageUrl() }}" class="pagination-nav-btn">
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
    </div>
</div>

@push('scripts')
<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush
@endsection
