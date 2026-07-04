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
                <table class="table">
                    <thead>
                        <tr>
                            <th>Заявка</th><th>Статус</th><th>Позиций</th><th>Позиций с КП</th>
                            <th>Разослано писем</th><th title="горячие · тёплые · холодные">В1 · В2 · В3</th>
                            <th>Дали КП</th><th>Обновлена</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['requests'] as $r)
                            <tr>
                                <td>
                                    <strong>{{ $r['number'] }}</strong>
                                    @if ($r['title'])
                                        <div style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ \Illuminate\Support\Str::limit($r['title'], 60) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $r['status'] === 'responses_received' ? 'badge-success' : 'badge-primary' }}">
                                        {{ $r['status'] === 'responses_received' ? 'есть ответы' : 'разослано' }}
                                    </span>
                                </td>
                                <td>{{ $r['items'] }}</td>
                                <td style="color: {{ $r['items_with_offers'] > 0 ? 'var(--color-success)' : 'var(--color-text-muted)' }};">{{ $r['items_with_offers'] }} / {{ $r['items'] }}</td>
                                <td>{{ number_format($r['wave1'] + $r['wave2'] + $r['wave3'], 0, '.', ' ') }}</td>
                                <td style="white-space: nowrap;">
                                    <span title="В1 горячие">{{ $r['wave1'] }}</span>
                                    <span style="color: var(--color-text-muted);">·</span>
                                    <span title="В2 тёплые">{{ $r['wave2'] }}</span>
                                    <span style="color: var(--color-text-muted);">·</span>
                                    <span title="В3 холодные" style="color: var(--color-text-muted);">{{ $r['wave3'] }}</span>
                                </td>
                                <td style="color: var(--color-success);"><strong>{{ $r['offers'] }}</strong></td>
                                <td style="color: var(--color-text-muted); font-size: var(--text-sm);">
                                    {{ $r['updated_at'] ? \Illuminate\Support\Carbon::parse($r['updated_at'])->timezone('Europe/Moscow')->format('d.m H:i') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="color: var(--color-text-muted);">Нет активных заявок.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
