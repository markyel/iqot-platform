@extends('layouts.cabinet')

@section('title', 'Вопросы по заявке')

@push('styles')
<style>
    .container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .question-card {
        background: var(--neutral-50);
        border: 1px solid var(--neutral-200);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-3);
    }

    .question-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: var(--space-3);
        flex-wrap: wrap;
        gap: var(--space-2);
    }

    .question-meta {
        display: flex;
        gap: var(--space-3);
        flex-wrap: wrap;
        font-size: 0.875rem;
        color: var(--neutral-600);
        margin-bottom: var(--space-3);
    }

    .question-text {
        color: var(--neutral-900);
        font-size: 1rem;
        line-height: 1.5;
        margin-bottom: var(--space-3);
        padding: var(--space-3);
        background: var(--neutral-0);
        border-left: 3px solid var(--blue-600);
        border-radius: var(--radius-sm);
    }

    .question-actions {
        display: flex;
        gap: var(--space-2);
        flex-wrap: wrap;
    }

    .answer-form {
        margin-top: var(--space-3);
        padding: var(--space-3);
        background: var(--neutral-0);
        border-radius: var(--radius-sm);
        border: 1px solid var(--neutral-200);
    }

    .answer-textarea {
        width: 100%;
        padding: var(--space-3);
        border: 1px solid var(--neutral-300);
        border-radius: var(--radius-sm);
        min-height: 100px;
        font-size: 0.875rem;
        resize: vertical;
    }

    .answer-textarea:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px var(--primary-50);
    }
</style>
@endpush

@section('content')
<div class="container">
    <div style="margin-bottom: var(--space-4);">
        <x-button
            href="{{ route('cabinet.my.requests.show', $requestId) }}"
            variant="secondary"
            icon="arrow-left"
        >
            Назад к заявке
        </x-button>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger" style="margin-bottom: var(--space-4);">{{ session('error') }}</div>
    @endif

    @if(isset($notSynced) && $notSynced)
        <div class="alert alert-info" style="margin-bottom: var(--space-4);">
            <div style="display: flex; align-items: start; gap: var(--space-2);">
                <i data-lucide="clock" style="width: 20px; height: 20px; flex-shrink: 0;"></i>
                <div>
                    <strong>Заявка обрабатывается</strong>
                    <p style="margin: var(--space-2) 0 0 0;">Заявка находится на модерации и пока не отправлена поставщикам. Вопросы появятся после того, как заявка будет одобрена и отправлена.</p>
                </div>
            </div>
        </div>
        <x-empty-state
            icon="inbox"
            title="Заявка ещё не синхронизирована"
            description="Вопросы появятся после отправки заявки поставщикам"
        />
    @else
        <div class="alert alert-info" style="margin-bottom: var(--space-4);">
            <strong>Важно:</strong> Поставщики могут задавать уточняющие вопросы по вашей заявке. Пожалуйста, ответьте на них для получения точных коммерческих предложений.
        </div>

        <!-- Список вопросов -->
        @if(empty($questions))
            <x-empty-state
                icon="message-circle"
                title="Нет вопросов"
                description="По этой заявке пока нет вопросов от поставщиков"
            />
        @else
            <div class="card" style="margin-bottom: var(--space-4);">
                <div style="display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap;">
                    <div style="font-weight: 600; color: var(--neutral-900);">
                        Вопросов: {{ count($questions) }}
                    </div>
                    @php
                        $unanswered = collect($questions)->filter(fn($q) => empty($q['author_answer']) && empty($q['answer_text']))->count();
                    @endphp
                    @if($unanswered > 0)
                        <x-badge variant="warning">{{ $unanswered }} требуют ответа</x-badge>
                    @endif
                </div>
            </div>

            @foreach($questions as $question)
            <div class="question-card">
                <div class="question-header">
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 var(--space-2) 0; font-size: 1.125rem; display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap;">
                            Вопрос #{{ $question['id'] }}
                            @if($question['status'] === 'answered' || $question['status'] === 'author_answered')
                                <x-badge variant="success">
                                    <i data-lucide="check" style="width: 12px; height: 12px;"></i>
                                    Отвечен
                                </x-badge>
                            @else
                                <x-badge variant="warning">
                                    <i data-lucide="help-circle" style="width: 12px; height: 12px;"></i>
                                    Требует ответа
                                </x-badge>
                            @endif
                            @if(!empty($question['priority']))
                                @php
                                    $priorityVariant = match($question['priority']) {
                                        'high' => 'danger',
                                        'medium' => 'warning',
                                        'low' => 'info',
                                        default => 'neutral'
                                    };
                                    $priorityIcon = match($question['priority']) {
                                        'high' => 'alert-circle',
                                        'medium' => 'alert-triangle',
                                        'low' => 'info',
                                        default => 'circle'
                                    };
                                    $priorityLabel = match($question['priority']) {
                                        'high' => 'Высокий',
                                        'medium' => 'Средний',
                                        'low' => 'Низкий',
                                        default => $question['priority']
                                    };
                                @endphp
                                <x-badge :variant="$priorityVariant">
                                    <i data-lucide="{{ $priorityIcon }}" style="width: 12px; height: 12px;"></i>
                                    {{ $priorityLabel }}
                                </x-badge>
                            @endif
                        </h3>
                        <div class="question-meta">
                            <span><strong>От:</strong> {{ $question['supplier_name'] ?? 'Поставщик' }}</span>
                            <span>
                                <i data-lucide="calendar" style="width: 14px; height: 14px; vertical-align: text-bottom;"></i>
                                {{ \Carbon\Carbon::parse($question['created_at'])->format('d.m.Y H:i') }}
                            </span>
                        </div>
                    </div>
                </div>

                @if(!empty($question['item_name']))
                    <div class="question-meta" style="margin-bottom: var(--space-3);">
                        <span>
                            <strong>Позиция:</strong> #{{ $question['position_number'] ?? '?' }} {{ $question['item_name'] }}
                        </span>
                    </div>
                @endif

                <div class="question-text">
                    {{ $question['question_text'] }}
                </div>

                @if(!empty($question['author_answer']) || !empty($question['answer_text']))
                    <div class="answer-form" style="background: var(--green-50); border-color: var(--green-600);">
                        <div style="font-size: 0.875rem; color: var(--green-900); margin-bottom: var(--space-2); font-weight: 600;">
                            <i data-lucide="check-circle" style="width: 16px; height: 16px; vertical-align: text-bottom;"></i>
                            Ваш ответ:
                        </div>
                        <div style="color: var(--green-900);">{{ $question['author_answer'] ?? $question['answer_text'] }}</div>
                        @if(!empty($question['answered_at']))
                            <div style="font-size: 0.75rem; color: var(--green-700); margin-top: var(--space-2);">
                                Отвечено: {{ \Carbon\Carbon::parse($question['answered_at'])->format('d.m.Y H:i') }}
                            </div>
                        @endif
                    </div>
                @else
                    <div class="question-actions">
                        <x-button
                            type="button"
                            variant="primary"
                            icon="message-square"
                            onclick="toggleAnswerForm({{ $question['id'] }})"
                        >
                            Ответить
                        </x-button>
                    </div>

                    <div id="answer-form-{{ $question['id'] }}" class="answer-form" style="display: none;">
                        <form method="POST" action="{{ route('cabinet.questions.answer', $question['id']) }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="request_id" value="{{ $requestId }}">
                            <div class="form-group">
                                <label class="form-label">Ваш ответ:</label>
                                <textarea name="answer" class="answer-textarea" required placeholder="Введите ответ на вопрос..."></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Прикрепить файлы (необязательно):</label>
                                <input
                                    type="file"
                                    name="files[]"
                                    multiple
                                    class="input"
                                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                                    style="padding: var(--space-2);"
                                >
                                <small class="text-muted" style="display: block; margin-top: var(--space-1);">
                                    Максимальный размер файла: 10 МБ. Можно прикрепить несколько файлов.
                                </small>
                            </div>
                            <div style="display: flex; gap: var(--space-2);">
                                <x-button type="submit" variant="primary">Отправить ответ</x-button>
                                <x-button
                                    type="button"
                                    variant="secondary"
                                    onclick="toggleAnswerForm({{ $question['id'] }})"
                                >
                                    Отмена
                                </x-button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
            @endforeach
        @endif
    @endif
</div>

@push('scripts')
<script>
function toggleAnswerForm(questionId) {
    const form = document.getElementById('answer-form-' + questionId);
    if (form.style.display === 'none') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}

lucide.createIcons();
</script>
@endpush
@endsection
