@extends('layouts.cabinet')

@section('title', 'Вопросы по заявке')
@section('header', 'Вопросы по заявке')

@push('styles')
<style>
    .container { max-width: 1000px; margin: 0 auto; }
    .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
    .card-header { padding: 1.25rem; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #111827; }
    .card-body { padding: 1.5rem; }
    .btn { padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; font-size: 0.875rem; display: inline-block; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .btn-secondary { background: #6b7280; color: white; }
    .btn-secondary:hover { background: #4b5563; }

    .question-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
    .question-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
    .question-meta { display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem; }
    .question-text { color: #111827; font-size: 1rem; line-height: 1.5; margin-bottom: 1rem; padding: 1rem; background: white; border-left: 3px solid #3b82f6; border-radius: 0.5rem; }
    .question-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    .badge { padding: 0.375rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 600; white-space: nowrap; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-answered { background: #d1fae5; color: #065f46; }
    .badge-high { background: #fee2e2; color: #991b1b; }
    .badge-medium { background: #fef3c7; color: #92400e; }
    .badge-low { background: #e0e7ff; color: #3730a3; }

    .answer-form { margin-top: 1rem; padding: 1rem; background: white; border-radius: 0.5rem; border: 1px solid #e5e7eb; }
    .answer-textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; min-height: 100px; font-size: 0.875rem; resize: vertical; }

    .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
    .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; }
    .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
    .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
    .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }
</style>
@endpush

@section('content')
<div class="container">

    <div style="margin-bottom: 1.5rem;">
        <a href="{{ route('cabinet.my.requests.show', $requestId) }}" class="btn btn-secondary">← Назад к заявке</a>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
    <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @if(isset($notSynced) && $notSynced)
        <div class="alert alert-info">
            <strong>⏳ Заявка обрабатывается</strong>
            <p style="margin: 0.5rem 0 0 0;">Заявка находится на модерации и пока не отправлена поставщикам. Вопросы появятся после того, как заявка будет одобрена и отправлена.</p>
        </div>
        <div class="empty-state">
            <h3>Заявка ещё не синхронизирована</h3>
            <p>Вопросы появятся после отправки заявки поставщикам</p>
        </div>
    @else
        <div class="alert alert-info">
            <strong>Важно:</strong> Поставщики могут задавать уточняющие вопросы по вашей заявке. Пожалуйста, ответьте на них для получения точных коммерческих предложений.
        </div>

        <!-- Список вопросов -->
        @if(empty($questions))
            <div class="empty-state">
                <h3>Нет вопросов</h3>
                <p>По этой заявке пока нет вопросов от поставщиков</p>
            </div>
        @else
        <div class="card">
            <div class="card-header">
                Вопросов: {{ count($questions) }}
                @php
                    $unanswered = collect($questions)->filter(fn($q) => empty($q['author_answer']) && empty($q['answer_text']))->count();
                @endphp
                @if($unanswered > 0)
                    <span class="badge badge-pending" style="margin-left: 1rem;">{{ $unanswered }} требуют ответа</span>
                @endif
            </div>
        </div>

        @foreach($questions as $question)
        <div class="question-card">
            <div class="question-header">
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 0.5rem 0; font-size: 1.125rem; color: #111827;">
                        Вопрос #{{ $question['id'] }}
                        @if($question['status'] === 'answered' || $question['status'] === 'author_answered')
                            <span class="badge badge-answered">✅ Отвечен</span>
                        @else
                            <span class="badge badge-pending">❓ Требует ответа</span>
                        @endif
                        @if(!empty($question['priority']))
                            <span class="badge badge-{{ $question['priority'] }}">
                                @if($question['priority'] === 'high') 🔴 Высокий
                                @elseif($question['priority'] === 'medium') 🟡 Средний
                                @else 🟢 Низкий
                                @endif
                            </span>
                        @endif
                    </h3>
                    <div class="question-meta">
                        <span><strong>От:</strong> {{ $question['supplier_name'] ?? 'Поставщик' }}</span>
                        <span>📅 {{ \Carbon\Carbon::parse($question['created_at'])->format('d.m.Y H:i') }}</span>
                    </div>
                </div>
            </div>

            @if(!empty($question['item_name']))
                <div class="question-meta" style="margin-bottom: 1rem;">
                    <span><strong>Позиция:</strong> #{{ $question['position_number'] ?? '?' }} {{ $question['item_name'] }}</span>
                </div>
            @endif

            <div class="question-text">
                {{ $question['question_text'] }}
            </div>

            @if(!empty($question['author_answer']) || !empty($question['answer_text']))
                <div class="answer-form" style="background: #d1fae5; border-color: #10b981;">
                    <div style="font-size: 0.875rem; color: #065f46; margin-bottom: 0.5rem;"><strong>Ваш ответ:</strong></div>
                    <div style="color: #065f46;">{{ $question['author_answer'] ?? $question['answer_text'] }}</div>
                    @if(!empty($question['answered_at']))
                        <div style="font-size: 0.75rem; color: #059669; margin-top: 0.5rem;">
                            Отвечено: {{ \Carbon\Carbon::parse($question['answered_at'])->format('d.m.Y H:i') }}
                        </div>
                    @endif
                </div>
            @else
                <div class="question-actions">
                    <button type="button" class="btn btn-primary" onclick="toggleAnswerForm({{ $question['id'] }})">
                        Ответить
                    </button>
                </div>

                <div id="answer-form-{{ $question['id'] }}" class="answer-form" style="display: none;">
                    <form method="POST" action="{{ route('cabinet.questions.answer', $question['id']) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="request_id" value="{{ $requestId }}">
                        <div style="margin-bottom: 0.75rem;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Ваш ответ:</label>
                            <textarea name="answer" class="answer-textarea" required placeholder="Введите ответ на вопрос..."></textarea>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Прикрепить файлы (необязательно):</label>
                            <input type="file" name="files[]" multiple class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem;">
                            <small style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                                Максимальный размер файла: 10 МБ. Можно прикрепить несколько файлов.
                            </small>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="submit" class="btn btn-primary">Отправить ответ</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleAnswerForm({{ $question['id'] }})">Отмена</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
        @endforeach
    @endif
    @endif

</div>

<script>
function toggleAnswerForm(questionId) {
    const form = document.getElementById('answer-form-' + questionId);
    if (form.style.display === 'none') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}
</script>
@endsection
