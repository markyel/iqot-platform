@extends('layouts.cabinet')

@section('title', 'Заявка #' . $demoRequest->id)

@section('content')
<x-page-header
    title="Заявка #{{ $demoRequest->id }}"
    subtitle="Создана {{ $demoRequest->created_at->format('d.m.Y в H:i') }}"
>
    <x-slot:actions>
        <x-button variant="secondary" :href="route('admin.demo-requests.index')" icon="arrow-left">
            Назад к списку
        </x-button>
    </x-slot:actions>
</x-page-header>

<div style="max-width: 1400px; margin: 0 auto;">

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-error" style="margin-bottom: var(--space-4);">
            {{ session('error') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr; gap: var(--space-6);">
        @media (min-width: 1024px) {
            <style>
                .content-grid-demo {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: var(--space-6);
                }
            </style>
        }
    </div>

    <div class="content-grid-demo" style="display: grid; grid-template-columns: 1fr; gap: var(--space-6);">
        <!-- Основная информация -->
        <div>
            <!-- Данные заявителя -->
            <div class="card" style="margin-bottom: var(--space-6);">
                <div class="card-header">
                    <h2 class="card-title">Данные заявителя</h2>
                </div>
                <div class="card-body">
                    <div style="display: grid; gap: var(--space-4);">
                        <div>
                            <div class="form-label">ФИО</div>
                            <div style="color: var(--neutral-900); font-weight: 600;">{{ $demoRequest->full_name }}</div>
                        </div>
                        <div>
                            <div class="form-label">Организация</div>
                            <div style="color: var(--neutral-900); font-weight: 600;">{{ $demoRequest->organization }}</div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <div>
                                <div class="form-label">ИНН</div>
                                <div style="color: var(--neutral-900); font-family: monospace;">{{ $demoRequest->inn }}</div>
                            </div>
                            @if($demoRequest->kpp)
                            <div>
                                <div class="form-label">КПП</div>
                                <div style="color: var(--neutral-900); font-family: monospace;">{{ $demoRequest->kpp }}</div>
                            </div>
                            @endif
                        </div>
                        <div>
                            <div class="form-label">Email</div>
                            <div>
                                <a href="mailto:{{ $demoRequest->email }}" style="color: var(--primary-600); text-decoration: none;">
                                    {{ $demoRequest->email }}
                                </a>
                            </div>
                        </div>
                        <div>
                            <div class="form-label">Телефон</div>
                            <div style="color: var(--neutral-900); font-weight: 600;">{{ $demoRequest->phone }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Список товаров -->
            <div class="card" style="margin-bottom: var(--space-6);">
                <div class="card-header">
                    <h2 class="card-title">Список товаров для запроса КП</h2>
                </div>
                <div class="card-body">
                    <div style="background: var(--neutral-50); border-radius: 8px; padding: var(--space-4);">
                        <pre style="color: var(--neutral-700); white-space: pre-wrap; font-size: 0.875rem; margin: 0;">{{ $demoRequest->items_list }}</pre>
                    </div>
                </div>
            </div>

            <!-- Заметки -->
            @if($demoRequest->notes)
            <div class="card" style="margin-bottom: var(--space-6);">
                <div class="card-header">
                    <h2 class="card-title">Заметки</h2>
                </div>
                <div class="card-body">
                    <div style="background: var(--neutral-50); border-radius: 8px; padding: var(--space-4);">
                        <pre style="color: var(--neutral-700); white-space: pre-wrap; font-size: 0.875rem; margin: 0;">{{ $demoRequest->notes }}</pre>
                    </div>
                </div>
            </div>
            @endif

            <!-- Добавить заметку -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Добавить заметку</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.demo-requests.add-note', $demoRequest) }}">
                        @csrf
                        <div class="form-group">
                            <textarea name="note" rows="3" class="input" placeholder="Введите заметку..." required style="resize: vertical;"></textarea>
                        </div>
                        <x-button type="submit" variant="primary" icon="plus">
                            Добавить заметку
                        </x-button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Боковая панель -->
        <div>
            <!-- Статус -->
            <div class="card" style="margin-bottom: var(--space-6);">
                <div class="card-header">
                    <h2 class="card-title">Статус</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.demo-requests.update-status', $demoRequest) }}">
                        @csrf
                        @method('PATCH')
                        <select name="status" onchange="this.form.submit()" class="select">
                            <option value="new" {{ $demoRequest->status === 'new' ? 'selected' : '' }}>Новая</option>
                            <option value="processing" {{ $demoRequest->status === 'processing' ? 'selected' : '' }}>В обработке</option>
                            <option value="contacted" {{ $demoRequest->status === 'contacted' ? 'selected' : '' }}>Связались</option>
                            <option value="completed" {{ $demoRequest->status === 'completed' ? 'selected' : '' }}>Завершено</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Действия -->
            <div class="card" style="margin-bottom: var(--space-6);">
                <div class="card-header">
                    <h2 class="card-title">Действия</h2>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                        @if($demoRequest->status === 'new')
                        <form method="POST" action="{{ route('admin.demo-requests.approve', $demoRequest) }}">
                            @csrf
                            <x-button type="submit" variant="success" icon="check" style="width: 100%;"
                                    onclick="return confirm('Одобрить заявку и создать пользователя?')">
                                Одобрить заявку
                            </x-button>
                        </form>

                        <x-button type="button" variant="danger" icon="x" style="width: 100%;"
                                onclick="document.getElementById('rejectModal').classList.add('active')">
                            Отклонить заявку
                        </x-button>
                        @endif

                        <x-button href="mailto:{{ $demoRequest->email }}" variant="secondary" icon="mail" style="width: 100%;">
                            Написать на email
                        </x-button>
                    </div>
                </div>
            </div>

            <!-- Информация -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Информация</h2>
                </div>
                <div class="card-body">
                    <div style="display: grid; gap: var(--space-2);">
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span style="color: var(--neutral-600);">Создана:</span>
                            <span style="color: var(--neutral-900);">{{ $demoRequest->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span style="color: var(--neutral-600);">Обновлена:</span>
                            <span style="color: var(--neutral-900);">{{ $demoRequest->updated_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span style="color: var(--neutral-600);">Согласие:</span>
                            <span style="color: var(--success-600);">
                                Получено
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно отклонения -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <h3 style="margin: 0 0 var(--space-4) 0; font-size: var(--text-lg); font-weight: 600;">Отклонить заявку</h3>
        <form method="POST" action="{{ route('admin.demo-requests.reject', $demoRequest) }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Причина отклонения</label>
                <textarea name="reason" rows="4" class="input" placeholder="Укажите причину отклонения заявки..." required style="resize: vertical;"></textarea>
            </div>
            <div style="display: flex; gap: var(--space-3); margin-top: var(--space-4);">
                <x-button type="submit" variant="danger" icon="x" style="flex: 1;">
                    Отклонить
                </x-button>
                <x-button type="button" variant="secondary" style="flex: 1;" onclick="document.getElementById('rejectModal').classList.remove('active')">
                    Отмена
                </x-button>
            </div>
        </form>
    </div>
</div>

<style>
    .content-grid-demo {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--space-6);
    }

    @media (min-width: 1024px) {
        .content-grid-demo {
            grid-template-columns: 2fr 1fr;
        }
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.6);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        padding: var(--space-6);
        border-radius: var(--radius-lg);
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
</style>

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
@endsection
