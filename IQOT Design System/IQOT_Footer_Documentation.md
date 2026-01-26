# IQOT Footer — UX/UI Документация

## Обзор

Футер сайта iqot.ru — информационный блок внизу страницы с навигацией, юридическими ссылками и контактами.

---

## Структура

### Компоновка (Desktop)

```
┌─────────────────────────────────────────────────────────────────────┐
│  FOOTER                                                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  [Logo] IQOT          Информация       Юридическое      Контакты    │
│                                                                      │
│  Интеллектуальный     • Частые         • Условия        • Email     │
│  сбор и анализ          вопросы          использования  • Telegram  │
│  коммерческих         • Как это        • Политика                   │
│  предложений            работает         конфиденц.                 │
│                       • Тарифы         • Договор-оферта             │
│                                                                      │
├─────────────────────────────────────────────────────────────────────┤
│  © 2025 IQOT. Все права защищены                                    │
└─────────────────────────────────────────────────────────────────────┘
```

### Колонки ссылок

| Колонка | Назначение | Текущие ссылки | Возможные дополнения |
|---------|------------|----------------|----------------------|
| **Информация** | Продуктовые страницы | Частые вопросы | Как это работает, Тарифы, Почему это работает |
| **Юридическое** | Правовые документы | Условия использования, Политика конфиденциальности, Договор-оферта | — |
| **Контакты** | Связь с компанией | Email | Telegram, телефон |

---

## Визуальные стандарты

### Цвета

```css
--bg-footer: #08080c;           /* Фон футера (темнее основного) */
--border: rgba(255,255,255,0.06); /* Разделители */
--text-white: #ffffff;          /* Заголовки колонок, логотип */
--text-gray: #9898a0;           /* Ссылки (default) */
--text-muted: #58585f;          /* Описание, копирайт */
--accent: #10b981;              /* Логотип (иконка) */
```

### Типографика

| Элемент | Размер | Вес | Цвет | Доп. стили |
|---------|--------|-----|------|------------|
| Логотип (текст) | 18px | 700 | white | — |
| Описание бренда | 14px | 400 | muted | line-height: 1.6 |
| Заголовок колонки | 13px | 600 | white | uppercase, letter-spacing: 0.05em |
| Ссылка | 14px | 400 | gray → white (hover) | transition: 0.2s |
| Копирайт | 13px | 400 | muted | — |

### Отступы

```
Footer padding:        48px 24px 32px
Container max-width:   1200px
Grid gap:              48px (между колонками)
Column gap:            16px (между заголовком и ссылками)
Links gap:             12px (между ссылками)
Bottom bar padding:    24px top
```

---

## Адаптивность

### Брейкпоинты

| Ширина | Поведение |
|--------|-----------|
| > 900px | 4 колонки: Brand (1.5fr) + 3 колонки ссылок (1fr) |
| 600-900px | 2 колонки, Brand на всю ширину сверху |
| < 600px | 1 колонка, всё вертикально, текст по центру |

### Mobile (< 600px)

```
┌──────────────────────┐
│      [Logo] IQOT     │
│                      │
│  Интеллектуальный    │
│  сбор и анализ...    │
│                      │
│     ИНФОРМАЦИЯ       │
│   Частые вопросы     │
│   Как это работает   │
│                      │
│     ЮРИДИЧЕСКОЕ      │
│   Условия исп...     │
│   Политика конф...   │
│   Договор-оферта     │
│                      │
│      КОНТАКТЫ        │
│    info@iqot.ru      │
│      Telegram        │
│                      │
│ © 2025 IQOT. Все...  │
└──────────────────────┘
```

---

## HTML Структура

```html
<footer class="footer">
  <div class="footer-container">
    
    <!-- Main content -->
    <div class="footer-main">
      
      <!-- Brand -->
      <div class="footer-brand">
        <div class="footer-logo">
          <svg>...</svg>
          <span>IQOT</span>
        </div>
        <p class="footer-tagline">Описание продукта</p>
      </div>
      
      <!-- Column: Информация -->
      <div class="footer-column">
        <div class="footer-column-title">Информация</div>
        <div class="footer-links">
          <a href="/faq">Частые вопросы</a>
          <a href="/how-it-works">Как это работает</a>
        </div>
      </div>
      
      <!-- Column: Юридическое -->
      <div class="footer-column">
        <div class="footer-column-title">Юридическое</div>
        <div class="footer-links">
          <a href="/terms">Условия использования</a>
          <a href="/privacy">Политика конфиденциальности</a>
          <a href="/offer">Договор-оферта</a>
        </div>
      </div>
      
      <!-- Column: Контакты -->
      <div class="footer-column">
        <div class="footer-column-title">Контакты</div>
        <div class="footer-links">
          <a href="mailto:info@iqot.ru">info@iqot.ru</a>
        </div>
      </div>
      
    </div>
    
    <!-- Bottom bar -->
    <div class="footer-bottom">
      <div class="footer-copy">© 2025 IQOT. Все права защищены</div>
    </div>
    
  </div>
</footer>
```

---

## CSS (Production Ready)

```css
/* ═══════════════════════════════════════
   FOOTER
   ═══════════════════════════════════════ */

.footer {
  background: #08080c;
  border-top: 1px solid rgba(255,255,255,0.06);
  padding: 48px 24px 32px;
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Main grid */
.footer-main {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr 1fr;
  gap: 48px;
  padding-bottom: 40px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}

/* Brand */
.footer-brand {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.footer-logo {
  display: flex;
  align-items: center;
  gap: 12px;
}

.footer-logo svg {
  width: 32px;
  height: 32px;
  fill: #10b981;
}

.footer-logo span {
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
}

.footer-tagline {
  font-size: 14px;
  color: #58585f;
  line-height: 1.6;
  max-width: 280px;
}

/* Columns */
.footer-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.footer-column-title {
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #ffffff;
}

.footer-links {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.footer-links a {
  font-size: 14px;
  color: #9898a0;
  text-decoration: none;
  transition: color 0.2s;
}

.footer-links a:hover {
  color: #ffffff;
}

/* Bottom */
.footer-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 24px;
}

.footer-copy {
  font-size: 13px;
  color: #58585f;
}

/* ═══════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════ */

@media (max-width: 900px) {
  .footer-main {
    grid-template-columns: 1fr 1fr;
    gap: 40px;
  }
  
  .footer-brand {
    grid-column: 1 / -1;
  }
}

@media (max-width: 600px) {
  .footer {
    padding: 40px 20px 24px;
  }
  
  .footer-main {
    grid-template-columns: 1fr;
    gap: 32px;
  }
  
  .footer-brand {
    grid-column: auto;
    text-align: center;
    align-items: center;
  }
  
  .footer-tagline {
    max-width: 100%;
  }
  
  .footer-column {
    align-items: center;
    text-align: center;
  }
  
  .footer-links {
    align-items: center;
  }
  
  .footer-bottom {
    flex-direction: column;
    text-align: center;
  }
}
```

---

## Правила добавления ссылок

### Куда добавлять

| Тип контента | Колонка |
|--------------|---------|
| Продуктовые страницы (FAQ, How it works, Pricing) | Информация |
| Правовые документы (Terms, Privacy, Offer) | Юридическое |
| Способы связи (Email, Telegram, Phone) | Контакты |

### Порядок ссылок

- **Информация**: от общего к частному (FAQ → How it works → Pricing)
- **Юридическое**: по важности (Terms → Privacy → Offer)
- **Контакты**: по приоритету (Email → Telegram → Phone)

### Максимум ссылок

- Рекомендуется: 3-5 ссылок на колонку
- Максимум: 7 ссылок на колонку
- При превышении — рассмотреть создание подкатегорий

---

## Чеклист для разработчика

- [ ] Использовать CSS-переменные для цветов
- [ ] Проверить hover-состояния ссылок
- [ ] Протестировать на 320px, 600px, 900px, 1200px
- [ ] Убедиться что логотип — SVG (не PNG)
- [ ] Проверить что ссылки ведут на правильные URL
- [ ] Обновить год в копирайте при необходимости
