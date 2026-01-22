@extends('layouts.cabinet')

@section('title', 'Мониторинг позиций')

@section('content')
<div style="max-width: 1800px; margin: 0 auto;">
    <x-page-header
        title="Мониторинг позиций"
        description="Все товарные позиции из заявок"
    />

    <!-- Фильтры -->
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-body">
            <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); align-items: end;">
                <div class="form-group">
                    <label class="form-label">Поиск по названию</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Введите название..." class="input">
                </div>

                <div class="form-group">
                    <label class="form-label">Бренд</label>
                    <input type="text" name="brand" value="{{ request('brand') }}" placeholder="Введите бренд..." class="input">
                </div>

                <div class="form-group">
                    <label class="form-label">Статус</label>
                    <select name="status" class="select">
                        <option value="">Все статусы</option>
                        @foreach(\App\Models\ExternalRequestItem::getStatusLabels() as $value => $label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display: block; color: transparent; font-size: 0.875rem; margin-bottom: var(--space-2);">&nbsp;</label>
                    <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                        <input type="checkbox" name="has_offers" value="1" {{ request('has_offers') ? 'checked' : '' }} style="width: 16px; height: 16px;">
                        <span style="color: var(--neutral-900); font-size: 0.875rem;">Только с предложениями</span>
                    </label>
                </div>

                <div style="display: flex; gap: var(--space-2);">
                    <x-button type="submit">Применить</x-button>
                    @if(request()->hasAny(['search', 'brand', 'status', 'has_offers']))
                        <x-button variant="secondary" href="{{ route('admin.items.index') }}">Сбросить</x-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Список позиций -->
    <table class="table">
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
                <td data-label="№" style="color: var(--neutral-600); font-weight: 600;">{{ $item->position_number }}</td>
                <td data-label="Название">
                    <div style="color: var(--neutral-900); font-weight: 500; margin-bottom: var(--space-1);">{{ $item->name }}</div>
                    <div style="color: var(--neutral-600); font-size: 0.75rem;">
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
                <td data-label="Заявка">
                    @if($item->request)
                        <span style="color: var(--primary-600); font-size: 0.875rem; font-weight: 500;">
                            {{ $item->request->request_number }}
                        </span>
                    @else
                        <span style="color: var(--neutral-500);">—</span>
                    @endif
                </td>
                <td data-label="Количество" style="color: var(--neutral-900); font-weight: 600;">
                    {{ rtrim(rtrim(number_format($item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit }}
                </td>
                <td data-label="Предложения">
                    @php
                        $receivedOffersCount = $item->offers()
                            ->whereIn('status', ['received', 'processed'])
                            ->whereNotNull('price_per_unit')
                            ->where('price_per_unit', '>', 0)
                            ->count();
                    @endphp
                    @if($receivedOffersCount > 0)
                        <x-badge variant="success">{{ $receivedOffersCount }} шт</x-badge>
                        @if($item->min_price)
                            <div style="color: var(--neutral-600); font-size: 0.75rem; margin-top: var(--space-1);">
                                от {{ number_format($item->min_price, 2) }} ₽
                            </div>
                        @endif
                    @else
                        <x-badge variant="neutral">Нет</x-badge>
                    @endif
                </td>
                <td data-label="Действия">
                    <x-button size="sm" href="{{ route('admin.items.show', $item) }}">
                        Открыть
                    </x-button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: var(--space-12); color: var(--neutral-500);">
                    <x-empty-state
                        icon="package-search"
                        title="Позиции не найдены"
                        description="Попробуйте изменить параметры фильтрации"
                    />
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Пагинация -->
    @if($items->hasPages())
        <div style="margin-top: var(--space-6); display: flex; justify-content: space-between; align-items: center;">
            <div style="color: var(--neutral-600); font-size: 0.875rem;">
                Показано {{ $items->firstItem() }}–{{ $items->lastItem() }} из {{ $items->total() }}
            </div>
            <div style="display: flex; gap: var(--space-2);">
                @if($items->onFirstPage())
                    <span style="background: var(--neutral-100); border: 1px solid var(--neutral-200); padding: var(--space-2) var(--space-4); border-radius: 6px; color: var(--neutral-400); cursor: not-allowed;">
                        ← Назад
                    </span>
                @else
                    <a href="{{ $items->previousPageUrl() }}" style="background: white; border: 1px solid var(--neutral-200); padding: var(--space-2) var(--space-4); border-radius: 6px; color: var(--primary-600); text-decoration: none;">
                        ← Назад
                    </a>
                @endif

                @foreach($items->getUrlRange(max(1, $items->currentPage() - 2), min($items->lastPage(), $items->currentPage() + 2)) as $page => $url)
                    @if($page == $items->currentPage())
                        <span style="background: var(--primary-600); border: 1px solid var(--primary-600); padding: var(--space-2) var(--space-3); border-radius: 6px; color: white; font-weight: 600; min-width: 40px; text-align: center;">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}" style="background: white; border: 1px solid var(--neutral-200); padding: var(--space-2) var(--space-3); border-radius: 6px; color: var(--neutral-700); text-decoration: none; min-width: 40px; text-align: center; display: inline-block;">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach

                @if($items->hasMorePages())
                    <a href="{{ $items->nextPageUrl() }}" style="background: white; border: 1px solid var(--neutral-200); padding: var(--space-2) var(--space-4); border-radius: 6px; color: var(--primary-600); text-decoration: none;">
                        Вперёд →
                    </a>
                @else
                    <span style="background: var(--neutral-100); border: 1px solid var(--neutral-200); padding: var(--space-2) var(--space-4); border-radius: 6px; color: var(--neutral-400); cursor: not-allowed;">
                        Вперёд →
                    </span>
                @endif
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
@endsection
