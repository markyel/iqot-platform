@extends('layouts.cabinet')

@section('title', 'Очередь рассылки')

@section('content')
    <x-page-header
        title="Очередь рассылки"
        description="Состояние почтовой очереди и успешность отправки писем"
    />

    @if (! ($stats['ok'] ?? false))
        <div class="card">
            <div class="card-body">
                <p>Не удалось получить статистику из БД рассылки.</p>
                <p style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $stats['error'] ?? '' }}</p>
            </div>
        </div>
    @else
        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card
                value="{{ number_format($stats['in_queue'], 0, '.', ' ') }}"
                label="Писем в очереди"
                icon="mail"
                icon-type="primary"
            />
            <x-stat-card
                value="{{ number_format($stats['sent_today'], 0, '.', ' ') }}"
                label="Отправлено сегодня"
                icon="send"
                icon-type="success"
            />
            <x-stat-card
                value="{{ $stats['success_rate'] !== null ? $stats['success_rate'] . '%' : '—' }}"
                label="Успешность отправки"
                icon="check-circle"
                icon-type="success"
            />
            <x-stat-card
                value="{{ number_format($stats['error'], 0, '.', ' ') }}"
                label="Ошибок (всего)"
                icon="alert-triangle"
                icon-type="error"
            />
        </div>

        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card
                value="{{ number_format($stats['pending'], 0, '.', ' ') }}"
                label="Ожидают (pending)"
                icon="clock"
                icon-type="warning"
            />
            <x-stat-card
                value="{{ number_format($stats['sending'], 0, '.', ' ') }}"
                label="В отправке"
                icon="loader"
                icon-type="primary"
            />
            <x-stat-card
                value="{{ number_format($stats['sent'], 0, '.', ' ') }}"
                label="Отправлено (всего)"
                icon="check"
                icon-type="success"
            />
            <x-stat-card
                value="{{ number_format($stats['error_retryable'], 0, '.', ' ') }}"
                label="Ошибки к ретраю"
                icon="refresh-cw"
                icon-type="warning"
            />
        </div>

        <div class="stats-grid">
            <x-stat-card
                value="{{ number_format($stats['active_senders'], 0, '.', ' ') }}"
                label="Активных отправителей"
                icon="users"
                icon-type="primary"
            />
            <x-stat-card
                value="{{ number_format($stats['blocked_senders'], 0, '.', ' ') }}"
                label="Заблокировано (ratelimit)"
                icon="shield-off"
                icon-type="error"
            />
            <x-stat-card
                value="{{ number_format($stats['batches_completed'], 0, '.', ' ') }}"
                label="Рассылок завершено"
                icon="package-check"
                icon-type="success"
            />
            <x-stat-card
                value="{{ number_format($stats['batches_queued'], 0, '.', ' ') }}"
                label="Рассылок в работе"
                icon="package"
                icon-type="warning"
            />
        </div>
    @endif
@endsection
