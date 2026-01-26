@extends('layouts.cabinet')

@section('title', 'Акты')

@section('content')
<x-page-header title="Акты" description="Управление актами оказанных услуг">
    <x-slot name="actions">
        <x-button variant="primary" :href="route('admin.billing.acts.create')" icon="plus">
            Сформировать акт
        </x-button>
    </x-slot>
</x-page-header>

<!-- Статистика -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--primary-600); margin-bottom: var(--space-2);">
                {{ $stats['total'] }}
            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Всего актов</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--warning-600); margin-bottom: var(--space-2);">
                {{ $stats['generated'] }}
            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Сформировано</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--info-600); margin-bottom: var(--space-2);">
                {{ $stats['sent'] }}
            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Отправлено</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--success-600); margin-bottom: var(--space-2);">
                {{ $stats['signed'] }}
            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Подписано</div>
        </div>
    </div>
</div>

<!-- Фильтры -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.billing.acts.index') }}">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: var(--space-4); align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" class="input" placeholder="Номер акта, имя или email пользователя" value="{{ request('search') }}">
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Год</label>
                    <select name="year" class="select">
                        <option value="">Все</option>
                        @for($y = date('Y'); $y >= 2020; $y--)
                            <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Месяц</label>
                    <select name="month" class="select">
                        <option value="">Все</option>
                        @foreach(['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'] as $m => $monthName)
                            <option value="{{ $m + 1 }}" {{ request('month') == ($m + 1) ? 'selected' : '' }}>{{ $monthName }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Статус</label>
                    <select name="status" class="select">
                        <option value="">Все статусы</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Черновик</option>
                        <option value="generated" {{ request('status') === 'generated' ? 'selected' : '' }}>Сформирован</option>
                        <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Отправлен</option>
                        <option value="signed" {{ request('status') === 'signed' ? 'selected' : '' }}>Подписан</option>
                    </select>
                </div>

                <div style="display: flex; gap: var(--space-2);">
                    <x-button type="submit" variant="primary" icon="search">
                        Найти
                    </x-button>
                    @if(request()->hasAny(['search', 'year', 'month', 'status']))
                        <x-button type="button" variant="secondary" icon="x" onclick="window.location.href='{{ route('admin.billing.acts.index') }}'">
                            Сбросить
                        </x-button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Список актов -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        @if($acts->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>Номер</th>
                    <th>Период</th>
                    <th>Пользователь</th>
                    <th>Дата акта</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                    <th style="text-align: right;">Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($acts as $act)
                <tr>
                    <td style="font-family: var(--font-mono); font-weight: 600;">
                        <a href="{{ route('admin.billing.acts.show', $act->id) }}" style="color: var(--primary-600); text-decoration: none;">
                            {{ $act->number }}
                        </a>
                    </td>
                    <td style="white-space: nowrap;">
                        {{ $act->period_name }}
                    </td>
                    <td>
                        <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                            <a href="{{ route('admin.users.show', $act->user_id) }}" style="font-weight: 600; color: var(--neutral-900); text-decoration: none;">
                                {{ $act->user->name }}
                            </a>
                            <span style="font-size: var(--text-sm); color: var(--neutral-500);">{{ $act->user->email }}</span>
                        </div>
                    </td>
                    <td style="color: var(--neutral-600); font-size: var(--text-sm); white-space: nowrap;">
                        {{ $act->act_date->format('d.m.Y') }}
                    </td>
                    <td style="font-family: var(--font-mono); font-weight: 600; white-space: nowrap;">
                        {{ number_format($act->total, 2, ',', ' ') }} ₽
                    </td>
                    <td>
                        @php
                            $statusVariant = match($act->status) {
                                'draft' => 'secondary',
                                'generated' => 'warning',
                                'sent' => 'info',
                                'signed' => 'success',
                                default => 'secondary'
                            };
                            $statusText = match($act->status) {
                                'draft' => 'Черновик',
                                'generated' => 'Сформирован',
                                'sent' => 'Отправлен',
                                'signed' => 'Подписан',
                                default => $act->status
                            };
                        @endphp
                        <x-badge :variant="$statusVariant" size="sm">{{ $statusText }}</x-badge>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: var(--space-2); justify-content: flex-end;">
                            <x-button variant="secondary" size="sm" :href="route('admin.billing.acts.show', $act->id)" icon="eye">
                                Открыть
                            </x-button>
                            <x-button variant="primary" size="sm" :href="route('admin.billing.acts.download', $act->id)" icon="download" target="_blank">
                                PDF
                            </x-button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if($acts->hasPages())
        <div style="padding: var(--space-4); border-top: 1px solid var(--neutral-200);">
            {{ $acts->links() }}
        </div>
        @endif
        @else
        <div style="padding: var(--space-8); text-align: center;">
            <i data-lucide="inbox" style="width: 3rem; height: 3rem; color: var(--neutral-300); margin: 0 auto var(--space-4);"></i>
            <div style="font-size: var(--text-base); color: var(--neutral-600);">
                @if(request()->hasAny(['search', 'year', 'month', 'status']))
                    Акты не найдены
                @else
                    Актов еще нет
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
lucide.createIcons();
</script>
@endpush
@endsection
