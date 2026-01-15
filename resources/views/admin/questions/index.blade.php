@extends('layouts.cabinet')

@section('title', 'Вопросы от поставщиков')

@push('styles')
<style>
    .answer-form {
        margin-top: var(--space-4);
        padding: var(--space-4);
        background: var(--neutral-0);
        border-radius: var(--radius-md);
        border: 1px solid var(--neutral-200);
    }

    .answer-form.answered {
        background: var(--success-50);
        border-color: var(--success-500);
    }

    .answer-textarea {
        width: 100%;
        padding: var(--space-3);
        border: 1px solid var(--neutral-300);
        border-radius: var(--radius-md);
        min-height: 100px;
        font-size: var(--text-sm);
        resize: vertical;
        font-family: var(--font-primary);
    }

    .answer-textarea:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px var(--primary-100);
    }
</style>
@endpush

@section('content')

<!-- Page Header -->
<x-page-header
    title="Вопросы от поставщиков"
    description="Ответы на вопросы поставщиков по заявкам"
/>

<!-- Filters Card -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.questions.index') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); align-items: end;">
            <div class="form-group">
                <label class="form-label">Статус</label>
                <select name="status" class="input select">
                    <option value="">Неотвеченные</option>
                    <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>Все</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Ожидает ответа</option>
                    <option value="answered" {{ request('status') === 'answered' ? 'selected' : '' }}>Отвечены</option>
                    <option value="skipped" {{ request('status') === 'skipped' ? 'selected' : '' }}>Пропущены</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Приоритет</label>
                <select name="priority" class="input select">
                    <option value="">Все</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>Высокий</option>
                    <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Средний</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Низкий</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Номер заявки</label>
                <input type="text" name="request_number" class="input" placeholder="REQ-..." value="{{ request('request_number') }}">
            </div>

            <div class="form-group">
                <label class="form-label">Поиск</label>
                <input type="text" name="search" class="input" placeholder="Текст вопроса..." value="{{ request('search') }}">
            </div>

            <div style="display: flex; gap: var(--space-2);">
                <button type="submit" class="btn btn-primary btn-md">
                    <i data-lucide="filter" class="icon-sm"></i>
                    Применить
                </button>
                @if(request()->hasAny(['status', 'priority', 'request_number', 'search']))
                    <a href="{{ route('admin.questions.index') }}" class="btn btn-secondary btn-md">
                        <i data-lucide="x" class="icon-sm"></i>
                        Сбросить
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Список вопросов -->
@if(empty($questions))
    <x-empty-state
        icon="help-circle"
        title="Нет вопросов"
        description="По заданным фильтрам вопросов не найдено"
    />
@else
    @foreach($questions as $question)
        @php
            $status = 'pending';
            if ($question['status'] === 'answered' || $question['status'] === 'author_answered') {
                $status = 'answered';
            } elseif ($question['status'] === 'skipped') {
                $status = 'skipped';
            }

            // Формируем массив поставщиков
            $suppliers = [];
            if (!empty($question['supplier_name'])) {
                $suppliers[] = $question['supplier_name'];
            }

            // Берем номер заявки из request или request_number
            $requestNumber = $question['request']['number'] ?? $question['request_number'] ?? null;

            // Берем название позиции
            $itemName = $question['request_item']['name'] ?? $question['item_name'] ?? null;
        @endphp

        <div class="question-card" style="margin-bottom: var(--space-4);">
            <div class="question-header">
                <div class="question-meta">
                    @if($requestNumber)
                        <span class="text-code">{{ $requestNumber }}</span>
                    @else
                        <span class="text-code">Вопрос #{{ $question['id'] }}</span>
                    @endif
                    @if($itemName)
                        <span class="question-separator">•</span>
                        <span class="question-item">{{ $itemName }}</span>
                    @endif
                </div>
                <x-badge :type="$status" dot>
                    @if($status === 'answered') Отвечено
                    @elseif($status === 'skipped') Пропущено
                    @else Требует ответа
                    @endif
                </x-badge>
            </div>

            <div class="question-body">
                <p class="question-text">{{ $question['question_text'] }}</p>

                @if(count($suppliers) > 0)
                    <div class="question-suppliers">
                        @foreach($suppliers as $supplier)
                            <span class="supplier-tag">{{ $supplier }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @if(!empty($question['author_answer']) || !empty($question['answer_text']))
                <div class="answer-form answered">
                    <div style="font-size: var(--text-sm); color: var(--success-700); margin-bottom: var(--space-2); font-weight: 600;">
                        <i data-lucide="check-circle" class="icon-sm" style="display: inline-block; vertical-align: middle;"></i>
                        Ваш ответ:
                    </div>
                    <div style="color: var(--success-700);">{{ $question['author_answer'] ?? $question['answer_text'] }}</div>
                    @if(!empty($question['answered_at']))
                        <div style="font-size: var(--text-xs); color: var(--success-600); margin-top: var(--space-2);">
                            Отвечено: {{ \Carbon\Carbon::parse($question['answered_at'])->format('d.m.Y H:i') }}
                        </div>
                    @endif
                </div>
            @else
                <div class="question-footer">
                    <span class="question-time">{{ \Carbon\Carbon::parse($question['created_at'])->format('d.m.Y H:i') }}</span>
                    <div class="question-actions">
                        <form method="POST" action="{{ route('admin.questions.skip', $question['id']) }}" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('Пропустить этот вопрос?')">
                                Пропустить
                            </button>
                        </form>
                        <button type="button" class="btn btn-primary btn-sm" onclick="toggleAnswerForm({{ $question['id'] }})">
                            <i data-lucide="message-circle" class="icon-sm"></i>
                            Ответить
                        </button>
                    </div>
                </div>

                <div id="answer-form-{{ $question['id'] }}" class="answer-form" style="display: none;">
                    <form method="POST" action="{{ route('admin.questions.answer', $question['id']) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label class="form-label">Ваш ответ:</label>
                            <textarea name="answer" class="answer-textarea" required placeholder="Введите ответ на вопрос..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Прикрепить файлы (необязательно):</label>
                            <input type="file" name="files[]" multiple class="input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            <small style="color: var(--neutral-500); font-size: var(--text-xs); margin-top: var(--space-1); display: block;">
                                Максимальный размер файла: 10 МБ. Можно прикрепить несколько файлов.
                            </small>
                        </div>
                        <div style="display: flex; gap: var(--space-2);">
                            <button type="submit" class="btn btn-primary btn-md">Отправить ответ</button>
                            <button type="button" class="btn btn-secondary btn-md" onclick="toggleAnswerForm({{ $question['id'] }})">Отмена</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    @endforeach

    <!-- Пагинация -->
    @if($paginationData['total_pages'] > 1)
    <div class="pagination" style="margin-top: var(--space-6);">
        @php
            $queryParams = request()->except('page');
            $startPage = max(1, $paginationData['page'] - 2);
            $endPage = min($paginationData['total_pages'], $paginationData['page'] + 2);
        @endphp

        @if($paginationData['page'] > 1)
            <a href="?{{ http_build_query(array_merge($queryParams, ['page' => $paginationData['page'] - 1])) }}" class="page-link">
                <i data-lucide="chevron-left" class="icon-sm"></i>
                Назад
            </a>
        @endif

        @if($startPage > 1)
            <a href="?{{ http_build_query(array_merge($queryParams, ['page' => 1])) }}" class="page-link">1</a>
            @if($startPage > 2)
                <span class="page-ellipsis">...</span>
            @endif
        @endif

        @for($i = $startPage; $i <= $endPage; $i++)
            <a href="?{{ http_build_query(array_merge($queryParams, ['page' => $i])) }}" class="page-link {{ $i === $paginationData['page'] ? 'active' : '' }}">{{ $i }}</a>
        @endfor

        @if($endPage < $paginationData['total_pages'])
            @if($endPage < $paginationData['total_pages'] - 1)
                <span class="page-ellipsis">...</span>
            @endif
            <a href="?{{ http_build_query(array_merge($queryParams, ['page' => $paginationData['total_pages']])) }}" class="page-link">{{ $paginationData['total_pages'] }}</a>
        @endif

        @if($paginationData['page'] < $paginationData['total_pages'])
            <a href="?{{ http_build_query(array_merge($queryParams, ['page' => $paginationData['page'] + 1])) }}" class="page-link">
                Вперёд
                <i data-lucide="chevron-right" class="icon-sm"></i>
            </a>
        @endif
    </div>
    @endif
@endif

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

// Reinitialize Lucide icons after content is loaded
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush
@endsection
