@extends('layouts.cabinet')

@php
    $v = $data['value'] ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0, '.', ' ');
    $pct = fn ($a, $b) => $b > 0 ? number_format(100 * $a / $b, 1, ',', '') . '%' : '—';
@endphp

@section('title', 'Охват поставщиков')

@section('content')
    <x-page-header title="Охват поставщиков" description="Письма по обычным заявкам (без мониторинга цен) за {{ $v['days'] ?? 30 }} дней: кто получает, кто отвечает. Обновляется раз в 10 минут." />
    @include('admin.progress._nav')

    @if (! ($data['ok'] ?? false))
        <div class="card"><div class="card-body"><p>Не удалось получить данные.</p>
            <p style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $data['error'] ?? '' }}</p></div></div>
    @else
        @php($t = $v['t']) @php($p = $v['positions'])
        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card value="{{ $fmt($t->letters) }}" label="Писем (≈{{ $fmt($t->letters / max(1, $t->days)) }} в день)" icon="mail" icon-type="primary" />
            <x-stat-card value="{{ $fmt($t->sups) }} из {{ $fmt($v['base']) }}" label="Поставщиков получили ({{ $pct($t->sups, $v['base']) }} базы)" icon="users" icon-type="primary" />
            <x-stat-card value="{{ $pct($t->rep, $t->letters) }}" label="Ответили на письма" icon="message-circle" icon-type="success" />
            <x-stat-card value="{{ $pct($t->kp, $t->letters) }}" label="Письма с ценой" icon="check-circle" icon-type="success" />
        </div>
        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card value="{{ $fmt($p->items) }}" label="Позиций в заявках" icon="list" icon-type="primary" />
            <x-stat-card value="{{ number_format((float) $p->asked, 1, ',', '') }}" label="Спрошено поставщиков на позицию" icon="send" icon-type="primary" />
            <x-stat-card value="{{ $pct($p->ge1, $p->items) }}" label="Позиций с ≥1 ценой" icon="check" icon-type="success" />
            <x-stat-card value="{{ $pct($p->ge3, $p->items) }}" label="Позиций с ≥3 ценами" icon="trending-up" icon-type="{{ $p->items > 0 && $p->ge3 / $p->items < 0.2 ? 'warning' : 'success' }}" />
        </div>

        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Сколько писем получает поставщик</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Писем за период</th><th>Поставщиков</th><th>Писем</th><th>Доля писем</th><th>Ответили</th><th>С ценой</th></tr></thead>
                    <tbody>
                        @foreach ($v['buckets'] as $b)
                            <tr>
                                <td><strong>{{ $b->grp }}</strong></td><td>{{ $fmt($b->sups) }}</td><td>{{ $fmt($b->letters) }}</td>
                                <td>{{ $pct($b->letters, $t->letters) }}</td><td>{{ $pct($b->rep, $b->letters) }}</td><td>{{ $pct($b->kp, $b->letters) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Позиций в письме</h3></div>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>Позиций</th><th>Писем</th><th>Ответили</th><th>С ценой</th></tr></thead>
                        <tbody>
                            @foreach ($v['by_k'] as $k)
                                <tr><td>{{ $k->k }}</td><td>{{ $fmt($k->letters) }}</td><td>{{ $pct($k->rep, $k->letters) }}</td><td>{{ $pct($k->kp, $k->letters) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">По дням</h3></div>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>День</th><th>Писем</th><th>Поставщиков</th><th>Позиций в письме</th></tr></thead>
                        <tbody>
                            @foreach ($v['by_day'] as $d)
                                <tr><td>{{ \Illuminate\Support\Carbon::parse($d->d)->format('d.m') }}</td><td>{{ $fmt($d->letters) }}</td><td>{{ $fmt($d->sups) }}</td><td>{{ $d->per_letter }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Больше всего писем получают</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Поставщик</th><th>Охват типов</th><th>Писем</th><th>В день</th><th>Ответили</th><th>С ценой</th></tr></thead>
                    <tbody>
                        @foreach ($v['top'] as $s)
                            <tr>
                                <td><strong>{{ \Illuminate\Support\Str::limit($s->name, 40) }}</strong> <span style="color: var(--color-text-muted);">#{{ $s->supplier_id }}</span>
                                    <div style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $s->email }}</div></td>
                                <td>{!! $s->scope === 'all' ? '<span class="badge badge-warning">все типы</span>' : '<span class="badge">по списку</span>' !!}</td>
                                <td>{{ $fmt($s->c) }}</td><td>{{ $s->per_day }}</td>
                                <td style="{{ $s->rep == 0 ? 'color: var(--color-error);' : '' }}">{{ $fmt($s->rep) }} ({{ $pct($s->rep, $s->c) }})</td>
                                <td>{{ $fmt($s->kp) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
