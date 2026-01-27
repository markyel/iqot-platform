@extends('layouts.cabinet')

@section('title', 'Создать рассылку')

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    <x-page-header
        title="Создать рассылку"
        description="Шаг 1 из 3: Основные данные"
    />

    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="mail" class="icon-md"></i>
                Основные данные рассылки
            </h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.campaigns.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="name">Название рассылки</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        class="input @error('name') is-invalid @enderror"
                        placeholder="Промокоды март 2026"
                        required
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="subject">Тема письма</label>
                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        value="{{ old('subject') }}"
                        class="input @error('subject') is-invalid @enderror"
                        placeholder="Ваш персональный промокод"
                        required
                    >
                    @error('subject')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="html_template">HTML шаблон письма</label>
                    <input
                        type="file"
                        id="html_template"
                        name="html_template"
                        accept=".html,.htm,.txt"
                        class="input @error('html_template') is-invalid @enderror"
                        required
                    >
                    <small class="form-help">
                        Загрузите HTML файл с шаблоном письма. Используйте метки вида @{{ Variable @}} для подстановки данных
                    </small>
                    @error('html_template')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="delay_seconds">Задержка между письмами (секунды)</label>
                    <input
                        type="number"
                        id="delay_seconds"
                        name="delay_seconds"
                        value="{{ old('delay_seconds', 2) }}"
                        min="1"
                        max="60"
                        class="input @error('delay_seconds') is-invalid @enderror"
                        style="max-width: 200px;"
                        required
                    >
                    <small class="form-help">
                        Пауза между отправкой писем (рекомендуется 2-3 сек для предотвращения блокировки)
                    </small>
                    @error('delay_seconds')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                    <x-button type="submit" variant="accent" icon="arrow-right">
                        Продолжить
                    </x-button>
                    <x-button
                        variant="secondary"
                        href="{{ route('admin.campaigns.index') }}"
                    >
                        Отмена
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
