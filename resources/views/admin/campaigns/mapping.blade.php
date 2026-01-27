@extends('layouts.cabinet')

@section('title', 'Маппинг полей')

@section('content')
<div style="max-width: 1000px; margin: 0 auto;">
    <x-page-header
        :title="$campaign->name"
        description="Шаг 3 из 3: Настройка соответствий"
    >
        <x-slot name="actions">
            <x-button variant="secondary" icon="upload" :href="route('admin.campaigns.upload', $campaign)">
                Заменить файлы
            </x-button>
        </x-slot>
    </x-page-header>

    <div class="alert alert-info" style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: start; gap: var(--space-3);">
            <i data-lucide="info" class="icon-md"></i>
            <div>
                <strong>Найденные переменные в шаблоне:</strong>
                @if(count($templateVariables) > 0)
                    <div style="margin-top: var(--space-2);">
                        @foreach($templateVariables as $var)
                            <code style="background: var(--gray-100); padding: 2px 6px; border-radius: 4px; margin-right: 8px;">&#123;&#123;{{ $var }}&#125;&#125;</code>
                        @endforeach
                    </div>
                @else
                    <p style="margin: var(--space-2) 0 0;">Переменные не найдены</p>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="link" class="icon-md"></i>
                Укажите источники данных для каждой переменной
            </h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.campaigns.save-mapping', $campaign) }}" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="email_source">Email получателя</label>
                    <select id="email_source" name="email_source" class="input" required>
                        <option value="">-- Выберите источник email --</option>
                        @foreach($dataSources as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if(count($templateVariables) > 0)
                <h3 style="margin: var(--space-6) 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600;">
                    Маппинг переменных шаблона
                </h3>

                @foreach($templateVariables as $variable)
                <div class="form-group">
                    <label class="form-label" for="var_{{ $variable }}">&#123;&#123;{{ $variable }}&#125;&#125;</label>
                    <select id="var_{{ $variable }}" name="{{ $variable }}" class="input">
                        <option value="">-- Оставить пустым --</option>
                        @foreach($dataSources as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @endforeach
                @endif

                @if(count($templateImages) > 0)
                <h3 style="margin: var(--space-6) 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600;">
                    Загрузка изображений для шаблона
                </h3>

                <div class="alert alert-info" style="margin-bottom: var(--space-4);">
                    <div style="display: flex; align-items: start; gap: var(--space-3);">
                        <i data-lucide="info" class="icon-md"></i>
                        <div>
                            <strong>Изображения будут встроены в письмо</strong>
                            <p style="margin: var(--space-2) 0 0;">Загрузите файлы изображений (PNG/JPG), которые используются в шаблоне. Они будут встроены в письмо через CID-вложения, что гарантирует их отображение во всех почтовых клиентах.</p>
                        </div>
                    </div>
                </div>

                @foreach($templateImages as $imageSrc)
                <div class="form-group">
                    <label class="form-label" for="image_{{ md5($imageSrc) }}">
                        Изображение: <code style="background: var(--gray-100); padding: 2px 6px; border-radius: 4px;">{{ $imageSrc }}</code>
                    </label>
                    <input
                        type="file"
                        id="image_{{ md5($imageSrc) }}"
                        name="image_{{ md5($imageSrc) }}"
                        accept="image/png,image/jpeg,image/jpg"
                        class="input"
                        required
                    >
                </div>
                @endforeach
                @endif

                <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                    <x-button type="submit" variant="accent" icon="check">
                        Сохранить и завершить
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

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); margin-top: var(--space-6);">
        <!-- Данные получателя -->
        <div class="alert alert-info">
            <div style="display: flex; align-items: start; gap: var(--space-3);">
                <i data-lucide="database" class="icon-md"></i>
                <div style="flex: 1;">
                    <strong>Данные первого получателя:</strong>
                    <div style="margin-top: var(--space-3); padding: var(--space-3); background: var(--gray-50); border-radius: 4px; font-family: monospace; font-size: 0.875rem;">
                        @php
                            $firstRecipient = $recipientsData[0] ?? [];
                        @endphp
                        @foreach($firstRecipient as $key => $value)
                            <div><strong>{{ $key }}:</strong> {{ $value }}</div>
                        @endforeach
                        @if($additionalData)
                            <div style="margin-top: var(--space-2);"><strong>Доп. файл (строка 1):</strong> {{ $additionalData[0] ?? '' }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Предпросмотр письма -->
        <div class="card">
            <div class="card-header">
                <h3 style="margin: 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="eye" class="icon-md"></i>
                    Предпросмотр письма
                </h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <iframe
                    id="email-preview"
                    style="width: 100%; min-height: 400px; border: none; background: white;"
                    sandbox="allow-same-origin"
                ></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const template = @json($campaign->html_template);
    const firstRecipient = @json($recipientsData[0] ?? []);
    const additionalData = @json($additionalData);
    const iframe = document.getElementById('email-preview');

    function updatePreview() {
        let html = template;

        // Собираем все данные для подстановки
        const data = {};

        // Получаем выбранные маппинги
        const selects = document.querySelectorAll('select[name]:not([name="email_source"])');
        selects.forEach(select => {
            const variable = select.name;
            const source = select.value;

            if (source && source.startsWith('recipients_')) {
                const column = source.replace('recipients_', '');
                data[variable] = firstRecipient[column] || '';
            } else if (source === 'additional_line' && additionalData) {
                data[variable] = additionalData[0] || '';
            } else if (source === '_unsubscribe_url') {
                data[variable] = '#unsubscribe-preview';
            }
        });

        // Заменяем только те переменные, для которых есть значение
        for (const [key, value] of Object.entries(data)) {
            if (value) {
                // Экранируем спецсимволы в ключе для регулярки
                const escapedKey = key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                const regex = new RegExp('\\{\\{' + escapedKey + '\\}\\}', 'g');
                html = html.replace(regex, value);
            }
        }

        // Добавляем CSS стили для подсветки незаполненных переменных
        const styledHtml = `
            <style>
                body { margin: 0; padding: 20px; }
            </style>
            ${html}
        `;

        // Записываем в iframe
        iframe.contentDocument.open();
        iframe.contentDocument.write(styledHtml);
        iframe.contentDocument.close();

        // Автоматически подстраиваем высоту
        setTimeout(() => {
            iframe.style.height = iframe.contentDocument.body.scrollHeight + 'px';
        }, 100);
    }

    // Обновляем при изменении селектов
    document.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', updatePreview);
    });

    // Первоначальный рендер
    updatePreview();
});
</script>
@endsection
