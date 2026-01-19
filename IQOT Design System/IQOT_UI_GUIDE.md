# IQOT Design System
## UX/UI Гайд v1.0

> Дизайн-система для личного кабинета пользователя и административной панели IQOT — платформы автоматизации B2B закупок лифтового оборудования.

---

## 1. Философия дизайна

### Концепция: Industrial Precision

IQOT работает в сфере лифтового и эскалаторного оборудования — это точные механизмы, где важна надёжность и эффективность. Дизайн должен отражать эти качества:

| Принцип | Реализация |
|---------|------------|
| **Precision** | Чёткие линии, точная сетка, предсказуемые паттерны |
| **Reliability** | Стабильные состояния, ясная обратная связь |
| **Efficiency** | Минимум кликов, максимум информации на экране |
| **Professional** | Сдержанная палитра, типографика без декораций |

### Тон интерфейса

```
Серьёзный ←────────●───────→ Игривый
                   ↑
              Профессиональный

Плотный ←────────●─────────→ Воздушный
                 ↑
            Информативный

Минималистичный ←────●──────→ Насыщенный
                     ↑
               Функциональный
```

---

## 2. Цветовая палитра

### Основные цвета

```css
:root {
  /* ═══════════════════════════════════════════════════════════════
     PRIMARY — Индустриальный синий
     Используется для: основных действий, активных состояний, ссылок
     ═══════════════════════════════════════════════════════════════ */
  --primary-900: #0C1929;
  --primary-800: #162D4D;
  --primary-700: #1E3A5F;
  --primary-600: #274B78;  /* Основной */
  --primary-500: #3366A0;
  --primary-400: #5588C2;
  --primary-300: #7FAAD8;
  --primary-200: #B3CEE8;
  --primary-100: #E6F0F8;
  --primary-50:  #F5F9FC;
  
  /* ═══════════════════════════════════════════════════════════════
     ACCENT — Индустриальный оранжевый
     Используется для: важных элементов, CTA, уведомлений, badges
     ═══════════════════════════════════════════════════════════════ */
  --accent-700: #B84D00;
  --accent-600: #E86100;  /* Основной */
  --accent-500: #FF7A1A;
  --accent-400: #FF9547;
  --accent-300: #FFB580;
  --accent-200: #FFD4B3;
  --accent-100: #FFF0E6;
  
  /* ═══════════════════════════════════════════════════════════════
     NEUTRAL — Стальной серый
     Используется для: фонов, границ, текста, разделителей
     ═══════════════════════════════════════════════════════════════ */
  --neutral-950: #0F1114;
  --neutral-900: #1A1D21;
  --neutral-800: #2D3138;
  --neutral-700: #404650;
  --neutral-600: #545B68;
  --neutral-500: #6B7280;
  --neutral-400: #9CA3AF;
  --neutral-300: #C4C9D2;
  --neutral-200: #E2E5EA;
  --neutral-100: #F3F4F6;
  --neutral-50:  #F9FAFB;
  --neutral-0:   #FFFFFF;
}
```

### Семантические цвета

```css
:root {
  /* ═══════════════════════════════════════════════════════════════
     SUCCESS — Для успешных операций
     ═══════════════════════════════════════════════════════════════ */
  --success-700: #15803D;
  --success-600: #16A34A;
  --success-500: #22C55E;
  --success-100: #DCFCE7;
  --success-50:  #F0FDF4;
  
  /* ═══════════════════════════════════════════════════════════════
     WARNING — Для предупреждений
     ═══════════════════════════════════════════════════════════════ */
  --warning-700: #B45309;
  --warning-600: #D97706;
  --warning-500: #F59E0B;
  --warning-100: #FEF3C7;
  --warning-50:  #FFFBEB;
  
  /* ═══════════════════════════════════════════════════════════════
     ERROR — Для ошибок
     ═══════════════════════════════════════════════════════════════ */
  --error-700: #B91C1C;
  --error-600: #DC2626;
  --error-500: #EF4444;
  --error-100: #FEE2E2;
  --error-50:  #FEF2F2;
  
  /* ═══════════════════════════════════════════════════════════════
     INFO — Для информационных сообщений
     ═══════════════════════════════════════════════════════════════ */
  --info-700: #0369A1;
  --info-600: #0284C7;
  --info-500: #0EA5E9;
  --info-100: #E0F2FE;
  --info-50:  #F0F9FF;
}
```

### Применение цветов

| Элемент | Цвет | CSS Variable |
|---------|------|--------------|
| Фон страницы | Светло-серый | `--neutral-50` |
| Фон карточки | Белый | `--neutral-0` |
| Фон sidebar | Тёмный | `--primary-900` |
| Основной текст | Тёмный | `--neutral-800` |
| Вторичный текст | Серый | `--neutral-500` |
| Границы | Светлые | `--neutral-200` |
| Hover фон | Очень светлый | `--neutral-100` |
| Активная ссылка | Primary | `--primary-600` |
| Кнопка CTA | Accent | `--accent-600` |

---

## 3. Типографика

### Шрифтовая пара

```css
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
  /* Основной шрифт — для текста и UI */
  --font-primary: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  
  /* Моноширинный — для данных, кодов, артикулов */
  --font-mono: 'JetBrains Mono', 'SF Mono', Consolas, monospace;
}
```

**Почему DM Sans:**
- Современный, но не игривый
- Отличная читаемость при малых размерах
- Хорошо работает с цифрами и таблицами
- Не «заезженный» как Inter или Roboto

**Почему JetBrains Mono:**
- Идеален для артикулов и кодов (FRN18.5LM1S-4EA)
- Чёткое различение похожих символов (0/O, 1/l/I)
- Профессиональный технический вид

### Шкала размеров

```css
:root {
  /* ═══════════════════════════════════════════════════════════════
     TYPE SCALE — Modular Scale (1.25 ratio)
     ═══════════════════════════════════════════════════════════════ */
  --text-xs:   0.75rem;    /* 12px — метки, подписи */
  --text-sm:   0.875rem;   /* 14px — вторичный текст, таблицы */
  --text-base: 1rem;       /* 16px — основной текст */
  --text-lg:   1.125rem;   /* 18px — акценты */
  --text-xl:   1.25rem;    /* 20px — подзаголовки */
  --text-2xl:  1.5rem;     /* 24px — заголовки секций */
  --text-3xl:  1.875rem;   /* 30px — заголовки страниц */
  --text-4xl:  2.25rem;    /* 36px — hero */
  
  /* ═══════════════════════════════════════════════════════════════
     LINE HEIGHT
     ═══════════════════════════════════════════════════════════════ */
  --leading-tight:  1.25;
  --leading-snug:   1.375;
  --leading-normal: 1.5;
  --leading-relaxed: 1.625;
  
  /* ═══════════════════════════════════════════════════════════════
     FONT WEIGHT
     ═══════════════════════════════════════════════════════════════ */
  --font-normal:   400;
  --font-medium:   500;
  --font-semibold: 600;
  --font-bold:     700;
  
  /* ═══════════════════════════════════════════════════════════════
     LETTER SPACING
     ═══════════════════════════════════════════════════════════════ */
  --tracking-tight:  -0.025em;
  --tracking-normal: 0;
  --tracking-wide:   0.025em;
  --tracking-wider:  0.05em;
}
```

### Стили текста

```css
/* ═══════════════════════════════════════════════════════════════
   HEADING STYLES
   ═══════════════════════════════════════════════════════════════ */

.heading-page {
  font-family: var(--font-primary);
  font-size: var(--text-3xl);
  font-weight: var(--font-bold);
  line-height: var(--leading-tight);
  letter-spacing: var(--tracking-tight);
  color: var(--neutral-900);
}

.heading-section {
  font-family: var(--font-primary);
  font-size: var(--text-2xl);
  font-weight: var(--font-semibold);
  line-height: var(--leading-tight);
  color: var(--neutral-800);
}

.heading-card {
  font-family: var(--font-primary);
  font-size: var(--text-xl);
  font-weight: var(--font-semibold);
  line-height: var(--leading-snug);
  color: var(--neutral-800);
}

.heading-subsection {
  font-family: var(--font-primary);
  font-size: var(--text-lg);
  font-weight: var(--font-medium);
  line-height: var(--leading-snug);
  color: var(--neutral-700);
}

/* ═══════════════════════════════════════════════════════════════
   BODY STYLES
   ═══════════════════════════════════════════════════════════════ */

.text-body {
  font-family: var(--font-primary);
  font-size: var(--text-base);
  font-weight: var(--font-normal);
  line-height: var(--leading-normal);
  color: var(--neutral-700);
}

.text-secondary {
  font-family: var(--font-primary);
  font-size: var(--text-sm);
  font-weight: var(--font-normal);
  line-height: var(--leading-normal);
  color: var(--neutral-500);
}

.text-caption {
  font-family: var(--font-primary);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  line-height: var(--leading-normal);
  color: var(--neutral-400);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wider);
}

/* ═══════════════════════════════════════════════════════════════
   SPECIAL STYLES
   ═══════════════════════════════════════════════════════════════ */

.text-mono {
  font-family: var(--font-mono);
  font-size: var(--text-sm);
  font-weight: var(--font-normal);
  letter-spacing: var(--tracking-normal);
}

/* Для артикулов, номеров заявок, ID */
.text-code {
  font-family: var(--font-mono);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--primary-700);
  background: var(--primary-50);
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
}

/* Для цен */
.text-price {
  font-family: var(--font-mono);
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  color: var(--neutral-900);
  letter-spacing: var(--tracking-tight);
}

.text-price-currency {
  font-size: var(--text-sm);
  font-weight: var(--font-normal);
  color: var(--neutral-500);
}
```

---

## 4. Пространство и сетка

### Spacing Scale

```css
:root {
  /* ═══════════════════════════════════════════════════════════════
     SPACING — Base 4px
     ═══════════════════════════════════════════════════════════════ */
  --space-0:   0;
  --space-1:   0.25rem;   /* 4px */
  --space-2:   0.5rem;    /* 8px */
  --space-3:   0.75rem;   /* 12px */
  --space-4:   1rem;      /* 16px */
  --space-5:   1.25rem;   /* 20px */
  --space-6:   1.5rem;    /* 24px */
  --space-8:   2rem;      /* 32px */
  --space-10:  2.5rem;    /* 40px */
  --space-12:  3rem;      /* 48px */
  --space-16:  4rem;      /* 64px */
  --space-20:  5rem;      /* 80px */
  --space-24:  6rem;      /* 96px */
}
```

### Сетка страницы

```
┌─────────────────────────────────────────────────────────────────────┐
│                           HEADER (64px)                              │
├──────────────┬──────────────────────────────────────────────────────┤
│              │                                                       │
│   SIDEBAR    │                    MAIN CONTENT                       │
│    (260px)   │                                                       │
│              │  ┌─────────────────────────────────────────────────┐ │
│  Navigation  │  │             PAGE HEADER                         │ │
│  User Info   │  │  Title + Actions                                │ │
│  Quick Stats │  └─────────────────────────────────────────────────┘ │
│              │                                                       │
│              │  ┌─────────────────────────────────────────────────┐ │
│              │  │             CONTENT AREA                        │ │
│              │  │                                                 │ │
│              │  │  Cards, Tables, Forms                           │ │
│              │  │  Padding: 24px                                  │ │
│              │  │  Gap: 24px                                      │ │
│              │  │                                                 │ │
│              │  └─────────────────────────────────────────────────┘ │
│              │                                                       │
└──────────────┴──────────────────────────────────────────────────────┘
```

### Размеры компонентов

```css
:root {
  /* ═══════════════════════════════════════════════════════════════
     LAYOUT DIMENSIONS
     ═══════════════════════════════════════════════════════════════ */
  --header-height: 64px;
  --sidebar-width: 260px;
  --sidebar-collapsed: 72px;
  --content-max-width: 1440px;
  --content-padding: var(--space-6);  /* 24px */
  
  /* ═══════════════════════════════════════════════════════════════
     COMPONENT DIMENSIONS
     ═══════════════════════════════════════════════════════════════ */
  --card-padding: var(--space-5);     /* 20px */
  --card-gap: var(--space-4);         /* 16px */
  --table-cell-padding-x: var(--space-4);  /* 16px */
  --table-cell-padding-y: var(--space-3);  /* 12px */
  --input-padding-x: var(--space-3);  /* 12px */
  --input-padding-y: var(--space-2);  /* 8px */
  
  /* ═══════════════════════════════════════════════════════════════
     BORDER RADIUS
     ═══════════════════════════════════════════════════════════════ */
  --radius-sm:   4px;
  --radius-md:   6px;
  --radius-lg:   8px;
  --radius-xl:   12px;
  --radius-2xl:  16px;
  --radius-full: 9999px;
}
```

---

## 5. Компоненты

### 5.1 Кнопки

```css
/* ═══════════════════════════════════════════════════════════════
   BUTTON BASE
   ═══════════════════════════════════════════════════════════════ */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  font-family: var(--font-primary);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  line-height: 1;
  text-decoration: none;
  white-space: nowrap;
  cursor: pointer;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  transition: all 0.15s ease;
}

.btn:focus-visible {
  outline: 2px solid var(--primary-500);
  outline-offset: 2px;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ═══════════════════════════════════════════════════════════════
   BUTTON SIZES
   ═══════════════════════════════════════════════════════════════ */

.btn-sm {
  height: 32px;
  padding: 0 var(--space-3);
  font-size: var(--text-xs);
}

.btn-md {
  height: 40px;
  padding: 0 var(--space-4);
}

.btn-lg {
  height: 48px;
  padding: 0 var(--space-6);
  font-size: var(--text-base);
}

/* ═══════════════════════════════════════════════════════════════
   BUTTON VARIANTS
   ═══════════════════════════════════════════════════════════════ */

/* Primary — основное действие */
.btn-primary {
  background: var(--primary-600);
  color: var(--neutral-0);
}

.btn-primary:hover {
  background: var(--primary-700);
}

.btn-primary:active {
  background: var(--primary-800);
}

/* Accent — CTA, важные действия */
.btn-accent {
  background: var(--accent-600);
  color: var(--neutral-0);
}

.btn-accent:hover {
  background: var(--accent-700);
}

/* Secondary — вторичные действия */
.btn-secondary {
  background: var(--neutral-0);
  color: var(--neutral-700);
  border-color: var(--neutral-300);
}

.btn-secondary:hover {
  background: var(--neutral-50);
  border-color: var(--neutral-400);
}

/* Ghost — минимальный стиль */
.btn-ghost {
  background: transparent;
  color: var(--neutral-600);
}

.btn-ghost:hover {
  background: var(--neutral-100);
  color: var(--neutral-800);
}

/* Danger — опасные действия */
.btn-danger {
  background: var(--error-600);
  color: var(--neutral-0);
}

.btn-danger:hover {
  background: var(--error-700);
}

/* Success — подтверждающие действия */
.btn-success {
  background: var(--success-600);
  color: var(--neutral-0);
}

.btn-success:hover {
  background: var(--success-700);
}
```

### Иерархия кнопок на странице

```
┌─────────────────────────────────────────────────────────────┐
│                                                              │
│  PRIMARY (1 на экран)    →  btn-accent / btn-primary        │
│  Главное действие           "Создать заявку", "Отправить"   │
│                                                              │
│  SECONDARY (2-3 на экран) →  btn-secondary                  │
│  Альтернативные действия    "Фильтр", "Экспорт", "Отмена"   │
│                                                              │
│  TERTIARY (много)         →  btn-ghost                      │
│  Контекстные действия       "Редактировать", "Удалить"      │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 5.2 Inputs (Поля ввода)

```css
/* ═══════════════════════════════════════════════════════════════
   INPUT BASE
   ═══════════════════════════════════════════════════════════════ */

.input {
  width: 100%;
  height: 40px;
  padding: var(--input-padding-y) var(--input-padding-x);
  font-family: var(--font-primary);
  font-size: var(--text-sm);
  color: var(--neutral-800);
  background: var(--neutral-0);
  border: 1px solid var(--neutral-300);
  border-radius: var(--radius-md);
  transition: all 0.15s ease;
}

.input::placeholder {
  color: var(--neutral-400);
}

.input:hover {
  border-color: var(--neutral-400);
}

.input:focus {
  outline: none;
  border-color: var(--primary-500);
  box-shadow: 0 0 0 3px var(--primary-100);
}

.input:disabled {
  background: var(--neutral-100);
  color: var(--neutral-500);
  cursor: not-allowed;
}

/* ═══════════════════════════════════════════════════════════════
   INPUT STATES
   ═══════════════════════════════════════════════════════════════ */

.input-error {
  border-color: var(--error-500);
}

.input-error:focus {
  border-color: var(--error-500);
  box-shadow: 0 0 0 3px var(--error-100);
}

.input-success {
  border-color: var(--success-500);
}

/* ═══════════════════════════════════════════════════════════════
   INPUT WITH ICON
   ═══════════════════════════════════════════════════════════════ */

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;
  left: var(--space-3);
  top: 50%;
  transform: translateY(-50%);
  color: var(--neutral-400);
  pointer-events: none;
}

.input-with-icon {
  padding-left: 40px;
}

/* ═══════════════════════════════════════════════════════════════
   TEXTAREA
   ═══════════════════════════════════════════════════════════════ */

.textarea {
  min-height: 120px;
  resize: vertical;
  line-height: var(--leading-normal);
}

/* ═══════════════════════════════════════════════════════════════
   SELECT
   ═══════════════════════════════════════════════════════════════ */

.select {
  appearance: none;
  background-image: url("data:image/svg+xml,..."); /* chevron-down */
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 40px;
}
```

### 5.3 Form Group (Группа формы)

```css
.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.form-label {
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--neutral-700);
}

.form-label-required::after {
  content: '*';
  color: var(--error-500);
  margin-left: var(--space-1);
}

.form-hint {
  font-size: var(--text-xs);
  color: var(--neutral-500);
}

.form-error {
  font-size: var(--text-xs);
  color: var(--error-600);
}
```

### 5.4 Cards (Карточки)

```css
/* ═══════════════════════════════════════════════════════════════
   CARD BASE
   ═══════════════════════════════════════════════════════════════ */

.card {
  background: var(--neutral-0);
  border: 1px solid var(--neutral-200);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.card-padding {
  padding: var(--card-padding);
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-4) var(--card-padding);
  border-bottom: 1px solid var(--neutral-200);
}

.card-title {
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  color: var(--neutral-800);
}

.card-body {
  padding: var(--card-padding);
}

.card-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-3);
  padding: var(--space-4) var(--card-padding);
  background: var(--neutral-50);
  border-top: 1px solid var(--neutral-200);
}

/* ═══════════════════════════════════════════════════════════════
   CARD VARIANTS
   ═══════════════════════════════════════════════════════════════ */

/* Интерактивная карточка (клик) */
.card-interactive {
  cursor: pointer;
  transition: all 0.15s ease;
}

.card-interactive:hover {
  border-color: var(--neutral-300);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

/* Карточка с акцентом (важный элемент) */
.card-accent {
  border-left: 4px solid var(--accent-500);
}

/* Карточка успеха */
.card-success {
  border-left: 4px solid var(--success-500);
  background: var(--success-50);
}

/* Карточка предупреждения */
.card-warning {
  border-left: 4px solid var(--warning-500);
  background: var(--warning-50);
}
```

### 5.5 Tables (Таблицы)

```css
/* ═══════════════════════════════════════════════════════════════
   TABLE BASE
   ═══════════════════════════════════════════════════════════════ */

.table-container {
  overflow-x: auto;
  border: 1px solid var(--neutral-200);
  border-radius: var(--radius-lg);
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-sm);
}

.table th {
  padding: var(--table-cell-padding-y) var(--table-cell-padding-x);
  font-weight: var(--font-semibold);
  text-align: left;
  color: var(--neutral-600);
  background: var(--neutral-50);
  border-bottom: 1px solid var(--neutral-200);
  white-space: nowrap;
}

.table td {
  padding: var(--table-cell-padding-y) var(--table-cell-padding-x);
  color: var(--neutral-700);
  border-bottom: 1px solid var(--neutral-100);
  vertical-align: middle;
}

.table tbody tr:hover {
  background: var(--neutral-50);
}

.table tbody tr:last-child td {
  border-bottom: none;
}

/* ═══════════════════════════════════════════════════════════════
   TABLE CELL VARIANTS
   ═══════════════════════════════════════════════════════════════ */

/* Ячейка с моноширинным текстом (артикулы, ID) */
.table-cell-mono {
  font-family: var(--font-mono);
  font-size: var(--text-xs);
  color: var(--primary-700);
}

/* Ячейка с ценой */
.table-cell-price {
  font-family: var(--font-mono);
  font-weight: var(--font-semibold);
  text-align: right;
  white-space: nowrap;
}

/* Ячейка с действиями */
.table-cell-actions {
  text-align: right;
  white-space: nowrap;
}

/* Sortable header */
.table-th-sortable {
  cursor: pointer;
  user-select: none;
}

.table-th-sortable:hover {
  color: var(--primary-600);
}
```

### 5.6 Badges (Метки)

```css
/* ═══════════════════════════════════════════════════════════════
   BADGE BASE
   ═══════════════════════════════════════════════════════════════ */

.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 2px 8px;
  font-family: var(--font-primary);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  line-height: 1.5;
  border-radius: var(--radius-full);
  white-space: nowrap;
}

/* ═══════════════════════════════════════════════════════════════
   BADGE VARIANTS — Статусы заявок
   ═══════════════════════════════════════════════════════════════ */

.badge-draft {
  background: var(--neutral-100);
  color: var(--neutral-600);
}

.badge-pending {
  background: var(--warning-100);
  color: var(--warning-700);
}

.badge-in-progress {
  background: var(--info-100);
  color: var(--info-700);
}

.badge-completed {
  background: var(--success-100);
  color: var(--success-700);
}

.badge-cancelled {
  background: var(--error-100);
  color: var(--error-700);
}

/* ═══════════════════════════════════════════════════════════════
   BADGE VARIANTS — Статусы вопросов
   ═══════════════════════════════════════════════════════════════ */

.badge-question-pending {
  background: var(--accent-100);
  color: var(--accent-700);
}

.badge-question-answered {
  background: var(--success-100);
  color: var(--success-700);
}

.badge-question-skipped {
  background: var(--neutral-100);
  color: var(--neutral-600);
}

/* ═══════════════════════════════════════════════════════════════
   BADGE SIZES
   ═══════════════════════════════════════════════════════════════ */

.badge-sm {
  padding: 1px 6px;
  font-size: 10px;
}

.badge-lg {
  padding: 4px 12px;
  font-size: var(--text-sm);
}

/* С точкой-индикатором */
.badge-dot::before {
  content: '';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}
```

### 5.7 Alerts (Уведомления)

```css
/* ═══════════════════════════════════════════════════════════════
   ALERT BASE
   ═══════════════════════════════════════════════════════════════ */

.alert {
  display: flex;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  font-size: var(--text-sm);
}

.alert-icon {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
}

.alert-content {
  flex: 1;
}

.alert-title {
  font-weight: var(--font-semibold);
  margin-bottom: var(--space-1);
}

.alert-close {
  flex-shrink: 0;
  cursor: pointer;
  opacity: 0.6;
}

.alert-close:hover {
  opacity: 1;
}

/* ═══════════════════════════════════════════════════════════════
   ALERT VARIANTS
   ═══════════════════════════════════════════════════════════════ */

.alert-info {
  background: var(--info-50);
  border: 1px solid var(--info-200);
  color: var(--info-700);
}

.alert-success {
  background: var(--success-50);
  border: 1px solid var(--success-200);
  color: var(--success-700);
}

.alert-warning {
  background: var(--warning-50);
  border: 1px solid var(--warning-200);
  color: var(--warning-700);
}

.alert-error {
  background: var(--error-50);
  border: 1px solid var(--error-200);
  color: var(--error-700);
}
```

### 5.8 Modals (Модальные окна)

```css
/* ═══════════════════════════════════════════════════════════════
   MODAL
   ═══════════════════════════════════════════════════════════════ */

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 17, 20, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-6);
  z-index: 1000;
}

.modal {
  width: 100%;
  max-width: 560px;
  max-height: calc(100vh - 48px);
  background: var(--neutral-0);
  border-radius: var(--radius-xl);
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-5) var(--space-6);
  border-bottom: 1px solid var(--neutral-200);
}

.modal-title {
  font-size: var(--text-xl);
  font-weight: var(--font-semibold);
  color: var(--neutral-800);
}

.modal-close {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  cursor: pointer;
  color: var(--neutral-500);
  transition: all 0.15s ease;
}

.modal-close:hover {
  background: var(--neutral-100);
  color: var(--neutral-700);
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: var(--space-6);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-6);
  background: var(--neutral-50);
  border-top: 1px solid var(--neutral-200);
}

/* Размеры модалок */
.modal-sm { max-width: 400px; }
.modal-lg { max-width: 720px; }
.modal-xl { max-width: 960px; }
```

### 5.9 Sidebar (Боковая панель)

```css
/* ═══════════════════════════════════════════════════════════════
   SIDEBAR
   ═══════════════════════════════════════════════════════════════ */

.sidebar {
  width: var(--sidebar-width);
  height: 100vh;
  position: fixed;
  left: 0;
  top: 0;
  background: var(--primary-900);
  display: flex;
  flex-direction: column;
  z-index: 100;
}

.sidebar-header {
  height: var(--header-height);
  padding: 0 var(--space-5);
  display: flex;
  align-items: center;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-logo {
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  color: var(--neutral-0);
  letter-spacing: var(--tracking-tight);
}

.sidebar-nav {
  flex: 1;
  padding: var(--space-4) var(--space-3);
  overflow-y: auto;
  overflow-x: hidden;
  
  /* Custom scrollbar - Firefox */
  scrollbar-width: thin;
  scrollbar-color: transparent transparent;
  transition: scrollbar-color 0.3s ease;
}

.sidebar-nav:hover {
  scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}

/* Custom scrollbar - Webkit (Chrome, Safari, Edge) */
.sidebar-nav::-webkit-scrollbar {
  width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
  background: transparent;
  margin: var(--space-2) 0;
}

.sidebar-nav::-webkit-scrollbar-thumb {
  background: transparent;
  border-radius: 3px;
  transition: background 0.3s ease;
}

.sidebar-nav:hover::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.35);
}

.sidebar-nav::-webkit-scrollbar-thumb:active {
  background: rgba(255, 255, 255, 0.5);
}

.sidebar-section {
  margin-bottom: var(--space-6);
}

.sidebar-section-title {
  padding: var(--space-2) var(--space-3);
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
  color: var(--primary-400);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wider);
}

/* ═══════════════════════════════════════════════════════════════
   SIDEBAR NAV ITEM
   ═══════════════════════════════════════════════════════════════ */

.sidebar-item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-3);
  border-radius: var(--radius-md);
  color: var(--primary-200);
  text-decoration: none;
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  transition: all 0.15s ease;
}

.sidebar-item:hover {
  background: rgba(255, 255, 255, 0.08);
  color: var(--neutral-0);
}

.sidebar-item.active {
  background: var(--primary-700);
  color: var(--neutral-0);
}

.sidebar-item-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.sidebar-item-badge {
  margin-left: auto;
  padding: 2px 8px;
  font-size: 11px;
  font-weight: var(--font-semibold);
  background: var(--accent-600);
  color: var(--neutral-0);
  border-radius: var(--radius-full);
}

/* ═══════════════════════════════════════════════════════════════
   SIDEBAR FOOTER
   ═══════════════════════════════════════════════════════════════ */

.sidebar-footer {
  padding: var(--space-4);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-user {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.sidebar-user:hover {
  background: rgba(255, 255, 255, 0.08);
}

.sidebar-user-avatar {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-full);
  background: var(--primary-600);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: var(--font-semibold);
  color: var(--neutral-0);
  font-size: var(--text-sm);
}

.sidebar-user-info {
  flex: 1;
  min-width: 0;
}

.sidebar-user-name {
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--neutral-0);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-user-email {
  font-size: var(--text-xs);
  color: var(--primary-300);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
```

---

## 6. Паттерны интерфейса

### 6.1 Page Header

```html
<!-- Стандартный заголовок страницы -->
<div class="page-header">
  <div class="page-header-content">
    <div class="page-header-breadcrumb">
      <a href="#">Главная</a>
      <span>/</span>
      <span>Заявки</span>
    </div>
    <h1 class="page-header-title">Активные заявки</h1>
    <p class="page-header-description">
      Управление текущими заявками на запчасти
    </p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-secondary btn-md">
      <svg><!-- icon --></svg>
      Экспорт
    </button>
    <button class="btn btn-accent btn-md">
      <svg><!-- icon --></svg>
      Новая заявка
    </button>
  </div>
</div>
```

```css
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--space-6);
  margin-bottom: var(--space-6);
}

.page-header-breadcrumb {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-sm);
  color: var(--neutral-500);
  margin-bottom: var(--space-2);
}

.page-header-breadcrumb a {
  color: var(--primary-600);
  text-decoration: none;
}

.page-header-breadcrumb a:hover {
  text-decoration: underline;
}

.page-header-title {
  font-size: var(--text-3xl);
  font-weight: var(--font-bold);
  color: var(--neutral-900);
  letter-spacing: var(--tracking-tight);
}

.page-header-description {
  font-size: var(--text-base);
  color: var(--neutral-500);
  margin-top: var(--space-1);
}

.page-header-actions {
  display: flex;
  gap: var(--space-3);
  flex-shrink: 0;
}
```

### 6.2 Stats Cards

```html
<!-- Блок статистики -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon stat-icon-primary">
      <svg><!-- icon --></svg>
    </div>
    <div class="stat-content">
      <div class="stat-value">24</div>
      <div class="stat-label">Активные заявки</div>
    </div>
  </div>
  
  <div class="stat-card">
    <div class="stat-icon stat-icon-accent">
      <svg><!-- icon --></svg>
    </div>
    <div class="stat-content">
      <div class="stat-value">8</div>
      <div class="stat-label">Ожидают ответа</div>
    </div>
  </div>
  
  <div class="stat-card">
    <div class="stat-icon stat-icon-success">
      <svg><!-- icon --></svg>
    </div>
    <div class="stat-content">
      <div class="stat-value">156</div>
      <div class="stat-label">Получено КП</div>
    </div>
  </div>
</div>
```

```css
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-4);
}

.stat-card {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-5);
  background: var(--neutral-0);
  border: 1px solid var(--neutral-200);
  border-radius: var(--radius-lg);
}

.stat-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-lg);
  flex-shrink: 0;
}

.stat-icon-primary {
  background: var(--primary-100);
  color: var(--primary-600);
}

.stat-icon-accent {
  background: var(--accent-100);
  color: var(--accent-600);
}

.stat-icon-success {
  background: var(--success-100);
  color: var(--success-600);
}

.stat-value {
  font-family: var(--font-mono);
  font-size: var(--text-2xl);
  font-weight: var(--font-bold);
  color: var(--neutral-900);
  line-height: 1;
}

.stat-label {
  font-size: var(--text-sm);
  color: var(--neutral-500);
  margin-top: var(--space-1);
}
```

### 6.3 Data Table Pattern

```html
<!-- Типичная таблица данных -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Список заявок</h2>
    <div class="table-filters">
      <div class="input-wrapper">
        <svg class="input-icon"><!-- search --></svg>
        <input type="text" class="input input-with-icon" placeholder="Поиск...">
      </div>
      <select class="input select">
        <option>Все статусы</option>
        <option>В работе</option>
        <option>Завершённые</option>
      </select>
    </div>
  </div>
  
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th class="table-th-sortable">№ Заявки ↓</th>
          <th>Наименование</th>
          <th>Артикул</th>
          <th>Статус</th>
          <th>КП</th>
          <th>Дата</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="table-cell-mono">REQ-20260112-7348</td>
          <td>Преобразователь частоты Fuji Electric</td>
          <td class="table-cell-mono">FRN18.5LM1S-4EA</td>
          <td><span class="badge badge-in-progress">В работе</span></td>
          <td>12</td>
          <td>12 янв 2026</td>
          <td class="table-cell-actions">
            <button class="btn btn-ghost btn-sm">Открыть</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  
  <div class="table-pagination">
    <div class="pagination-info">
      Показано 1-20 из 156
    </div>
    <div class="pagination-controls">
      <button class="btn btn-ghost btn-sm" disabled>Назад</button>
      <button class="btn btn-ghost btn-sm">Вперёд</button>
    </div>
  </div>
</div>
```

```css
.table-filters {
  display: flex;
  gap: var(--space-3);
}

.table-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-4) var(--card-padding);
  border-top: 1px solid var(--neutral-200);
}

.pagination-info {
  font-size: var(--text-sm);
  color: var(--neutral-500);
}

.pagination-controls {
  display: flex;
  gap: var(--space-2);
}
```

### 6.4 Empty State

```html
<!-- Пустое состояние -->
<div class="empty-state">
  <div class="empty-state-icon">
    <svg><!-- inbox --></svg>
  </div>
  <h3 class="empty-state-title">Нет активных заявок</h3>
  <p class="empty-state-description">
    Создайте первую заявку для начала работы с системой
  </p>
  <button class="btn btn-primary btn-md">
    Создать заявку
  </button>
</div>
```

```css
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--space-16) var(--space-6);
  text-align: center;
}

.empty-state-icon {
  width: 64px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--neutral-100);
  border-radius: var(--radius-full);
  color: var(--neutral-400);
  margin-bottom: var(--space-4);
}

.empty-state-title {
  font-size: var(--text-xl);
  font-weight: var(--font-semibold);
  color: var(--neutral-800);
  margin-bottom: var(--space-2);
}

.empty-state-description {
  font-size: var(--text-base);
  color: var(--neutral-500);
  max-width: 360px;
  margin-bottom: var(--space-6);
}
```

### 6.5 Question Card (Специфичный для IQOT)

```html
<!-- Карточка консолидированного вопроса -->
<div class="question-card">
  <div class="question-header">
    <div class="question-meta">
      <span class="text-code">REQ-20260112-7348</span>
      <span class="question-separator">•</span>
      <span class="question-item">Преобразователь частоты</span>
    </div>
    <span class="badge badge-question-pending">3 поставщика</span>
  </div>
  
  <div class="question-body">
    <p class="question-text">Поставщик просит прислать фото шильдика</p>
    <div class="question-suppliers">
      <span class="supplier-tag">SIEMENS</span>
      <span class="supplier-tag">Ziplift</span>
      <span class="supplier-tag">ЛифтКомплект</span>
    </div>
  </div>
  
  <div class="question-footer">
    <span class="question-time">12 янв, 14:45</span>
    <div class="question-actions">
      <button class="btn btn-ghost btn-sm">Пропустить</button>
      <button class="btn btn-primary btn-sm">Ответить</button>
    </div>
  </div>
</div>
```

```css
.question-card {
  background: var(--neutral-0);
  border: 1px solid var(--neutral-200);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.question-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-4);
  background: var(--neutral-50);
  border-bottom: 1px solid var(--neutral-200);
}

.question-meta {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-sm);
  color: var(--neutral-600);
}

.question-separator {
  color: var(--neutral-300);
}

.question-item {
  font-weight: var(--font-medium);
}

.question-body {
  padding: var(--space-5);
}

.question-text {
  font-size: var(--text-base);
  color: var(--neutral-800);
  line-height: var(--leading-relaxed);
  margin-bottom: var(--space-4);
}

.question-suppliers {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.supplier-tag {
  padding: var(--space-1) var(--space-2);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  color: var(--primary-700);
  background: var(--primary-50);
  border-radius: var(--radius-sm);
}

.question-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-4);
  border-top: 1px solid var(--neutral-100);
}

.question-time {
  font-size: var(--text-sm);
  color: var(--neutral-500);
}

.question-actions {
  display: flex;
  gap: var(--space-2);
}
```

---

## 7. Иконки

### Библиотека иконок

Используем **Lucide Icons** — современный форк Feather Icons с расширенным набором:

```html
<!-- CDN -->
<script src="https://unpkg.com/lucide@latest"></script>
<script>lucide.createIcons();</script>

<!-- Использование -->
<i data-lucide="file-text"></i>
<i data-lucide="send"></i>
<i data-lucide="check-circle"></i>
```

### Основные иконки для IQOT

| Функция | Иконка | Lucide Name |
|---------|--------|-------------|
| Заявки | 📄 | `file-text` |
| Новая заявка | ➕ | `plus` |
| Поставщики | 🏭 | `factory` |
| Вопросы | ❓ | `message-circle-question` |
| Ответы | 💬 | `message-circle` |
| КП / Предложения | 💰 | `receipt` |
| Отчёты | 📊 | `bar-chart-3` |
| Email | ✉️ | `mail` |
| Отправить | ➤ | `send` |
| Настройки | ⚙️ | `settings` |
| Пользователь | 👤 | `user` |
| Поиск | 🔍 | `search` |
| Фильтр | ⏳ | `filter` |
| Экспорт | ↓ | `download` |
| Загрузка файла | ↑ | `upload` |
| Вложение | 📎 | `paperclip` |
| Успех | ✓ | `check-circle` |
| Ошибка | ✕ | `x-circle` |
| Предупреждение | ⚠ | `alert-triangle` |
| Инфо | ℹ | `info` |

### Размеры иконок

```css
.icon-sm { width: 16px; height: 16px; }  /* В кнопках, badges */
.icon-md { width: 20px; height: 20px; }  /* Стандартный размер */
.icon-lg { width: 24px; height: 24px; }  /* В заголовках */
.icon-xl { width: 32px; height: 32px; }  /* Empty states */
```

---

## 8. Анимации и переходы

```css
:root {
  /* ═══════════════════════════════════════════════════════════════
     TIMING
     ═══════════════════════════════════════════════════════════════ */
  --duration-fast:   100ms;
  --duration-normal: 150ms;
  --duration-slow:   300ms;
  
  /* ═══════════════════════════════════════════════════════════════
     EASING
     ═══════════════════════════════════════════════════════════════ */
  --ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
  --ease-out:    cubic-bezier(0, 0, 0.2, 1);
  --ease-in:     cubic-bezier(0.4, 0, 1, 1);
}

/* ═══════════════════════════════════════════════════════════════
   TRANSITIONS
   ═══════════════════════════════════════════════════════════════ */

.transition-colors {
  transition: background-color var(--duration-normal) var(--ease-in-out),
              border-color var(--duration-normal) var(--ease-in-out),
              color var(--duration-normal) var(--ease-in-out);
}

.transition-transform {
  transition: transform var(--duration-normal) var(--ease-out);
}

.transition-opacity {
  transition: opacity var(--duration-normal) var(--ease-in-out);
}

.transition-all {
  transition: all var(--duration-normal) var(--ease-in-out);
}

/* ═══════════════════════════════════════════════════════════════
   ANIMATIONS
   ═══════════════════════════════════════════════════════════════ */

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.animate-fade-in {
  animation: fadeIn var(--duration-slow) var(--ease-out);
}

.animate-slide-up {
  animation: slideInUp var(--duration-slow) var(--ease-out);
}

.animate-pulse {
  animation: pulse 2s var(--ease-in-out) infinite;
}
```

---

## 9. Адаптивность

### Breakpoints

```css
:root {
  --breakpoint-sm:  640px;   /* Мобильные */
  --breakpoint-md:  768px;   /* Планшеты */
  --breakpoint-lg:  1024px;  /* Ноутбуки */
  --breakpoint-xl:  1280px;  /* Десктопы */
  --breakpoint-2xl: 1536px;  /* Большие экраны */
}

/* Mobile-first approach */
@media (min-width: 640px) { /* sm */ }
@media (min-width: 768px) { /* md */ }
@media (min-width: 1024px) { /* lg */ }
@media (min-width: 1280px) { /* xl */ }
```

### Адаптивный sidebar

```css
/* Mobile: sidebar скрыт */
@media (max-width: 1023px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform var(--duration-slow) var(--ease-out);
  }
  
  .sidebar.open {
    transform: translateX(0);
  }
  
  .main-content {
    margin-left: 0;
  }
}

/* Desktop: sidebar виден */
@media (min-width: 1024px) {
  .main-content {
    margin-left: var(--sidebar-width);
  }
}
```

### Пагинация

Полноценная пагинация с номерами страниц, навигацией и выбором количества элементов:

```
DESKTOP
┌─────────────────────────────────────────────────────────────────────────┐
│ Показано 1–5 из 156 заявок    [◀ Назад] 1 2 3 4 5 ... 32 [Вперёд ▶]    │
│                                                    Показывать: [5 ▼]   │
└─────────────────────────────────────────────────────────────────────────┘

MOBILE
┌─────────────────────────────────────────┐
│      Показано 1–5 из 156 заявок         │
│                                         │
│   [◀ Назад]              [Вперёд ▶]     │
│                                         │
│          Показывать: [5 ▼]              │
└─────────────────────────────────────────┘
```

**HTML структура:**

```html
<div class="table-footer">
  <div class="pagination-info">
    Показано <strong>1–5</strong> из <strong>156</strong> заявок
  </div>
  
  <div class="pagination">
    <div class="pagination-nav">
      <button class="pagination-nav-btn" disabled>
        <i data-lucide="chevron-left"></i>
        <span>Назад</span>
      </button>
    </div>
    
    <button class="pagination-btn active">1</button>
    <button class="pagination-btn">2</button>
    <button class="pagination-btn">3</button>
    <button class="pagination-btn hide-tablet">4</button>
    <button class="pagination-btn hide-tablet">5</button>
    <span class="pagination-ellipsis">...</span>
    <button class="pagination-btn">32</button>
    
    <div class="pagination-nav">
      <button class="pagination-nav-btn">
        <span>Вперёд</span>
        <i data-lucide="chevron-right"></i>
      </button>
    </div>
  </div>
  
  <div class="pagination-per-page">
    <span>Показывать:</span>
    <select>
      <option value="5" selected>5</option>
      <option value="10">10</option>
      <option value="25">25</option>
      <option value="50">50</option>
    </select>
  </div>
</div>
```

**Классы:**

| Класс | Описание |
|-------|----------|
| `.pagination-btn` | Кнопка номера страницы |
| `.pagination-btn.active` | Активная страница (синий фон) |
| `.pagination-btn.hide-tablet` | Скрывается на планшетах |
| `.pagination-nav-btn` | Кнопки Назад/Вперёд с бордером |
| `.pagination-ellipsis` | Многоточие между страницами |
| `.pagination-per-page` | Селектор "Показывать: N" |

**Responsive поведение:**

| Ширина | Элементы |
|--------|----------|
| Desktop (>1024px) | Все номера, nav, per-page |
| Tablet (769-1024px) | Часть номеров (`.hide-tablet` скрыты) |
| Mobile (≤768px) | Только nav-кнопки, per-page, info |

### Адаптивные таблицы

```css
/* На мобильных — карточки вместо таблиц */
@media (max-width: 767px) {
  .table-responsive thead {
    display: none;
  }
  
  .table-responsive tr {
    display: block;
    margin-bottom: var(--space-4);
    border: 1px solid var(--neutral-200);
    border-radius: var(--radius-lg);
  }
  
  .table-responsive td {
    display: flex;
    justify-content: space-between;
    padding: var(--space-3) var(--space-4);
    border-bottom: 1px solid var(--neutral-100);
  }
  
  .table-responsive td::before {
    content: attr(data-label);
    font-weight: var(--font-medium);
    color: var(--neutral-500);
  }
}
```

---

## 10. Responsive Sidebar (Адаптивное меню)

### Концепция

Sidebar имеет три состояния:
1. **Expanded** (Desktop) — полная ширина 260px, все элементы видны
2. **Collapsed** (Desktop) — компактная ширина 72px, только иконки с тултипами
3. **Mobile** — скрыт по умолчанию, выезжает поверх контента

```
DESKTOP (>1024px)                    MOBILE (≤1024px)
─────────────────                    ─────────────────

┌────────┬───────────────┐           ┌───────────────────┐
│        │               │           │ ☰  IQOT      🔔   │ ← Top Header
│  SIDE  │    CONTENT    │           ├───────────────────┤
│  BAR   │               │           │                   │
│        │               │           │     CONTENT       │
│ 260px  │               │           │                   │
│   or   │               │           │                   │
│  72px  │               │           │                   │
└────────┴───────────────┘           └───────────────────┘

                                     При клике на ☰:
                                     ┌─────────┬─────────┐
                                     │░░░░░░░░░│         │
                                     │  SIDE   │ OVERLAY │
                                     │  BAR    │  (dim)  │
                                     │         │         │
                                     │ 260px   │         │
                                     └─────────┴─────────┘
```

### CSS переменные для Sidebar

```css
:root {
  --sidebar-width: 260px;
  --sidebar-collapsed-width: 72px;
  --header-height: 64px;
  --sidebar-transition: 250ms cubic-bezier(0, 0, 0.2, 1);
}
```

### Структура HTML

```html
<div class="app-layout">
  <!-- Overlay для мобильных -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <a href="#" class="sidebar-logo">
        <div class="sidebar-logo-icon"><!-- Icon --></div>
        <span class="sidebar-logo-text">IQOT</span>
      </a>
      
      <!-- Desktop: кнопка сворачивания -->
      <button class="sidebar-toggle" id="sidebarToggle">
        <i data-lucide="chevrons-left"></i>
      </button>
      
      <!-- Mobile: кнопка закрытия -->
      <button class="sidebar-close" id="sidebarClose">
        <i data-lucide="x"></i>
      </button>
    </div>
    
    <nav class="sidebar-nav">
      <!-- Navigation items -->
      <a href="#" class="sidebar-item">
        <i class="sidebar-item-icon"></i>
        <span class="sidebar-item-text">Название</span>
        <span class="sidebar-item-badge">5</span>
        <!-- Тултип для collapsed состояния -->
        <span class="sidebar-item-tooltip">Название</span>
      </a>
    </nav>
    
    <div class="sidebar-footer">
      <!-- User info -->
    </div>
  </aside>
  
  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <!-- Mobile Header -->
    <header class="top-header">
      <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i data-lucide="menu"></i>
      </button>
      <div class="mobile-logo">IQOT</div>
      <div class="mobile-actions"><!-- Actions --></div>
    </header>
    
    <div class="page-content">
      <!-- Page content -->
    </div>
  </main>
</div>
```

### CSS для Sidebar

```css
/* ═══════════════════════════════════════════════════════════════
   SIDEBAR BASE
   ═══════════════════════════════════════════════════════════════ */

.sidebar {
  width: var(--sidebar-width);
  height: 100vh;
  position: fixed;
  left: 0;
  top: 0;
  background: var(--primary-900);
  display: flex;
  flex-direction: column;
  z-index: 200;
  transition: transform var(--sidebar-transition),
              width var(--sidebar-transition);
}

/* ═══════════════════════════════════════════════════════════════
   SIDEBAR COLLAPSED STATE (Desktop)
   ═══════════════════════════════════════════════════════════════ */

.sidebar.collapsed {
  width: var(--sidebar-collapsed-width);
}

/* Скрываем текст в collapsed состоянии */
.sidebar.collapsed .sidebar-logo-text,
.sidebar.collapsed .sidebar-section-title,
.sidebar.collapsed .sidebar-item-text,
.sidebar.collapsed .sidebar-item-badge,
.sidebar.collapsed .sidebar-user-info {
  opacity: 0;
  width: 0;
  overflow: hidden;
}

/* Поворачиваем иконку toggle */
.sidebar.collapsed .sidebar-toggle-icon {
  transform: rotate(180deg);
}

/* Центрируем элементы */
.sidebar.collapsed .sidebar-item {
  justify-content: center;
}

/* Показываем тултипы при hover */
.sidebar.collapsed .sidebar-item:hover .sidebar-item-tooltip {
  opacity: 1;
  visibility: visible;
}

/* ═══════════════════════════════════════════════════════════════
   SIDEBAR ITEM TOOLTIP
   ═══════════════════════════════════════════════════════════════ */

.sidebar-item-tooltip {
  position: absolute;
  left: calc(100% + 12px);
  top: 50%;
  transform: translateY(-50%);
  padding: var(--space-2) var(--space-3);
  background: var(--neutral-900);
  color: var(--neutral-0);
  font-size: var(--text-sm);
  border-radius: var(--radius-md);
  white-space: nowrap;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transition: opacity 150ms ease;
  z-index: 300;
  box-shadow: var(--shadow-lg);
}

/* Стрелка тултипа */
.sidebar-item-tooltip::before {
  content: '';
  position: absolute;
  left: -6px;
  top: 50%;
  transform: translateY(-50%);
  border: 6px solid transparent;
  border-right-color: var(--neutral-900);
  border-left: none;
}

/* ═══════════════════════════════════════════════════════════════
   MOBILE OVERLAY
   ═══════════════════════════════════════════════════════════════ */

.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 17, 20, 0.6);
  backdrop-filter: blur(4px);
  z-index: 150;
  opacity: 0;
  visibility: hidden;
  transition: opacity 250ms ease, visibility 250ms ease;
}

.sidebar-overlay.visible {
  opacity: 1;
  visibility: visible;
}

/* ═══════════════════════════════════════════════════════════════
   MAIN CONTENT
   ═══════════════════════════════════════════════════════════════ */

.main-content {
  flex: 1;
  margin-left: var(--sidebar-width);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  transition: margin-left var(--sidebar-transition);
}

.main-content.sidebar-collapsed {
  margin-left: var(--sidebar-collapsed-width);
}

/* ═══════════════════════════════════════════════════════════════
   TOP HEADER (Mobile only)
   ═══════════════════════════════════════════════════════════════ */

.top-header {
  height: var(--header-height);
  background: var(--neutral-0);
  border-bottom: 1px solid var(--neutral-200);
  display: none;  /* Скрыт на desktop */
  align-items: center;
  justify-content: space-between;
  padding: 0 var(--space-4);
  position: sticky;
  top: 0;
  z-index: 100;
}

.mobile-menu-btn {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  border-radius: var(--radius-md);
  color: var(--neutral-600);
  cursor: pointer;
}

.mobile-menu-btn:hover {
  background: var(--neutral-100);
  color: var(--neutral-800);
}

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE: Tablets (≤1024px)
   ═══════════════════════════════════════════════════════════════ */

@media (max-width: 1024px) {
  /* Sidebar скрыт по умолчанию */
  .sidebar {
    transform: translateX(-100%);
    width: var(--sidebar-width); /* Всегда полная ширина */
  }
  
  /* Показываем при открытии */
  .sidebar.mobile-open {
    transform: translateX(0);
  }
  
  /* Сбрасываем collapsed состояние */
  .sidebar.collapsed {
    width: var(--sidebar-width);
  }
  
  .sidebar.collapsed .sidebar-logo-text,
  .sidebar.collapsed .sidebar-item-text,
  .sidebar.collapsed .sidebar-item-badge,
  .sidebar.collapsed .sidebar-user-info {
    opacity: 1;
    width: auto;
  }
  
  /* Скрываем desktop toggle */
  .sidebar-toggle {
    display: none;
  }
  
  /* Показываем mobile close */
  .sidebar-close {
    display: flex !important;
  }
  
  /* Показываем top header */
  .top-header {
    display: flex;
  }
  
  /* Сбрасываем margin */
  .main-content,
  .main-content.sidebar-collapsed {
    margin-left: 0;
  }
}

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE: Mobile phones (≤768px)
   ═══════════════════════════════════════════════════════════════ */

@media (max-width: 768px) {
  .page-content {
    padding: var(--space-4);
  }
  
  .page-header {
    flex-direction: column;
  }
  
  .page-header-actions {
    width: 100%;
  }
}

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE: Small phones (≤480px)
   ═══════════════════════════════════════════════════════════════ */

@media (max-width: 480px) {
  .sidebar {
    width: 100%; /* На маленьких экранах — полная ширина */
  }
}
```

### JavaScript логика

```javascript
// DOM Elements
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarClose = document.getElementById('sidebarClose');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mainContent = document.getElementById('mainContent');

// State
let isSidebarCollapsed = false;
let isMobileSidebarOpen = false;

// Определение мобильного режима
function isMobile() {
  return window.innerWidth <= 1024;
}

// Desktop: Toggle collapsed
sidebarToggle.addEventListener('click', () => {
  if (!isMobile()) {
    isSidebarCollapsed = !isSidebarCollapsed;
    sidebar.classList.toggle('collapsed', isSidebarCollapsed);
    mainContent.classList.toggle('sidebar-collapsed', isSidebarCollapsed);
    localStorage.setItem('sidebarCollapsed', isSidebarCollapsed);
  }
});

// Mobile: Open sidebar
mobileMenuBtn.addEventListener('click', () => {
  isMobileSidebarOpen = true;
  sidebar.classList.add('mobile-open');
  sidebarOverlay.classList.add('visible');
  document.body.style.overflow = 'hidden'; // Блокируем скролл
});

// Mobile: Close sidebar
function closeMobileSidebar() {
  isMobileSidebarOpen = false;
  sidebar.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('visible');
  document.body.style.overflow = '';
}

sidebarClose.addEventListener('click', closeMobileSidebar);
sidebarOverlay.addEventListener('click', closeMobileSidebar);

// Закрытие при клике на пункт меню (mobile)
document.querySelectorAll('.sidebar-item').forEach(item => {
  item.addEventListener('click', () => {
    if (isMobile() && isMobileSidebarOpen) {
      closeMobileSidebar();
    }
  });
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
  // Escape — закрыть мобильное меню
  if (e.key === 'Escape' && isMobileSidebarOpen) {
    closeMobileSidebar();
  }
  
  // Ctrl/Cmd + B — toggle sidebar
  if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
    e.preventDefault();
    if (isMobile()) {
      isMobileSidebarOpen ? closeMobileSidebar() : mobileMenuBtn.click();
    } else {
      sidebarToggle.click();
    }
  }
});

// Handle resize
window.addEventListener('resize', () => {
  if (!isMobile()) {
    closeMobileSidebar();
    // Восстанавливаем saved state
    const saved = localStorage.getItem('sidebarCollapsed') === 'true';
    sidebar.classList.toggle('collapsed', saved);
    mainContent.classList.toggle('sidebar-collapsed', saved);
  }
});
```

### Состояния и переходы

| Событие | Desktop | Mobile |
|---------|---------|--------|
| Клик на toggle | Collapsed ↔ Expanded | — |
| Клик на hamburger | — | Открыть sidebar + overlay |
| Клик на overlay | — | Закрыть sidebar |
| Клик на close | — | Закрыть sidebar |
| Escape | — | Закрыть sidebar |
| Ctrl+B | Toggle collapsed | Toggle open |
| Resize → desktop | Восстановить saved state | Закрыть sidebar |
| Resize → mobile | Сбросить collapsed | — |

### UX рекомендации

1. **Сохранение состояния** — collapsed state сохраняется в localStorage
2. **Плавные анимации** — все переходы 250ms ease
3. **Тултипы** — показываем названия пунктов в collapsed состоянии
4. **Блокировка скролла** — на mobile при открытом меню
5. **Keyboard shortcuts** — Ctrl+B, Escape
6. **Автозакрытие** — на mobile закрываем при клике на пункт меню

### Custom Scrollbar

Изящный скроллбар для навигации sidebar — скрыт по умолчанию, появляется при hover:

```css
.sidebar-nav {
  /* Firefox */
  scrollbar-width: thin;
  scrollbar-color: transparent transparent;
  transition: scrollbar-color 0.3s ease;
}

.sidebar-nav:hover {
  scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}

/* Webkit (Chrome, Safari, Edge) */
.sidebar-nav::-webkit-scrollbar {
  width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
  background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
  background: transparent;
  border-radius: 3px;
  transition: background 0.3s ease;
}

.sidebar-nav:hover::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.35);
}

.sidebar-nav::-webkit-scrollbar-thumb:active {
  background: rgba(255, 255, 255, 0.5);
}
```

**Особенности:**

| Состояние | Цвет thumb | Описание |
|-----------|------------|----------|
| По умолчанию | `transparent` | Скроллбар скрыт |
| Hover на nav | `rgba(255,255,255, 0.2)` | Появляется при наведении на меню |
| Hover на thumb | `rgba(255,255,255, 0.35)` | Ярче при наведении на сам скроллбар |
| Active (drag) | `rgba(255,255,255, 0.5)` | Ещё ярче при перетаскивании |

**Параметры:**
- Ширина: 6px
- Радиус: 3px
- Анимация: 0.3s ease
- Цвет: белый полупрозрачный (для тёмного фона sidebar)

---

## 11. Различия: Личный кабинет vs Админка

### Личный кабинет (Клиент)

**Цель:** Создание заявок, отслеживание статуса, ответы на вопросы

**Особенности:**
- Упрощённая навигация
- Фокус на активных заявках
- Wizard для создания заявок
- Dashboard со статистикой своих заявок

**Цветовая схема:**
- Основной фон: `--neutral-50` (светлый)
- Акценты: `--accent-600` (оранжевый для CTA)

### Админка (/manage/)

**Цель:** Полное управление системой, все данные

**Особенности:**
- Расширенная навигация (все разделы)
- Детальные таблицы с фильтрацией
- Batch-операции
- Системные настройки

**Цветовая схема:**
- Можно использовать тот же стиль
- Дополнительные статусы для служебной информации

### Структура навигации

```
ЛИЧНЫЙ КАБИНЕТ                    АДМИНКА (/manage/)
──────────────                    ──────────────────
🏠 Главная                        🏠 Dashboard
📄 Мои заявки                     📄 Заявки
  └─ Активные                       ├─ Все заявки
  └─ Завершённые                    ├─ В обработке
❓ Вопросы                           └─ Архив
📊 Отчёты                         👥 Пользователи
⚙️ Профиль                        🏭 Поставщики
                                  ❓ Вопросы
                                  ✉️ Email-аккаунты
                                  📊 Отчёты
                                  ⚙️ Настройки
                                    ├─ Категории
                                    ├─ Шаблоны
                                    └─ Система
```

### Адаптивные таблицы

На мобильных устройствах (≤768px) таблицы преобразуются в карточки:

```
DESKTOP                              MOBILE
───────────────────────────────      ─────────────────────
│ № Заявки │ Название │ Статус │     ┌─────────────────────┐
├──────────┼──────────┼────────┤     │ № ЗАЯВКИ            │
│ REQ-001  │ Датчик   │ ● Work │     │ REQ-20260112-7348   │
├──────────┼──────────┼────────┤     ├─────────────────────┤
│ REQ-002  │ Привод   │ ● Done │     │ НАИМЕНОВАНИЕ        │
└──────────┴──────────┴────────┘     │ Преобразователь...  │
                                     ├─────────────────────┤
                                     │ СТАТУС  ● В работе  │
                                     ├─────────────────────┤
                                     │         [Открыть]   │
                                     └─────────────────────┘
```

**HTML структура с data-label:**

```html
<table class="table">
  <thead>
    <tr>
      <th>№ Заявки</th>
      <th>Наименование</th>
      <th>Статус</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td data-label="№ Заявки" class="table-cell-mono">REQ-20260112-7348</td>
      <td data-label="Наименование">Преобразователь частоты</td>
      <td data-label="Статус"><span class="badge badge-in-progress">В работе</span></td>
      <td><button class="btn btn-ghost btn-sm">Открыть</button></td>
    </tr>
  </tbody>
</table>
```

**Ключевые правила:**
- Атрибут `data-label` обязателен для всех ячеек кроме последней (actions)
- Последняя ячейка (кнопки действий) не имеет label и выравнивается вправо
- На mobile каждая строка становится отдельной карточкой
- Заголовок таблицы скрывается, вместо него — labels из `data-label`

---

## 13. Тени и глубина

```css
:root {
  /* ═══════════════════════════════════════════════════════════════
     SHADOWS — Minimal, industrial style
     ═══════════════════════════════════════════════════════════════ */
  --shadow-sm:  0 1px 2px rgba(0, 0, 0, 0.05);
  --shadow-md:  0 4px 6px rgba(0, 0, 0, 0.07);
  --shadow-lg:  0 10px 15px rgba(0, 0, 0, 0.10);
  --shadow-xl:  0 20px 25px rgba(0, 0, 0, 0.15);
  
  /* Для dropdown, modals */
  --shadow-dropdown: 0 4px 16px rgba(0, 0, 0, 0.12);
  --shadow-modal:    0 24px 48px rgba(0, 0, 0, 0.20);
}
```

**Применение:**
- Карточки: без тени (только border)
- Hover на карточках: `--shadow-md`
- Dropdown меню: `--shadow-dropdown`
- Модальные окна: `--shadow-modal`

---

## 14. Z-index Scale

```css
:root {
  --z-base:      0;
  --z-dropdown:  100;
  --z-sticky:    200;
  --z-fixed:     300;
  --z-modal-bg:  400;
  --z-modal:     500;
  --z-popover:   600;
  --z-tooltip:   700;
  --z-toast:     800;
}
```

---

## 15. Checklist для разработки

### При создании нового компонента:

- [ ] Использованы переменные цветов из `:root`
- [ ] Использован `--font-primary` или `--font-mono`
- [ ] Размеры текста из type scale
- [ ] Отступы из spacing scale
- [ ] Border-radius из переменных
- [ ] Transitions добавлены для интерактивных элементов
- [ ] Focus state прописан для доступности
- [ ] Hover state для интерактивных элементов

### При создании новой страницы:

- [ ] Page header с breadcrumbs
- [ ] Корректная структура сетки
- [ ] Empty state для пустых данных
- [ ] Loading state
- [ ] Error state
- [ ] Адаптивность проверена

---

## 16. Логотип IQOT

### SVG файлы

**iqot-logo-icon.svg** — иконка (круг с Q):
```svg
<svg viewBox="0 0 119.81 119.81" xmlns="http://www.w3.org/2000/svg">
  <path d="M59.9,0C26.82,0,0,26.82,0,59.9s26.82,59.9,59.9,59.9,59.9-26.82,59.9-59.9S92.99,0,59.9,0ZM22.91,60.5c0-20.43,16.56-36.99,36.99-36.99s36.99,16.56,36.99,36.99c0,9.7-3.74,18.52-9.84,25.12l-17.05-15.39-3.36,3.73,16.75,15.12c-6.39,5.26-14.57,8.41-23.49,8.41-20.43,0-36.99-16.56-36.99-36.99Z"/>
</svg>
```

**iqot-logo-full.svg** — полный логотип с текстом "IQOT"

### Использование

```css
/* Белый логотип (для тёмного фона — sidebar) */
.sidebar-logo-icon svg {
  width: 36px;
  height: 36px;
  fill: var(--neutral-0);
}

/* Accent логотип (для светлого фона — mobile header) */
.mobile-logo-icon svg {
  width: 32px;
  height: 32px;
  fill: var(--accent-600);
}

/* Тёмный логотип (для светлого фона) */
.logo-dark svg {
  fill: var(--neutral-900);
}
```

### Размеры

| Контекст | Размер | Цвет |
|----------|--------|------|
| Sidebar (desktop) | 36×36px | Белый `--neutral-0` |
| Mobile header | 32×32px | Accent `--accent-600` |
| Favicon | 32×32px | Accent |
| Footer | 24×24px | Серый `--neutral-400` |

---

## Приложение A: Полный CSS файл

Полные стили доступны в отдельном файле `iqot-design-tokens.css`.

## Приложение B: Figma / Design Tokens

При необходимости токены можно экспортировать в формат Figma Tokens или Style Dictionary.
