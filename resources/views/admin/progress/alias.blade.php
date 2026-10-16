@extends('layouts.cabinet')

@php
    $v = $data['value'] ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0, '.', ' ');
    $pct = fn ($a, $b) => $b > 0 ? number_format(100 * $a / $b, 1, ',', '') . '%' : '—';
    $msk = fn ($t) => $t ? \Carbon\Carbon::parse($t, 'UTC')->setTimezone('Europe/Moscow')->format('d.m H:i') : '—';
@endphp

@section('title', 'Эксперимент «ящик на батч»')

@section('content')
    <x-page-header title="Эксперимент «ящик на батч»" description="Параллельная ветка рассылки без меток: новый ящик на каждый батч из 1–2 позиций, письма всем поставщикам пула в «живом» темпе, ответы — в общий info@ домена. Обновляется раз в минуту." />
    @include('admin.progress._nav')

    @if (! ($data['ok'] ?? false))
        <div class="card"><div class="card-body"><p>Не удалось получить данные.</p>
            <p style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $data['error'] ?? '' }}</p></div></div>
    @else
        @php($r = $v['reserve'])
        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card value="{{ $v['enabled'] ? 'включена' : 'выключена' }}" label="Ветка в дневном плане (доля {{ $fmt($v['share'] * 100) }}%, до {{ $v['per_batch'] }} поз. в батче)" icon="git-branch" icon-type="{{ $v['enabled'] ? 'success' : 'warning' }}" />
            <x-stat-card value="{{ $v['window_open'] ? 'открыто' : 'закрыто' }}" label="Окно отправки: {{ $v['window'] }}, 1 письмо в {{ intdiv($v['delay'], 60) }} мин" icon="clock" icon-type="{{ $v['window_open'] ? 'success' : 'primary' }}" />
            <x-stat-card value="{{ $v['budget_left'] }} из {{ $v['budget'] }}" label="Можно создать ящиков сегодня" icon="inbox" icon-type="primary" />
            <x-stat-card value="{{ $fmt($r['active']) }}" label="Организаций в резерве (не использованы {{ $fmt($r['never_used']) }}, с живым ящиком {{ $fmt($r['live']) }})" icon="building-2" icon-type="primary" />
        </div>

        @if ($v['cmp'])
            @php($a = $v['cmp']['alias']) @php($m = $v['cmp']['main'])
            <div class="card" style="margin-bottom: var(--space-6);">
                <div class="card-header"><h3 class="card-title">Сравнение веток — письма с {{ $msk($v['cmp']['since']) }} МСК</h3></div>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>Ветка</th><th>Отправлено</th><th>Ответили</th><th>Доля ответов</th><th>С ценой</th><th>Доля с ценой</th><th>Отказов «спам»</th></tr></thead>
                        <tbody>
                            <tr><td><strong>Ящик на батч</strong></td><td>{{ $fmt($a->letters) }}</td><td>{{ $fmt($a->replied) }}</td><td>{{ $pct($a->replied, $a->letters) }}</td>
                                <td>{{ $fmt($a->kp) }}</td><td>{{ $pct($a->kp, $a->letters) }}</td><td>{{ $fmt($v['cmp']['alias_spam']) }} ({{ $pct($v['cmp']['alias_spam'], $a->letters + $v['cmp']['alias_spam']) }})</td></tr>
                            <tr><td><strong>Основная</strong></td><td>{{ $fmt($m->letters) }}</td><td>{{ $fmt($m->replied) }}</td><td>{{ $pct($m->replied, $m->letters) }}</td>
                                <td>{{ $fmt($m->kp) }}</td><td>{{ $pct($m->kp, $m->letters) }}</td><td>{{ $fmt($v['cmp']['main_spam']) }} ({{ $pct($v['cmp']['main_spam'], $m->letters + $v['cmp']['main_spam']) }})</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-body" style="font-size: var(--text-sm); color: var(--color-text-muted);">
                    Ответ — входящее письмо в беседе по паре «батч × поставщик», цена — КП по этой паре. На свежих письмах доли занижены: поставщики отвечают в течение нескольких дней.
                </div>
            </div>
        @endif

        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Батчи и ящики</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Ящик / персона</th><th>Позиции</th><th>Писем</th><th>Отправлено</th><th>Ждут</th><th>Ошибок (спам)</th><th>Отбойников (спам)</th><th>Ответили</th><th>С ценой</th><th>Последнее / следующее</th><th>Состояние</th></tr></thead>
                    <tbody>
                        @forelse ($v['rows'] as $row)
                            @php($mb = $row['mailbox'])
                            <tr>
                                <td><strong>{{ $mb->email }}</strong><br><span style="font-size: var(--text-sm); color: var(--color-text-muted);">{{ $row['persona'] }} · {{ $row['org'] }}<br>до {{ $msk($mb->expires_at) }} · batch {{ implode(', ', $row['batch_ids']) ?: '—' }}</span></td>
                                <td style="font-size: var(--text-sm);">
                                    @forelse ($row['items'] as $it)
                                        <div>{{ \Illuminate\Support\Str::limit($it->name, 60) }} — <a href="{{ route('admin.progress.request', $it->request_id) }}">{{ $it->request_number }}</a></div>
                                    @empty — @endforelse
                                </td>
                                <td>{{ $fmt($row['total']) }}</td><td>{{ $fmt($row['sent']) }}</td><td>{{ $fmt($row['pending']) }}</td>
                                <td>{{ $fmt($row['err']) }} ({{ $fmt($row['spam']) }})</td><td>{{ $fmt($row['bounces']) }} ({{ $fmt($row['bounce_spam']) }})</td>
                                <td>{{ $fmt($row['replied']) }} <span style="font-size: var(--text-sm); color: var(--color-text-muted);">{{ $pct($row['replied'], $row['sent']) }}</span></td>
                                <td>{{ $fmt($row['kp']) }}</td>
                                <td style="font-size: var(--text-sm);">{{ $msk($row['last_sent']) }}<br>{{ $msk($row['next_at']) }}</td>
                                <td>
                                    @if ($row['state'] === 'в работе') <span class="badge badge-success">в работе</span>
                                    @elseif ($row['state'] === 'удалён') <span class="badge">удалён</span>
                                    @else <span class="badge badge-error">{{ $row['state'] }}</span> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11">Ящиков эксперимента пока нет.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Домены эксперимента</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Домен</th><th>Общий ящик</th><th>Приём на любые адреса</th><th>Активен</th><th>Ящиков создано</th><th>Последний</th></tr></thead>
                    <tbody>
                        @forelse ($v['domains'] as $d)
                            <tr><td><strong>{{ $d->domain }}</strong></td><td>{{ $d->inbox . "@" . $d->domain }}</td>
                                <td>{!! $d->catch_all ? '<span class="badge badge-success">да</span>' : '<span class="badge badge-error">нет</span>' !!}</td>
                                <td>{!! $d->active ? '<span class="badge badge-success">да</span>' : '<span class="badge">нет</span>' !!}</td>
                                <td>{{ $fmt($d->mailboxes_created) }}</td><td>{{ $msk($d->last_used_at) }}</td></tr>
                        @empty
                            <tr><td colspan="6">Доменов нет — подключение: <code>php artisan alias-exp domain-add &lt;домен&gt;</code></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
