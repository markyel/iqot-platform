@extends('layouts.cabinet')

@section('title', 'Диагностика системы')

<x-page-header
    title="Диагностика системы"
    description="Проверка конфигурации и работоспособности сервисов"
/>

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    @foreach($diagnostics as $key => $diag)
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-4);">
            <div>
                <h2 style="font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin-bottom: var(--space-2);">
                    {{ $diag['name'] }}
                </h2>
                <p style="color: var(--text-muted); font-size: 0.875rem;">{{ $diag['message'] }}</p>
            </div>
            @if($diag['status'] === 'ok')
                <x-badge type="completed" size="lg">
                    <i data-lucide="check" class="icon-xs"></i>
                    OK
                </x-badge>
            @elseif($diag['status'] === 'warning')
                <x-badge type="pending" size="lg">
                    <i data-lucide="alert-triangle" class="icon-xs"></i>
                    Предупреждение
                </x-badge>
            @else
                <x-badge type="cancelled" size="lg">
                    <i data-lucide="x" class="icon-xs"></i>
                    Ошибка
                </x-badge>
            @endif
        </div>

        @if(!empty($diag['config']))
        <div style="margin-top: var(--space-4);">
            <div style="font-weight: 600; color: var(--text-secondary); margin-bottom: var(--space-2); font-size: 0.875rem;">
                Конфигурация:
            </div>
            <div class="code-block">
                @foreach($diag['config'] as $k => $v)
                <div style="margin-bottom: var(--space-1);">
                    <strong>{{ $k }}:</strong> {{ empty($v) ? '(не задано)' : (strlen($v) > 80 ? substr($v, 0, 80) . '...' : $v) }}
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($diag['details']))
        <ul style="margin-top: var(--space-3); padding-left: var(--space-6); color: var(--text-muted); font-size: 0.875rem;">
            @foreach($diag['details'] as $detail)
            <li style="margin-bottom: var(--space-1);">{{ $detail }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endforeach

    <!-- Тест парсинга -->
    <div class="card">
        <h2 style="font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
            <i data-lucide="flask-conical" class="icon-md"></i>
            Тестирование AI парсинга
        </h2>
        <p style="color: var(--text-muted); margin-bottom: var(--space-4); font-size: 0.875rem;">
            Введите текст заявки для проверки работы сервиса парсинга
        </p>

        <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: var(--space-6);">
            <div class="form-group">
                <label class="form-label">
                    <i data-lucide="file-text" class="icon-xs"></i>
                    Текст заявки
                </label>
                <textarea id="testText" class="input" rows="4" placeholder="Например: Кнопка вызова лифта Otis XAA177AK1 - 2 шт, Датчик уровня KONE - 1 шт"></textarea>
            </div>

            <x-button variant="primary" type="button" onclick="testParse()" icon="play">
                <span id="btnText">Протестировать</span>
                <span class="spinner" id="spinner" style="display: none; margin-left: var(--space-2);"></span>
            </x-button>

            <div id="testResult" style="margin-top: var(--space-4); display: none;"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function testParse() {
    const text = document.getElementById('testText').value.trim();
    if (!text) {
        alert('Введите текст заявки');
        return;
    }

    const btn = document.querySelector('[onclick="testParse()"]');
    const spinner = document.getElementById('spinner');
    const btnText = document.getElementById('btnText');
    const resultDiv = document.getElementById('testResult');

    btn.disabled = true;
    spinner.style.display = 'inline-block';
    btnText.textContent = 'Обработка...';
    resultDiv.style.display = 'none';

    try {
        const response = await fetch('{{ route("admin.diagnostics.test-parse") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ text: text })
        });

        const result = await response.json();

        if (result.success && result.items) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <div style="display: flex; align-items: start; gap: var(--space-3);">
                        <i data-lucide="check-circle" class="icon-md"></i>
                        <div style="flex: 1;">
                            <strong>Успешно распознано позиций: ${result.items.length}</strong>
                            <div style="margin-top: var(--space-4); background: white; padding: var(--space-4); border-radius: var(--radius-md); color: var(--text-primary);">
                                ${result.items.map((item, i) => `
                                    <div style="padding: var(--space-2) 0; border-bottom: 1px solid var(--border-color);">
                                        <strong>${i + 1}. ${item.name}</strong><br>
                                        <small style="color: var(--text-muted);">
                                            Бренд: ${item.brand || '—'} |
                                            Артикул: ${item.article || '—'} |
                                            Количество: ${item.quantity} ${item.unit || 'шт.'}
                                        </small>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-error">
                    <div style="display: flex; align-items: start; gap: var(--space-3);">
                        <i data-lucide="x-circle" class="icon-md"></i>
                        <div>
                            <strong>Ошибка парсинга</strong>
                            <div style="margin-top: var(--space-2); font-size: 0.875rem;">${result.message || 'Неизвестная ошибка'}</div>
                            ${result.error ? `<div style="margin-top: var(--space-2); font-family: monospace; font-size: 0.75rem;">Код ошибки: ${result.error}</div>` : ''}
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();
        }

        resultDiv.style.display = 'block';
    } catch (e) {
        resultDiv.innerHTML = `
            <div class="alert alert-error">
                <div style="display: flex; align-items: start; gap: var(--space-3);">
                    <i data-lucide="wifi-off" class="icon-md"></i>
                    <div>
                        <strong>Ошибка соединения</strong>
                        <div style="margin-top: var(--space-2); font-size: 0.875rem;">${e.message}</div>
                    </div>
                </div>
            </div>
        `;
        resultDiv.style.display = 'block';
        lucide.createIcons();
    } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
        btnText.textContent = 'Протестировать';
    }
}

lucide.createIcons();
</script>

<style>
.code-block {
    background: var(--surface);
    border: 1px solid var(--border-color);
    padding: var(--space-4);
    border-radius: var(--radius-md);
    font-family: monospace;
    font-size: 0.875rem;
    overflow-x: auto;
}

.spinner {
    width: 1rem;
    height: 1rem;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spin 0.75s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
@endpush
