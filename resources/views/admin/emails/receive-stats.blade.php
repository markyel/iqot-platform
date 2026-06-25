@extends('layouts.cabinet')

@section('title', 'Приём писем')

@section('content')
    <x-page-header
        title="Приём писем"
        description="Входящая почта: поток писем, беседы с поставщиками, неопознанные письма и вложения"
    />

    @if (! ($stats['ok'] ?? false))
        <div class="card">
            <div class="card-body">
                <p>Не удалось получить статистику приёма из БД.</p>
                <p style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $stats['error'] ?? '' }}</p>
            </div>
        </div>
    @else
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-body" style="display: flex; gap: var(--space-6); flex-wrap: wrap; align-items: center;">
                <span>
                    Приём на стороне Laravel:
                    @if ($stats['enabled'])
                        <strong style="color: var(--color-success);">включён</strong>
                    @else
                        <strong style="color: var(--color-text-muted);">выключен</strong>
                    @endif
                </span>
                <span style="color: var(--color-text-muted);">
                    Последнее входящее: {{ $stats['last_received_at'] ?? '—' }}
                </span>
            </div>
        </div>

        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card
                value="{{ number_format($stats['incoming_today'], 0, '.', ' ') }}"
                label="Входящих сегодня"
                icon="mail"
                icon-type="primary"
            />
            <x-stat-card
                value="{{ number_format($stats['unident_today'], 0, '.', ' ') }}"
                label="Неопознанных сегодня"
                icon="mail-question"
                icon-type="warning"
            />
            <x-stat-card
                value="{{ number_format($stats['incoming_total'], 0, '.', ' ') }}"
                label="Входящих всего"
                icon="inbox"
                icon-type="success"
            />
            <x-stat-card
                value="{{ number_format($stats['unident_total'], 0, '.', ' ') }}"
                label="Неопознанных всего"
                icon="help-circle"
                icon-type="warning"
            />
        </div>

        <div class="stats-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card
                value="{{ number_format($stats['conv_waiting'], 0, '.', ' ') }}"
                label="Беседы: ждут ответа"
                icon="clock"
                icon-type="warning"
            />
            <x-stat-card
                value="{{ number_format($stats['conv_complete'], 0, '.', ' ') }}"
                label="Беседы: завершены"
                icon="check-circle"
                icon-type="success"
            />
            <x-stat-card
                value="{{ number_format($stats['conv_needs_clarification'], 0, '.', ' ') }}"
                label="Беседы: нужно уточнение"
                icon="messages-square"
                icon-type="accent"
            />
            <x-stat-card
                value="{{ number_format($stats['conv_has_offers'], 0, '.', ' ') }}"
                label="Беседы с предложениями"
                icon="package-check"
                icon-type="primary"
            />
        </div>

        <div class="stats-grid">
            <x-stat-card
                value="{{ number_format($stats['unident_manual_review'], 0, '.', ' ') }}"
                label="На ручном разборе"
                icon="search"
                icon-type="warning"
            />
            <x-stat-card
                value="{{ number_format($stats['unident_no_token'], 0, '.', ' ') }}"
                label="Без токена"
                icon="search-x"
                icon-type="warning"
            />
            <x-stat-card
                value="{{ number_format($stats['attachments_total'], 0, '.', ' ') }}"
                label="Вложений получено"
                icon="paperclip"
                icon-type="primary"
            />
            <x-stat-card
                value="{{ number_format($stats['blocked_mailboxes'], 0, '.', ' ') }}"
                label="Заблокировано ящиков"
                icon="shield-off"
                icon-type="error"
            />
        </div>
    @endif
@endsection
