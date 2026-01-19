# 🚀 Быстрая справка по компонентам

## Blade компоненты

### Badge
```blade
<x-badge type="draft">Черновик</x-badge>
<x-badge type="pending">В ожидании</x-badge>
<x-badge type="in-progress">В процессе</x-badge>
<x-badge type="completed">Завершено</x-badge>
<x-badge type="cancelled">Отменено</x-badge>
<x-badge type="pending" dot>3</x-badge>
<x-badge type="success" size="lg">Большой</x-badge>
```

### Button
```blade
<x-button variant="primary" :href="route('some.route')">Открыть</x-button>
<x-button variant="accent" icon="plus">Создать</x-button>
<x-button variant="secondary" type="submit">Отправить</x-button>
<x-button variant="ghost" size="sm">Маленькая</x-button>
<x-button variant="danger">Удалить</x-button>
```

### Page Header
```blade
<x-page-header title="Заголовок" description="Описание">
    <x-slot:actions>
        <x-button variant="accent" icon="plus">Создать</x-button>
    </x-slot:actions>
</x-page-header>
```

### Stat Card
```blade
<div class="stats-grid">
    <x-stat-card value="42" label="Всего" icon="file-text" icon-type="primary" />
    <x-stat-card value="15" label="Активные" icon="clock" icon-type="accent" />
    <x-stat-card value="27" label="Завершённые" icon="check-circle" icon-type="success" />
</div>
```

### Empty State
```blade
<x-empty-state icon="inbox" title="Нет данных" description="Попробуйте изменить фильтры">
    <x-slot:action>
        <x-button variant="primary" icon="plus">Создать</x-button>
    </x-slot:action>
</x-empty-state>
```

### Question Card (IQOT-специфичный)
```blade
<!-- Базовое использование -->
<x-question-card
    request-code="REQ-20260112-7348"
    item-name="Преобразователь частоты"
    question-text="Поставщик просит прислать фото шильдика оборудования."
    :suppliers="['SIEMENS', 'Ziplift', 'ЛифтКомплект']"
    :suppliers-count="3"
    time="12 янв, 14:45"
    status="pending"
    on-answer="answerQuestion(123)"
    on-skip="skipQuestion(123)"
/>

<!-- Отвеченный вопрос с кастомными действиями -->
<x-question-card
    request-code="REQ-20260112-7348"
    item-name="Преобразователь частоты"
    question-text="Вопрос был отвечен администратором."
    :suppliers="['SIEMENS']"
    time="12 янв, 14:45"
    status="answered"
>
    <x-slot:actions>
        <x-button variant="ghost" size="sm" icon="eye">Просмотр</x-button>
    </x-slot:actions>
</x-question-card>

<!-- Статусы: pending, answered, skipped -->
```

## CSS классы

### Card
```html
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Заголовок</h2>
        <button class="btn btn-ghost btn-sm">Действие</button>
    </div>
    <div class="card-body">Контент</div>
    <div class="card-footer">
        <button class="btn btn-primary">Сохранить</button>
    </div>
</div>
```

### Table
```html
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Колонка</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td data-label="Колонка">Значение</td>
            </tr>
        </tbody>
    </table>
</div>
```

### Form
```html
<div class="form-group">
    <label class="form-label">Поле</label>
    <input type="text" class="input" placeholder="Введите значение">
    <span class="form-hint">Подсказка</span>
</div>

<div class="form-group">
    <label class="form-label form-label-required">Обязательное</label>
    <select class="input select">
        <option>Вариант</option>
    </select>
</div>

<div class="form-group">
    <label class="form-label">Комментарий</label>
    <textarea class="input textarea" rows="4"></textarea>
</div>
```

### Alert
```html
<div class="alert alert-success">
    <i data-lucide="check-circle" class="alert-icon"></i>
    <div class="alert-content">Успех!</div>
</div>

<div class="alert alert-error">
    <i data-lucide="x-circle" class="alert-icon"></i>
    <div class="alert-content">Ошибка!</div>
</div>
```

### Pagination
```html
<div class="table-footer">
    <div class="pagination-info">
        Страница <strong>1</strong> из <strong>10</strong>
    </div>
    <div class="pagination">
        <button class="pagination-nav-btn" disabled>
            <i data-lucide="chevron-left"></i> Назад
        </button>
        <a href="?page=1" class="pagination-btn active">1</a>
        <a href="?page=2" class="pagination-btn">2</a>
        <a href="?page=3" class="pagination-btn">3</a>
        <span class="pagination-ellipsis">...</span>
        <a href="?page=2" class="pagination-nav-btn">
            Вперёд <i data-lucide="chevron-right"></i>
        </a>
    </div>
</div>
```

## CSS переменные

### Цвета
```css
--primary-600: #274B78
--accent-600: #E86100
--neutral-700: #404650
--success-600: #16A34A
--warning-600: #D97706
--error-600: #DC2626
```

### Spacing
```css
--space-2: 0.5rem
--space-4: 1rem
--space-6: 1.5rem
--space-8: 2rem
```

### Typography
```css
--text-xs: 0.75rem
--text-sm: 0.875rem
--text-base: 1rem
--text-lg: 1.125rem
--text-xl: 1.25rem
--text-2xl: 1.5rem
```

## Иконки Lucide

```html
<i data-lucide="file-text" class="icon-sm"></i>
<i data-lucide="check-circle" class="icon-md"></i>
<i data-lucide="alert-triangle" class="icon-lg"></i>

<!-- После добавления новых иконок: -->
<script>lucide.createIcons();</script>
```

**Популярные иконки**:
- `file-text`, `file-check`, `file-plus`
- `check-circle`, `x-circle`, `alert-triangle`
- `plus`, `minus`, `edit`, `trash-2`
- `search`, `filter`, `download`, `upload`
- `chevron-left`, `chevron-right`, `chevron-down`
- `user`, `users`, `building-2`, `package`
- `settings`, `log-out`, `menu`, `x`

Полный список: https://lucide.dev/icons/
