@extends('layouts.cabinet')

@section('title', 'Модерация классификации товаров')

@section('content')
<x-page-header
    title="Модерация классификации товаров"
    description="Управление категориями товаров и областями применения"
/>

<!-- Статистика -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <x-stat-card
        id="stat-pending"
        value="0"
        label="Ожидает модерации"
        icon="alert-circle"
        icon-type="warning"
    />
    <x-stat-card
        id="stat-domains"
        value="0"
        label="Всего доменов"
        icon="folder"
        icon-type="primary"
    />
    <x-stat-card
        id="stat-types"
        value="0"
        label="Всего типов товаров"
        icon="package"
        icon-type="primary"
    />
</div>

<!-- Вкладки -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header" style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--neutral-200); padding: 0;">
        <button class="tab-btn active" data-tab="pending">
            <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
            Ожидают модерации
            <span class="badge badge-warning badge-sm" id="pending-count">0</span>
        </button>
        <button class="tab-btn" data-tab="domains">
            <i data-lucide="folder" style="width: 16px; height: 16px;"></i>
            Области применения
        </button>
        <button class="tab-btn" data-tab="types">
            <i data-lucide="package" style="width: 16px; height: 16px;"></i>
            Типы товаров
        </button>
    </div>

    <div class="card-body">
        <!-- Вкладка: Ожидают модерации -->
        <div id="tab-pending" class="tab-content active">
            <div id="loading-pending" style="text-align: center; padding: var(--space-8); color: var(--neutral-500);">
                <i data-lucide="loader-2" style="width: 32px; height: 32px; animation: spin 1s linear infinite;"></i>
                <p style="margin-top: var(--space-2);">Загрузка...</p>
            </div>
            <div id="pending-empty" style="display: none;">
                <x-empty-state
                    icon="check-circle"
                    title="Нет ожидающих категорий"
                    description="Все AI-созданные категории обработаны"
                />
            </div>
            <div id="pending-content" style="display: none;">
                <!-- Домены -->
                <div id="pending-domains-section" style="margin-bottom: var(--space-6);">
                    <h3 style="font-size: var(--text-lg); font-weight: 600; margin-bottom: var(--space-4); color: var(--neutral-900);">
                        Области применения
                    </h3>
                    <div id="pending-domains-list"></div>
                </div>

                <!-- Типы товаров -->
                <div id="pending-types-section">
                    <h3 style="font-size: var(--text-lg); font-weight: 600; margin-bottom: var(--space-4); color: var(--neutral-900);">
                        Типы товаров
                    </h3>
                    <div id="pending-types-list"></div>
                </div>
            </div>
        </div>

        <!-- Вкладка: Области применения -->
        <div id="tab-domains" class="tab-content">
            <div style="margin-bottom: var(--space-4);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Статус</label>
                        <select id="domains-status-filter" class="input select">
                            <option value="all">Все</option>
                            <option value="active">Активные</option>
                            <option value="pending">Ожидают модерации</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Источник</label>
                        <select id="domains-source-filter" class="input select">
                            <option value="all">Все</option>
                            <option value="manual">Вручную</option>
                            <option value="ai_suggested">AI</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Поиск</label>
                        <input type="text" id="domains-search" placeholder="Название или ключевое слово..." class="input">
                    </div>
                </div>
            </div>
            <div id="domains-loading" style="text-align: center; padding: var(--space-8); color: var(--neutral-500);">
                <i data-lucide="loader-2" style="width: 32px; height: 32px; animation: spin 1s linear infinite;"></i>
                <p style="margin-top: var(--space-2);">Загрузка...</p>
            </div>
            <div id="domains-list"></div>
        </div>

        <!-- Вкладка: Типы товаров -->
        <div id="tab-types" class="tab-content">
            <div style="margin-bottom: var(--space-4);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Статус</label>
                        <select id="types-status-filter" class="input select">
                            <option value="all">Все</option>
                            <option value="active">Активные</option>
                            <option value="pending">Ожидают модерации</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Источник</label>
                        <select id="types-source-filter" class="input select">
                            <option value="all">Все</option>
                            <option value="manual">Вручную</option>
                            <option value="ai_suggested">AI</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Поиск</label>
                        <input type="text" id="types-search" placeholder="Название или ключевое слово..." class="input">
                    </div>
                </div>
            </div>
            <div id="types-loading" style="text-align: center; padding: var(--space-8); color: var(--neutral-500);">
                <i data-lucide="loader-2" style="width: 32px; height: 32px; animation: spin 1s linear infinite;"></i>
                <p style="margin-top: var(--space-2);">Загрузка...</p>
            </div>
            <div id="types-list"></div>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования домена -->
<div id="edit-domain-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Редактировать область применения</h3>
            <button class="modal-close" onclick="closeEditDomainModal()" type="button">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="edit-domain-form">
                <input type="hidden" id="edit-domain-id">
                <div class="form-group">
                    <label class="form-label" for="edit-domain-name">Название</label>
                    <input type="text" id="edit-domain-name" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-domain-parent">Родительский элемент</label>
                    <select id="edit-domain-parent" class="input select">
                        <option value="">Нет (корневой элемент)</option>
                    </select>
                    <small style="color: var(--neutral-600); font-size: var(--text-sm);">Выберите родительский элемент для создания иерархии</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-domain-description">Описание</label>
                    <textarea id="edit-domain-description" class="input" rows="8"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-domain-keywords">Ключевые слова (через запятую)</label>
                    <input type="text" id="edit-domain-keywords" class="input">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closeEditDomainModal()">Отмена</button>
            <button type="button" class="btn btn-primary btn-md" onclick="saveEditedDomain()">Сохранить</button>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования типа -->
<div id="edit-type-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Редактировать тип товара</h3>
            <button class="modal-close" onclick="closeEditTypeModal()" type="button">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="edit-type-form">
                <input type="hidden" id="edit-type-id">
                <div class="form-group">
                    <label class="form-label" for="edit-type-name">Название</label>
                    <input type="text" id="edit-type-name" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-type-parent">Родительский элемент</label>
                    <select id="edit-type-parent" class="input select">
                        <option value="">Нет (корневой элемент)</option>
                    </select>
                    <small style="color: var(--neutral-600); font-size: var(--text-sm);">Выберите родительский элемент для создания иерархии</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-type-description">Описание</label>
                    <textarea id="edit-type-description" class="input" rows="8"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-type-keywords">Ключевые слова (через запятую)</label>
                    <input type="text" id="edit-type-keywords" class="input">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closeEditTypeModal()">Отмена</button>
            <button type="button" class="btn btn-primary btn-md" onclick="saveEditedType()">Сохранить</button>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.tab-btn {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-4);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--neutral-600);
    font-size: var(--text-sm);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.tab-btn:hover {
    color: var(--neutral-900);
    background: var(--neutral-50);
}

.tab-btn.active {
    color: var(--primary-600);
    border-bottom-color: var(--primary-600);
    background: var(--primary-50);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.taxonomy-item {
    padding: var(--space-4);
    border: 1px solid var(--neutral-200);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-3);
    background: white;
    transition: box-shadow 0.2s;
}

.taxonomy-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.taxonomy-item-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: var(--space-3);
}

.taxonomy-item-title {
    font-size: var(--text-base);
    font-weight: 600;
    color: var(--neutral-900);
    margin-bottom: var(--space-1);
}

.taxonomy-item-meta {
    display: flex;
    gap: var(--space-2);
    flex-wrap: wrap;
    margin-bottom: var(--space-2);
}

.taxonomy-item-details {
    font-size: var(--text-sm);
    color: var(--neutral-600);
    line-height: 1.6;
    margin-bottom: var(--space-3);
}

.taxonomy-item-details > div {
    margin-bottom: var(--space-2);
}

.taxonomy-item-details strong {
    color: var(--neutral-700);
    font-weight: 600;
}

.taxonomy-item-actions {
    display: flex;
    gap: var(--space-2);
    flex-wrap: wrap;
}

/* Модальные окна используют стили из дизайн-системы (iqot-design-tokens.css) */
</style>

@push('scripts')
<script>
const apiBase = '/manage/api/taxonomy';
let csrfToken = '{{ csrf_token() }}';

// Кэш для списков (чтобы не загружать каждый раз при открытии модального окна)
let allDomainsCache = null;
let allTypesCache = null;

document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    loadPendingData();
    loadStats();

    // Предзагрузка списков для быстрого открытия модальных окон
    loadAllDomainsCache();
    loadAllTypesCache();

    // Переключение вкладок
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(`tab-${tab}`).classList.add('active');

            if (tab === 'domains') {
                loadDomains();
            } else if (tab === 'types') {
                loadProductTypes();
            }
        });
    });

    // Фильтры для доменов
    ['domains-status-filter', 'domains-source-filter', 'domains-search'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => loadDomains());
    });
    document.getElementById('domains-search')?.addEventListener('input', debounce(() => loadDomains(), 500));

    // Фильтры для типов
    ['types-status-filter', 'types-source-filter', 'types-search'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => loadProductTypes());
    });
    document.getElementById('types-search')?.addEventListener('input', debounce(() => loadProductTypes(), 500));
});

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

async function loadAllDomainsCache() {
    if (allDomainsCache) return allDomainsCache;
    try {
        const response = await fetch(`${apiBase}/domains`, {
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (result.success) {
            allDomainsCache = result.data;
            return allDomainsCache;
        }
    } catch (error) {
        console.error('Error loading domains cache:', error);
    }
    return [];
}

async function loadAllTypesCache() {
    if (allTypesCache) return allTypesCache;
    try {
        const response = await fetch(`${apiBase}/product-types`, {
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (result.success) {
            allTypesCache = result.data;
            return allTypesCache;
        }
    } catch (error) {
        console.error('Error loading types cache:', error);
    }
    return [];
}

async function loadStats() {
    try {
        const response = await fetch(`${apiBase}/stats`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const result = await response.json();

        if (result.success) {
            document.getElementById('stat-pending').querySelector('.stat-value').textContent = result.data.pending_count || 0;
            document.getElementById('stat-domains').querySelector('.stat-value').textContent = result.data.total_domains || 0;
            document.getElementById('stat-types').querySelector('.stat-value').textContent = result.data.total_types || 0;
            document.getElementById('pending-count').textContent = result.data.pending_count || 0;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadPendingData() {
    try {
        const response = await fetch(`${apiBase}/pending`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const result = await response.json();

        if (result.success) {
            const { domains, product_types } = result.data;
            const hasPending = domains.length > 0 || product_types.length > 0;

            document.getElementById('loading-pending').style.display = 'none';

            if (!hasPending) {
                document.getElementById('pending-empty').style.display = 'block';
                document.getElementById('pending-content').style.display = 'none';
            } else {
                document.getElementById('pending-empty').style.display = 'none';
                document.getElementById('pending-content').style.display = 'block';

                if (domains.length > 0) {
                    document.getElementById('pending-domains-section').style.display = 'block';
                    renderPendingDomains(domains);
                } else {
                    document.getElementById('pending-domains-section').style.display = 'none';
                }

                if (product_types.length > 0) {
                    document.getElementById('pending-types-section').style.display = 'block';
                    renderPendingTypes(product_types);
                } else {
                    document.getElementById('pending-types-section').style.display = 'none';
                }
            }

            lucide.createIcons();
        }
    } catch (error) {
        console.error('Error loading pending data:', error);
        document.getElementById('loading-pending').innerHTML = '<div style="text-align: center; padding: var(--space-8);"><i data-lucide="alert-circle" style="width: 48px; height: 48px; color: var(--danger-500); margin: 0 auto;"></i><p style="margin-top: var(--space-4); font-size: var(--text-lg); font-weight: 600; color: var(--neutral-900);">Ошибка загрузки</p></div>';
        lucide.createIcons();
    }
}

function renderPendingDomains(domains) {
    const container = document.getElementById('pending-domains-list');
    container.innerHTML = domains.map(domain => `
        <div class="taxonomy-item">
            <div class="taxonomy-item-header">
                <div style="flex: 1;">
                    <div class="taxonomy-item-title">${escapeHtml(domain.name)}</div>
                    <div class="taxonomy-item-meta">
                        <span class="badge badge-warning badge-sm">Ожидает модерации</span>
                        <span class="badge badge-info badge-sm">AI</span>
                        ${domain.items_count > 0 ? `<span class="badge badge-neutral badge-sm">${domain.items_count} позиций</span>` : ''}
                    </div>
                </div>
            </div>

            ${domain.description || (domain.keywords && domain.keywords.length) ? `
                <div class="taxonomy-item-details">
                    ${domain.description ? `<div><strong>Описание:</strong> ${escapeHtml(domain.description)}</div>` : ''}
                    ${domain.keywords && domain.keywords.length > 0 ? `
                        <div><strong>Ключевые слова:</strong> ${domain.keywords.map(k => `<span class="badge badge-neutral badge-sm">${escapeHtml(k)}</span>`).join(' ')}</div>
                    ` : ''}
                </div>
            ` : ''}

            <div class="taxonomy-item-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="editDomain(${domain.id})">
                    <i data-lucide="edit" style="width: 14px; height: 14px;"></i>
                    Редактировать
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="approveDomain(${domain.id})">
                    <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                    Одобрить
                </button>
                <button type="button" class="btn btn-danger btn-sm" onclick="rejectDomain(${domain.id})">
                    <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                    Отклонить
                </button>
            </div>
        </div>
    `).join('');
}

function renderPendingTypes(types) {
    const container = document.getElementById('pending-types-list');
    container.innerHTML = types.map(type => `
        <div class="taxonomy-item">
            <div class="taxonomy-item-header">
                <div style="flex: 1;">
                    <div class="taxonomy-item-title">${escapeHtml(type.name)}</div>
                    <div class="taxonomy-item-meta">
                        <span class="badge badge-warning badge-sm">Ожидает модерации</span>
                        <span class="badge badge-info badge-sm">AI</span>
                        ${type.items_count > 0 ? `<span class="badge badge-neutral badge-sm">${type.items_count} позиций</span>` : ''}
                        ${type.is_leaf ? `<span class="badge badge-success badge-sm">Листовой</span>` : ''}
                    </div>
                </div>
            </div>

            ${type.description || (type.keywords && type.keywords.length) ? `
                <div class="taxonomy-item-details">
                    ${type.description ? `<div><strong>Описание:</strong> ${escapeHtml(type.description)}</div>` : ''}
                    ${type.keywords && type.keywords.length > 0 ? `
                        <div><strong>Ключевые слова:</strong> ${type.keywords.map(k => `<span class="badge badge-neutral badge-sm">${escapeHtml(k)}</span>`).join(' ')}</div>
                    ` : ''}
                </div>
            ` : ''}

            <div class="taxonomy-item-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="editProductType(${type.id})">
                    <i data-lucide="edit" style="width: 14px; height: 14px;"></i>
                    Редактировать
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="approveProductType(${type.id})">
                    <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                    Одобрить
                </button>
                <button type="button" class="btn btn-danger btn-sm" onclick="rejectProductType(${type.id})">
                    <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                    Отклонить
                </button>
            </div>
        </div>
    `).join('');
}

async function loadDomains() {
    const status = document.getElementById('domains-status-filter').value;
    const source = document.getElementById('domains-source-filter').value;
    const search = document.getElementById('domains-search').value;

    const params = new URLSearchParams();
    if (status !== 'all') params.append('status', status);
    if (source !== 'all') params.append('created_by', source);
    if (search) params.append('search', search);

    document.getElementById('domains-loading').style.display = 'block';
    document.getElementById('domains-list').innerHTML = '';

    try {
        const response = await fetch(`${apiBase}/domains?${params}`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const result = await response.json();

        document.getElementById('domains-loading').style.display = 'none';

        if (result.success && result.data.length > 0) {
            document.getElementById('domains-list').innerHTML = result.data.map(domain => `
                <div class="taxonomy-item">
                    <div class="taxonomy-item-header">
                        <div style="flex: 1;">
                            <div class="taxonomy-item-title">${escapeHtml(domain.name)}</div>
                            <div class="taxonomy-item-meta">
                                ${domain.status === 'pending' ? '<span class="badge badge-warning badge-sm">Ожидает</span>' : '<span class="badge badge-success badge-sm">Активен</span>'}
                                ${domain.created_by === 'ai_suggested' ? '<span class="badge badge-info badge-sm">AI</span>' : '<span class="badge badge-neutral badge-sm">Вручную</span>'}
                                ${!domain.is_active ? '<span class="badge badge-danger badge-sm">Неактивен</span>' : ''}
                            </div>
                        </div>
                    </div>
                    ${domain.description || (domain.keywords && domain.keywords.length) ? `
                        <div class="taxonomy-item-details">
                            ${domain.description ? `<div><strong>Описание:</strong> ${escapeHtml(domain.description)}</div>` : ''}
                            ${domain.keywords && domain.keywords.length > 0 ? `
                                <div><strong>Ключевые слова:</strong> ${domain.keywords.map(k => `<span class="badge badge-neutral badge-sm">${escapeHtml(k)}</span>`).join(' ')}</div>
                            ` : ''}
                        </div>
                    ` : ''}
                    <div class="taxonomy-item-actions">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="editDomain(${domain.id})">
                            <i data-lucide="edit" style="width: 14px; height: 14px;"></i>
                            Редактировать
                        </button>
                        ${domain.status === 'pending' ? `
                            <button type="button" class="btn btn-success btn-sm" onclick="approveDomain(${domain.id})">
                                <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                                Одобрить
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="rejectDomain(${domain.id})">
                                <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                                Отклонить
                            </button>
                        ` : domain.is_active ? `
                            <button type="button" class="btn btn-danger btn-sm" onclick="toggleDomainStatus(${domain.id}, false)">
                                <i data-lucide="toggle-right" style="width: 14px; height: 14px;"></i>
                                Деактивировать
                            </button>
                        ` : `
                            <button type="button" class="btn btn-success btn-sm" onclick="toggleDomainStatus(${domain.id}, true)">
                                <i data-lucide="toggle-left" style="width: 14px; height: 14px;"></i>
                                Активировать
                            </button>
                        `}
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        } else {
            document.getElementById('domains-list').innerHTML = '<div style="text-align: center; padding: var(--space-8);"><i data-lucide="folder" style="width: 48px; height: 48px; color: var(--neutral-400); margin: 0 auto;"></i><p style="margin-top: var(--space-4); font-size: var(--text-lg); font-weight: 600; color: var(--neutral-900);">Нет данных</p><p style="color: var(--neutral-600); font-size: var(--text-sm);">Области применения не найдены</p></div>';
            lucide.createIcons();
        }
    } catch (error) {
        console.error('Error loading domains:', error);
        document.getElementById('domains-loading').style.display = 'none';
        document.getElementById('domains-list').innerHTML = '<div style="text-align: center; padding: var(--space-8);"><i data-lucide="alert-circle" style="width: 48px; height: 48px; color: var(--danger-500); margin: 0 auto;"></i><p style="margin-top: var(--space-4); font-size: var(--text-lg); font-weight: 600; color: var(--neutral-900);">Ошибка загрузки</p></div>';
        lucide.createIcons();
    }
}

async function loadProductTypes() {
    const status = document.getElementById('types-status-filter').value;
    const source = document.getElementById('types-source-filter').value;
    const search = document.getElementById('types-search').value;

    const params = new URLSearchParams();
    if (status !== 'all') params.append('status', status);
    if (source !== 'all') params.append('created_by', source);
    if (search) params.append('search', search);

    document.getElementById('types-loading').style.display = 'block';
    document.getElementById('types-list').innerHTML = '';

    try {
        const response = await fetch(`${apiBase}/product-types?${params}`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const result = await response.json();

        document.getElementById('types-loading').style.display = 'none';

        if (result.success && result.data.length > 0) {
            document.getElementById('types-list').innerHTML = result.data.map(type => `
                <div class="taxonomy-item">
                    <div class="taxonomy-item-header">
                        <div style="flex: 1;">
                            <div class="taxonomy-item-title">${escapeHtml(type.name)}</div>
                            <div class="taxonomy-item-meta">
                                ${type.status === 'pending' ? '<span class="badge badge-warning badge-sm">Ожидает</span>' : '<span class="badge badge-success badge-sm">Активен</span>'}
                                ${type.created_by === 'ai_suggested' ? '<span class="badge badge-info badge-sm">AI</span>' : '<span class="badge badge-neutral badge-sm">Вручную</span>'}
                                ${!type.is_active ? '<span class="badge badge-danger badge-sm">Неактивен</span>' : ''}
                                ${type.is_leaf ? '<span class="badge badge-primary badge-sm">Листовой</span>' : ''}
                            </div>
                        </div>
                    </div>
                    ${type.description || (type.keywords && type.keywords.length) ? `
                        <div class="taxonomy-item-details">
                            ${type.description ? `<div><strong>Описание:</strong> ${escapeHtml(type.description)}</div>` : ''}
                            ${type.keywords && type.keywords.length > 0 ? `
                                <div><strong>Ключевые слова:</strong> ${type.keywords.map(k => `<span class="badge badge-neutral badge-sm">${escapeHtml(k)}</span>`).join(' ')}</div>
                            ` : ''}
                        </div>
                    ` : ''}
                    <div class="taxonomy-item-actions">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="editProductType(${type.id})">
                            <i data-lucide="edit" style="width: 14px; height: 14px;"></i>
                            Редактировать
                        </button>
                        ${type.status === 'pending' ? `
                            <button type="button" class="btn btn-success btn-sm" onclick="approveProductType(${type.id})">
                                <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                                Одобрить
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="rejectProductType(${type.id})">
                                <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                                Отклонить
                            </button>
                        ` : type.is_active ? `
                            <button type="button" class="btn btn-danger btn-sm" onclick="toggleProductTypeStatus(${type.id}, false)">
                                <i data-lucide="toggle-right" style="width: 14px; height: 14px;"></i>
                                Деактивировать
                            </button>
                        ` : `
                            <button type="button" class="btn btn-success btn-sm" onclick="toggleProductTypeStatus(${type.id}, true)">
                                <i data-lucide="toggle-left" style="width: 14px; height: 14px;"></i>
                                Активировать
                            </button>
                        `}
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        } else {
            document.getElementById('types-list').innerHTML = '<div style="text-align: center; padding: var(--space-8);"><i data-lucide="package" style="width: 48px; height: 48px; color: var(--neutral-400); margin: 0 auto;"></i><p style="margin-top: var(--space-4); font-size: var(--text-lg); font-weight: 600; color: var(--neutral-900);">Нет данных</p><p style="color: var(--neutral-600); font-size: var(--text-sm);">Типы товаров не найдены</p></div>';
            lucide.createIcons();
        }
    } catch (error) {
        console.error('Error loading product types:', error);
        document.getElementById('types-loading').style.display = 'none';
        document.getElementById('types-list').innerHTML = '<div style="text-align: center; padding: var(--space-8);"><i data-lucide="alert-circle" style="width: 48px; height: 48px; color: var(--danger-500); margin: 0 auto;"></i><p style="margin-top: var(--space-4); font-size: var(--text-lg); font-weight: 600; color: var(--neutral-900);">Ошибка загрузки</p></div>';
        lucide.createIcons();
    }
}

async function approveDomain(id) {
    if (!confirm('Одобрить эту область применения?')) return;
    try {
        const response = await fetch(`${apiBase}/domains/${id}/approve`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (result.success) {
            alert('Область применения одобрена');
            loadPendingData();
            loadStats();
        } else {
            alert('Ошибка: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error approving domain:', error);
        alert('Ошибка соединения');
    }
}

async function rejectDomain(id) {
    if (!confirm('Отклонить эту область применения? Она будет деактивирована.')) return;
    try {
        const response = await fetch(`${apiBase}/domains/${id}/reject`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (result.success) {
            alert('Область применения отклонена');
            loadPendingData();
            loadStats();
        } else {
            alert('Ошибка: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error rejecting domain:', error);
        alert('Ошибка соединения');
    }
}

async function approveProductType(id) {
    if (!confirm('Одобрить этот тип товара?')) return;
    try {
        const response = await fetch(`${apiBase}/product-types/${id}/approve`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (result.success) {
            alert('Тип товара одобрен');
            loadPendingData();
            loadStats();
        } else {
            alert('Ошибка: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error approving product type:', error);
        alert('Ошибка соединения');
    }
}

async function rejectProductType(id) {
    if (!confirm('Отклонить этот тип товара? Он будет деактивирован.')) return;
    try {
        const response = await fetch(`${apiBase}/product-types/${id}/reject`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (result.success) {
            alert('Тип товара отклонен');
            loadPendingData();
            loadStats();
        } else {
            alert('Ошибка: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error rejecting product type:', error);
        alert('Ошибка соединения');
    }
}

async function editDomain(id) {
    try {
        // Загружаем данные домена и используем кэшированный список доменов
        const [domainResponse, allDomains] = await Promise.all([
            fetch(`${apiBase}/domains/${id}`, {
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            }),
            loadAllDomainsCache()
        ]);

        const domainResult = await domainResponse.json();

        if (domainResult.success) {
            const domain = domainResult.data;

            // Заполняем форму
            document.getElementById('edit-domain-id').value = domain.id;
            document.getElementById('edit-domain-name').value = domain.name;
            document.getElementById('edit-domain-description').value = domain.description || '';
            document.getElementById('edit-domain-keywords').value = domain.keywords ? domain.keywords.join(', ') : '';

            // Заполняем список родительских элементов (исключая текущий)
            const parentSelect = document.getElementById('edit-domain-parent');
            parentSelect.innerHTML = '<option value="">Нет (корневой элемент)</option>';
            allDomains.forEach(d => {
                if (d.id !== domain.id) {
                    const option = document.createElement('option');
                    option.value = d.id;
                    option.textContent = d.name;
                    if (d.id === domain.parent_id) {
                        option.selected = true;
                    }
                    parentSelect.appendChild(option);
                }
            });

            document.getElementById('edit-domain-modal').style.display = 'block';
            lucide.createIcons();
        } else {
            alert('Ошибка загрузки данных');
        }
    } catch (error) {
        console.error('Error loading domain:', error);
        alert('Ошибка соединения');
    }
}

function closeEditDomainModal() {
    document.getElementById('edit-domain-modal').style.display = 'none';
}

async function saveEditedDomain() {
    const id = document.getElementById('edit-domain-id').value;
    const name = document.getElementById('edit-domain-name').value;
    const description = document.getElementById('edit-domain-description').value;
    const keywordsText = document.getElementById('edit-domain-keywords').value;
    const keywords = keywordsText ? keywordsText.split(',').map(k => k.trim()).filter(k => k) : [];
    const parentId = document.getElementById('edit-domain-parent').value;

    try {
        const response = await fetch(`${apiBase}/domains/${id}`, {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, description, keywords, parent_id: parentId || null })
        });
        const result = await response.json();
        if (result.success) {
            alert('Область применения обновлена');
            allDomainsCache = null; // Сбрасываем кэш
            closeEditDomainModal();
            loadPendingData();
        } else {
            alert('Ошибка: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error updating domain:', error);
        alert('Ошибка соединения');
    }
}

async function editProductType(id) {
    try {
        // Загружаем данные типа и используем кэшированный список типов
        const [typeResponse, allTypes] = await Promise.all([
            fetch(`${apiBase}/product-types/${id}`, {
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            }),
            loadAllTypesCache()
        ]);

        const typeResult = await typeResponse.json();

        if (typeResult.success) {
            const type = typeResult.data;

            // Заполняем форму
            document.getElementById('edit-type-id').value = type.id;
            document.getElementById('edit-type-name').value = type.name;
            document.getElementById('edit-type-description').value = type.description || '';
            document.getElementById('edit-type-keywords').value = type.keywords ? type.keywords.join(', ') : '';

            // Заполняем список родительских элементов (исключая текущий)
            const parentSelect = document.getElementById('edit-type-parent');
            parentSelect.innerHTML = '<option value="">Нет (корневой элемент)</option>';
            allTypes.forEach(t => {
                if (t.id !== type.id) {
                    const option = document.createElement('option');
                    option.value = t.id;
                    option.textContent = t.name;
                    if (t.id === type.parent_id) {
                        option.selected = true;
                    }
                    parentSelect.appendChild(option);
                }
            });

            document.getElementById('edit-type-modal').style.display = 'block';
            lucide.createIcons();
        } else {
            alert('Ошибка загрузки данных');
        }
    } catch (error) {
        console.error('Error loading product type:', error);
        alert('Ошибка соединения');
    }
}

function closeEditTypeModal() {
    document.getElementById('edit-type-modal').style.display = 'none';
}

async function saveEditedType() {
    const id = document.getElementById('edit-type-id').value;
    const name = document.getElementById('edit-type-name').value;
    const description = document.getElementById('edit-type-description').value;
    const keywordsText = document.getElementById('edit-type-keywords').value;
    const keywords = keywordsText ? keywordsText.split(',').map(k => k.trim()).filter(k => k) : [];
    const parentId = document.getElementById('edit-type-parent').value;

    try {
        const response = await fetch(`${apiBase}/product-types/${id}`, {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, description, keywords, parent_id: parentId || null })
        });
        const result = await response.json();
        if (result.success) {
            alert('Тип товара обновлен');
            allTypesCache = null; // Сбрасываем кэш
            closeEditTypeModal();
            loadPendingData();
        } else {
            alert('Ошибка: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error updating product type:', error);
        alert('Ошибка соединения');
    }
}

async function toggleDomainStatus(id, isActive) {
    const action = isActive ? 'активировать' : 'деактивировать';
    if (!confirm(`Вы уверены, что хотите ${action} эту область применения?`)) {
        return;
    }

    try {
        const response = await fetch(`${apiBase}/domains/${id}`, {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ is_active: isActive })
        });
        const result = await response.json();
        if (result.success) {
            alert(`Область применения ${isActive ? 'активирована' : 'деактивирована'}`);
            allDomainsCache = null; // Сбрасываем кэш
            loadPendingData();
        } else {
            alert('Ошибка: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error toggling domain status:', error);
        alert('Ошибка соединения');
    }
}

async function toggleProductTypeStatus(id, isActive) {
    const action = isActive ? 'активировать' : 'деактивировать';
    if (!confirm(`Вы уверены, что хотите ${action} этот тип товара?`)) {
        return;
    }

    try {
        const response = await fetch(`${apiBase}/product-types/${id}`, {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ is_active: isActive })
        });
        const result = await response.json();
        if (result.success) {
            alert(`Тип товара ${isActive ? 'активирован' : 'деактивирован'}`);
            allTypesCache = null; // Сбрасываем кэш
            loadPendingData();
        } else {
            alert('Ошибка: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error toggling product type status:', error);
        alert('Ошибка соединения');
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
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
