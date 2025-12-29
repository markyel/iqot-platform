@extends('layouts.cabinet')

@section('title', 'Диагностика системы')

@push('styles')
<style>
    .admin-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .status-ok { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; padding: 0.5rem 1rem; border-radius: 6px; display: inline-block; font-weight: 600; font-size: 0.875rem; }
    .status-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 0.5rem 1rem; border-radius: 6px; display: inline-block; font-weight: 600; font-size: 0.875rem; }
    .status-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; padding: 0.5rem 1rem; border-radius: 6px; display: inline-block; font-weight: 600; font-size: 0.875rem; }
    .code-block { background: #f9fafb; border: 1px solid #e5e7eb; padding: 1rem; border-radius: 6px; font-family: monospace; font-size: 0.875rem; overflow-x: auto; margin-top: 0.5rem; }
    .detail-list { margin-top: 0.5rem; padding-left: 1.5rem; color: #6b7280; font-size: 0.875rem; }
    .test-form { background: #f9fafb; border: 1px solid #e5e7eb; padding: 1.5rem; border-radius: 8px; margin-top: 1rem; }
    .form-input { width: 100%; background: #ffffff; border: 1px solid #d1d5db; color: #111827; padding: 0.625rem 1rem; border-radius: 8px; outline: none; }
    .form-input:focus { border-color: #10b981; }
    .btn-green { background: #10b981; color: white; padding: 0.625rem 1.5rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: background 0.2s; }
    .btn-green:hover { background: #059669; }
    .spinner { display: none; width: 1rem; height: 1rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spin 0.75s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; color: #111827;">
            🔧 Диагностика системы
        </h1>
        <p style="color: #6b7280; margin-top: 0.5rem;">Проверка конфигурации и работоспособности сервисов</p>
    </div>

    @foreach($diagnostics as $key => $diag)
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
            <div>
                <h2 style="font-size: 1.125rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;">
                    {{ $diag['name'] }}
                </h2>
                <p style="color: #6b7280; font-size: 0.875rem;">{{ $diag['message'] }}</p>
            </div>
            <span class="status-{{ $diag['status'] }}">
                @if($diag['status'] === 'ok') ✓ OK
                @elseif($diag['status'] === 'warning') ⚠ Предупреждение
                @else ✗ Ошибка
                @endif
            </span>
        </div>

        @if(!empty($diag['config']))
        <div style="margin-top: 1rem;">
            <div style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">Конфигурация:</div>
            <div class="code-block">
                @foreach($diag['config'] as $k => $v)
                <div><strong>{{ $k }}:</strong> {{ empty($v) ? '(не задано)' : (strlen($v) > 80 ? substr($v, 0, 80) . '...' : $v) }}</div>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($diag['details']))
        <ul class="detail-list">
            @foreach($diag['details'] as $detail)
            <li>{{ $detail }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endforeach

    <!-- Тест парсинга -->
    <div class="admin-card">
        <h2 style="font-size: 1.125rem; font-weight: 700; color: #111827; margin-bottom: 1rem;">
            🧪 Тестирование AI парсинга
        </h2>
        <p style="color: #6b7280; margin-bottom: 1rem; font-size: 0.875rem;">
            Введите текст заявки для проверки работы сервиса парсинга
        </p>

        <div class="test-form">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: #374151;">
                    Текст заявки
                </label>
                <textarea id="testText" class="form-input" rows="4" placeholder="Например: Кнопка вызова лифта Otis XAA177AK1 - 2 шт, Датчик уровня KONE - 1 шт"></textarea>
            </div>

            <button type="button" onclick="testParse()" class="btn-green">
                <span class="spinner" id="spinner"></span>
                <span id="btnText">Протестировать</span>
            </button>

            <div id="testResult" style="margin-top: 1rem; display: none;"></div>
        </div>
    </div>
</div>

<script>
async function testParse() {
    const text = document.getElementById('testText').value.trim();
    if (!text) {
        alert('Введите текст заявки');
        return;
    }

    const btn = document.querySelector('.btn-green');
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
                <div style="background: #d1fae5; border: 1px solid #a7f3d0; padding: 1rem; border-radius: 8px; color: #065f46;">
                    <strong>✓ Успешно распознано позиций: ${result.items.length}</strong>
                    <div style="margin-top: 1rem; background: white; padding: 1rem; border-radius: 6px; color: #111827;">
                        ${result.items.map((item, i) => `
                            <div style="padding: 0.5rem 0; border-bottom: 1px solid #e5e7eb;">
                                <strong>${i + 1}. ${item.name}</strong><br>
                                <small style="color: #6b7280;">
                                    Бренд: ${item.brand || '—'} |
                                    Артикул: ${item.article || '—'} |
                                    Количество: ${item.quantity} ${item.unit || 'шт.'}
                                </small>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div style="background: #fee2e2; border: 1px solid #fecaca; padding: 1rem; border-radius: 8px; color: #991b1b;">
                    <strong>✗ Ошибка парсинга</strong><br>
                    <div style="margin-top: 0.5rem; font-size: 0.875rem;">${result.message || 'Неизвестная ошибка'}</div>
                    ${result.error ? `<div style="margin-top: 0.5rem; font-family: monospace; font-size: 0.75rem;">Код ошибки: ${result.error}</div>` : ''}
                </div>
            `;
        }

        resultDiv.style.display = 'block';
    } catch (e) {
        resultDiv.innerHTML = `
            <div style="background: #fee2e2; border: 1px solid #fecaca; padding: 1rem; border-radius: 8px; color: #991b1b;">
                <strong>✗ Ошибка соединения</strong><br>
                <div style="margin-top: 0.5rem; font-size: 0.875rem;">${e.message}</div>
            </div>
        `;
        resultDiv.style.display = 'block';
    } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
        btnText.textContent = 'Протестировать';
    }
}
</script>
@endsection
