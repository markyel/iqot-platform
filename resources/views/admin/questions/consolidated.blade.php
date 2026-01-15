@extends('layouts.cabinet')

@section('title', 'Консолидированные вопросы')

@push('styles')
<style>
    .consolidated-text {
        font-size: var(--text-lg);
        font-weight: 600;
        color: var(--neutral-900);
        margin-bottom: var(--space-4);
        padding: var(--space-4);
        background: var(--primary-50);
        border-left: 4px solid var(--primary-500);
        border-radius: var(--radius-md);
    }

    .item-info {
        display: flex;
        gap: var(--space-6);
        margin-bottom: var(--space-4);
        padding: var(--space-4);
        background: var(--neutral-50);
        border-radius: var(--radius-md);
        flex-wrap: wrap;
    }

    .item-field {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    .item-label {
        font-size: var(--text-xs);
        text-transform: uppercase;
        color: var(--neutral-500);
        font-weight: 600;
        letter-spacing: 0.05em;
    }

    .item-value {
        font-size: var(--text-sm);
        color: var(--neutral-900);
        font-weight: 500;
    }

    .form-textarea {
        width: 100%;
        padding: var(--space-3);
        border: 1px solid var(--neutral-300);
        border-radius: var(--radius-md);
        font-size: var(--text-sm);
        resize: vertical;
        min-height: 120px;
        font-family: var(--font-primary);
    }

    .form-textarea:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px var(--primary-100);
    }
</style>
@endpush

@section('content')

<!-- Page Header -->
<x-page-header
    title="Консолидированные вопросы"
    description="Похожие вопросы от разных поставщиков объединены в группы для эффективного ответа"
/>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom: var(--space-6);">
    <i data-lucide="check-circle" class="icon-sm"></i>
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="alert alert-error" style="margin-bottom: var(--space-6);">
    <i data-lucide="alert-circle" class="icon-sm"></i>
    {{ session('error') }}
</div>
@endif

@if(isset($error))
<div class="alert alert-error" style="margin-bottom: var(--space-6);">
    <i data-lucide="alert-circle" class="icon-sm"></i>
    {{ $error }}
</div>
@endif

<!-- Статистика -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <x-stat-card
        icon="layers"
        value="{{ $totalConsolidated }}"
        label="Консолидированных групп"
    />
    <x-stat-card
        icon="help-circle"
        value="{{ $totalOriginal }}"
        label="Всего вопросов"
    />
    <x-stat-card
        icon="file-text"
        value="{{ count($byRequest) }}"
        label="Затронуто заявок"
    />
</div>

<!-- Информационное сообщение -->
@if(!empty($consolidatedQuestions))
<div class="alert alert-info" style="margin-bottom: var(--space-6);">
    <i data-lucide="info" class="icon-sm"></i>
    <strong>Консолидация вопросов:</strong> Похожие вопросы от разных поставщиков объединены в группы. Ответив на группу один раз, вы отправите ответ всем поставщикам в этой группе.
</div>
@endif

<!-- Список консолидированных вопросов -->
@if(empty($consolidatedQuestions))
    <x-empty-state
        icon="layers"
        title="Нет вопросов для консолидации"
        description="Все вопросы уже обработаны или нет похожих вопросов для объединения"
    />
@else
    @foreach($consolidatedQuestions as $question)
    <div class="card" style="margin-bottom: var(--space-4);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4);">
            <div>
                <h3 style="margin: 0; font-size: var(--text-lg); color: var(--neutral-900); font-weight: 600;">
                    <i data-lucide="layers" class="icon-sm" style="display: inline-block; vertical-align: middle; margin-right: var(--space-2);"></i>
                    Группа #{{ $question['index'] }}
                </h3>
                <p style="margin: var(--space-1) 0 0 0; font-size: var(--text-sm); color: var(--neutral-500);">
                    {{ $question['suppliers_count'] }} {{ $question['suppliers_count'] == 1 ? 'поставщик' : 'поставщиков' }}
                </p>
            </div>
            <button type="button" class="btn btn-accent btn-md" onclick="toggleAnswerForm({{ $question['index'] }})">
                <i data-lucide="send" class="icon-sm"></i>
                Ответить всем
            </button>
        </div>

        <div class="card-body">
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
                        <a href="{{ route('admin.manage.requests.show', $question['request_id']) }}" class="link">
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
            <div style="margin-bottom: var(--space-4);">
                <div style="font-size: var(--text-sm); font-weight: 600; color: var(--neutral-700); margin-bottom: var(--space-2);">
                    Поставщики в группе ({{ $question['suppliers_count'] }}):
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                    @foreach($question['supplier_names'] as $supplierName)
                        <span class="supplier-tag">{{ $supplierName }}</span>
                    @endforeach
                </div>
            </div>

            <!-- Детали вопросов -->
            <div style="margin-bottom: var(--space-4); padding: var(--space-4); background: var(--neutral-50); border-radius: var(--radius-md);">
                <div style="font-size: var(--text-sm); color: var(--neutral-600); line-height: 1.6;">
                    <div style="margin-bottom: var(--space-2);"><strong>Количество вопросов:</strong> {{ $question['questions_count'] ?? count($question['original_question_ids']) }}</div>
                    <div style="margin-bottom: var(--space-2);"><strong>Первый вопрос:</strong> {{ isset($question['first_question_at']) ? \Carbon\Carbon::parse($question['first_question_at'])->format('d.m.Y H:i') : 'н/д' }}</div>
                    <div><strong>Последний вопрос:</strong> {{ isset($question['last_question_at']) ? \Carbon\Carbon::parse($question['last_question_at'])->format('d.m.Y H:i') : 'н/д' }}</div>
                </div>
            </div>

            <!-- Форма ответа -->
            <div id="answer-form-{{ $question['index'] }}" style="display: none; padding: var(--space-4); background: var(--neutral-50); border-radius: var(--radius-md); border: 2px solid var(--neutral-200);">
                <form method="POST" action="{{ route('admin.questions.consolidated.answer') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="consolidation_id" value="{{ $question['consolidation_id'] }}">

                    <div class="form-group">
                        <label class="form-label">Ваш ответ (будет отправлен всем {{ $question['suppliers_count'] }} поставщикам):</label>
                        <textarea name="answer" class="form-textarea" required placeholder="Введите ответ..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Прикрепить файлы (необязательно):</label>
                        <input type="file" name="files[]" multiple class="input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                        <small style="color: var(--neutral-500); font-size: var(--text-xs); margin-top: var(--space-1); display: block;">
                            Максимальный размер файла: 10 МБ. Можно прикрепить несколько файлов.
                        </small>
                    </div>

                    <div style="display: flex; gap: var(--space-3);">
                        <button type="submit" class="btn btn-accent btn-md">
                            <i data-lucide="send" class="icon-sm"></i>
                            Отправить ответ {{ $question['suppliers_count'] }} поставщикам
                        </button>
                        <button type="button" class="btn btn-secondary btn-md" onclick="toggleAnswerForm({{ $question['index'] }})">
                            Отмена
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
@endif

@push('scripts')
<script>
function toggleAnswerForm(index) {
    const form = document.getElementById('answer-form-' + index);
    if (form.style.display === 'none') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}

// Reinitialize Lucide icons after content is loaded
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush
@endsection
