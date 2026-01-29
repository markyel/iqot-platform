@extends('layouts.cabinet')

@section('title', $campaign->name)

@section('content')
<x-page-header
    :title="$campaign->name"
    :description="'Тема: ' . $campaign->subject"
>
    <x-slot name="actions">
        @if($campaign->isEditable())
            <x-button
                variant="secondary"
                icon="edit"
                href="{{ route('admin.campaigns.edit', $campaign) }}"
            >
                Редактировать
            </x-button>
        @endif

        @if($campaign->status === 'draft' && $campaign->total_recipients > 0)
            <form action="{{ route('admin.campaigns.start', $campaign) }}" method="POST" style="display: inline;">
                @csrf
                <x-button
                    type="submit"
                    variant="success"
                    icon="play"
                    onclick="return confirm('Запустить рассылку?')"
                >
                    Запустить рассылку
                </x-button>
            </form>
        @endif

        <x-button
            variant="secondary"
            icon="arrow-left"
            href="{{ route('admin.campaigns.index') }}"
        >
            К списку
        </x-button>
    </x-slot>
</x-page-header>

@if($campaign->status === 'sending')
    <div class="alert alert-info" style="margin-bottom: var(--space-6);" id="sending-alert">
        <div style="display: flex; align-items: center; gap: var(--space-3);">
            <i data-lucide="loader" class="icon-md" style="animation: spin 1s linear infinite;"></i>
            <div style="flex: 1;">
                <strong>Рассылка в процессе</strong>
                <p style="margin-top: var(--space-1); margin-bottom: var(--space-2);">
                    Письма отправляются асинхронно через очередь. Прогресс обновляется автоматически.
                </p>
                <!-- Прогресс-бар -->
                <div style="background: rgba(255,255,255,0.3); border-radius: var(--radius-sm); height: 24px; overflow: hidden; position: relative;">
                    <div id="progress-bar" style="background: var(--success-500); height: 100%; width: 0%; transition: width 0.5s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: var(--text-sm);">
                        <span id="progress-text">0%</span>
                    </div>
                </div>
                <p style="margin-top: var(--space-2); margin-bottom: 0; font-size: var(--text-sm);" id="progress-status">
                    Отправлено: <span id="progress-sent">0</span> / <span id="progress-total">0</span>
                </p>
            </div>
        </div>
    </div>
@endif

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card">
        <div class="card-body text-center">
            <div class="text-muted" style="font-size: var(--text-sm);">Получателей</div>
            <div style="font-size: var(--text-2xl); font-weight: 700; margin-top: var(--space-1);">
                {{ $campaign->total_recipients }}
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body text-center">
            <div class="text-muted" style="font-size: var(--text-sm);">Отправлено</div>
            <div class="text-success" style="font-size: var(--text-2xl); font-weight: 700; margin-top: var(--space-1);">
                {{ $campaign->sent_count }}
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body text-center">
            <div class="text-muted" style="font-size: var(--text-sm);">Ошибок</div>
            <div class="text-danger" style="font-size: var(--text-2xl); font-weight: 700; margin-top: var(--space-1);">
                {{ $campaign->failed_count }}
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body text-center">
            <div class="text-muted" style="font-size: var(--text-sm);">Задержка</div>
            <div style="font-size: var(--text-2xl); font-weight: 700; margin-top: var(--space-1);">
                {{ $campaign->delay_seconds }}с
            </div>
        </div>
    </div>
</div>

@if($validationStats['total_validated'] > 0)
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
            <i data-lucide="check-circle" class="icon-md"></i>
            Статистика валидации email
        </h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-4);">
            <div style="text-align: center; padding: var(--space-3); background: var(--gray-50); border-radius: var(--radius-md);">
                <div class="text-muted" style="font-size: var(--text-sm);">Проверено</div>
                <div style="font-size: var(--text-xl); font-weight: 700; margin-top: var(--space-1); color: var(--primary-600);">
                    {{ $validationStats['total_validated'] }}
                </div>
            </div>
            <div style="text-align: center; padding: var(--space-3); background: var(--success-50); border-radius: var(--radius-md);">
                <div class="text-muted" style="font-size: var(--text-sm);">Валидных</div>
                <div class="text-success" style="font-size: var(--text-xl); font-weight: 700; margin-top: var(--space-1);">
                    {{ $validationStats['valid'] }}
                </div>
            </div>
            <div style="text-align: center; padding: var(--space-3); background: var(--danger-50); border-radius: var(--radius-md);">
                <div class="text-muted" style="font-size: var(--text-sm);">Невалидных</div>
                <div class="text-danger" style="font-size: var(--text-xl); font-weight: 700; margin-top: var(--space-1);">
                    {{ $validationStats['invalid'] }}
                </div>
            </div>
            <div style="text-align: center; padding: var(--space-3); background: var(--gray-50); border-radius: var(--radius-md);">
                <div class="text-muted" style="font-size: var(--text-sm);">Не проверено</div>
                <div style="font-size: var(--text-xl); font-weight: 700; margin-top: var(--space-1);">
                    {{ $validationStats['not_validated'] }}
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@if($campaign->total_recipients > 0)
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
            <i data-lucide="send" class="icon-md"></i>
            Тестовая отправка
        </h2>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.campaigns.send-test', $campaign) }}" method="POST" style="display: flex; gap: var(--space-3); align-items: end;">
            @csrf
            <div class="form-group" style="flex: 1; margin: 0;">
                <label class="form-label" for="test_email">Email для теста</label>
                <input
                    type="email"
                    id="test_email"
                    name="test_email"
                    class="input"
                    placeholder="test@example.com"
                    required
                >
            </div>
            <x-button type="submit" variant="secondary" icon="send">
                Отправить тест
            </x-button>
        </form>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header">
        <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">
            Список получателей
        </h2>
    </div>

    @if($recipients->isEmpty())
        <div class="empty-state">
            <p>Получатели не загружены</p>
            <a href="{{ route('admin.campaigns.upload', $campaign) }}" class="btn btn-primary">
                Загрузить данные
            </a>
        </div>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Валидация</th>
                    <th>Данные</th>
                    <th>Статус</th>
                    <th>Дата отправки</th>
                    <th>Ошибка</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recipients as $recipient)
                <tr>
                    <td>{{ $recipient->email }}</td>
                    <td>
                        @if($recipient->email_validated)
                            @if($recipient->validation_status === 'valid')
                                <x-badge type="completed">✓ Валидный</x-badge>
                            @else
                                <x-badge type="failed">✗ {{ $recipient->validation_reason }}</x-badge>
                            @endif
                            <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 2px;">
                                {{ $recipient->validation_provider }}
                            </div>
                        @else
                            <x-badge type="pending">Не проверен</x-badge>
                        @endif
                    </td>
                    <td>
                        <details>
                            <summary style="cursor: pointer; color: var(--accent-600);">Показать данные</summary>
                            <pre style="margin-top: var(--space-2); font-size: 0.75rem;">{{ json_encode($recipient->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    </td>
                    <td>
                        @if($recipient->status === 'pending')
                            <x-badge type="pending">Ожидает</x-badge>
                        @elseif($recipient->status === 'sent')
                            <x-badge type="completed">Отправлено</x-badge>
                        @else
                            <x-badge type="failed">Ошибка</x-badge>
                        @endif
                    </td>
                    <td>{{ $recipient->sent_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td>
                        @if($recipient->error_message)
                            <span class="text-danger" style="font-size: 0.875rem;">{{ $recipient->error_message }}</span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="card-footer">
            {{ $recipients->links() }}
        </div>
    @endif
</div>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

@if($campaign->status === 'sending')
<script>
// Автоматическое обновление прогресса рассылки
let progressInterval = null;

function updateProgress() {
    fetch('{{ route("admin.campaigns.progress", $campaign) }}')
        .then(response => response.json())
        .then(data => {
            // Обновляем прогресс-бар
            document.getElementById('progress-bar').style.width = data.percent_complete + '%';
            document.getElementById('progress-text').textContent = data.percent_complete + '%';

            // Обновляем счетчики
            document.getElementById('progress-sent').textContent = data.sent;
            document.getElementById('progress-total').textContent = data.total;

            // Обновляем карточки статистики
            document.querySelectorAll('.card-body .text-success')[0].textContent = data.sent;
            document.querySelectorAll('.card-body .text-danger')[0].textContent = data.failed;

            // Если завершена - перезагружаем страницу
            if (data.is_completed) {
                clearInterval(progressInterval);
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        })
        .catch(error => {
            console.error('Ошибка получения прогресса:', error);
        });
}

// Запускаем обновление каждые 3 секунды
progressInterval = setInterval(updateProgress, 3000);

// Первое обновление сразу
updateProgress();
</script>
@endif
@endsection
