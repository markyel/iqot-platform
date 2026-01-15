@extends('layouts.cabinet')

@section('title', 'Создать заявку')

@push('styles')
<style>
    .d-none { display: none; }
    .spinner-border {
        width: 1rem; height: 1rem;
        border: 2px solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: spinner-border 0.75s linear infinite;
        display: inline-block;
        vertical-align: middle;
        margin-right: var(--space-2);
    }
    @keyframes spinner-border { to { transform: rotate(360deg); } }
</style>
@endpush

@section('content')
<x-page-header
    title="Создать заявку"
    subtitle="Опишите необходимые запчасти для быстрого подбора предложений"
/>

<div style="max-width: 1000px;">
    <div class="alert alert-info" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
        <div><strong>Ваш баланс:</strong> <span id="available-balance">{{ number_format($availableBalance, 2) }}</span> ₽</div>
        <div><strong>Стоимость позиции:</strong> {{ number_format($pricePerItem, 2) }} ₽</div>
    </div>

    <div class="card" id="step-input" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Шаг 1: Опишите что нужно закупить</h2>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom: var(--space-4);">
                <div style="display: flex; align-items: start; gap: var(--space-2);">
                    <i data-lucide="lightbulb" style="width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px;"></i>
                    <div>
                        <strong>Подсказка:</strong> Введите список позиций в свободной форме. Укажите название, марку оборудования, артикул и количество.<br>
                        <em>Пример: Кнопка вызова лифта Otis XAA177AK1 - 2 шт, Датчик уровня KONE - 1 шт</em>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <textarea id="request-text" class="input" rows="8" placeholder="Введите список позиций для закупки..." style="resize: vertical;"></textarea>
            </div>
            <x-button type="button" id="btn-parse" variant="primary" icon="wand-2">
                <span class="spinner-border d-none"></span>
                Разобрать заявку
            </x-button>
        </div>
    </div>

    <div class="card d-none" id="step-confirm" style="margin-bottom: var(--space-6);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Шаг 2: Проверьте позиции</h2>
            <x-button type="button" id="btn-back" variant="secondary" size="sm" icon="arrow-left">
                Назад к редактированию
            </x-button>
        </div>
        <div class="card-body">
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40px">#</th>
                            <th>Название</th>
                            <th style="width: 100px">Бренд</th>
                            <th style="width: 120px">Артикул</th>
                            <th style="width: 70px">Кол-во</th>
                            <th style="width: 70px">Ед.</th>
                            <th style="width: 40px"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body"></tbody>
                </table>
            </div>
            <div class="alert" id="cost-alert" style="margin-top: var(--space-4);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div><strong>Позиций:</strong> <span id="total-items">0</span></div>
                    <div><strong>Стоимость:</strong> <span id="total-cost">0</span> ₽</div>
                </div>
            </div>
            <div style="display: flex; gap: var(--space-3); margin-top: var(--space-4); flex-wrap: wrap;">
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

    <div class="card d-none" id="step-success">
        <div class="card-body" style="text-align: center; padding: var(--space-8) var(--space-6);">
            <div style="color: var(--success-600); font-size: 4rem; margin-bottom: var(--space-4);">
                <i data-lucide="check-circle" style="width: 80px; height: 80px;"></i>
            </div>
            <h2 style="font-size: var(--text-2xl); font-weight: 600; margin-bottom: var(--space-2);">Заявка успешно создана!</h2>
            <p class="text-muted" style="margin-bottom: var(--space-6);">
                Номер заявки: <strong id="request-number"></strong><br>
                Позиций: <span id="success-items"></span>, Заморожено: <span id="success-cost"></span> ₽
            </p>
            <div style="display: flex; gap: var(--space-3); justify-content: center; flex-wrap: wrap;">
                <x-button href="{{ route('cabinet.my.requests.create') }}" variant="primary">
                    Создать ещё заявку
                </x-button>
                <x-button href="{{ route('cabinet.my.requests.index') }}" variant="secondary">
                    Мои заявки
                </x-button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
const pricePerItem = {{ $pricePerItem }};
let parsedItems = [];

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM загружен, инициализация обработчиков...');

document.getElementById('btn-parse').addEventListener('click', async function() {
    const text = document.getElementById('request-text').value.trim();
    if (!text) { alert('Введите текст заявки'); return; }

    const btn = this;
    const spinner = btn.querySelector('.spinner-border');
    btn.disabled = true;
    spinner.classList.remove('d-none');

    try {
        const response = await fetch('{{ route("cabinet.my.requests.parse") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ text: text })
        });
        const result = await response.json();

        if (result.success && result.items?.length > 0) {
            parsedItems = result.items;
            renderItems();
            updateCostInfo(result.cost_info);
            document.getElementById('step-input').classList.add('d-none');
            document.getElementById('step-confirm').classList.remove('d-none');
        } else {
            alert(result.message || 'Не удалось распознать позиции');
        }
    } catch (e) {
        alert('Ошибка соединения');
    } finally {
        btn.disabled = false;
        spinner.classList.add('d-none');
    }
});

document.getElementById('btn-back').addEventListener('click', function() {
    document.getElementById('step-confirm').classList.add('d-none');
    document.getElementById('step-input').classList.remove('d-none');
});

function renderItems() {
    const tbody = document.getElementById('items-body');
    tbody.innerHTML = parsedItems.map((item, index) => `
        <tr data-index="${index}" data-category="${escapeHtml(item.category || '')}"
            data-product-type-id="${item.product_type_id || ''}" data-domain-id="${item.domain_id || ''}"
            data-type-confidence="${item.type_confidence || ''}" data-domain-confidence="${item.domain_confidence || ''}"
            data-needs-review="${item.needs_review || false}">
            <td>${index + 1}</td>
            <td><input type="text" class="input" style="min-width: 200px;" name="name" value="${escapeHtml(item.name)}" required></td>
            <td><input type="text" class="input" name="brand" value="${escapeHtml(item.brand || '')}"></td>
            <td><input type="text" class="input" name="article" value="${escapeHtml(item.article || '')}"></td>
            <td><input type="number" class="input" name="quantity" value="${item.quantity || 1}" min="1"></td>
            <td><input type="text" class="input" name="unit" value="${escapeHtml(item.unit || 'шт.')}"></td>
            <td><button type="button" class="btn-remove" style="color: var(--danger-600); background: none; border: none; cursor: pointer; font-size: 1.5rem; line-height: 1; padding: var(--space-1); width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); transition: background-color 0.2s;" onmouseover="this.style.background='var(--danger-50)'" onmouseout="this.style.background='none'">×</button></td>
        </tr>
    `).join('');
    updateTotals();
}

function updateTotals() {
    const count = document.querySelectorAll('#items-body tr').length;
    const cost = count * pricePerItem;
    document.getElementById('total-items').textContent = count;
    document.getElementById('total-cost').textContent = cost.toFixed(2);
    const availableBalance = parseFloat(document.getElementById('available-balance').textContent.replace(/[\s,]/g, ''));
    const alertEl = document.getElementById('cost-alert');
    if (cost > availableBalance) {
        alertEl.classList.add('alert-danger');
        alertEl.classList.remove('alert-info');
        document.getElementById('btn-create').disabled = true;
    } else {
        alertEl.classList.remove('alert-danger');
        alertEl.classList.add('alert-info');
        document.getElementById('btn-create').disabled = false;
    }
}

function updateCostInfo(costInfo) {
    if (costInfo) document.getElementById('available-balance').textContent = costInfo.available_balance.toFixed(2);
}

document.getElementById('items-body').addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-remove')) {
        e.target.closest('tr').remove();
        document.querySelectorAll('#items-body tr').forEach((row, i) => { row.querySelector('td:first-child').textContent = i + 1; row.dataset.index = i; });
        updateTotals();
    }
});

document.getElementById('btn-add-item').addEventListener('click', function() {
    const tbody = document.getElementById('items-body');
    const index = tbody.querySelectorAll('tr').length;
    const row = document.createElement('tr');
    row.dataset.index = index;
    row.dataset.category = 'Другое';
    row.innerHTML = `
        <td>${index + 1}</td>
        <td><input type="text" class="input" style="min-width: 200px;" name="name" required></td>
        <td><input type="text" class="input" name="brand"></td>
        <td><input type="text" class="input" name="article"></td>
        <td><input type="number" class="input" name="quantity" value="1" min="1"></td>
        <td><input type="text" class="input" name="unit" value="шт."></td>
        <td><button type="button" class="btn-remove" style="color: var(--danger-600); background: none; border: none; cursor: pointer; font-size: 1.5rem; line-height: 1; padding: var(--space-1); width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); transition: background-color 0.2s;" onmouseover="this.style.background='var(--danger-50)'" onmouseout="this.style.background='none'">×</button></td>
    `;
    tbody.appendChild(row);
    updateTotals();
});

document.getElementById('btn-create').addEventListener('click', async function() {
    console.log('Кнопка создания заявки нажата');

    const rows = document.querySelectorAll('#items-body tr');
    console.log('Найдено строк:', rows.length);

    if (rows.length === 0) {
        alert('Добавьте хотя бы одну позицию');
        return;
    }

    const items = [];
    let valid = true;
    rows.forEach(row => {
        const name = row.querySelector('input[name="name"]').value.trim();
        if (!name) {
            valid = false;
            row.querySelector('input[name="name"]').classList.add('is-invalid');
        } else {
            items.push({
                name: name,
                brand: row.querySelector('input[name="brand"]').value.trim() || null,
                article: row.querySelector('input[name="article"]').value.trim() || null,
                quantity: parseInt(row.querySelector('input[name="quantity"]').value) || 1,
                unit: row.querySelector('input[name="unit"]').value.trim() || 'шт.',
                category: row.dataset.category || 'Другое',
                product_type_id: row.dataset.productTypeId ? parseInt(row.dataset.productTypeId) : null,
                domain_id: row.dataset.domainId ? parseInt(row.dataset.domainId) : null,
                type_confidence: row.dataset.typeConfidence ? parseFloat(row.dataset.typeConfidence) : null,
                domain_confidence: row.dataset.domainConfidence ? parseFloat(row.dataset.domainConfidence) : null,
                needs_review: row.dataset.needsReview === 'true'
            });
        }
    });

    console.log('Собрано позиций:', items.length, items);

    if (!valid) {
        alert('Заполните названия всех позиций');
        return;
    }

    const btn = this;
    const spinner = btn.querySelector('.spinner-border');
    btn.disabled = true;
    spinner.classList.remove('d-none');

    console.log('Отправка запроса...');

    try {
        const response = await fetch('{{ route("cabinet.my.requests.store") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ items: items })
        });

        console.log('Статус ответа:', response.status);

        const result = await response.json();
        console.log('Результат:', result);

        if (result.success) {
            document.getElementById('request-number').textContent = result.request_number;
            document.getElementById('success-items').textContent = result.items_count;
            document.getElementById('success-cost').textContent = result.total_cost.toFixed(2);
            document.getElementById('step-confirm').classList.add('d-none');
            document.getElementById('step-success').classList.remove('d-none');
        } else {
            alert(result.message || 'Ошибка при создании заявки');
        }
    } catch (e) {
        console.error('Ошибка:', e);
        alert('Ошибка соединения: ' + e.message);
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

}); // Закрытие DOMContentLoaded
</script>
@endpush
