@extends('layouts.cabinet')

@section('title', 'Акты пользователя ' . $user->name)

@section('content')
<x-page-header
    title="Акты пользователя"
    :description="$user->name . ' (' . $user->email . ')'"
>
    <x-slot:actions>
        <x-button
            variant="secondary"
            icon="arrow-left"
            :href="route('admin.users.show', $user->id)"
        >
            Назад к профилю
        </x-button>
    </x-slot:actions>
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
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--success-600); margin-bottom: var(--space-2);">
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
            <div style="font-size: var(--text-2xl); font-weight: 700; color: var(--accent-600); margin-bottom: var(--space-2);">
                {{ number_format($stats['total_amount'], 2) }} ₽
            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Всего по актам</div>
        </div>
    </div>
</div>

<!-- Таблица актов -->
@if($acts->isEmpty())
    <div class="card">
        <div class="card-body" style="text-align: center; padding: var(--space-12) var(--space-6);">
            <i data-lucide="file-text" style="width: 48px; height: 48px; color: var(--neutral-400); margin: 0 auto var(--space-4);"></i>
            <h3 style="margin: 0 0 var(--space-2); font-size: var(--text-lg); color: var(--neutral-700);">
                Акты не найдены
            </h3>
            <p style="margin: 0; color: var(--neutral-600);">
                Для этого пользователя еще не сформировано ни одного акта
            </p>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Номер</th>
                        <th>Дата акта</th>
                        <th>Период</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th style="text-align: right;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($acts as $act)
                    <tr>
                        <td>
                            <strong>{{ $act->number }}</strong>
                        </td>
                        <td>
                            {{ $act->act_date->format('d.m.Y') }}
                        </td>
                        <td>
                            <div style="font-weight: 600;">{{ $act->period_name }}</div>
                            <div style="font-size: var(--text-sm); color: var(--neutral-600);">
                                {{ $act->items->count() }} {{ \Illuminate\Support\Str::plural('позиция', $act->items->count(), ['позиция', 'позиции', 'позиций']) }}
                            </div>
                        </td>
                        <td>
                            <strong>{{ number_format($act->total, 2, ',', ' ') }} ₽</strong>
                            @if($act->vat_amount > 0)
                                <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 2px;">
                                    в т.ч. НДС {{ number_format($act->vat_amount, 2, ',', ' ') }} ₽
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($act->status === 'generated')
                                <span class="badge badge-success">
                                    <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                                    Сформирован
                                </span>
                                @if($act->generated_at)
                                    <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 2px;">
                                        {{ $act->generated_at->format('d.m.Y H:i') }}
                                    </div>
                                @endif
                            @elseif($act->status === 'sent')
                                <span class="badge badge-info">
                                    <i data-lucide="send" style="width: 14px; height: 14px;"></i>
                                    Отправлен
                                </span>
                                @if($act->sent_at)
                                    <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 2px;">
                                        {{ $act->sent_at->format('d.m.Y H:i') }}
                                    </div>
                                @endif
                            @elseif($act->status === 'signed')
                                <span class="badge badge-success">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                    Подписан
                                </span>
                                @if($act->signed_at)
                                    <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 2px;">
                                        {{ $act->signed_at->format('d.m.Y H:i') }}
                                    </div>
                                @endif
                            @elseif($act->status === 'draft')
                                <span class="badge badge-secondary">
                                    <i data-lucide="file" style="width: 14px; height: 14px;"></i>
                                    Черновик
                                </span>
                            @else
                                <span class="badge badge-secondary">{{ $act->status }}</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: var(--space-2); justify-content: flex-end;">
                                <x-button
                                    variant="secondary"
                                    size="sm"
                                    :href="route('admin.billing.acts.show', $act->id)"
                                    icon="eye"
                                >
                                    Просмотр
                                </x-button>
                                <x-button
                                    variant="primary"
                                    size="sm"
                                    :href="route('admin.billing.acts.download', $act->id)"
                                    icon="download"
                                >
                                    PDF
                                </x-button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($acts->hasPages())
        <div style="margin-top: var(--space-6);">
            {{ $acts->links() }}
        </div>
    @endif
@endif

@endsection
