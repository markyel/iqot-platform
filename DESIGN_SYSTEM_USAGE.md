# IQOT Design System - Руководство по использованию

## 📦 Что уже реализовано

### ✅ Layout и структура
- **Темный sidebar** с индустриальным синим (#0C1929)
- **Responsive sidebar** с collapsed состоянием на desktop
- **Mobile overlay** с backdrop-filter
- **Tooltips** при hover в collapsed режиме
- **Keyboard shortcuts**: `Ctrl+B` - toggle sidebar, `Escape` - закрыть mobile menu
- **Mobile header** с hamburger меню
- **Автосохранение** состояния sidebar в localStorage

### ✅ Компоненты Blade

#### 1. Badge - Бейдж для статусов
```blade
<x-badge type="draft">Черновик</x-badge>
<x-badge type="pending">В ожидании</x-badge>
<x-badge type="in-progress">В процессе</x-badge>
<x-badge type="completed">Завершено</x-badge>
<x-badge type="cancelled">Отменено</x-badge>
<x-badge type="pending" dot>3</x-badge> <!-- С точкой -->
<x-badge type="success" size="sm">Маленький</x-badge>
<x-badge type="warning" size="lg">Большой</x-badge>
```

#### 2. Button - Кнопка
```blade
<!-- Кнопка-ссылка -->
<x-button variant="primary" :href="route('some.route')">
    Открыть
</x-button>

<!-- Кнопка с иконкой -->
<x-button variant="accent" icon="plus" :href="route('create')">
    Создать
</x-button>

<!-- Обычная кнопка -->
<x-button variant="secondary" type="submit">
    Отправить
</x-button>

<!-- Варианты: primary, accent, secondary, ghost, danger, success -->
<!-- Размеры: sm, md, lg -->
<!-- iconPosition: left, right -->
```

#### 3. Page Header - Заголовок страницы
```blade
<x-page-header
    title="Управление заявками"
    description="Создание и управление заявками через n8n API"
>
    <x-slot:actions>
        <x-button variant="accent" icon="plus" :href="route('create')">
            Создать заявку
        </x-button>
    </x-slot:actions>
</x-page-header>

<!-- С breadcrumbs -->
<x-page-header
    title="Детали заявки"
    :breadcrumbs="[
        ['label' => 'Главная', 'url' => route('dashboard')],
        ['label' => 'Заявки', 'url' => route('requests.index')],
        ['label' => 'Детали']
    ]"
/>
```

#### 4. Stat Card - Карточка статистики
```blade
<div class="stats-grid">
    <x-stat-card
        value="42"
        label="Всего заявок"
        icon="file-text"
        icon-type="primary"
    />

    <x-stat-card
        value="15"
        label="Активные"
        icon="clock"
        icon-type="accent"
    />

    <x-stat-card
        value="27"
        label="Завершённые"
        icon="check-circle"
        icon-type="success"
    />
</div>
```

#### 5. Empty State - Пустое состояние
```blade
<x-empty-state
    icon="inbox"
    title="Нет данных"
    description="Данные не найдены. Попробуйте изменить фильтры"
>
    <x-slot:action>
        <x-button variant="primary" icon="plus" :href="route('create')">
            Создать первую запись
        </x-button>
    </x-slot:action>
</x-empty-state>
```

### ✅ CSS классы из Design System

#### Карточки
```html
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Заголовок</h2>
    </div>
    <div class="card-body">
        Контент
    </div>
    <div class="card-footer">
        <button class="btn btn-primary">Действие</button>
    </div>
</div>
```

#### Таблицы
```html
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Колонка 1</th>
                    <th>Колонка 2</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-label="Колонка 1">Значение 1</td>
                    <td data-label="Колонка 2">Значение 2</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Пагинация -->
    <div class="table-footer">
        <div class="pagination-info">
            Страница <strong>1</strong> из <strong>10</strong>
        </div>
        <div class="pagination">
            <button class="pagination-nav-btn" disabled>
                <i data-lucide="chevron-left"></i>
                Назад
            </button>
            <a href="?page=1" class="pagination-btn active">1</a>
            <a href="?page=2" class="pagination-btn">2</a>
            <a href="?page=3" class="pagination-btn">3</a>
            <span class="pagination-ellipsis">...</span>
            <a href="?page=10" class="pagination-btn">10</a>
            <a href="?page=2" class="pagination-nav-btn">
                Вперёд
                <i data-lucide="chevron-right"></i>
            </a>
        </div>
    </div>
</div>
```

#### Формы
```html
<div class="form-group">
    <label class="form-label">Название поля</label>
    <input type="text" class="input" placeholder="Введите значение">
    <span class="form-hint">Подсказка</span>
</div>

<div class="form-group">
    <label class="form-label form-label-required">Обязательное поле</label>
    <select class="input select">
        <option>Вариант 1</option>
    </select>
    <span class="form-error">Ошибка валидации</span>
</div>

<div class="form-group">
    <label class="form-label">Текстовая область</label>
    <textarea class="input textarea" rows="4"></textarea>
</div>
```

#### Alerts
```html
<div class="alert alert-success">
    <i data-lucide="check-circle" class="alert-icon"></i>
    <div class="alert-content">
        Операция выполнена успешно
    </div>
</div>

<div class="alert alert-error">
    <i data-lucide="x-circle" class="alert-icon"></i>
    <div class="alert-content">
        Произошла ошибка
    </div>
</div>

<div class="alert alert-warning">
    <i data-lucide="alert-triangle" class="alert-icon"></i>
    <div class="alert-content">
        Предупреждение
    </div>
</div>
```

### 🎨 Цветовая палитра

```css
/* Primary (Industrial Blue) */
--primary-900: #0C1929
--primary-600: #274B78
--primary-500: #3366A0

/* Accent (Industrial Orange) */
--accent-600: #E86100
--accent-500: #FF7A1A

/* Neutral (Steel Gray) */
--neutral-900: #1A1D21
--neutral-700: #404650
--neutral-500: #6B7280
--neutral-200: #E2E5EA
--neutral-100: #F3F4F6
--neutral-50:  #F9FAFB
--neutral-0:   #FFFFFF

/* Semantic */
--success-600: #16A34A
--warning-600: #D97706
--error-600: #DC2626
--info-600: #0284C7
```

### 📏 Spacing (8-point grid)

```css
--space-1:  0.25rem  (4px)
--space-2:  0.5rem   (8px)
--space-3:  0.75rem  (12px)
--space-4:  1rem     (16px)
--space-5:  1.25rem  (20px)
--space-6:  1.5rem   (24px)
--space-8:  2rem     (32px)
--space-10: 2.5rem   (40px)
--space-12: 3rem     (48px)
```

### 📝 Типографика

```css
/* Font Family */
--font-primary: 'DM Sans'
--font-mono: 'JetBrains Mono'

/* Font Sizes */
--text-xs:   0.75rem   (12px)
--text-sm:   0.875rem  (14px)
--text-base: 1rem      (16px)
--text-lg:   1.125rem  (18px)
--text-xl:   1.25rem   (20px)
--text-2xl:  1.5rem    (24px)
--text-3xl:  1.875rem  (30px)

/* Font Weights */
--font-normal:   400
--font-medium:   500
--font-semibold: 600
--font-bold:     700
```

### 🎯 Иконки (Lucide)

Используйте иконки Lucide через тег `<i>`:

```html
<i data-lucide="file-text" class="icon-sm"></i>
<i data-lucide="check-circle" class="icon-md"></i>
<i data-lucide="alert-triangle" class="icon-lg"></i>

<!-- Размеры: icon-sm (16px), icon-md (20px), icon-lg (24px), icon-xl (32px) -->
```

После загрузки нового контента не забудьте инициализировать иконки:

```javascript
lucide.createIcons();
```

### 📱 Responsive

- **Desktop**: `min-width: 1024px` - sidebar visible
- **Tablet**: `max-width: 1024px` - sidebar hidden, mobile menu
- **Mobile**: `max-width: 768px` - tables → cards, уменьшенные отступы

Таблицы автоматически превращаются в карточки на мобильных благодаря атрибутам `data-label`.

## 🚀 Как использовать на новых страницах

1. **Наследуйте layout**:
```blade
@extends('layouts.cabinet')

@section('title', 'Заголовок страницы')
```

2. **Используйте компоненты**:
```blade
@section('content')
<x-page-header title="Заголовок" />

<div class="card">
    <!-- Ваш контент -->
</div>
@endsection
```

3. **Инициализируйте иконки** (если добавляете динамический контент):
```blade
@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
```

## ✨ Готовые страницы

- ✅ `cabinet/dashboard.blade.php` - Главная страница с статистикой
- ✅ `admin/manage/requests/index.blade.php` - Список заявок с фильтрами
- ✅ `layouts/cabinet.blade.php` - Основной layout с sidebar

Остальные страницы автоматически получат новый sidebar, но их контент нужно обновить по аналогии с примерами выше.
