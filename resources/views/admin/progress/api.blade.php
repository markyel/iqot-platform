@extends('layouts.cabinet')

@php
    $v = $data['value'] ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0, '.', ' ');
    $msk = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->timezone('Europe/Moscow')->format('d.m H:i') : '—';
    $reasonRu = [
        'b2c_consumer_goods' => 'потребительский товар', 'out_of_scope' => 'вне тематики', 'insufficient_data' => 'мало данных',
        'duplicate' => 'дубль', 'no_suppliers_available' => 'нет поставщиков', 'moderator_rejected' => 'отклонено модератором',
        'unclassified' => 'не классифицирована', 'moderation_timeout' => 'не разобрана за 24 ч', 'deadline_expired' => 'истёк срок',
        'cancelled_by_client' => 'отменено клиентом',
    ];
@endphp

@section('title', 'API-заявки — ход выполнения')

@section('content')
    <x-page-header title="API-заявки" description="Заявки клиентов через публичный API: статусы, позиции, отклонения, резервы баланса" />
    @include('admin.progress._nav')

    @if (! ($data['ok'] ?? false))
        <div class="card"><div class="card-body"><p>Не удалось получить данные.</p>
            <p style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $data['error'] ?? '' }}</p></div></div>
    @else
        @php($h = $v['holds'])
        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card value="{{ $fmt(collect($v['clients'])->sum('subs')) }}" label="Заявок за {{ $v['days'] }} дн" icon="clipboard-list" icon-type="primary" />
            <x-stat-card value="{{ $fmt(collect($v['clients'])->sum('items')) }}" label="Позиций в них" icon="list" icon-type="primary" />
            <x-stat-card value="{{ $v['ready_minutes'] !== null ? round($v['ready_minutes'] / 60, 1) . ' ч' : '—' }}" label="Среднее время до готовности" icon="clock" icon-type="primary" />
            <x-stat-card value="{{ $fmt($h['held']->amount ?? 0) }} ₽" label="В резерве ({{ $fmt($h['held']->c ?? 0) }})" icon="lock" icon-type="warning" />
            <x-stat-card value="{{ $fmt($h['charged']->amount ?? 0) }} ₽" label="Списано ({{ $fmt($h['charged']->c ?? 0) }})" icon="credit-card" icon-type="success" />
        </div>

        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Клиенты API</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Клиент</th><th>Активен</th><th>Баланс</th><th>Заявок</th><th>Заявки по статусам</th><th>Позиции по статусам</th></tr></thead>
                    <tbody>
                        @forelse ($v['clients'] as $c)
                            <tr>
                                <td><strong>{{ $c['name'] }}</strong> <span style="color: var(--color-text-muted);">#{{ $c['id'] }}</span></td>
                                <td>{!! $c['active'] ? '<span class="badge badge-success">да</span>' : '<span class="badge badge-error">нет</span>' !!}</td>
                                <td>{{ $fmt($c['balance']) }} ₽</td>
                                <td>{{ $c['subs'] }}</td>
                                <td style="font-size: var(--text-sm);">@foreach ($c['by_status'] as $st => $n){{ $st }}: {{ $n }}@if (!$loop->last) · @endif @endforeach</td>
                                <td style="font-size: var(--text-sm);">@foreach ($c['items_by_status'] as $st => $n){{ $st }}: {{ $n }}@if (!$loop->last) · @endif @endforeach</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="color: var(--color-text-muted);">Клиентов API нет.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Причины отклонения позиций (за {{ $v['days'] }} дн)</h3></div>
            <div class="table-container">
                <table class="table">
                    <tbody>
                        @forelse ($v['reasons'] as $r => $n)
                            <tr><td style="width: 340px;">{{ $reasonRu[$r] ?? $r }} <span style="color: var(--color-text-muted);">({{ $r }})</span></td><td>{{ $n }}</td></tr>
                        @empty
                            <tr><td style="color: var(--color-text-muted);">Отклонений нет.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Последние заявки</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Заявка</th><th>Клиент</th><th>Статус / стадия</th><th>Позиций (принято / откл.)</th><th>Создана</th><th>Готова</th><th>Срок</th><th>Боевая заявка</th></tr></thead>
                    <tbody>
                        @forelse ($v['recent'] as $s)
                            <tr>
                                <td><a href="{{ route('admin.api-submissions.show', $s->id) }}">sub_{{ $s->external_id }}</a><div style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $s->client_ref ?? '' }}</div></td>
                                <td style="font-size: var(--text-sm);">{{ $s->client ?? '—' }}</td>
                                <td><span class="badge badge-primary">{{ $s->status }}</span> <span style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $s->stage }}</span></td>
                                <td>{{ $s->items_total }} ({{ $s->items_accepted }} / {{ $s->items_rejected }})</td>
                                <td style="font-size: var(--text-sm);">{{ $msk($s->created_at) }}</td>
                                <td style="font-size: var(--text-sm);">{{ $msk($s->ready_at) }}</td>
                                <td style="font-size: var(--text-sm);">{{ $msk($s->deadline_at) }}</td>
                                <td>@if ($s->internal_request_id)<a href="{{ route('admin.progress.request', $s->internal_request_id) }}">#{{ $s->internal_request_id }}</a>@else — @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="color: var(--color-text-muted);">Заявок нет.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
