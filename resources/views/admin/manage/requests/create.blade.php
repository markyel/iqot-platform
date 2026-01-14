@extends('layouts.cabinet')

@section('title', 'Создать заявку')
@section('header', 'Создать заявку через n8n')

@push('styles')
<style>
    .form-container { max-width: 1200px; margin: 0 auto; }
    .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
    .card-header { padding: 1.25rem; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #111827; display: flex; justify-content: space-between; align-items: center; }
    .card-body { padding: 1.5rem; }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem; }
    .form-group label.required::after { content: ' *'; color: #ef4444; }
    .form-control { width: 100%; padding: 0.625rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; }
    .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .form-control.is-invalid { border-color: #ef4444; }
    .invalid-feedback { color: #ef4444; font-size: 0.875rem; margin-top: 0.25rem; }
    .form-check { display: flex; align-items: center; gap: 0.5rem; }
    .form-check input[type="checkbox"] { width: 1.25rem; height: 1.25rem; cursor: pointer; }
    .btn { padding: 0.625rem 1.25rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; font-size: 0.875rem; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .btn-primary:disabled { background: #93c5fd; cursor: not-allowed; }
    .btn-secondary { background: #6b7280; color: white; }
    .btn-secondary:hover { background: #4b5563; }
    .btn-success { background: #10b981; color: white; }
    .btn-success:hover { background: #059669; }
    .btn-outline-primary { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; }
    .btn-outline-primary:hover { background: #3b82f6; color: white; }
    .btn-outline-danger { background: transparent; border: 1px solid #ef4444; color: #ef4444; }
    .btn-outline-danger:hover { background: #ef4444; color: white; }
    .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }
    .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; }
    .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }
    .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
    .spinner { display: inline-block; width: 1rem; height: 1rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spinner 0.75s linear infinite; }
    @keyframes spinner { to { transform: rotate(360deg); } }
    .items-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .items-table th, .items-table td { padding: 0.75rem; border: 1px solid #e5e7eb; font-size: 0.875rem; }
    .items-table th { background: #f9fafb; font-weight: 600; color: #6b7280; text-align: left; }
    .items-table input, .items-table select, .items-table textarea { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; }
    .items-table textarea { min-height: 60px; resize: vertical; }
    .form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; }
    .hidden { display: none; }
    .two-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
</style>
@endpush

@section('content')
<div class="form-container">

    @if($errors->any())
    <div class="alert" style="background: #fee2e2; color: #991b1b; border-left-color: #ef4444;">
        <strong>Ошибки валидации:</strong>
        <ul style="margin: 0.5rem 0 0 1.5rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(session('error'))
    <div class="alert" style="background: #fee2e2; color: #991b1b; border-left-color: #ef4444;">
        <strong>{{ session('error') }}</strong>
    </div>
    @endif

    <form action="{{ route('admin.manage.requests.store') }}" method="POST" id="request-form">
        @csrf

        <!-- AI-парсинг -->
        <div class="card">
            <div class="card-header">
                <span>🤖 AI-парсинг текста заявки (опционально)</span>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Подсказка:</strong> Введите список позиций в свободной форме. AI автоматически распознает названия, бренды, артикулы и количество.
                    <br><em>Пример: "Кнопка вызова OTIS AAA123 10шт, датчик SALSIS 5шт"</em>
                </div>

                <div class="form-group">
                    <label>Текст заявки</label>
                    <textarea id="parse-text" class="form-control" rows="6" placeholder="Введите список позиций..."></textarea>
                </div>

                <button type="button" id="btn-parse" class="btn btn-primary">
                    <span class="spinner hidden"></span>
                    Распознать позиции
                </button>
            </div>
        </div>

        <!-- Основные настройки -->
        <div class="card">
            <div class="card-header">Основные настройки</div>
            <div class="card-body">
                <div class="two-cols">
                    <div class="form-group">
                        <label for="status" class="required">Статус</label>
                        <select name="status" id="status" class="form-control" required>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ old('status', 'draft') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <small style="color: #6b7280;">Заявки со статусом "В работу" автоматически попадут в очередь на рассылку</small>
                    </div>

                    <div class="form-group">
                        <label for="is_customer_request">
                            <input type="checkbox" name="is_customer_request" id="is_customer_request" value="1" {{ old('is_customer_request') ? 'checked' : '' }}>
                            Именная заявка (для конкретного клиента)
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Данные клиента (показывается только для именных заявок) -->
        <div class="card hidden" id="customer-fields">
            <div class="card-header">Данные клиента</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="client_organization_id">Выбрать существующую организацию</label>
                    <select name="client_organization_id" id="client_organization_id" class="form-control">
                        <option value="">-- Или создать новую ниже --</option>
                        @foreach($organizations as $id => $name)
                            <option value="{{ $id }}" {{ old('client_organization_id') == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="two-cols">
                    <div class="form-group">
                        <label for="customer_company" class="required">Название компании</label>
                        <input type="text" name="customer_company" id="customer_company" class="form-control" value="{{ old('customer_company') }}">
                    </div>

                    <div class="form-group">
                        <label for="customer_contact_person">Контактное лицо</label>
                        <input type="text" name="customer_contact_person" id="customer_contact_person" class="form-control" value="{{ old('customer_contact_person') }}">
                    </div>
                </div>

                <div class="two-cols">
                    <div class="form-group">
                        <label for="customer_email">Email</label>
                        <input type="email" name="customer_email" id="customer_email" class="form-control" value="{{ old('customer_email') }}">
                    </div>

                    <div class="form-group">
                        <label for="customer_phone">Телефон</label>
                        <input type="text" name="customer_phone" id="customer_phone" class="form-control" value="{{ old('customer_phone') }}">
                    </div>
                </div>
            </div>
        </div>

        <!-- Позиции заявки -->
        <div class="card">
            <div class="card-header">
                <span>Позиции заявки</span>
                <button type="button" id="btn-add-item" class="btn btn-sm btn-success">+ Добавить позицию</button>
            </div>
            <div class="card-body">
                <table class="items-table">
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
                                    <td><input type="text" name="items[{{ $index }}][brand]" value="{{ $item['brand'] ?? '' }}"></td>
                                    <td><input type="text" name="items[{{ $index }}][article]" value="{{ $item['article'] ?? '' }}"></td>
                                    <td><input type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" required></td>
                                    <td><input type="text" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? 'шт' }}" required></td>
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
                    <label for="notes">Заметки к заявке</label>
                    <textarea name="notes" id="notes" class="form-control" rows="4" placeholder="Комментарии, особые условия...">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Кнопки действий -->
        <div class="form-actions">
            <a href="{{ route('admin.manage.requests.index') }}" class="btn btn-secondary">Отмена</a>
            <button type="submit" class="btn btn-success">Создать заявку</button>
        </div>
    </form>

</div>

@push('scripts')
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
        customerFields.classList.remove('hidden');
        customerCompany.required = true;
    } else {
        customerFields.classList.add('hidden');
        customerCompany.required = false;
    }
});

// Триггер при загрузке
if (document.getElementById('is_customer_request').checked) {
    document.getElementById('customer-fields').classList.remove('hidden');
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
    spinner.classList.remove('hidden');

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
        spinner.classList.add('hidden');
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
