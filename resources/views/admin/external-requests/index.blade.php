@extends('layouts.cabinet')

@section('title', 'Заявки из базы отчетов')

<x-page-header
    title="Заявки"
    description="Заявки из системы ценовых котировок"
/>

@section('content')
<div style="max-width: 1600px; margin: 0 auto;">
    <!-- Фильтры -->
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-body">
            <form method="GET" style="display: flex; gap: var(--space-4); align-items: center; flex-wrap: wrap;">
                <select name="status" class="select">
                    <option value="">Все статусы</option>
                    @foreach(\App\Models\ExternalRequest::getStatusLabels() as $value => $label)
                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="is_customer_request" class="select">
                    <option value="">Все типы</option>
                    <option value="1" {{ request('is_customer_request') === '1' ? 'selected' : '' }}>Клиентские заявки</option>
                    <option value="0" {{ request('is_customer_request') === '0' ? 'selected' : '' }}>Внутренние заявки</option>
                </select>

                <x-button type="submit" variant="primary">
                    <i data-lucide="filter" style="width: 1rem; height: 1rem;"></i>
                    Применить
                </x-button>

                @if(request()->hasAny(['status', 'is_customer_request']))
                    <x-button tag="a" href="{{ route('admin.external-requests.index') }}" variant="secondary">
                        Сбросить
                    </x-button>
                @endif
            </form>
        </div>
    </div>

    <!-- Список заявок -->
    <table class="table">
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
                <td data-label="Номер">
                    <div style="color: var(--neutral-900); font-family: var(--font-mono); font-weight: 600;">{{ $request->request_number }}</div>
                    @if($request->is_customer_request)
                        <x-badge type="success" size="sm" style="margin-top: var(--space-1);">
                            <i data-lucide="user" style="width: 0.75rem; height: 0.75rem;"></i>
                            Клиентская
                        </x-badge>
                    @endif
                </td>
                <td data-label="Заголовок / Позиции">
                    <div style="color: var(--neutral-900); font-weight: 500; margin-bottom: var(--space-2);">{{ $request->title ?: '—' }}</div>
                    @if($request->items->count() > 0)
                        <div style="background: var(--neutral-50); border-radius: var(--radius-sm); padding: var(--space-2); margin-top: var(--space-2);">
                            <div style="color: var(--neutral-600); font-size: 0.75rem; margin-bottom: var(--space-1);">Позиции ({{ $request->total_items }}):</div>
                            @foreach($request->items as $item)
                                <div style="color: var(--neutral-700); font-size: 0.75rem; padding: 0.125rem 0;">
                                    {{ $item->position_number }}. {{ Str::limit($item->name, 50) }}
                                    @if($item->offers_count > 0)
                                        <span style="color: var(--success-600); font-weight: 600;">({{ $item->offers_count }})</span>
                                    @endif
                                </div>
                            @endforeach
                            @if($request->total_items > $request->items->count())
                                <div style="color: var(--neutral-600); font-size: 0.75rem; margin-top: var(--space-1); font-style: italic;">
                                    и ещё {{ $request->total_items - $request->items->count() }}...
                                </div>
                            @endif
                        </div>
                    @endif
                </td>
                <td data-label="Статус / Прогресс">
                    @php
                        $statusMap = [
                            'draft' => 'secondary',
                            'new' => 'info',
                            'active' => 'success',
                            'collecting' => 'warning',
                            'completed' => 'success',
                            'cancelled' => 'danger',
                            'emails_sent' => 'info',
                            'responses_received' => 'primary',
                            'queued_for_sending' => 'warning'
                        ];
                        $statusType = $statusMap[$request->status] ?? 'secondary';
                        $statusLabel = \App\Models\ExternalRequest::getStatusLabels()[$request->status] ?? $request->status;
                    @endphp
                    <div style="margin-bottom: var(--space-3);">
                        <x-badge type="{{ $statusType }}">{{ $statusLabel }}</x-badge>
                    </div>
                    <div style="color: var(--neutral-900); font-size: 0.875rem; font-weight: 600; margin-bottom: var(--space-1);">
                        {{ number_format($request->completion_percentage, 0) }}% • {{ $request->items_with_offers }}/{{ $request->total_items }}
                    </div>
                    <div style="width: 120px; height: 8px; background: var(--neutral-200); border-radius: var(--radius-sm); overflow: hidden;">
                        <div style="height: 100%; background: var(--success-500); width: {{ $request->completion_percentage }}%; transition: width 0.3s;"></div>
                    </div>
                </td>
                <td data-label="Дата создания">
                    <div style="color: var(--neutral-900); font-weight: 500;">{{ $request->created_at ? $request->created_at->format('d.m.Y') : '—' }}</div>
                    @if($request->collection_deadline)
                        <div style="color: var(--neutral-600); font-size: 0.75rem; margin-top: var(--space-1);">
                            До: {{ $request->collection_deadline->format('d.m.Y') }}
                        </div>
                    @endif
                </td>
                <td data-label="Действия">
                    <x-button tag="a" href="{{ route('admin.external-requests.show', $request) }}" variant="primary" size="sm">
                        Открыть
                    </x-button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5">
                    <x-empty-state
                        icon="inbox"
                        title="Заявок не найдено"
                        description="Попробуйте изменить фильтры или создать новую заявку"
                    />
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Пагинация -->
    @if($requests->hasPages())
        <div style="margin-top: var(--space-8); display: flex; justify-content: space-between; align-items: center;">
            <div style="color: var(--neutral-600); font-size: 0.875rem;">
                Показано {{ $requests->firstItem() }}–{{ $requests->lastItem() }} из {{ $requests->total() }}
            </div>
            <div style="display: flex; gap: var(--space-2);">
                {{-- Предыдущая страница --}}
                @if($requests->onFirstPage())
                    <span style="background: var(--neutral-100); border: 1px solid var(--neutral-200); padding: var(--space-2) var(--space-4); border-radius: var(--radius-md); color: var(--neutral-400); cursor: not-allowed;">
                        <i data-lucide="chevron-left" style="width: 1rem; height: 1rem;"></i>
                        Назад
                    </span>
                @else
                    <a href="{{ $requests->previousPageUrl() }}" style="background: white; border: 1px solid var(--neutral-200); padding: var(--space-2) var(--space-4); border-radius: var(--radius-md); color: var(--primary-600); text-decoration: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: var(--space-1);">
                        <i data-lucide="chevron-left" style="width: 1rem; height: 1rem;"></i>
                        Назад
                    </a>
                @endif

                {{-- Номера страниц --}}
                @foreach($requests->getUrlRange(max(1, $requests->currentPage() - 2), min($requests->lastPage(), $requests->currentPage() + 2)) as $page => $url)
                    @if($page == $requests->currentPage())
                        <span style="background: var(--primary-600); border: 1px solid var(--primary-600); padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); color: white; font-weight: 600; min-width: 40px; text-align: center;">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}" style="background: white; border: 1px solid var(--neutral-200); padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); color: var(--neutral-700); text-decoration: none; transition: all 0.2s; min-width: 40px; text-align: center; display: inline-block;">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach

                {{-- Следующая страница --}}
                @if($requests->hasMorePages())
                    <a href="{{ $requests->nextPageUrl() }}" style="background: white; border: 1px solid var(--neutral-200); padding: var(--space-2) var(--space-4); border-radius: var(--radius-md); color: var(--primary-600); text-decoration: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: var(--space-1);">
                        Вперёд
                        <i data-lucide="chevron-right" style="width: 1rem; height: 1rem;"></i>
                    </a>
                @else
                    <span style="background: var(--neutral-100); border: 1px solid var(--neutral-200); padding: var(--space-2) var(--space-4); border-radius: var(--radius-md); color: var(--neutral-400); cursor: not-allowed;">
                        Вперёд
                        <i data-lucide="chevron-right" style="width: 1rem; height: 1rem;"></i>
                    </span>
                @endif
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush
@endsection
