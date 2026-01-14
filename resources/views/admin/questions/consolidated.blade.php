@extends('layouts.cabinet')

@section('title', 'Консолидированные вопросы')
@section('header', 'Консолидированные вопросы')

@push('styles')
<style>
    .container { max-width: 1400px; margin: 0 auto; }
    .stats { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
    .stat-card { flex: 1; background: white; padding: 1.25rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .stat-label { font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem; }
    .stat-value { font-size: 1.875rem; font-weight: 700; color: #111827; }

    .consolidated-card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; overflow: hidden; }
    .consolidated-header { background: #f9fafb; padding: 1.25rem; border-bottom: 2px solid #e5e7eb; display: flex; justify-content: space-between; align-items: start; }
    .consolidated-body { padding: 1.5rem; }

    .consolidated-text { font-size: 1.125rem; font-weight: 600; color: #111827; margin-bottom: 1rem; padding: 1rem; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 0.5rem; }

    .item-info { display: flex; gap: 2rem; margin-bottom: 1.5rem; padding: 1rem; background: #f9fafb; border-radius: 0.5rem; }
    .item-field { display: flex; flex-direction: column; gap: 0.25rem; }
    .item-label { font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; }
    .item-value { font-size: 0.875rem; color: #111827; font-weight: 500; }

    .suppliers-list { margin-bottom: 1.5rem; }
    .suppliers-header { font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.75rem; }
    .supplier-badge { display: inline-block; padding: 0.5rem 0.875rem; background: #dbeafe; color: #1e40af; border-radius: 0.5rem; font-size: 0.875rem; margin-right: 0.5rem; margin-bottom: 0.5rem; }

    .questions-details { margin-bottom: 1.5rem; }
    .question-item { padding: 1rem; background: #f9fafb; border-left: 3px solid #d1d5db; margin-bottom: 0.75rem; border-radius: 0.5rem; }
    .question-meta { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem; }
    .question-text { font-size: 0.875rem; color: #374151; }

    .answer-form { padding: 1.5rem; background: #f9fafb; border-radius: 0.5rem; border: 2px solid #e5e7eb; }
    .form-group { margin-bottom: 1rem; }
    .form-label { display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
    .form-textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; resize: vertical; min-height: 120px; }
    .form-control { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; }

    .btn { padding: 0.625rem 1.25rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; font-size: 0.875rem; display: inline-block; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .btn-secondary { background: #6b7280; color: white; }
    .btn-secondary:hover { background: #4b5563; }

    .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; }
    .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
    .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
    .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }

    .empty-state { text-align: center; padding: 4rem 2rem; color: #6b7280; }
    .empty-state svg { width: 64px; height: 64px; margin: 0 auto 1rem; opacity: 0.3; }
</style>
@endpush

@section('content')
<div class="container">

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
    <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @if(isset($error))
    <div class="alert alert-error">{{ $error }}</div>
    @endif

    <!-- Статистика -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Консолидированных групп</div>
            <div class="stat-value">{{ $totalConsolidated }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Всего вопросов</div>
            <div class="stat-value">{{ $totalOriginal }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Затронуто заявок</div>
            <div class="stat-value">{{ count($byRequest) }}</div>
        </div>
    </div>

    <!-- Информационное сообщение -->
    @if(!empty($consolidatedQuestions))
    <div class="alert alert-info">
        💡 <strong>Консолидация вопросов:</strong> Похожие вопросы от разных поставщиков объединены в группы. Ответив на группу один раз, вы отправите ответ всем поставщикам в этой группе.
    </div>
    @endif

    <!-- Список консолидированных вопросов -->
    @if(empty($consolidatedQuestions))
        <div class="empty-state">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            <h3 style="font-size: 1.25rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Нет вопросов для консолидации</h3>
            <p>Все вопросы уже обработаны или нет похожих вопросов для объединения</p>
        </div>
    @else
        @foreach($consolidatedQuestions as $question)
        <div class="consolidated-card">
            <div class="consolidated-header">
                <div>
                    <h3 style="margin: 0; font-size: 1.125rem; color: #111827;">Группа #{{ $question['index'] }}</h3>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #6b7280;">
                        {{ $question['suppliers_count'] }} {{ $question['suppliers_count'] == 1 ? 'поставщик' : 'поставщиков' }}
                    </p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="toggleAnswerForm({{ $question['index'] }})">
                        ✉️ Ответить всем
                    </button>
                </div>
            </div>

            <div class="consolidated-body">
                <!-- Консолидированный текст вопроса -->
                <div class="consolidated-text">
                    {{ $question['consolidated_text'] }}
                </div>

                <!-- Информация о позиции -->
                @if(!empty($question['request_id']) || !empty($question['item_name']) || !empty($question['item_article']))
                <div class="item-info">
                    @if(!empty($question['request_id']))
                    <div class="item-field">
                        <span class="item-label">Заявка</span>
                        <span class="item-value">
                            <a href="{{ route('admin.manage.requests.show', $question['request_id']) }}" style="color: #3b82f6; text-decoration: none;">
                                {{ $question['request_number'] ?? 'REQ-' . $question['request_id'] }}
                            </a>
                        </span>
                    </div>
                    @endif
                    @if(!empty($question['item_name']))
                    <div class="item-field">
                        <span class="item-label">Позиция</span>
                        <span class="item-value">{{ $question['item_name'] }}</span>
                    </div>
                    @endif
                    @if(!empty($question['item_article']))
                    <div class="item-field">
                        <span class="item-label">Артикул</span>
                        <span class="item-value">{{ $question['item_article'] }}</span>
                    </div>
                    @endif
                </div>
                @endif

                <!-- Список поставщиков -->
                <div class="suppliers-list">
                    <div class="suppliers-header">Поставщики в группе ({{ $question['suppliers_count'] }}):</div>
                    <div>
                        @foreach($question['supplier_names'] as $supplierName)
                            <span class="supplier-badge">{{ $supplierName }}</span>
                        @endforeach
                    </div>
                </div>

                <!-- Детали вопросов -->
                <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f9fafb; border-radius: 0.5rem;">
                    <div style="font-size: 0.875rem; color: #6b7280;">
                        <strong>Количество вопросов:</strong> {{ $question['questions_count'] ?? count($question['original_question_ids']) }}<br>
                        <strong>Первый вопрос:</strong> {{ isset($question['first_question_at']) ? \Carbon\Carbon::parse($question['first_question_at'])->format('d.m.Y H:i') : 'н/д' }}<br>
                        <strong>Последний вопрос:</strong> {{ isset($question['last_question_at']) ? \Carbon\Carbon::parse($question['last_question_at'])->format('d.m.Y H:i') : 'н/д' }}
                    </div>
                </div>

                <!-- Форма ответа -->
                <div id="answer-form-{{ $question['index'] }}" class="answer-form" style="display: none;">
                    <form method="POST" action="{{ route('admin.questions.consolidated.answer') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="consolidation_id" value="{{ $question['consolidation_id'] }}">

                        <div class="form-group">
                            <label class="form-label">Ваш ответ (будет отправлен всем {{ $question['suppliers_count'] }} поставщикам):</label>
                            <textarea name="answer" class="form-textarea" required placeholder="Введите ответ..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Прикрепить файлы (необязательно):</label>
                            <input type="file" name="files[]" multiple class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            <small style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                                Максимальный размер файла: 10 МБ. Можно прикрепить несколько файлов.
                            </small>
                        </div>

                        <div style="display: flex; gap: 0.75rem;">
                            <button type="submit" class="btn btn-primary">
                                ✉️ Отправить ответ {{ $question['suppliers_count'] }} поставщикам
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleAnswerForm({{ $question['index'] }})">
                                Отмена
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
        @endforeach
    @endif

</div>

<script>
function toggleAnswerForm(index) {
    const form = document.getElementById('answer-form-' + index);
    if (form.style.display === 'none') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}
</script>
@endsection
