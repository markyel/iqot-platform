@extends('layouts.cabinet')

@section('title', 'Создать заявку')

@push('styles')
<style>
    .spinner-border {
        width: 1rem;
        height: 1rem;
        border: 2px solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: spinner-border 0.75s linear infinite;
        display: inline-block;
        vertical-align: middle;
        margin-right: var(--space-2);
    }
    @keyframes spinner-border {
        to { transform: rotate(360deg); }
    }
    .d-none {
        display: none !important;
    }
</style>
@endpush

@section('content')
<x-page-header
    title="Создать заявку"
    description="Опишите необходимые запчасти для быстрого подбора предложений"
/>

<div class="alert alert-info" style="display: flex; justify-content: space-between; align-items: center;">
    <div><strong>Ваш баланс:</strong> <span id="available-balance">{{ number_format($availableBalance, 2) }}</span> ₽</div>
    <div>
        @if($limitsInfo['items_limit'] !== null)
            <strong>Лимит тарифа:</strong> {{ $limitsInfo['items_used'] }} / {{ $limitsInfo['items_limit'] }} поз.
            @if($limitsInfo['items_used'] >= $limitsInfo['items_limit'])
                | <strong>Сверх лимита:</strong> {{ number_format($pricePerItem, 2) }} ₽/поз.
            @else
                | <strong>Осталось:</strong> {{ $limitsInfo['items_remaining'] }} поз.
            @endif
        @else
            <strong>Тариф:</strong> Безлимитный
        @endif
    </div>
</div>

<!-- Шаг 1: Ввод текста -->
<div class="card" id="step-input">
    <div class="card-header">
        <div style="display: flex; align-items: center; gap: var(--space-2);">
            <i data-lucide="file-text" style="width: 1.25rem; height: 1.25rem;"></i>
            <span>Шаг 1: Опишите что нужно закупить</span>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i data-lucide="lightbulb" class="alert-icon"></i>
            <div class="alert-content">
                <strong>Подсказка:</strong> Введите список позиций в свободной форме. Укажите название, марку оборудования, артикул и количество.<br>
                <em>Пример: Кнопка вызова лифта Otis XAA177AK1 - 2 шт, Датчик уровня KONE - 1 шт</em>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Список позиций</label>
            <textarea id="request-text" class="input textarea" rows="8" placeholder="Введите список позиций для закупки..." style="resize: vertical;"></textarea>
        </div>

        <x-button type="button" id="btn-parse" variant="primary" icon="wand-2">
            <span class="spinner-border d-none"></span>
            Разобрать заявку
        </x-button>
    </div>
</div>

<!-- Шаг 2: Проверка позиций -->
<div class="card d-none" id="step-confirm">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
        <div style="display: flex; align-items: center; gap: var(--space-2);">
            <i data-lucide="list-checks" style="width: 1.25rem; height: 1.25rem;"></i>
            <span>Шаг 2: Проверьте позиции</span>
        </div>
        <x-button type="button" id="btn-back" variant="secondary" size="sm" icon="arrow-left">
            Назад к редактированию
        </x-button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px">#</th>
                        <th>Название *</th>
                        <th>Бренд</th>
                        <th>Артикул</th>
                        <th style="width: 100px">Кол-во *</th>
                        <th style="width: 100px">Ед. изм. *</th>
                        <th>Категория *</th>
                        <th>Тип оборудования</th>
                        <th>Область применения</th>
                        <th style="width: 60px"></th>
                    </tr>
                </thead>
                <tbody id="items-body"></tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <div class="alert" id="cost-alert" style="margin-bottom: var(--space-4);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div><strong>Позиций:</strong> <span id="total-items">0</span></div>
                <div><strong>Стоимость:</strong> <span id="total-cost">0</span> ₽</div>
            </div>
        </div>

        <div style="display: flex; gap: var(--space-3); flex-wrap: wrap;">
            <x-button type="button" id="btn-create" variant="accent" icon="check">
                <span class="spinner-border d-none"></span>
                Создать заявку
            </x-button>
            <x-button type="button" id="btn-add-item" variant="secondary" icon="plus">
                Добавить позицию
            </x-button>
        </div>
    </div>
</div>

<!-- Шаг 3: Успех -->
<div class="card d-none" id="step-success">
    <div class="card-body" style="text-align: center; padding: var(--space-8) var(--space-6);">
        <div style="color: var(--success-600); font-size: 4rem; margin-bottom: var(--space-4);">
            <i data-lucide="check-circle" style="width: 80px; height: 80px; margin: 0 auto;"></i>
        </div>
        <h2 style="font-size: var(--text-2xl); font-weight: 600; margin-bottom: var(--space-2);">Заявка успешно создана!</h2>
        <p style="color: var(--neutral-600); margin-bottom: var(--space-6);">
            Номер заявки: <strong id="request-number"></strong><br>
            Позиций: <span id="success-items"></span>, Заморожено: <span id="success-cost"></span> ₽
        </p>
        <div style="display: flex; gap: var(--space-3); justify-content: center; flex-wrap: wrap;">
            <x-button :href="route('cabinet.my.requests.create')" variant="primary">
                Создать ещё заявку
            </x-button>
            <x-button :href="route('cabinet.my.requests.index')" variant="secondary">
                Мои заявки
            </x-button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const pricePerItem = {{ $pricePerItem }};
const limitsInfo = @json($limitsInfo ?? []);
let parsedItems = [];
const categories = @json($categories);
let productTypes = @json($productTypes);
let applicationDomains = @json($applicationDomains);

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация иконок
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Парсинг заявки
    document.getElementById('btn-parse').addEventListener('click', async function() {
        const text = document.getElementById('request-text').value.trim();
        if (!text) {
            alert('Введите текст заявки');
            return;
        }

        const btn = this;
        const spinner = btn.querySelector('.spinner-border');
        btn.disabled = true;
        spinner.classList.remove('d-none');

        try {
            const response = await fetch('{{ route("cabinet.my.requests.parse") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ text: text })
            });
            const result = await response.json();

            if (result.success && result.items?.length > 0) {
                parsedItems = result.items;

                // Если AI создал новые категории - обновляем списки
                if (result.has_new_classifications) {
                    if (result.updated_product_types) {
                        productTypes = result.updated_product_types;
                    }
                    if (result.updated_application_domains) {
                        applicationDomains = result.updated_application_domains;
                    }

                    // Показываем уведомление о создании новых категорий
                    const createdCount = (result.created_types_count || 0) + (result.created_domains_count || 0);
                    if (createdCount > 0) {
                        console.log(`AI создал ${createdCount} новых категорий, которые ожидают модерации`);
                    }
                }

                renderItems();
                updateCostInfo(result.cost_info);
                document.getElementById('step-input').classList.add('d-none');
                document.getElementById('step-confirm').classList.remove('d-none');
                lucide.createIcons();
            } else {
                alert(result.message || 'Не удалось распознать позиции');
            }
        } catch (e) {
            console.error('Ошибка парсинга:', e);
            alert('Ошибка соединения');
        } finally {
            btn.disabled = false;
            spinner.classList.add('d-none');
        }
    });

    // Назад к редактированию
    document.getElementById('btn-back').addEventListener('click', function() {
        document.getElementById('step-confirm').classList.add('d-none');
        document.getElementById('step-input').classList.remove('d-none');
    });

    // Рендер позиций
    function renderItems() {
        const tbody = document.getElementById('items-body');
        tbody.innerHTML = parsedItems.map((item, index) => {
            const categoryOptions = Object.entries(categories).map(([id, name]) =>
                `<option value="${escapeHtml(name)}" ${item.category === name ? 'selected' : ''}>${escapeHtml(name)}</option>`
            ).join('');

            const productTypeOptions = Object.entries(productTypes).map(([id, data]) => {
                const label = typeof data === 'object' ? data.name : data;
                const isPending = typeof data === 'object' && data.is_pending;
                const suffix = isPending ? ' ⏳ (ожидает модерации)' : '';
                return `<option value="${id}" ${item.product_type_id && item.product_type_id == id ? 'selected' : ''}>${escapeHtml(label)}${suffix}</option>`;
            }).join('');

            const domainOptions = Object.entries(applicationDomains).map(([id, data]) => {
                const label = typeof data === 'object' ? data.name : data;
                const isPending = typeof data === 'object' && data.is_pending;
                const suffix = isPending ? ' ⏳ (ожидает модерации)' : '';
                return `<option value="${id}" ${item.domain_id && item.domain_id == id ? 'selected' : ''}>${escapeHtml(label)}${suffix}</option>`;
            }).join('');

            return `
                <tr data-index="${index}">
                    <td data-label="#">${index + 1}</td>
                    <td data-label="Название">
                        <input type="text" class="input" name="name" value="${escapeHtml(item.name)}" required style="min-width: 200px;">
                    </td>
                    <td data-label="Бренд">
                        <input type="text" class="input" name="brand" value="${escapeHtml(item.brand || '')}">
                    </td>
                    <td data-label="Артикул">
                        <input type="text" class="input" name="article" value="${escapeHtml(item.article || '')}">
                    </td>
                    <td data-label="Кол-во">
                        <input type="number" class="input" name="quantity" value="${item.quantity || 1}" min="1">
                    </td>
                    <td data-label="Ед. изм.">
                        <input type="text" class="input" name="unit" value="${escapeHtml(item.unit || 'шт.')}">
                    </td>
                    <td data-label="Категория">
                        <select class="input select" name="category" required>
                            <option value="">Выберите</option>
                            ${categoryOptions}
                        </select>
                    </td>
                    <td data-label="Тип оборудования">
                        <select class="input select" name="product_type_id">
                            <option value="">Не указан</option>
                            ${productTypeOptions}
                        </select>
                    </td>
                    <td data-label="Область применения">
                        <select class="input select" name="domain_id">
                            <option value="">Не указана</option>
                            ${domainOptions}
                        </select>
                    </td>
                    <td data-label="">
                        <button type="button" class="btn-remove" style="color: var(--danger-600); background: none; border: none; cursor: pointer; font-size: 1.5rem; padding: var(--space-2); border-radius: var(--radius-sm); transition: background-color 0.2s;" onmouseover="this.style.background='var(--danger-50)'" onmouseout="this.style.background='none'">×</button>
                    </td>
                </tr>
            `;
        }).join('');
        updateTotals();
    }

    // Обновить итоги с учетом лимитов тарифа
    function updateTotals() {
        const count = document.querySelectorAll('#items-body tr').length;

        // Рассчитываем стоимость с учетом лимитов
        let cost = 0;
        if (limitsInfo.items_limit !== null && limitsInfo.items_limit !== undefined) {
            // Есть лимит
            const itemsUsed = limitsInfo.items_used || 0;
            const itemsLimit = limitsInfo.items_limit;

            // Сколько позиций осталось в лимите
            const remainingLimit = Math.max(0, itemsLimit - itemsUsed);

            // Из текущей заявки сколько позиций сверх лимита
            if (count > remainingLimit) {
                const itemsOverLimit = count - remainingLimit;
                cost = itemsOverLimit * pricePerItem;
            }
            // Если в пределах лимита - cost остается 0
        } else {
            // Безлимитный тариф - бесплатно
            cost = 0;
        }

        document.getElementById('total-items').textContent = count;
        document.getElementById('total-cost').textContent = cost.toFixed(2);

        const availableBalance = parseFloat(document.getElementById('available-balance').textContent.replace(/[\s,]/g, ''));
        const alertEl = document.getElementById('cost-alert');
        const btnCreate = document.getElementById('btn-create');

        if (cost > availableBalance) {
            alertEl.classList.add('alert-error');
            alertEl.classList.remove('alert-info');
            btnCreate.disabled = true;
        } else {
            alertEl.classList.remove('alert-error');
            alertEl.classList.add('alert-info');
            btnCreate.disabled = false;
        }
    }

    // Обновить информацию о балансе
    function updateCostInfo(costInfo) {
        if (costInfo) {
            document.getElementById('available-balance').textContent = costInfo.available_balance.toFixed(2);
        }
    }

    // Удаление позиции
    document.getElementById('items-body').addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-remove')) {
            e.target.closest('tr').remove();
            document.querySelectorAll('#items-body tr').forEach((row, i) => {
                row.querySelector('td:first-child').textContent = i + 1;
                row.dataset.index = i;
            });
            updateTotals();
        }
    });

    // Добавить позицию
    document.getElementById('btn-add-item').addEventListener('click', function() {
        const tbody = document.getElementById('items-body');
        const index = tbody.querySelectorAll('tr').length;

        const categoryOptions = Object.entries(categories).map(([id, name]) =>
            `<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`
        ).join('');

        const productTypeOptions = Object.entries(productTypes).map(([id, data]) => {
            const label = typeof data === 'object' ? data.name : data;
            const isPending = typeof data === 'object' && data.is_pending;
            const suffix = isPending ? ' ⏳ (ожидает модерации)' : '';
            return `<option value="${id}">${escapeHtml(label)}${suffix}</option>`;
        }).join('');

        const domainOptions = Object.entries(applicationDomains).map(([id, data]) => {
            const label = typeof data === 'object' ? data.name : data;
            const isPending = typeof data === 'object' && data.is_pending;
            const suffix = isPending ? ' ⏳ (ожидает модерации)' : '';
            return `<option value="${id}">${escapeHtml(label)}${suffix}</option>`;
        }).join('');

        const row = document.createElement('tr');
        row.dataset.index = index;
        row.innerHTML = `
            <td data-label="#">${index + 1}</td>
            <td data-label="Название">
                <input type="text" class="input" name="name" required style="min-width: 200px;">
            </td>
            <td data-label="Бренд">
                <input type="text" class="input" name="brand">
            </td>
            <td data-label="Артикул">
                <input type="text" class="input" name="article">
            </td>
            <td data-label="Кол-во">
                <input type="number" class="input" name="quantity" value="1" min="1">
            </td>
            <td data-label="Ед. изм.">
                <input type="text" class="input" name="unit" value="шт.">
            </td>
            <td data-label="Категория">
                <select class="input select" name="category" required>
                    <option value="">Выберите</option>
                    ${categoryOptions}
                </select>
            </td>
            <td data-label="Тип оборудования">
                <select class="input select" name="product_type_id">
                    <option value="">Не указан</option>
                    ${productTypeOptions}
                </select>
            </td>
            <td data-label="Область применения">
                <select class="input select" name="domain_id">
                    <option value="">Не указана</option>
                    ${domainOptions}
                </select>
            </td>
            <td data-label="">
                <button type="button" class="btn-remove" style="color: var(--danger-600); background: none; border: none; cursor: pointer; font-size: 1.5rem; padding: var(--space-2); border-radius: var(--radius-sm); transition: background-color 0.2s;" onmouseover="this.style.background='var(--danger-50)'" onmouseout="this.style.background='none'">×</button>
            </td>
        `;
        tbody.appendChild(row);
        updateTotals();
    });

    // Создать заявку
    document.getElementById('btn-create').addEventListener('click', async function() {
        const rows = document.querySelectorAll('#items-body tr');

        if (rows.length === 0) {
            alert('Добавьте хотя бы одну позицию');
            return;
        }

        const items = [];
        let valid = true;

        rows.forEach(row => {
            const name = row.querySelector('input[name="name"]').value.trim();
            const category = row.querySelector('select[name="category"]').value;

            if (!name) {
                valid = false;
                row.querySelector('input[name="name"]').classList.add('input-error');
            }
            if (!category) {
                valid = false;
                row.querySelector('select[name="category"]').classList.add('input-error');
            }

            if (name && category) {
                const productTypeId = row.querySelector('select[name="product_type_id"]').value;
                const domainId = row.querySelector('select[name="domain_id"]').value;

                items.push({
                    name: name,
                    brand: row.querySelector('input[name="brand"]').value.trim() || null,
                    article: row.querySelector('input[name="article"]').value.trim() || null,
                    quantity: parseInt(row.querySelector('input[name="quantity"]').value) || 1,
                    unit: row.querySelector('input[name="unit"]').value.trim() || 'шт.',
                    category: category,
                    product_type_id: productTypeId ? parseInt(productTypeId) : null,
                    domain_id: domainId ? parseInt(domainId) : null
                });
            }
        });

        if (!valid) {
            alert('Заполните названия и категории всех позиций');
            return;
        }

        const btn = this;
        const spinner = btn.querySelector('.spinner-border');
        btn.disabled = true;
        spinner.classList.remove('d-none');

        try {
            const response = await fetch('{{ route("cabinet.my.requests.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ items: items })
            });

            const result = await response.json();

            if (result.success) {
                document.getElementById('request-number').textContent = result.request_number;
                document.getElementById('success-items').textContent = result.items_count;
                document.getElementById('success-cost').textContent = result.total_cost.toFixed(2);
                document.getElementById('step-confirm').classList.add('d-none');
                document.getElementById('step-success').classList.remove('d-none');
                lucide.createIcons();
            } else {
                alert(result.message || 'Ошибка при создании заявки');
            }
        } catch (e) {
            console.error('Ошибка создания заявки:', e);
            alert('Ошибка соединения');
        } finally {
            btn.disabled = false;
            spinner.classList.add('d-none');
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endpush
