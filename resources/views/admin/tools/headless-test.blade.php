@extends('layouts.cabinet')

@section('title', 'Тест рендера страниц (headless)')

@section('content')
<x-page-header
    title="Тест рендера страниц"
    description="Ввёл URL → скриншот страницы через headless-Chrome (тот же движок, что парсит цены с сайтов поставщиков)"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('admin.diagnostics.index')" icon="activity">
            Диагностика
        </x-button>
    </x-slot>
</x-page-header>

<!-- Форма -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.tools.headless.render') }}">
            @csrf
            <div class="form-group" style="margin: 0 0 var(--space-4);">
                <label class="form-label" for="url">URL страницы</label>
                <input type="text" name="url" id="url" class="input"
                       placeholder="https://example.com/product/123"
                       value="{{ $url }}" autofocus>
                @error('url')
                    <div style="color: var(--danger-600); font-size: var(--text-sm); margin-top: var(--space-1);">{{ $message }}</div>
                @enderror
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: var(--space-5); align-items: center;">
                <label class="form-label" style="display: flex; align-items: center; gap: var(--space-2); margin: 0;">
                    <input type="checkbox" name="full_page" value="1" {{ $fullPage ? 'checked' : '' }}>
                    Вся страница (не только видимая область)
                </label>
                <label class="form-label" style="display: flex; align-items: center; gap: var(--space-2); margin: 0;">
                    <input type="checkbox" name="with_text" value="1" {{ $withText ? 'checked' : '' }}>
                    Также извлечь текст (что видит AI)
                </label>
                <div style="margin-left: auto;">
                    <x-button type="submit" variant="primary" icon="camera">Сделать скриншот</x-button>
                </div>
            </div>
            <div style="margin-top: var(--space-3); font-size: var(--text-xs); color: var(--neutral-500);">
                Рендер синхронный (ждём загрузку и networkIdle) — обычно 3–15 c. С галочкой «извлечь текст» браузер запускается дважды (скриншот + текст), т.е. дольше.
            </div>
        </form>
    </div>
</div>

@if($result !== null)
    @if($result['ok'])
        <!-- Мета -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
            <div class="card"><div class="card-body">
                <div style="font-size: var(--text-sm); color: var(--neutral-600);">Время рендера</div>
                <div style="font-size: var(--text-xl); font-weight: 700; font-family: var(--font-mono);">{{ number_format($result['elapsed_ms'] / 1000, 1, ',', ' ') }} c</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div style="font-size: var(--text-sm); color: var(--neutral-600);">Размер скриншота</div>
                <div style="font-size: var(--text-xl); font-weight: 700; font-family: var(--font-mono);">{{ number_format(strlen(base64_decode($result['png_base64'])) / 1024, 0, ',', ' ') }} КБ</div>
            </div></div>
            @if($withText)
            <div class="card"><div class="card-body">
                <div style="font-size: var(--text-sm); color: var(--neutral-600);">Извлечено текста</div>
                <div style="font-size: var(--text-xl); font-weight: 700; font-family: var(--font-mono);">{{ number_format($result['text_length'], 0, ',', ' ') }} симв.</div>
            </div></div>
            @endif
        </div>

        <!-- Извлечённый текст -->
        @if($withText)
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <i data-lucide="file-text" style="width: 1.25rem; height: 1.25rem;"></i>
                Извлечённый текст (document.body.innerText)
            </div>
            <div class="card-body">
                @if($result['text'] !== null && $result['text'] !== '')
                    <pre style="white-space: pre-wrap; word-break: break-word; max-height: 320px; overflow-y: auto; font-size: var(--text-sm); background: var(--neutral-50); padding: var(--space-4); border-radius: var(--radius-md); margin: 0;">{{ $result['text'] }}</pre>
                @else
                    <div style="color: var(--warning-600); font-size: var(--text-sm);">Текст пустой — страница ничего видимого не отдала (возможна заглушка/пустой body).</div>
                @endif
            </div>
        </div>
        @endif

        <!-- Скриншот -->
        <div class="card">
            <div class="card-header" style="display: flex; align-items: center;">
                <i data-lucide="image" style="width: 1.25rem; height: 1.25rem;"></i>
                Скриншот
                <span style="margin-left: auto;">
                    <a href="data:image/png;base64,{{ $result['png_base64'] }}" download="screenshot.png"
                       class="btn btn-secondary btn-sm" style="text-decoration: none;">
                        <i data-lucide="download" style="width: 1rem; height: 1rem;"></i> Скачать PNG
                    </a>
                </span>
            </div>
            <div class="card-body" style="overflow: auto; max-height: 70vh;">
                <img src="data:image/png;base64,{{ $result['png_base64'] }}" alt="Скриншот {{ $url }}"
                     style="max-width: 100%; height: auto; border: 1px solid var(--neutral-200); border-radius: var(--radius-md);">
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <div style="display: flex; gap: var(--space-3); align-items: flex-start;">
                    <i data-lucide="alert-triangle" style="width: 1.5rem; height: 1.5rem; color: var(--danger-600); flex-shrink: 0;"></i>
                    <div>
                        <div style="font-weight: 600; color: var(--danger-600); margin-bottom: var(--space-1);">Не удалось отрендерить страницу</div>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600);">{{ $result['error'] }}</div>
                        <div style="font-size: var(--text-xs); color: var(--neutral-500); margin-top: var(--space-2);">
                            URL: {{ $url }} · Chrome: {{ $result['chrome'] }} · {{ number_format($result['elapsed_ms'] / 1000, 1, ',', ' ') }} c
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif

@push('scripts')
<script>
lucide.createIcons();
</script>
@endpush
@endsection
