<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Пресс-кит — IQOT</title>
<meta name="description" content="Медиа-материалы, пресс-релизы, логотипы и скриншоты платформы IQOT для журналистов и редакций.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
  --bg-primary: #0F1117;
  --bg-card: #161921;
  --bg-card-hover: #1c1f2a;
  --border-color: rgba(255,255,255,0.08);
  --border-hover: rgba(16,185,129,0.3);
  --text-primary: #ffffff;
  --text-secondary: rgba(255,255,255,0.65);
  --text-muted: rgba(255,255,255,0.4);
  --accent: #10B981;
  --accent-light: #34D399;
  --accent-gradient: linear-gradient(135deg, #10B981 0%, #34D399 100%);
  --font: 'Manrope', sans-serif;
  --radius: 16px;
  --radius-sm: 10px;
}

html { scroll-behavior: smooth; }

body {
  font-family: var(--font);
  background: var(--bg-primary);
  color: var(--text-primary);
  line-height: 1.7;
  -webkit-font-smoothing: antialiased;
}

/* ── HERO ── */
.hero {
  padding: 100px 40px 100px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute; top: -200px; left: 50%; transform: translateX(-50%);
  width: 800px; height: 800px;
  background: radial-gradient(circle, rgba(16,185,129,0.08) 0%, transparent 70%);
  pointer-events: none;
}
.hero-label {
  display: inline-block;
  font-size: 12px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase;
  color: var(--accent);
  margin-bottom: 24px;
}
.hero h1 {
  font-size: clamp(36px, 5vw, 60px);
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.1;
  margin-bottom: 20px;
}
.hero p {
  font-size: 18px;
  color: var(--text-secondary);
  max-width: 600px;
  margin: 0 auto;
}

/* ── SECTIONS ── */
.section {
  padding: 80px 40px;
  max-width: 1100px;
  margin: 0 auto;
}
.section-label {
  font-size: 12px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase;
  color: var(--accent);
  margin-bottom: 16px;
}
.section h2 {
  font-size: clamp(28px, 3.5vw, 40px);
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.15;
  margin-bottom: 24px;
}
.section-description {
  color: var(--text-secondary);
  font-size: 16px;
  max-width: 700px;
  margin-bottom: 48px;
}
.divider {
  border: none;
  border-top: 1px solid var(--border-color);
  margin: 0 40px;
  max-width: 1100px;
  margin-left: auto; margin-right: auto;
}

/* ── ABOUT / BOILERPLATE ── */
.boilerplate-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius);
  padding: 48px;
  position: relative;
  transition: border-color 0.3s;
}
.boilerplate-card:hover { border-color: var(--border-hover); }
.boilerplate-text {
  color: var(--text-secondary);
  font-size: 16px;
  line-height: 1.85;
  margin-bottom: 32px;
}
.boilerplate-text strong { color: var(--text-primary); font-weight: 600; }
.copy-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 20px; border-radius: var(--radius-sm);
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-secondary);
  font-family: var(--font); font-size: 13px; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
}
.copy-btn:hover {
  border-color: var(--accent);
  color: var(--accent);
  background: rgba(16,185,129,0.05);
}
.copy-btn.copied {
  border-color: var(--accent);
  color: var(--accent);
}
.copy-btn svg { width: 16px; height: 16px; }

/* ── FACTS GRID ── */
.facts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
}
.fact-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius);
  padding: 32px 28px;
  transition: border-color 0.3s, transform 0.3s;
}
.fact-card:hover {
  border-color: var(--border-hover);
  transform: translateY(-2px);
}
.fact-value {
  font-size: 36px;
  font-weight: 800;
  letter-spacing: -0.03em;
  background: var(--accent-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 8px;
}
.fact-label {
  font-size: 14px;
  color: var(--text-secondary);
  font-weight: 500;
}

/* ── PRESS RELEASE ── */
.press-release-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius);
  padding: 48px;
  transition: border-color 0.3s;
}
.press-release-card:hover { border-color: var(--border-hover); }
.pr-date {
  font-size: 13px;
  color: var(--text-muted);
  font-weight: 500;
  margin-bottom: 12px;
}
.pr-title {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin-bottom: 16px;
  line-height: 1.3;
}
.pr-excerpt {
  color: var(--text-secondary);
  font-size: 15px;
  line-height: 1.7;
  margin-bottom: 28px;
}
.pr-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 12px 28px; border-radius: var(--radius-sm);
  background: var(--accent-gradient);
  color: var(--bg-primary);
  font-family: var(--font); font-size: 14px; font-weight: 700;
  text-decoration: none; border: none; cursor: pointer;
  transition: opacity 0.2s, transform 0.2s;
}
.btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-outline {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 12px 28px; border-radius: var(--radius-sm);
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-primary);
  font-family: var(--font); font-size: 14px; font-weight: 600;
  text-decoration: none; cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}
.btn-outline:hover {
  border-color: var(--border-hover);
  background: rgba(16,185,129,0.05);
}

/* ── SCREENSHOTS ── */
.screenshots-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 24px;
}
.screenshot-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius);
  overflow: hidden;
  transition: border-color 0.3s, transform 0.3s;
  text-decoration: none;
  display: block;
  color: inherit;
}
.screenshot-card:hover {
  border-color: var(--border-hover);
  transform: translateY(-3px);
}
.screenshot-card:hover .screenshot-title {
  color: var(--accent);
}
.screenshot-img {
  width: 100%;
  aspect-ratio: 16/10;
  object-fit: cover;
  display: block;
  border-bottom: 1px solid var(--border-color);
  cursor: pointer;
}
.screenshot-placeholder {
  width: 100%;
  aspect-ratio: 16/10;
  display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 12px;
  background: linear-gradient(135deg, rgba(16,185,129,0.03) 0%, rgba(16,185,129,0.08) 100%);
  border-bottom: 1px solid var(--border-color);
  color: var(--text-muted); font-size: 14px;
}
.screenshot-placeholder svg { width: 40px; height: 40px; opacity: 0.3; }
.screenshot-info { padding: 20px 24px; }
.screenshot-title { font-size: 15px; font-weight: 600; margin-bottom: 4px; color: var(--text-primary); transition: color 0.2s; }
.screenshot-desc { font-size: 13px; color: var(--text-muted); }

/* ── LOGOS ── */
.logos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 24px;
}
.logo-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius);
  overflow: hidden;
  transition: border-color 0.3s;
}
.logo-card:hover { border-color: var(--border-hover); }
.logo-preview {
  height: 160px;
  display: flex; align-items: center; justify-content: center;
  border-bottom: 1px solid var(--border-color);
}
.logo-preview.dark-bg { background: var(--bg-primary); }
.logo-preview.light-bg { background: #f5f5f5; }
.logo-preview img { max-height: 48px; max-width: 70%; }
.logo-preview.dark-bg img[src*="iqot-logo-full.svg"],
.logo-preview.dark-bg img[src*="iqot-logo-icon.svg"] { filter: brightness(0) invert(1); }
.logo-preview.light-bg img[src*="iqot-logo-full.svg"] { filter: brightness(0); }
.logo-info {
  padding: 20px 24px;
  display: flex; align-items: center; justify-content: space-between;
}
.logo-meta .logo-name { font-size: 14px; font-weight: 600; }
.logo-meta .logo-format { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.download-btn {
  width: 36px; height: 36px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.2s;
  text-decoration: none;
}
.download-btn:hover {
  border-color: var(--accent);
  color: var(--accent);
  background: rgba(16,185,129,0.05);
}
.download-btn svg { width: 16px; height: 16px; }

/* ── CONTACT ── */
.contact-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius);
  padding: 48px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.contact-card::before {
  content: '';
  position: absolute; bottom: -100px; left: 50%; transform: translateX(-50%);
  width: 500px; height: 500px;
  background: radial-gradient(circle, rgba(16,185,129,0.06) 0%, transparent 70%);
  pointer-events: none;
}
.contact-card h3 {
  font-size: 24px; font-weight: 800; margin-bottom: 12px;
  position: relative;
}
.contact-card p {
  color: var(--text-secondary); font-size: 15px;
  margin-bottom: 28px; position: relative;
}
.contact-email {
  font-size: 20px; font-weight: 700;
  background: var(--accent-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-decoration: none;
  position: relative;
}
.contact-email:hover { opacity: 0.85; }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  .hero { padding: 80px 20px 60px; }
  .section { padding: 60px 20px; }
  .boilerplate-card, .press-release-card, .contact-card { padding: 32px 24px; }
  .screenshots-grid { grid-template-columns: 1fr; }
  .logos-grid { grid-template-columns: 1fr; }
  .facts-grid { grid-template-columns: repeat(2, 1fr); }
}

/* ── ANIMATIONS ── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(24px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-in {
  opacity: 0;
  animation: fadeUp 0.6s ease forwards;
}
.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
.delay-4 { animation-delay: 0.4s; }
</style>
</head>
<body>

<!-- HERO -->
<section class="hero">
  <div class="hero-label animate-in">Пресс-кит</div>
  <h1 class="animate-in delay-1">Медиа-материалы</h1>
  <p class="animate-in delay-2">Всё, что нужно для публикации об IQOT: описание компании, ключевые факты, пресс-релизы, скриншоты продукта и логотипы для скачивания.</p>
</section>

<hr class="divider">

<!-- ABOUT -->
<section class="section" id="about">
  <div class="section-label">О компании</div>
  <h2>Готовый текст для публикации</h2>
  <p class="section-description">Краткое описание компании и продукта. Можно использовать как есть или адаптировать под контекст вашего издания.</p>

  <div class="boilerplate-card">
    <div class="boilerplate-text" id="boilerplate-text">
      <strong>IQOT</strong> — ИИ-система интеллектуального сбора и анализа коммерческих предложений для B2B-закупок. Платформа автоматизирует полный цикл работы с поставщиками: от рассылки запросов и ведения переписки до консолидации ответов в сводный отчёт с ценами и рекомендациями.<br><br>
      Система автоматически подбирает релевантных поставщиков, извлекает данные из ответов в любом формате — PDF, Excel, письмо — и самостоятельно отвечает на типовые уточняющие вопросы, консолидируя однотипные запросы от разных поставщиков. Чем дольше компания работает с IQOT, тем меньше вопросов требуют участия человека.<br><br>
      На сегодняшний день платформа прошла наиболее глубокую обкатку в сегменте запасных частей для лифтов и эскалаторов. Архитектура не привязана к конкретной отрасли и готова к масштабированию на другие направления B2B-закупок.<br><br>
      Сайт: <strong>iqot.ru</strong> · Контакт: <strong>info@iqot.ru</strong>
    </div>
    <button class="copy-btn" onclick="copyBoilerplate()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
      <span id="copy-label">Скопировать текст</span>
    </button>
  </div>
</section>

<hr class="divider">

<!-- FACTS -->
<section class="section" id="facts">
  <div class="section-label">Ключевые факты</div>
  <h2>IQOT в цифрах</h2>
  <p class="section-description">Результаты пилотных проектов и ключевые характеристики платформы.</p>

  <div class="facts-grid">
    <div class="fact-card animate-in">
      <div class="fact-value">90%</div>
      <div class="fact-label">Экономия времени на сбор КП</div>
    </div>
    <div class="fact-card animate-in delay-1">
      <div class="fact-value">3 мин</div>
      <div class="fact-label">На создание заявки</div>
    </div>
    <div class="fact-card animate-in delay-2">
      <div class="fact-value">113</div>
      <div class="fact-label">Поставщиков запрошено за раз</div>
    </div>
    <div class="fact-card animate-in delay-3">
      <div class="fact-value">51 КП</div>
      <div class="fact-label">Получено за 2 дня</div>
    </div>
    <div class="fact-card animate-in delay-4">
      <div class="fact-value">92%</div>
      <div class="fact-label">Позиций с полученными ценами</div>
    </div>
    <div class="fact-card animate-in delay-4">
      <div class="fact-value">0</div>
      <div class="fact-label">Ошибок консолидации</div>
    </div>
  </div>
</section>

<hr class="divider">

<!-- PRESS RELEASE -->
<section class="section" id="press-release">
  <div class="section-label">Пресс-релиз</div>
  <h2>Последние новости</h2>

  <div class="press-release-card">
    <div class="pr-date">Февраль 2026</div>
    <div class="pr-title">Внедрить ИИ в работу отдела закупок теперь можно за 5 минут</div>
    <div class="pr-excerpt">
      Компания IQOT объявляет о запуске ИИ-системы интеллектуального сбора и анализа коммерческих предложений для B2B-закупок. Платформа автоматизирует полный цикл: от рассылки запросов и переписки с поставщиками до формирования сводного отчёта с ценами и рекомендациями. В ходе пилотного проекта заявка из 28 позиций была обработана за 2 дня — получено 51 коммерческое предложение от 113 поставщиков.
    </div>
    <div class="pr-actions">
      <button class="btn-outline" onclick="copyPressRelease()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        Скопировать
      </button>
    </div>
  </div>
</section>

<hr class="divider">

<!-- SCREENSHOTS -->
<section class="section" id="screenshots">
  <div class="section-label">Скриншоты</div>
  <h2>Интерфейс платформы</h2>
  <p class="section-description">Скриншоты продукта для использования в публикациях.</p>

  <div class="screenshots-grid">
    @if(file_exists(public_path('press/Отчет-по-заявке-REQ-20260130-9685-IQOT-02-09-2026_03_02_PM.png')))
    <a href="{{ asset('press/Отчет-по-заявке-REQ-20260130-9685-IQOT-02-09-2026_03_02_PM.png') }}" download class="screenshot-card">
      <img src="{{ asset('press/Отчет-по-заявке-REQ-20260130-9685-IQOT-02-09-2026_03_02_PM.png') }}" alt="Сводный отчёт" class="screenshot-img">
      <div class="screenshot-info">
        <div class="screenshot-title">Сводный отчёт по заявке</div>
        <div class="screenshot-desc">Все позиции, все поставщики, лучшие цены подсвечены</div>
      </div>
    </a>
    @endif

    @if(file_exists(public_path('press/Мониторинг-позиций-IQOT-02-09-2026_03_03_PM.png')))
    <a href="{{ asset('press/Мониторинг-позиций-IQOT-02-09-2026_03_03_PM.png') }}" download class="screenshot-card">
      <img src="{{ asset('press/Мониторинг-позиций-IQOT-02-09-2026_03_03_PM.png') }}" alt="Мониторинг позиций" class="screenshot-img">
      <div class="screenshot-info">
        <div class="screenshot-title">Мониторинг позиций</div>
        <div class="screenshot-desc">Отслеживание цен и наличия товаров в реальном времени</div>
      </div>
    </a>
    @endif

    @if(file_exists(public_path('press/2026-02-09_15-00-09.png')))
    <a href="{{ asset('press/2026-02-09_15-00-09.png') }}" download class="screenshot-card">
      <img src="{{ asset('press/2026-02-09_15-00-09.png') }}" alt="Интерфейс платформы" class="screenshot-img">
      <div class="screenshot-info">
        <div class="screenshot-title">Интерфейс платформы</div>
        <div class="screenshot-desc">Рабочее пространство пользователя</div>
      </div>
    </a>
    @endif
  </div>
</section>

<hr class="divider">

<!-- LOGOS -->
<section class="section" id="logos">
  <div class="section-label">Логотипы</div>
  <h2>Брендинг IQOT</h2>
  <p class="section-description">Логотипы для использования в публикациях. Пожалуйста, не изменяйте пропорции, цвета и не добавляйте эффекты.</p>

  <div class="logos-grid">
    <div class="logo-card">
      <div class="logo-preview dark-bg">
        <img src="{{ asset('iqot-logo-full.svg') }}" alt="IQOT — логотип на тёмном фоне">
      </div>
      <div class="logo-info">
        <div class="logo-meta">
          <div class="logo-name">IQOT — тёмный фон</div>
          <div class="logo-format">SVG · Векторный</div>
        </div>
        <a href="{{ asset('iqot-logo-full.svg') }}" download class="download-btn" title="Скачать">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </a>
      </div>
    </div>
    <div class="logo-card">
      <div class="logo-preview dark-bg">
        <img src="{{ asset('iqot-logo-icon.svg') }}" alt="IQOT — иконка">
      </div>
      <div class="logo-info">
        <div class="logo-meta">
          <div class="logo-name">IQOT — иконка (Q)</div>
          <div class="logo-format">SVG · Векторный</div>
        </div>
        <a href="{{ asset('iqot-logo-icon.svg') }}" download class="download-btn" title="Скачать">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </a>
      </div>
    </div>
    <div class="logo-card">
      <div class="logo-preview light-bg">
        <img src="{{ asset('iqot-logo-full.svg') }}" alt="IQOT — логотип на светлом фоне" style="filter: brightness(0);">
      </div>
      <div class="logo-info">
        <div class="logo-meta">
          <div class="logo-name">IQOT — светлый фон</div>
          <div class="logo-format">Инвертированный · для светлых фонов</div>
        </div>
        <a href="{{ asset('iqot-logo-full.svg') }}" download class="download-btn" title="Скачать">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </a>
      </div>
    </div>
  </div>
</section>

<hr class="divider">

<!-- CONTACT -->
<section class="section" id="contact">
  <div class="contact-card">
    <h3>Контакты для прессы</h3>
    <p>Для запроса комментариев, интервью или дополнительных материалов</p>
    <a href="mailto:info@iqot.ru" class="contact-email">info@iqot.ru</a>
  </div>
</section>

<script>
function copyBoilerplate() {
  const text = document.getElementById('boilerplate-text').innerText;
  navigator.clipboard.writeText(text).then(() => {
    const label = document.getElementById('copy-label');
    const btn = label.closest('.copy-btn');
    label.textContent = 'Скопировано ✓';
    btn.classList.add('copied');
    setTimeout(() => {
      label.textContent = 'Скопировать текст';
      btn.classList.remove('copied');
    }, 2000);
  });
}

function copyPressRelease() {
  const excerpt = document.querySelector('.pr-excerpt').innerText;
  navigator.clipboard.writeText(excerpt).then(() => {
    const btn = event.target.closest('.btn-outline');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Скопировано';
    setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
  });
}

// Intersection Observer for scroll animations
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      entry.target.style.transform = 'translateY(0)';
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.animate-in').forEach(el => observer.observe(el));
</script>

</body>
</html>
