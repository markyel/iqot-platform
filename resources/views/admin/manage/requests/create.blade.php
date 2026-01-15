@extends('layouts.cabinet')

@section('title', 'Создать заявку')

@section('content')
<x-page-header
    title="Создать заявку через n8n"
    description="Создайте новую заявку с помощью AI-парсинга или заполните данные вручную"
/>
<div style="max-width: 1200px; margin: 0 auto;">

    @if($errors->any())
    <div class="alert alert-danger">
        <strong>Ошибки валидации:</strong>
        <ul style="margin: var(--space-2) 0 0 var(--space-6);">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger">
        <strong>{{ session('error') }}</strong>
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
                    <strong>Подсказка:</strong> Введите список позиций в свободной форме. AI автоматически распознает названия, бренды, артикулы и количество.
                    <br><em>Пример: "Кнопка вызова OTIS AAA123 10шт, датчик SALSIS 5шт"</em>
                </div>

                <div class="form-group">
                    <label class="form-label">Текст заявки</label>
                    <textarea id="parse-text" class="input" rows="6" placeholder="Введите список позиций..."></textarea>
                </div>

                <x-button type="button" id="btn-parse" variant="primary">
                    <span class="spinner" style="display: none;"></span>
                    Распознать позиции
                </x-button>
            </div>
        </div>

        <!-- Основные настройки -->
        <div class="card">
            <div class="card-header">Основные настройки</div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6);">
                    <div class="form-group">
                        <label for="status" class="form-label required">Статус</label>
                        <select name="status" id="status" class="select" required>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ old('status', 'draft') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <small style="color: var(--neutral-600); font-size: 0.875rem;">Заявки со статусом "В работу" автоматически попадут в очередь на рассылку</small>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                            <input type="checkbox" name="is_customer_request" id="is_customer_request" value="1" {{ old('is_customer_request') ? 'checked' : '' }} style="width: 1.25rem; height: 1.25rem;">
                            <span class="form-label" style="margin: 0;">Именная заявка (для конкретного клиента)</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Данные клиента (показывается только для именных заявок) -->
        <div class="card" style="display: none;" id="customer-fields">
            <div class="card-header">Данные клиента</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="client_organization_id" class="form-label">Выбрать существующую организацию</label>
                    <select name="client_organization_id" id="client_organization_id" class="select">
                        <option value="">-- Или создать новую ниже --</option>
                        @foreach($organizations as $id => $name)
                            <option value="{{ $id }}" {{ old('client_organization_id') == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6);">
                    <div class="form-group">
                        <label for="customer_company" class="form-label required">Название компании</label>
                        <input type="text" name="customer_company" id="customer_company" class="input" value="{{ old('customer_company') }}">
                    </div>

                    <div class="form-group">
                        <label for="customer_contact_person" class="form-label">Контактное лицо</label>
                        <input type="text" name="customer_contact_person" id="customer_contact_person" class="input" value="{{ old('customer_contact_person') }}">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6);">
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
                <x-button type="button" id="btn-add-item" variant="success" size="sm">
                    <i data-lucide="plus" style="width: 1rem; height: 1rem;"></i>
                    Добавить позицию
                </x-button>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 30px;">#</th>
                            <th style="width: 250px;">Название *</th>
                            <th style="width: 100px;">Бренд</th>
                            <th style="width: 100px;">Артикул</th>
                            <th style="width: 80px;">Кол-во *</th>
                            <th style="width: 80px;">Ед. изм. *</th>
                            <th style="width: 120px;">Категория *</th>
                            <th style="width: 120px;">Тип товара</th>
                            <th style="width: 120px;">Область</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody">
                        @if(old('items'))
                            @foreach(old('items') as $index => $item)
                                <tr data-index="{{ $index }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td><textarea name="items[{{ $index }}][name]" required>{{ $item['name'] }}</textarea></td>
                                    <td data-label="Бренд"><input type="text" name="items[{{ $index }}][brand]" value="{{ $item['brand'] ?? '' }}" class="input"></td>
                                    <td data-label="Артикул"><input type="text" name="items[{{ $index }}][article]" value="{{ $item['article'] ?? '' }}" class="input"></td>
                                    <td data-label="Кол-во"><input type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" required class="input"></td>
                                    <td data-label="Ед. изм."><input type="text" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? 'шт' }}" required class="input"></td>
                                    <td>
                                        <select name="items[{{ $index }}][category]" required>
                                            <option value="">-</option>
                                            @foreach($categories as $catId => $catName)
                                                <option value="{{ $catName }}" {{ ($item['category'] ?? '') === $catName ? 'selected' : '' }}>{{ $catName }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="items[{{ $index }}][product_type_id]">
                                            <option value="">-</option>
                                            @foreach($productTypes as $typeId => $typeName)
                                                <option value="{{ $typeId }}" {{ ($item['product_type_id'] ?? '') == $typeId ? 'selected' : '' }}>{{ $typeName }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="items[{{ $index }}][domain_id]">
                                            <option value="">-</option>
                                            @foreach($applicationDomains as $domainId => $domainName)
                                                <option value="{{ $domainId }}" {{ ($item['domain_id'] ?? '') == $domainId ? 'selected' : '' }}>{{ $domainName }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-item">×</button></td>
                                </tr>
                            @endforeach
                        @else
                            <!-- Пустая строка по умолчанию -->
                            <tr data-index="0">
                                <td>1</td>
                                <td><textarea name="items[0][name]" required></textarea></td>
                                <td><input type="text" name="items[0][brand]"></td>
                                <td><input type="text" name="items[0][article]"></td>
                                <td><input type="number" name="items[0][quantity]" value="1" min="1" required></td>
                                <td><input type="text" name="items[0][unit]" value="шт" required></td>
                                <td>
                                    <select name="items[0][category]" required>
                                        <option value="">-</option>
                                        @foreach($categories as $catId => $catName)
                                            <option value="{{ $catName }}">{{ $catName }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="items[0][product_type_id]">
                                        <option value="">-</option>
                                        @foreach($productTypes as $typeId => $typeName)
                                            <option value="{{ $typeId }}">{{ $typeName }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="items[0][domain_id]">
                                        <option value="">-</option>
                                        @foreach($applicationDomains as $domainId => $domainName)
                                            <option value="{{ $domainId }}">{{ $domainName }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-item">×</button></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Дополнительно -->
        <div class="card">
            <div class="card-header">Дополнительно</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="notes" class="form-label">Заметки к заявке</label>
                    <textarea name="notes" id="notes" class="input" rows="4" placeholder="Комментарии, особые условия...">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Кнопки действий -->
        <div style="display: flex; gap: var(--space-4); justify-content: flex-end; margin-top: var(--space-8);">
            <x-button tag="a" href="{{ route('admin.manage.requests.index') }}" variant="secondary">
                Отмена
            </x-button>
            <x-button type="submit" variant="success">
                <i data-lucide="check" style="width: 1rem; height: 1rem;"></i>
                Создать заявку
            </x-button>
        </div>
    </form>

</div>

@push('scripts')
<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
<script>
const categories = @json($categories);
const productTypes = @json($productTypes);
const applicationDomains = @json($applicationDomains);

let itemIndex = {{ old('items') ? count(old('items')) : 1 }};

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

// Триггер при загрузке
if (document.getElementById('is_customer_request').checked) {
    document.getElementById('customer-fields').style.display = 'block';
}

// AI-парсинг
document.getElementById('btn-parse').addEventListener('click', async function() {
    const text = document.getElementById('parse-text').value.trim();
    if (!text) {
        alert('Введите текст заявки');
        return;
    }

    const btn = this;
    const spinner = btn.querySelector('.spinner');
    btn.disabled = true;
    spinner.style.display = 'inline-block';

    try {
        const response = await fetch('{{ route('admin.manage.requests.parse-text') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ text: text })
        });

        const result = await response.json();

        if (result.success && result.items && result.items.length > 0) {
            // Очистить таблицу
            const tbody = document.getElementById('items-tbody');
            tbody.innerHTML = '';
            itemIndex = 0; // Сброс индекса на 0

            // Добавить распознанные позиции
            result.items.forEach((item, index) => {
                addItemRow(item);
            });
        } else {
            alert(result.message || 'Не удалось распознать позиции');
        }
    } catch (e) {
        alert('Ошибка соединения: ' + e.message);
    } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
    }
});

// Добавить позицию
document.getElementById('btn-add-item').addEventListener('click', function() {
    addItemRow();
});

// Удалить позицию
document.getElementById('items-tbody').addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-remove-item')) {
        e.target.closest('tr').remove();
        reindexItems();
    }
});

function addItemRow(data = {}) {
    const tbody = document.getElementById('items-tbody');
    const emptyRow = tbody.querySelector('td[colspan="10"]');
    if (emptyRow) {
        emptyRow.closest('tr').remove();
    }

    const row = document.createElement('tr');
    row.dataset.index = itemIndex;

    row.innerHTML = `
        <td>${itemIndex + 1}</td>
        <td><textarea name="items[${itemIndex}][name]" required>${escapeHtml(data.name || '')}</textarea></td>
        <td><input type="text" name="items[${itemIndex}][brand]" value="${escapeHtml(data.brand || '')}"></td>
        <td><input type="text" name="items[${itemIndex}][article]" value="${escapeHtml(data.article || '')}"></td>
        <td><input type="number" name="items[${itemIndex}][quantity]" value="${data.quantity || 1}" min="1" required></td>
        <td><input type="text" name="items[${itemIndex}][unit]" value="${escapeHtml(data.unit || 'шт')}" required></td>
        <td>
            <select name="items[${itemIndex}][category]" required>
                <option value="">-</option>
                ${Object.entries(categories).map(([id, name]) =>
                    `<option value="${escapeHtml(name)}" ${(data.category === name) ? 'selected' : ''}>${escapeHtml(name)}</option>`
                ).join('')}
            </select>
        </td>
        <td>
            <select name="items[${itemIndex}][product_type_id]">
                <option value="">-</option>
                ${Object.entries(productTypes).map(([id, name]) =>
                    `<option value="${id}" ${(data.product_type_id == id) ? 'selected' : ''}>${escapeHtml(name)}</option>`
                ).join('')}
            </select>
        </td>
        <td>
            <select name="items[${itemIndex}][domain_id]">
                <option value="">-</option>
                ${Object.entries(applicationDomains).map(([id, name]) =>
                    `<option value="${id}" ${(data.domain_id == id) ? 'selected' : ''}>${escapeHtml(name)}</option>`
                ).join('')}
            </select>
        </td>
        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-item">×</button></td>
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
</script>
@endpush
@endsection
