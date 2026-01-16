@extends('layouts.cabinet')

@section('title', 'Создать заявку')

@section('content')
<x-page-header
    title="Создать заявку через n8n"
    description="Создайте новую заявку с помощью AI-парсинга или заполните данные вручную"
/>

@if($errors->any())
<div class="alert alert-error">
    <i data-lucide="x-circle" class="alert-icon"></i>
    <div class="alert-content">
        <strong>Ошибки валидации:</strong>
        <ul style="margin: var(--space-2) 0 0 var(--space-6); padding-left: 0; list-style-position: inside;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@if(session('error'))
<div class="alert alert-error">
    <i data-lucide="x-circle" class="alert-icon"></i>
    <div class="alert-content">
        <strong>{{ session('error') }}</strong>
    </div>
</div>
@endif

<form action="{{ route('admin.manage.requests.store') }}" method="POST" id="request-form">
    @csrf

    <!-- AI-парсинг -->
    <div class="card">
        <div class="card-header">
            <div style="display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="bot" style="width: 1.25rem; height: 1.25rem;"></i>
                <span>AI-парсинг текста заявки (опционально)</span>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i data-lucide="info" class="alert-icon"></i>
                <div class="alert-content">
                    <strong>Подсказка:</strong> Введите список позиций в свободной форме. AI автоматически распознает названия, бренды, артикулы и количество.
                    <br><em>Пример: "Кнопка вызова OTIS AAA123 10шт, датчик SALSIS 5шт"</em>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Текст заявки</label>
                <textarea id="parse-text" class="input textarea" rows="6" placeholder="Введите список позиций..."></textarea>
            </div>

            <button type="button" id="btn-parse" class="btn btn-primary btn-md">
                <i data-lucide="wand-2" class="icon-sm"></i>
                Распознать позиции
            </button>
        </div>
    </div>

    <!-- Основные настройки -->
    <div class="card">
        <div class="card-header">Основные настройки</div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-6);">
                <div class="form-group">
                    <label for="status" class="form-label form-label-required">Статус</label>
                    <select name="status" id="status" class="input select" required>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ old('status', 'draft') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <span class="form-hint">Заявки со статусом "В работу" автоматически попадут в очередь на рассылку</span>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer; margin-top: var(--space-8);">
                        <input type="checkbox" name="is_customer_request" id="is_customer_request" value="1"
                            {{ old('is_customer_request') ? 'checked' : '' }}
                            style="width: 1.25rem; height: 1.25rem;">
                        <span>Именная заявка (для конкретного клиента)</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Данные клиента -->
    <div class="card" style="display: none;" id="customer-fields">
        <div class="card-header">Данные клиента</div>
        <div class="card-body">
            <div class="form-group">
                <label for="client_organization_id" class="form-label">Выбрать существующую организацию</label>
                <select name="client_organization_id" id="client_organization_id" class="input select">
                    <option value="">-- Или создать новую ниже --</option>
                    @foreach($organizations as $id => $name)
                        <option value="{{ $id }}" {{ old('client_organization_id') == $id ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-6);">
                <div class="form-group">
                    <label for="customer_company" class="form-label form-label-required">Название компании</label>
                    <input type="text" name="customer_company" id="customer_company" class="input" value="{{ old('customer_company') }}">
                </div>

                <div class="form-group">
                    <label for="customer_contact_person" class="form-label">Контактное лицо</label>
                    <input type="text" name="customer_contact_person" id="customer_contact_person" class="input" value="{{ old('customer_contact_person') }}">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-6);">
                <div class="form-group">
                    <label for="customer_email" class="form-label">Email</label>
                    <input type="email" name="customer_email" id="customer_email" class="input" value="{{ old('customer_email') }}">
                </div>

                <div class="form-group">
                    <label for="customer_phone" class="form-label">Телефон</label>
                    <input type="text" name="customer_phone" id="customer_phone" class="input" value="{{ old('customer_phone') }}">
                </div>
            </div>
        </div>
    </div>

    <!-- Позиции заявки -->
    <div class="card">
        <div class="card-header">
            <span>Позиции заявки</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Название *</th>
                            <th>Бренд</th>
                            <th>Артикул</th>
                            <th style="width: 100px;">Кол-во *</th>
                            <th style="width: 100px;">Ед. изм. *</th>
                            <th>Категория *</th>
                            <th>Тип оборудования</th>
                            <th>Область применения</th>
                            <th style="width: 60px;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody">
                        @if(old('items'))
                            @foreach(old('items') as $index => $item)
                                <tr data-index="{{ $index }}">
                                    <td data-label="#">{{ $index + 1 }}</td>
                                    <td data-label="Название">
                                        <textarea name="items[{{ $index }}][name]" class="input" rows="2" required style="min-width: 200px;">{{ $item['name'] }}</textarea>
                                    </td>
                                    <td data-label="Бренд">
                                        <input type="text" name="items[{{ $index }}][brand]" value="{{ $item['brand'] ?? '' }}" class="input">
                                    </td>
                                    <td data-label="Артикул">
                                        <input type="text" name="items[{{ $index }}][article]" value="{{ $item['article'] ?? '' }}" class="input">
                                    </td>
                                    <td data-label="Кол-во">
                                        <input type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" required class="input">
                                    </td>
                                    <td data-label="Ед. изм.">
                                        <input type="text" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? 'шт' }}" required class="input">
                                    </td>
                                    <td data-label="Категория">
                                        <select name="items[{{ $index }}][category]" required class="input select">
                                            <option value="">-</option>
                                            @foreach($categories as $catId => $catName)
                                                <option value="{{ $catName }}" {{ ($item['category'] ?? '') === $catName ? 'selected' : '' }}>{{ $catName }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td data-label="Тип оборудования">
                                        <select name="items[{{ $index }}][product_type_id]" class="input select">
                                            <option value="">-</option>
                                            @foreach($productTypes as $typeId => $typeName)
                                                <option value="{{ $typeId }}" {{ ($item['product_type_id'] ?? '') == $typeId ? 'selected' : '' }}>{{ $typeName }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td data-label="Область применения">
                                        <select name="items[{{ $index }}][domain_id]" class="input select">
                                            <option value="">-</option>
                                            @foreach($applicationDomains as $domainId => $domainName)
                                                <option value="{{ $domainId }}" {{ ($item['domain_id'] ?? '') == $domainId ? 'selected' : '' }}>{{ $domainName }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td data-label="">
                                        <x-button type="button" variant="danger" size="sm" class="btn-remove-item">
                                            <i data-lucide="x" style="width: 1rem; height: 1rem;"></i>
                                        </x-button>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <!-- Пустая строка по умолчанию -->
                            <tr data-index="0">
                                <td data-label="#">1</td>
                                <td data-label="Название">
                                    <textarea name="items[0][name]" class="input" rows="2" required style="min-width: 200px;"></textarea>
                                </td>
                                <td data-label="Бренд">
                                    <input type="text" name="items[0][brand]" class="input">
                                </td>
                                <td data-label="Артикул">
                                    <input type="text" name="items[0][article]" class="input">
                                </td>
                                <td data-label="Кол-во">
                                    <input type="number" name="items[0][quantity]" value="1" min="1" required class="input">
                                </td>
                                <td data-label="Ед. изм.">
                                    <input type="text" name="items[0][unit]" value="шт" required class="input">
                                </td>
                                <td data-label="Категория">
                                    <select name="items[0][category]" required class="input select">
                                        <option value="">-</option>
                                        @foreach($categories as $catId => $catName)
                                            <option value="{{ $catName }}">{{ $catName }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td data-label="Тип оборудования">
                                    <select name="items[0][product_type_id]" class="input select">
                                        <option value="">-</option>
                                        @foreach($productTypes as $typeId => $typeName)
                                            <option value="{{ $typeId }}">{{ $typeName }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td data-label="Область применения">
                                    <select name="items[0][domain_id]" class="input select">
                                        <option value="">-</option>
                                        @foreach($applicationDomains as $domainId => $domainName)
                                            <option value="{{ $domainId }}">{{ $domainName }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td data-label="">
                                    <x-button type="button" variant="danger" size="sm" class="btn-remove-item">
                                        <i data-lucide="x" style="width: 1rem; height: 1rem;"></i>
                                    </x-button>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <x-button type="button" id="btn-add-item" variant="secondary" icon="plus">
                Добавить позицию
            </x-button>
        </div>
    </div>

    <!-- Дополнительно -->
    <div class="card">
        <div class="card-header">Дополнительно</div>
        <div class="card-body">
            <div class="form-group">
                <label for="notes" class="form-label">Заметки к заявке</label>
                <textarea name="notes" id="notes" class="input textarea" rows="4">{{ old('notes') }}</textarea>
                <span class="form-hint">Внутренние комментарии, видны только сотрудникам</span>
            </div>
        </div>
    </div>

    <!-- Кнопки действий -->
    <div style="display: flex; gap: var(--space-4); justify-content: flex-end; margin-bottom: var(--space-8);">
        <x-button tag="a" :href="route('admin.manage.requests.index')" variant="secondary">
            Отмена
        </x-button>
        <x-button type="submit" variant="success" icon="check">
            Создать заявку
        </x-button>
    </div>
</form>

@push('scripts')
<script>
// Данные для select'ов
const categories = @json($categories);
const productTypes = @json($productTypes);
const applicationDomains = @json($applicationDomains);

let itemIndex = {{ old('items') ? count(old('items')) : 1 }};

// Инициализация иконок
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}

// Показать/скрыть поля клиента
document.getElementById('is_customer_request').addEventListener('change', function() {
    const customerFields = document.getElementById('customer-fields');
    const customerCompany = document.getElementById('customer_company');

    if (this.checked) {
        customerFields.style.display = 'block';
        customerCompany.required = true;
    } else {
        customerFields.style.display = 'none';
        customerCompany.required = false;
    }
});

// Удаление строки
document.getElementById('items-tbody').addEventListener('click', function(e) {
    const removeBtn = e.target.closest('.btn-remove-item');
    if (removeBtn) {
        const row = removeBtn.closest('tr');
        const tbody = document.getElementById('items-tbody');

        // Не удаляем последнюю строку
        if (tbody.querySelectorAll('tr').length > 1) {
            row.remove();
            reindexItems();
        } else {
            alert('Должна остаться хотя бы одна позиция');
        }
    }
});

// Добавление новой позиции
document.getElementById('btn-add-item').addEventListener('click', function() {
    addItemRow({});
    lucide.createIcons();
});

function addItemRow(data = {}) {
    const tbody = document.getElementById('items-tbody');
    const row = document.createElement('tr');
    row.dataset.index = itemIndex;

    row.innerHTML = `
        <td data-label="#">${itemIndex + 1}</td>
        <td data-label="Название">
            <textarea name="items[${itemIndex}][name]" class="input" rows="2" required style="min-width: 200px;">${escapeHtml(data.name || '')}</textarea>
        </td>
        <td data-label="Бренд">
            <input type="text" name="items[${itemIndex}][brand]" value="${escapeHtml(data.brand || '')}" class="input">
        </td>
        <td data-label="Артикул">
            <input type="text" name="items[${itemIndex}][article]" value="${escapeHtml(data.article || '')}" class="input">
        </td>
        <td data-label="Кол-во">
            <input type="number" name="items[${itemIndex}][quantity]" value="${data.quantity || 1}" min="1" required class="input">
        </td>
        <td data-label="Ед. изм.">
            <input type="text" name="items[${itemIndex}][unit]" value="${escapeHtml(data.unit || 'шт')}" required class="input">
        </td>
        <td data-label="Категория">
            <select name="items[${itemIndex}][category]" required class="input select">
                <option value="">-</option>
                ${Object.entries(categories).map(([id, name]) =>
                    `<option value="${escapeHtml(name)}" ${(data.category === name) ? 'selected' : ''}>${escapeHtml(name)}</option>`
                ).join('')}
            </select>
        </td>
        <td data-label="Тип оборудования">
            <select name="items[${itemIndex}][product_type_id]" class="input select">
                <option value="">-</option>
                ${Object.entries(productTypes).map(([id, name]) =>
                    `<option value="${id}" ${(data.product_type_id == id) ? 'selected' : ''}>${escapeHtml(name)}</option>`
                ).join('')}
            </select>
        </td>
        <td data-label="Область применения">
            <select name="items[${itemIndex}][domain_id]" class="input select">
                <option value="">-</option>
                ${Object.entries(applicationDomains).map(([id, name]) =>
                    `<option value="${id}" ${(data.domain_id == id) ? 'selected' : ''}>${escapeHtml(name)}</option>`
                ).join('')}
            </select>
        </td>
        <td data-label="">
            <x-button type="button" variant="danger" size="sm" class="btn-remove-item">
                <i data-lucide="x" style="width: 1rem; height: 1rem;"></i>
            </x-button>
        </td>
    `;

    tbody.appendChild(row);
    itemIndex++;
}

function reindexItems() {
    const rows = document.querySelectorAll('#items-tbody tr');
    rows.forEach((row, index) => {
        row.dataset.index = index;
        row.querySelector('td:first-child').textContent = index + 1;

        // Обновить имена полей
        row.querySelectorAll('input, select, textarea').forEach(field => {
            const name = field.name;
            if (name) {
                field.name = name.replace(/items\[\d+\]/, `items[${index}]`);
            }
        });
    });
    itemIndex = rows.length;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// AI-парсинг
document.getElementById('btn-parse')?.addEventListener('click', async function() {
    const text = document.getElementById('parse-text').value.trim();
    if (!text) {
        alert('Введите текст для парсинга');
        return;
    }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Распознаю...';

    try {
        const response = await fetch('{{ route("admin.manage.requests.parse-text") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ text: text })
        });

        const result = await response.json();

        if (result.success && result.items && result.items.length > 0) {
            // Очистить текущие строки
            document.getElementById('items-tbody').innerHTML = '';
            itemIndex = 0;

            // Добавить распознанные позиции
            result.items.forEach(item => {
                addItemRow(item);
            });

            lucide.createIcons();
        } else {
            alert(result.message || 'Не удалось распознать позиции');
        }
    } catch (error) {
        console.error('Ошибка парсинга:', error);
        alert('Ошибка соединения');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="wand-2"></i> Распознать позиции';
        lucide.createIcons();
    }
});
</script>
@endpush
@endsection
