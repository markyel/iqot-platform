@extends('layouts.cabinet')

@php
    $v = $data['value'] ?? null;
    $req = $v['req'] ?? null;
    $fmt = fn ($n) => number_format((float) $n, 0, '.', ' ');
    $msk = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->timezone('Europe/Moscow')->format('d.m.Y H:i') : '—';
@endphp

@section('title', 'Заявка ' . ($req->request_number ?? ''))

@section('content')
    <x-page-header
        title="Заявка {{ $req->request_number ?? ('#' . ($req->id ?? '')) }}"
        description="{{ \Illuminate\Support\Str::limit($req->title ?? '', 120) }}"
    />
    @include('admin.progress._nav')

    @if (! ($data['ok'] ?? false))
        <div class="card"><div class="card-body"><p>Не удалось получить данные.</p>
            <p style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $data['error'] ?? '' }}</p></div></div>
    @else
        @php($s = $v['summary'])
        @php($l = $v['letters'])
        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card value="{{ $s['items'] }}" label="Позиций" icon="list" icon-type="primary" />
            <x-stat-card value="{{ $s['priced1'] }}" label="С ≥1 ценой" icon="check-circle" icon-type="success" />
            <x-stat-card value="{{ $s['priced3'] }}" label="С ≥3 ценами (минимум)" icon="trending-up" icon-type="success" />
            <x-stat-card value="{{ $s['no_pool'] }}" label="Без поставщиков" icon="alert-triangle" icon-type="{{ $s['no_pool'] > 0 ? 'warning' : 'primary' }}" />
        </div>

        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Заявка и рассылка</h3></div>
            <div class="table-container">
                <table class="table">
                    <tbody>
                        <tr><th style="width: 260px;">Статус в БД</th><td>{{ $req->status }}</td></tr>
                        <tr><th>Источник</th><td>{{ $req->source ?? '—' }}{{ !empty($req->is_customer_request) ? ' · именная заявка клиента' : '' }}</td></tr>
                        <tr><th>Создана / обновлена</th><td>{{ $msk($req->created_at) }} / {{ $msk($req->updated_at) }}</td></tr>
                        <tr><th>Писем всего</th><td>{{ $fmt($l->total ?? 0) }}: отправлено {{ $fmt($l->sent ?? 0) }}, в очереди {{ $fmt($l->pending ?? 0) }}, резерв {{ $fmt($l->held ?? 0) }}, ошибок {{ $fmt($l->failed ?? 0) }}</td></tr>
                        <tr><th>Ответили</th><td style="color: var(--color-success);">{{ $fmt($l->replied ?? 0) }}{{ ($l->sent ?? 0) > 0 ? ' (' . round(100 * $l->replied / $l->sent, 1) . '% отправленных)' : '' }}</td></tr>
                        <tr><th>Первое / последнее письмо</th><td>{{ $msk($l->first_sent ?? null) }} / {{ $msk($l->last_sent ?? null) }}</td></tr>
                        <tr><th>Ящиков отправки · позиций в письме</th><td>{{ $fmt($l->senders ?? 0) }} · в среднем {{ $l->per_letter ?? '—' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Позиции</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>№</th><th>Позиция</th><th>Тип товара</th><th title="поставщиков в леджере кампании">Спрошено</th>
                            <th>Отправлено</th><th>В очереди / резерв</th><th>Ответили</th><th>Цен</th><th>Лучшая цена</th><th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($v['items'] as $i)
                            <tr>
                                <td>{{ $i['pos'] }}</td>
                                <td>
                                    <div>{{ \Illuminate\Support\Str::limit($i['name'], 70) }}</div>
                                    <div style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ trim(($i['brand'] ?? '') . ' ' . ($i['article'] ?? '')) ?: '—' }} · {{ $i['qty'] }}</div>
                                </td>
                                <td style="font-size: var(--text-sm);">{{ $i['type'] ?? '—' }}</td>
                                <td style="{{ $i['asked'] === 0 ? 'color: var(--color-error);' : '' }}">{{ $i['asked'] }}</td>
                                <td>{{ $i['sent'] }}</td>
                                <td style="color: var(--color-text-muted);">{{ $i['pending'] }} / {{ $i['held'] }}</td>
                                <td>{{ $i['replied'] }}</td>
                                <td><strong style="color: {{ $i['priced'] >= 3 ? 'var(--color-success)' : ($i['priced'] > 0 ? 'inherit' : 'var(--color-text-muted)') }};">{{ $i['priced'] }}</strong></td>
                                <td>{{ $i['best'] !== null ? number_format($i['best'], 2, ',', ' ') . ' ₽' : '—' }}</td>
                                <td style="font-size: var(--text-sm); color: var(--color-text-muted);">{{ $i['status'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" style="color: var(--color-text-muted);">Нет позиций.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
