<?php $__env->startSection('title', 'Создать заявку'); ?>
<?php $__env->startSection('header', 'Создать заявку'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
    .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-light { background: #f8f9fa; color: #6c757d; }
    .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
    .card-header { padding: 1.25rem; border-bottom: 1px solid #e5e7eb; }
    .card-body { padding: 1.5rem; }
    .form-control { width: 100%; padding: 0.625rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; }
    .form-control:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
    .form-control.is-invalid { border-color: #ef4444; }
    .btn { padding: 0.75rem 1.5rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .btn-success { background: #10b981; color: white; }
    .btn-success:hover { background: #059669; }
    .btn-outline-primary { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; }
    .btn-outline-secondary { background: transparent; border: 1px solid #6b7280; color: #6b7280; }
    .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.875rem; }
    .btn-outline-danger { background: transparent; border: 1px solid #ef4444; color: #ef4444; }
    .spinner-border { width: 1rem; height: 1rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spinner-border 0.75s linear infinite; }
    @keyframes spinner-border { to { transform: rotate(360deg); } }
    .table-responsive { overflow-x: auto; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 0.75rem; text-align: left; border: 1px solid #e5e7eb; }
    .table-light { background: #f9fafb; }
    .table-sm th, .table-sm td { padding: 0.5rem; }
    .d-none { display: none; }
    .d-flex { display: flex; }
    .gap-2 { gap: 0.5rem; }
    .justify-content-between { justify-content: space-between; }
    .align-items-center { align-items: center; }
    .text-center { text-align: center; }
    .text-success { color: #10b981; }
    .text-muted { color: #6b7280; }
    .mt-3 { margin-top: 1rem; }
    .mt-4 { margin-top: 1.5rem; }
    .mb-0 { margin-bottom: 0; }
    .py-5 { padding-top: 3rem; padding-bottom: 3rem; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width: 1000px; margin: 0 auto;">

    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <div><strong>Ваш баланс:</strong> <span id="available-balance"><?php echo e(number_format($availableBalance, 2)); ?></span> руб.</div>
        <div><strong>Стоимость позиции:</strong> <?php echo e(number_format($pricePerItem, 2)); ?> руб.</div>
    </div>

    <div class="card" id="step-input">
        <div class="card-header"><h5 class="mb-0">Шаг 1: Опишите что нужно закупить</h5></div>
        <div class="card-body">
            <div class="alert alert-light">
                <strong>Подсказка:</strong> Введите список позиций в свободной форме. Укажите название, марку оборудования, артикул и количество.<br>
                <em>Пример: Кнопка вызова лифта Otis XAA177AK1 - 2 шт, Датчик уровня KONE - 1 шт</em>
            </div>
            <textarea id="request-text" class="form-control" rows="8" placeholder="Введите список позиций для закупки..."></textarea>
            <div class="mt-3">
                <button type="button" id="btn-parse" class="btn btn-primary">
                    <span class="spinner-border spinner-border-sm d-none"></span>
                    Разобрать заявку
                </button>
            </div>
        </div>
    </div>

    <div class="card d-none" id="step-confirm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Шаг 2: Проверьте позиции</h5>
            <button type="button" id="btn-back" class="btn btn-sm btn-outline-secondary">← Назад к редактированию</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
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
            <div class="alert mt-3" id="cost-alert">
                <div style="display: flex; justify-content: space-between;">
                    <div><strong>Позиций:</strong> <span id="total-items">0</span></div>
                    <div><strong>Стоимость:</strong> <span id="total-cost">0</span> руб.</div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="button" id="btn-create" class="btn btn-success">
                    <span class="spinner-border spinner-border-sm d-none"></span>
                    Создать заявку
                </button>
                <button type="button" id="btn-add-item" class="btn btn-outline-primary">+ Добавить позицию</button>
            </div>
        </div>
    </div>

    <div class="card d-none" id="step-success">
        <div class="card-body text-center py-5">
            <div class="text-success" style="font-size: 4rem;">✓</div>
            <h4>Заявка успешно создана!</h4>
            <p class="text-muted">
                Номер заявки: <strong id="request-number"></strong><br>
                Позиций: <span id="success-items"></span>, Заморожено: <span id="success-cost"></span> руб.
            </p>
            <div class="mt-4">
                <a href="<?php echo e(route('cabinet.my.requests.create')); ?>" class="btn btn-primary">Создать ещё заявку</a>
                <a href="<?php echo e(route('cabinet.my.requests.index')); ?>" class="btn btn-outline-secondary">Мои заявки</a>
            </div>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
const pricePerItem = <?php echo e($pricePerItem); ?>;
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
        const response = await fetch('<?php echo e(route("cabinet.my.requests.parse")); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>' },
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
            <td><input type="text" class="form-control form-control-sm" name="name" value="${escapeHtml(item.name)}" required></td>
            <td><input type="text" class="form-control form-control-sm" name="brand" value="${escapeHtml(item.brand || '')}"></td>
            <td><input type="text" class="form-control form-control-sm" name="article" value="${escapeHtml(item.article || '')}"></td>
            <td><input type="number" class="form-control form-control-sm" name="quantity" value="${item.quantity || 1}" min="1"></td>
            <td><input type="text" class="form-control form-control-sm" name="unit" value="${escapeHtml(item.unit || 'шт.')}"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove">×</button></td>
        </tr>
    `).join('');
    updateTotals();
}

function updateTotals() {
    const count = document.querySelectorAll('#items-body tr').length;
    const cost = count * pricePerItem;
    document.getElementById('total-items').textContent = count;
    document.getElementById('total-cost').textContent = cost.toFixed(2);
    const availableBalance = parseFloat(document.getElementById('available-balance').textContent.replace(/\s/g, ''));
    const alertEl = document.getElementById('cost-alert');
    if (cost > availableBalance) {
        alertEl.classList.add('alert-danger');
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
        <td><input type="text" class="form-control form-control-sm" name="name" required></td>
        <td><input type="text" class="form-control form-control-sm" name="brand"></td>
        <td><input type="text" class="form-control form-control-sm" name="article"></td>
        <td><input type="number" class="form-control form-control-sm" name="quantity" value="1" min="1"></td>
        <td><input type="text" class="form-control form-control-sm" name="unit" value="шт."></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove">×</button></td>
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
        const response = await fetch('<?php echo e(route("cabinet.my.requests.store")); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>' },
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
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/requests/create.blade.php ENDPATH**/ ?>