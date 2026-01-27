@extends('layouts.cabinet')

@section('title', 'Промокоды')

@section('content')
<div style="max-width: 1600px; margin: 0 auto;">
    <x-page-header
        title="Промокоды"
        description="Управление промокодами и инвайтами для пользователей"
    >
        <x-slot name="actions">
            <x-button
                variant="accent"
                icon="plus"
                href="{{ route('admin.promo-codes.create') }}"
            >
                Сгенерировать промокоды
            </x-button>
            @if($stats['available'] > 0)
                <x-button
                    variant="secondary"
                    icon="download"
                    href="{{ route('admin.promo-codes.export', ['status' => 'available']) }}"
                >
                    Экспорт доступных
                </x-button>
            @endif
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <div class="alert alert-success">
            <i data-lucide="check-circle"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-error">
            <i data-lucide="alert-circle"></i>
            {{ session('error') }}
        </div>
    @endif

    <!-- Статистика -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
        <x-stat-card
            icon="ticket"
            icon-type="primary"
            :value="$stats['total']"
            label="Всего промокодов"
        />
        <x-stat-card
            icon="check-circle"
            icon-type="success"
            :value="$stats['used']"
            label="Использовано"
        />
        <x-stat-card
            icon="clock"
            icon-type="accent"
            :value="$stats['available']"
            label="Доступно"
        />
        <x-stat-card
            icon="dollar-sign"
            icon-type="success"
            :value="number_format($stats['total_amount'], 2) . ' ₽'"
            label="Общая сумма"
        />
        <x-stat-card
            icon="trending-up"
            icon-type="primary"
            :value="number_format($stats['used_amount'], 2) . ' ₽'"
            label="Активировано на"
        />
    </div>

    <!-- Фильтры -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.promo-codes.index') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; align-items: end;">
                <div class="form-group">
                    <label class="form-label">Поиск по коду</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Введите код..." class="input">
                </div>

                <div class="form-group">
                    <label class="form-label">Статус</label>
                    <select name="status" class="select">
                        <option value="">Все</option>
                        <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>Доступные</option>
                        <option value="used" {{ request('status') === 'used' ? 'selected' : '' }}>Использованные</option>
                    </select>
                </div>

                <x-button type="submit" variant="primary">Применить фильтры</x-button>
            </form>
        </div>
    </div>

    <!-- Таблица промокодов -->
    <div class="card" style="margin-top: var(--space-6);">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th style="width: 150px;">Код</th>
                    <th style="width: 120px;">Сумма</th>
                    <th style="width: 120px;">Статус</th>
                    <th>Использован</th>
                    <th style="width: 150px;">Дата использования</th>
                    <th>Описание</th>
                    <th style="width: 100px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                @forelse($promoCodes as $promoCode)
                    <tr>
                        <td>{{ $promoCode->id }}</td>
                        <td>
                            <code style="background: var(--surface-secondary); padding: 0.25rem 0.5rem; border-radius: 4px; font-family: monospace; font-weight: 600;">
                                {{ $promoCode->code }}
                            </code>
                        </td>
                        <td style="font-weight: 600; color: var(--success);">
                            {{ number_format($promoCode->amount, 2) }} ₽
                        </td>
                        <td>
                            @if($promoCode->is_used)
                                <span class="badge badge-success">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                    Использован
                                </span>
                            @else
                                <span class="badge badge-info">
                                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                    Доступен
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($promoCode->usedByUser)
                                <div>
                                    <div style="font-weight: 600;">{{ $promoCode->usedByUser->name }}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">{{ $promoCode->usedByUser->email }}</div>
                                </div>
                            @else
                                <span style="color: var(--text-tertiary);">—</span>
                            @endif
                        </td>
                        <td>
                            @if($promoCode->used_at)
                                <div style="font-size: 0.875rem;">
                                    {{ $promoCode->used_at->format('d.m.Y') }}
                                    <div style="color: var(--text-secondary);">{{ $promoCode->used_at->format('H:i') }}</div>
                                </div>
                            @else
                                <span style="color: var(--text-tertiary);">—</span>
                            @endif
                        </td>
                        <td>
                            @if($promoCode->description)
                                <div style="font-size: 0.875rem;">{{ $promoCode->description }}</div>
                            @else
                                <span style="color: var(--text-tertiary);">—</span>
                            @endif
                        </td>
                        <td>
                            @if(!$promoCode->is_used)
                                <form method="POST" action="{{ route('admin.promo-codes.destroy', $promoCode) }}" onsubmit="return confirm('Удалить этот промокод?');" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Удалить">
                                        <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: var(--space-8); color: var(--text-secondary);">
                            <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: var(--space-2);"></i>
                            <div>Промокоды не найдены</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($promoCodes->hasPages())
            <div class="card-footer">
                {{ $promoCodes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
