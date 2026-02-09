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
            <x-button
                variant="success"
                icon="play"
                onclick="document.getElementById('start-campaign-modal').style.display = 'flex'"
            >
                Запустить рассылку
            </x-button>
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

@if($campaign->failed_count > 0)
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
            <i data-lucide="alert-circle" class="icon-md" style="color: var(--danger-500);"></i>
            Дорассылка (повторная отправка)
        </h2>
    </div>
    <div class="card-body">
        <p style="margin-bottom: var(--space-4); color: var(--gray-600);">
            Найдено {{ $campaign->failed_count }} получателей с ошибками.
            Вы можете повторно отправить письма, отключив валидацию email (если ошибка связана с исчерпанием кредитов на EmailListVerify).
        </p>
        <form action="{{ route('admin.campaigns.retry', $campaign) }}" method="POST" style="display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap;">
            @csrf
            <div class="form-group" style="margin: 0; display: flex; align-items: center; gap: var(--space-2);">
                <input
                    type="checkbox"
                    id="use_email_validation_retry"
                    name="use_email_validation"
                    value="1"
                    style="width: 20px; height: 20px; cursor: pointer;"
                >
                <label for="use_email_validation_retry" style="margin: 0; cursor: pointer; user-select: none;">
                    Использовать валидацию email (EmailListVerify)
                </label>
            </div>
            <x-button
                type="submit"
                variant="warning"
                icon="refresh-cw"
                onclick="return confirm('Запустить дорассылку для получателей с ошибками?')"
            >
                Запустить дорассылку
            </x-button>
        </form>
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

<!-- Модалка запуска рассылки -->
<div id="start-campaign-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 9999;">
    <div style="background: white; border-radius: var(--radius-lg); padding: var(--space-6); max-width: 500px; width: 90%;">
        <h3 style="margin: 0 0 var(--space-4) 0; font-size: var(--text-xl); font-weight: 700;">
            Запуск рассылки
        </h3>
        <form action="{{ route('admin.campaigns.start', $campaign) }}" method="POST">
            @csrf
            <div style="margin-bottom: var(--space-5);">
                <div style="display: flex; align-items: start; gap: var(--space-3); padding: var(--space-4); background: var(--gray-50); border-radius: var(--radius-md);">
                    <input
                        type="checkbox"
                        id="use_email_validation_start"
                        name="use_email_validation"
                        value="1"
                        checked
                        style="width: 20px; height: 20px; cursor: pointer; margin-top: 2px;"
                    >
                    <label for="use_email_validation_start" style="margin: 0; cursor: pointer; user-select: none; flex: 1;">
                        <strong>Использовать валидацию email (EmailListVerify)</strong>
                        <p style="margin: var(--space-1) 0 0 0; font-size: var(--text-sm); color: var(--gray-600);">
                            Рекомендуется для проверки адресов перед отправкой. Требует кредиты на EmailListVerify.
                        </p>
                    </label>
                </div>
                <p style="margin: var(--space-3) 0 0 0; font-size: var(--text-sm); color: var(--gray-600);">
                    После запуска письма будут отправлены асинхронно через очередь.
                    Прогресс отправки можно отслеживать на этой странице.
                </p>
            </div>
            <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="document.getElementById('start-campaign-modal').style.display = 'none'"
                >
                    Отмена
                </button>
                <button
                    type="submit"
                    class="btn btn-success"
                    style="display: inline-flex; align-items: center; gap: var(--space-2);"
                >
                    <i data-lucide="play" class="icon-sm"></i>
                    Запустить
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Закрытие модалки по клику вне её
document.getElementById('start-campaign-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});
</script>

@endsection
