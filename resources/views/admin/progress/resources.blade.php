@extends('layouts.cabinet')

@php
    $v = $data['value'] ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0, '.', ' ');
    $msk = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->timezone('Europe/Moscow')->format('d.m H:i') : '—';
    $ok = fn ($good) => $good ? '<span class="badge badge-success">в порядке</span>' : '<span class="badge badge-error">проблема</span>';
@endphp

@section('title', 'Ресурсы системы')

@section('content')
    <x-page-header title="Ресурсы системы" description="Почтовые ящики и домены рассылки, релеи, каналы обхода сайтов, ИИ-прокси, очереди, диск. Пробы — живые (кеш 30 с)." />
    @include('admin.progress._nav')

    @if (! ($data['ok'] ?? false))
        <div class="card"><div class="card-body"><p>Не удалось получить данные.</p>
            <p style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $data['error'] ?? '' }}</p></div></div>
    @else
        @php($s = $v['s_tot']) @php($m = $v['mail'])
        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card value="{{ $fmt($s->live_domains) }} из {{ $fmt($s->domains) }}" label="Живых доменов (есть годные ящики)" icon="globe" icon-type="primary" />
            <x-stat-card value="{{ $fmt($s->ready) }}" label="Ящиков готовы слать (годных {{ $fmt($s->usable) }}; отправка отключена у {{ $fmt($s->disabled) }})" icon="mail" icon-type="success" />
            <x-stat-card value="{{ $fmt($s->blocked_now) }}" label="Временно заблокированы" icon="pause-circle" icon-type="{{ $s->blocked_now > 0 ? 'warning' : 'primary' }}" />
            <x-stat-card value="{{ $fmt($s->deactivated_7d) }}" label="Отключено за 7 дней (всего отключено {{ $fmt($s->inactive_total) }})" icon="x-circle" icon-type="{{ $s->deactivated_7d > 0 ? 'error' : 'primary' }}" />
        </div>
        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card value="{{ $fmt($m->sent_today) }}" label="Писем отправлено сегодня" icon="send" icon-type="success" />
            <x-stat-card value="{{ $v['capacity_left'] !== null ? $fmt($v['capacity_left']) : '—' }}" label="Остаток ёмкости ящиков на сегодня" icon="battery" icon-type="primary" />
            <x-stat-card value="{{ $fmt($m->pending) }}" label="В очереди на отправку" icon="clock" icon-type="primary" />
            <x-stat-card value="{{ $fmt($m->errors_24h) }}" label="Ошибок отправки за 24 ч" icon="alert-triangle" icon-type="{{ $m->errors_24h > 30 ? 'error' : 'primary' }}" />
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Почтовые релеи</h3></div>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>Релей</th><th>Вес</th><th>/health</th><th>Отклик</th><th>Предохранитель</th></tr></thead>
                        <tbody>
                            @forelse ($v['relays'] as $r)
                                <tr>
                                    <td style="font-size: var(--text-sm);">{{ $r['url'] }}</td>
                                    <td>{{ $r['weight'] }}</td>
                                    <td>{!! $ok($r['health'] === 'healthy' || $r['health'] === 'ok') !!} <span style="font-size: var(--text-sm); color: var(--color-text-muted);">{{ $r['health'] ?? $r['error'] }}</span></td>
                                    <td>{{ $r['ms'] }} мс</td>
                                    <td>{!! $r['down_mark'] ? '<span class="badge badge-error">помечен down</span>' : '<span class="badge badge-success">нет</span>' !!}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="color: var(--color-text-muted);">Отправка напрямую (пул релеев не задан).</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">ИИ-прокси</h3></div>
                <div class="table-container">
                    <table class="table">
                        <tbody>
                            <tr><th style="width: 200px;">Адрес</th><td style="font-size: var(--text-sm);">{{ $v['ai']['url'] }}</td></tr>
                            <tr><th>/health</th><td>{!! $ok($v['ai']['health'] === 'ok') !!} {{ $v['ai']['health'] }}{{ $v['ai']['ms'] !== null ? ' · ' . $v['ai']['ms'] . ' мс' : '' }}</td></tr>
                            <tr><th>Отказы 503/429 за час</th><td>{{ $v['ai']['errors_1h'] }} из {{ $fmt($v['ai']['calls_1h']) }} проверок страниц</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Каналы обхода сайтов (мониторинг)</h3></div>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>Канал</th><th>Прокси</th><th>Роль</th><th>Состояние</th><th>Сайтов</th></tr></thead>
                        <tbody>
                            @foreach ($v['egress'] as $e)
                                <tr>
                                    <td><strong>{{ $e['name'] }}</strong></td>
                                    <td style="font-size: var(--text-sm);">{{ $e['proxy'] ?? 'прямой IP прода' }}</td>
                                    <td>{{ $e['fallback'] ? 'резерв' : 'основной' }}</td>
                                    <td>{!! $e['down'] ? '<span class="badge badge-error">down</span>' : '<span class="badge badge-success">ok</span>' !!}</td>
                                    <td>{{ $fmt($e['sites']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Очереди и сервер</h3></div>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>Очередь</th><th>Задач</th><th>Выполняется</th><th>Старейшая</th></tr></thead>
                        <tbody>
                            @forelse ($v['queues'] as $q)
                                <tr><td>{{ $q->queue }}</td><td>{{ $fmt($q->c) }}</td><td>{{ $q->running }}</td><td style="font-size: var(--text-sm);">{{ $msk(\Illuminate\Support\Carbon::createFromTimestamp($q->oldest)) }}</td></tr>
                            @empty
                                <tr><td colspan="4" style="color: var(--color-text-muted);">Очереди пусты — всё разобрано.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="padding: var(--space-3) var(--space-4); font-size: var(--text-sm);">
                    Упавших задач за 24 ч: <strong style="{{ $v['failed_jobs_24h'] > 0 ? 'color: var(--color-error);' : '' }}">{{ $v['failed_jobs_24h'] }}</strong> ·
                    Диск: свободно {{ $v['disk']['free_gb'] }} из {{ $v['disk']['total_gb'] }} ГБ ({{ $v['disk']['pct'] }}%) ·
                    Писем «зависло» в очереди дольше 7 дней: {{ $fmt($m->stale_pending) }}
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Домены и ящики рассылки</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Домен</th><th>SMTP</th><th>Ящиков</th><th>Годных</th><th>Отправка отключена</th><th>Заблок. сейчас</th><th>Неактивны</th><th>Лимит/сутки</th><th>Отправлено сегодня</th><th>Ошибок 24 ч</th></tr></thead>
                    <tbody>
                        @foreach ($v['senders'] as $d)
                            <tr style="{{ (int) $d->usable === 0 ? 'color: var(--color-text-muted);' : '' }}">
                                <td><strong>{{ $d->domain }}</strong></td>
                                <td style="font-size: var(--text-sm);">{{ $d->smtp_server }}</td>
                                <td>{{ $d->total }}</td>
                                <td><strong>{{ $d->usable }}</strong></td>
                                <td style="{{ $d->disabled > 0 ? 'color: var(--color-error);' : '' }}">{{ $d->disabled }}</td>
                                <td style="{{ $d->blocked_now > 0 ? 'color: var(--color-warning);' : '' }}">{{ $d->blocked_now }}</td>
                                <td>{{ $d->inactive }}</td>
                                <td>{{ $fmt($d->daily_limit) }}</td>
                                <td>{{ $fmt($d->sent_today) }}</td>
                                <td style="{{ $d->errors_24h > 0 ? 'color: var(--color-error);' : '' }}">{{ $fmt($d->errors_24h) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if (count($v['blocked']))
            <div class="card">
                <div class="card-header"><h3 class="card-title">Заблокированные сейчас ящики (последние)</h3></div>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>Ящик</th><th>До</th><th>Причина</th><th>Блокировок</th></tr></thead>
                        <tbody>
                            @foreach ($v['blocked'] as $b)
                                <tr><td>{{ $b->email }}</td><td>{{ $msk($b->blocked_until) }}</td><td style="font-size: var(--text-sm);">{{ \Illuminate\Support\Str::limit($b->block_reason, 80) }}</td><td>{{ $b->block_count }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection
