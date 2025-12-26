@extends('layouts.cabinet')

@section('title', 'Заявка #' . $demoRequest->id)

@push('styles')
<style>
    /* Light theme for admin */
    .admin-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .back-link {
        color: #10b981;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 1rem;
        transition: color 0.2s;
    }

    .back-link:hover {
        color: #059669;
    }

    .page-header h1 {
        font-size: 1.875rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 0.5rem;
    }

    .page-header p {
        color: #6b7280;
        font-size: 0.875rem;
    }

    .info-item {
        margin-bottom: 1rem;
    }

    .info-label {
        color: #6b7280;
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }

    .info-value {
        color: #111827;
        font-weight: 600;
    }

    .info-value-mono {
        color: #111827;
        font-family: monospace;
    }

    .info-value-link {
        color: #10b981;
        text-decoration: none;
        transition: color 0.2s;
    }

    .info-value-link:hover {
        color: #059669;
    }

    .items-display {
        background: #f9fafb;
        border-radius: 8px;
        padding: 1rem;
    }

    .items-display pre {
        color: #374151;
        white-space: pre-wrap;
        font-size: 0.875rem;
        margin: 0;
    }

    .form-textarea {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        color: #111827;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        outline: none;
        font-family: inherit;
        resize: vertical;
    }

    .form-textarea:focus {
        border-color: #10b981;
    }

    .form-select {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        color: #111827;
        padding: 0.625rem 1rem;
        border-radius: 8px;
        outline: none;
        cursor: pointer;
    }

    .form-select:focus {
        border-color: #10b981;
    }

    .btn-green {
        background: #10b981;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        text-align: center;
        display: inline-block;
        text-decoration: none;
    }

    .btn-green:hover {
        background: #059669;
    }

    .btn-red {
        width: 100%;
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #ef4444;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
    }

    .btn-red:hover {
        background: rgba(239, 68, 68, 0.2);
    }

    .btn-gray {
        width: 100%;
        background: #374151;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: background 0.2s;
        text-align: center;
        display: block;
        text-decoration: none;
    }

    .btn-gray:hover {
        background: #4b5563;
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #10b981;
        padding: 1rem 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #ef4444;
        padding: 1rem 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(4px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: #161a22;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        max-width: 500px;
        width: 100%;
        padding: 1.5rem;
    }

    .modal-content h3 {
        color: #111827;
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.875rem;
    }

    .info-check {
        color: #10b981;
    }

    @media (min-width: 1024px) {
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
    }
</style>
@endpush

@section('content')
<div style="max-width: 1400px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.demo-requests.index') }}" class="back-link">
            ← Назад к списку заявок
        </a>
        <div class="page-header">
            <h1>Заявка #{{ $demoRequest->id }}</h1>
            <p>Создана {{ $demoRequest->created_at->format('d.m.Y в H:i') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert-error">
            {{ session('error') }}
        </div>
    @endif

    <div class="content-grid">
        <!-- Основная информация -->
        <div>
            <!-- Данные заявителя -->
            <div class="admin-card">
                <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">Данные заявителя</h2>
                <div>
                    <div class="info-item">
                        <div class="info-label">ФИО</div>
                        <div class="info-value">{{ $demoRequest->full_name }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Организация</div>
                        <div class="info-value">{{ $demoRequest->organization }}</div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <div class="info-label">ИНН</div>
                            <div class="info-value-mono">{{ $demoRequest->inn }}</div>
                        </div>
                        @if($demoRequest->kpp)
                        <div>
                            <div class="info-label">КПП</div>
                            <div class="info-value-mono">{{ $demoRequest->kpp }}</div>
                        </div>
                        @endif
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div>
                            <a href="mailto:{{ $demoRequest->email }}" class="info-value-link">
                                {{ $demoRequest->email }}
                            </a>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Телефон</div>
                        <div class="info-value">{{ $demoRequest->phone }}</div>
                    </div>
                </div>
            </div>

            <!-- Список товаров -->
            <div class="admin-card">
                <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Список товаров для запроса КП</h2>
                <div class="items-display">
                    <pre>{{ $demoRequest->items_list }}</pre>
                </div>
            </div>

            <!-- Заметки -->
            @if($demoRequest->notes)
            <div class="admin-card">
                <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Заметки</h2>
                <div class="items-display">
                    <pre>{{ $demoRequest->notes }}</pre>
                </div>
            </div>
            @endif

            <!-- Добавить заметку -->
            <div class="admin-card">
                <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Добавить заметку</h2>
                <form method="POST" action="{{ route('admin.demo-requests.add-note', $demoRequest) }}">
                    @csrf
                    <textarea name="note" rows="3" class="form-textarea" placeholder="Введите заметку..." required></textarea>
                    <button type="submit" class="btn-green" style="margin-top: 0.75rem;">
                        Добавить заметку
                    </button>
                </form>
            </div>
        </div>

        <!-- Боковая панель -->
        <div>
            <!-- Статус -->
            <div class="admin-card">
                <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Статус</h2>
                <form method="POST" action="{{ route('admin.demo-requests.update-status', $demoRequest) }}">
                    @csrf
                    @method('PATCH')
                    <select name="status" onchange="this.form.submit()" class="form-select">
                        <option value="new" {{ $demoRequest->status === 'new' ? 'selected' : '' }}>Новая</option>
                        <option value="processing" {{ $demoRequest->status === 'processing' ? 'selected' : '' }}>В обработке</option>
                        <option value="contacted" {{ $demoRequest->status === 'contacted' ? 'selected' : '' }}>Связались</option>
                        <option value="completed" {{ $demoRequest->status === 'completed' ? 'selected' : '' }}>Завершено</option>
                    </select>
                </form>
            </div>

            <!-- Действия -->
            <div class="admin-card">
                <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Действия</h2>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    @if($demoRequest->status === 'new')
                    <form method="POST" action="{{ route('admin.demo-requests.approve', $demoRequest) }}">
                        @csrf
                        <button type="submit" class="btn-green" style="width: 100%;"
                                onclick="return confirm('Одобрить заявку и создать пользователя?')">
                            ✓ Одобрить заявку
                        </button>
                    </form>

                    <button type="button" onclick="document.getElementById('rejectModal').classList.add('active')" class="btn-red">
                        ✗ Отклонить заявку
                    </button>
                    @endif

                    <a href="mailto:{{ $demoRequest->email }}" class="btn-gray">
                        Написать на email
                    </a>
                </div>
            </div>

            <!-- Информация -->
            <div class="admin-card">
                <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Информация</h2>
                <div class="info-grid">
                    <div class="info-row">
                        <span class="info-label">Создана:</span>
                        <span style="color: #111827;">{{ $demoRequest->created_at->format('d.m.Y H:i') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Обновлена:</span>
                        <span style="color: #111827;">{{ $demoRequest->updated_at->format('d.m.Y H:i') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Согласие:</span>
                        <span class="info-check">✓ Получено</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно отклонения -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Отклонить заявку</h3>
        <form method="POST" action="{{ route('admin.demo-requests.reject', $demoRequest) }}">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label class="info-label" style="display: block; margin-bottom: 0.5rem;">Причина отклонения</label>
                <textarea name="reason" rows="4" class="form-textarea" placeholder="Укажите причину отклонения заявки..." required></textarea>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button type="submit" class="btn-green" style="flex: 1; background: #ef4444;">
                    Отклонить
                </button>
                <button type="button" onclick="document.getElementById('rejectModal').classList.remove('active')" class="btn-gray" style="flex: 1;">
                    Отмена
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
