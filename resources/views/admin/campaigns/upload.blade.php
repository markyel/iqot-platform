@extends('layouts.cabinet')

@section('title', 'Загрузка файлов')

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    <x-page-header
        title="{{ $campaign->name }}"
        description="Шаг 2 из 3: Загрузка данных"
    />

    <div class="alert alert-info" style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: start; gap: var(--space-3);">
            <i data-lucide="info" class="icon-md"></i>
            <div>
                <strong>Как это работает</strong>
                <ul style="margin: var(--space-2) 0 0 var(--space-4); padding-left: var(--space-4);">
                    <li>Загрузите CSV файл с получателями (обязательно)</li>
                    <li>Опционально загрузите TXT/CSV с дополнительными данными (например, промокоды)</li>
                    <li>На следующем шаге укажете, какие данные куда подставлять</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="upload" class="icon-md"></i>
                Загрузка файлов с данными
            </h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.campaigns.process-upload', $campaign) }}" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="template_file">Файл шаблона (HTML)</label>
                    <input
                        type="file"
                        id="template_file"
                        name="template_file"
                        accept=".html,.htm"
                        class="input @error('template_file') is-invalid @enderror"
                    >
                    <small class="form-help">
                        Опционально: загрузите новый HTML шаблон письма. Если не выбран - останется текущий шаблон.
                        <br>Используйте переменные в формате: &#123;&#123;VARIABLE_NAME&#125;&#125;
                    </small>
                    @error('template_file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="recipients_file">Файл с получателями (CSV)</label>
                    <input
                        type="file"
                        id="recipients_file"
                        name="recipients_file"
                        accept=".csv,.txt"
                        class="input @error('recipients_file') is-invalid @enderror"
                    >
                    <small class="form-help">
                        Опционально: загрузите новый список получателей. Если не выбран - останется текущий список.
                        <br>CSV файл с колонками. Обязательно должна быть колонка с email адресами.
                        <br>Пример: email,name,company
                    </small>
                    @error('recipients_file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="data_file">Дополнительный файл (TXT/CSV)</label>
                    <input
                        type="file"
                        id="data_file"
                        name="data_file"
                        accept=".csv,.txt"
                        class="input @error('data_file') is-invalid @enderror"
                    >
                    <small class="form-help">
                        Опционально: файл с дополнительными данными (например, список промокодов по одному на строку).
                        <br>Данные будут распределены по порядку: 1-я строка → 1-му получателю и т.д.
                    </small>
                    @error('data_file')
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
