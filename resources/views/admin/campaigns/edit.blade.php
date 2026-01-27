@extends('layouts.cabinet')

@section('title', 'Редактирование рассылки')

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    <x-page-header
        title="Редактирование рассылки"
        description="Измените параметры рассылки"
    />

    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="edit" class="icon-md"></i>
                Основные параметры
            </h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.campaigns.update', $campaign) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label" for="name">Название рассылки</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $campaign->name) }}"
                        class="input @error('name') is-invalid @enderror"
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
                        value="{{ old('subject', $campaign->subject) }}"
                        class="input @error('subject') is-invalid @enderror"
                        required
                    >
                    @error('subject')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="delay_seconds">Задержка между письмами (секунды)</label>
                    <input
                        type="number"
                        id="delay_seconds"
                        name="delay_seconds"
                        value="{{ old('delay_seconds', $campaign->delay_seconds) }}"
                        class="input @error('delay_seconds') is-invalid @enderror"
                        min="1"
                        max="60"
                        required
                    >
                    <small class="form-help">
                        Задержка помогает избежать блокировки со стороны почтовых провайдеров
                    </small>
                    @error('delay_seconds')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                    <x-button type="submit" variant="accent" icon="check">
                        Сохранить изменения
                    </x-button>
                    <x-button
                        variant="secondary"
                        href="{{ route('admin.campaigns.show', $campaign) }}"
                    >
                        Отмена
                    </x-button>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top: var(--space-6);">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="file-text" class="icon-md"></i>
                Шаблон и данные
            </h2>
        </div>
        <div class="card-body">
            <p style="margin-bottom: var(--space-4); color: var(--gray-600);">
                Для изменения шаблона письма, списка получателей или изображений используйте кнопку ниже.
                Это перезапустит процесс настройки рассылки.
            </p>

            <x-button
                variant="secondary"
                icon="upload"
                href="{{ route('admin.campaigns.upload', $campaign) }}"
            >
                Заменить шаблон и данные
            </x-button>
        </div>
    </div>
</div>
@endsection
