@extends('layouts.cabinet')

@section('title', 'Ход выполнения заявок')

@section('content')
    <x-page-header
        title="Ход выполнения заявок"
        description="Активные заявки, волны рассылки и статистика ответов/отказов поставщиков"
    />

    @if (! ($data['ok'] ?? false))
        <div class="card">
            <div class="card-body">
                <p>Не удалось получить данные из БД рассылки.</p>
                <p style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $data['error'] ?? '' }}</p>
            </div>
        </div>
    @else
        @php($c = $data['cards'])
        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card value="{{ number_format($c['active_requests'], 0, '.', ' ') }}" label="Активных заявок" icon="clipboard-list" icon-type="primary" />
            <x-stat-card value="{{ number_format($c['offers_total'], 0, '.', ' ') }}" label="КП получено (поставщиков)" icon="check-circle" icon-type="success" />
            <x-stat-card value="{{ number_format($c['offers_today'], 0, '.', ' ') }}" label="КП сегодня" icon="trending-up" icon-type="success" />
            <x-stat-card value="{{ number_format($c['conversations'], 0, '.', ' ') }}" label="Живых бесед" icon="messages-square" icon-type="primary" />
        </div>
        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card value="{{ number_format($c['rejections'], 0, '.', ' ') }}" label="Отказов (90 дн)" icon="x-circle" icon-type="error" />
            <x-stat-card value="{{ number_format($c['pending_live'], 0, '.', ' ') }}" label="Писем ждут отправки" icon="clock" icon-type="warning" />
            <x-stat-card value="{{ number_format($c['held'], 0, '.', ' ') }}" label="Придержано (резерв волн)" icon="pause-circle" icon-type="warning" />
        </div>

        {{-- Волны рассылки --}}
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Волны рассылки</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Волна</th><th>Всего</th><th>Отправлено</th><th>Ответили</th>
                            <th>Ждут</th><th>Резерв (held)</th><th>Ошибки</th><th>Отменено</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['waves'] as $w)
                            <tr>
                                <td><strong>{{ $w['label'] }}</strong></td>
                                <td>{{ number_format($w['total'], 0, '.', ' ') }}</td>
                                <td>{{ number_format($w['sent'], 0, '.', ' ') }}</td>
                                <td style="color: var(--color-success);">{{ number_format($w['replied'], 0, '.', ' ') }}</td>
                                <td>{{ number_format($w['pending'], 0, '.', ' ') }}</td>
                                <td>{{ number_format($w['held'], 0, '.', ' ') }}</td>
                                <td style="color: var(--color-error);">{{ number_format($w['failed'], 0, '.', ' ') }}</td>
                                <td style="color: var(--color-text-muted);">{{ number_format($w['cancelled'], 0, '.', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="color: var(--color-text-muted);">Нет данных по волнам.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Типы ответов поставщиков --}}
        @php($rt = $data['replyTypes'])
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Ответы поставщиков за 90 дней</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr><th>КП / с ценой</th><th>Вопросы</th><th>Отказы</th><th>Автоответы</th><th>Пустые / NDR</th><th>Всего</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="color: var(--color-success);"><strong>{{ number_format($rt['offers'], 0, '.', ' ') }}</strong></td>
                            <td>{{ number_format($rt['questions'], 0, '.', ' ') }}</td>
                            <td style="color: var(--color-error);">{{ number_format($rt['rejections'], 0, '.', ' ') }}</td>
                            <td style="color: var(--color-text-muted);">{{ number_format($rt['auto_reply'], 0, '.', ' ') }}</td>
                            <td style="color: var(--color-text-muted);">{{ number_format($rt['empty_reply'], 0, '.', ' ') }}</td>
                            <td>{{ number_format($rt['total'], 0, '.', ' ') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Активные заявки --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">Активные заявки ({{ count($data['requests']) }})</h3></div>
            <div class="table-container">
                <div style="padding: 0 var(--space-4) var(--space-2);">
                    @forelse ($data['requests'] as $r)
                        <details style="border-bottom: 1px solid var(--color-border); padding: var(--space-3) 0;">
                            <summary style="cursor: pointer; display: flex; gap: var(--space-4); align-items: center; flex-wrap: wrap;">
                                <strong style="min-width: 200px;">{{ $r['number'] }}</strong>
                                <span class="badge {{ $r['status'] === 'responses_received' ? 'badge-success' : 'badge-primary' }}">
                                    {{ $r['status'] === 'responses_received' ? 'есть ответы' : 'разослано' }}
                                </span>
                                <span style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $r['items'] }} поз · {{ $r['items_with_offers'] }} с КП</span>
                                <span title="разослано писем всего">✉ {{ number_format($r['wave1'] + $r['wave2'] + $r['wave3'], 0, '.', ' ') }}</span>
                                <span style="white-space: nowrap;" title="В1 горячие · В2 тёплые · В3 холодные">
                                    В1&nbsp;{{ $r['wave1'] }} · В2&nbsp;{{ $r['wave2'] }} · <span style="color: var(--color-text-muted);">В3&nbsp;{{ $r['wave3'] }}</span>
                                </span>
                                <span style="color: var(--color-success);"><strong>КП&nbsp;{{ $r['offers'] }}</strong></span>
                                <span style="color: var(--color-text-muted); font-size: var(--text-sm); margin-left: auto;">
                                    {{ count($r['batches']) }} батч. · обн {{ $r['updated_at'] ? \Illuminate\Support\Carbon::parse($r['updated_at'])->timezone('Europe/Moscow')->format('d.m H:i') : '—' }}
                                </span>
                            </summary>
                            @if ($r['title'])
                                <div style="color: var(--color-text-muted); font-size: var(--text-sm); margin: var(--space-2) 0 0 var(--space-4);">{{ \Illuminate\Support\Str::limit($r['title'], 90) }}</div>
                            @endif
                            <div class="table-container" style="margin: var(--space-2) 0 var(--space-2) var(--space-4);">
                                <table class="table">
                                    <thead>
                                        <tr><th>Батч</th><th>Создан</th><th>В1</th><th>В2</th><th>В3</th><th>Всего</th><th>Статус</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($r['batches'] as $b)
                                            <tr>
                                                <td>#{{ $b['id'] }}</td>
                                                <td style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $b['created'] ? \Illuminate\Support\Carbon::parse($b['created'])->timezone('Europe/Moscow')->format('d.m H:i') : '—' }}</td>
                                                <td>{{ $b['w1'] }}</td>
                                                <td>{{ $b['w2'] }}</td>
                                                <td style="color: var(--color-text-muted);">{{ $b['w3'] }}</td>
                                                <td><strong>{{ $b['total'] }}</strong></td>
                                                <td style="font-size: var(--text-sm); color: var(--color-text-muted);">{{ $b['status'] }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" style="color: var(--color-text-muted);">Нет батчей.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @empty
                        <p style="color: var(--color-text-muted); padding: var(--space-4) 0;">Нет активных заявок.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
@endsection
