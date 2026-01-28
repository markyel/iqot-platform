<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FAQ — IQOT</title>
  <meta name="description" content="Часто задаваемые вопросы о системе автоматизации закупок IQOT. Ответы на вопросы о работе с платформой, тарифах, безопасности и интеграции.">
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-dark: #0a0a0f;
      --bg-card: #12121a;
      --text-white: #ffffff;
      --text-gray: #9898a0;
      --text-muted: #68686f;
      --accent: #10b981;
      --accent-light: #34d399;
      --border: rgba(255, 255, 255, 0.06);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Inter', -apple-system, sans-serif;
      font-size: 16px;
      line-height: 1.7;
      color: var(--text-gray);
      background: var(--bg-dark);
    }

    .container {
      max-width: 800px;
      margin: 0 auto;
      padding: 60px 24px;
    }

    /* Page Title */
    .page-title {
      text-align: center;
      margin-bottom: 60px;
    }

    .page-title h1 {
      font-size: 42px;
      font-weight: 800;
      color: var(--text-white);
      letter-spacing: -0.02em;
      margin-bottom: 16px;
    }

    .page-title h1 span {
      background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .page-title p {
      font-size: 18px;
      color: var(--text-gray);
    }

    /* FAQ */
    .faq-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .faq-item {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 28px;
    }

    .faq-item h3 {
      font-size: 17px;
      font-weight: 600;
      color: var(--text-white);
      margin-bottom: 14px;
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }

    .faq-item h3::before {
      content: 'Q';
      display: flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      background: var(--accent);
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      color: white;
      flex-shrink: 0;
    }

    .faq-item p {
      font-size: 15px;
      line-height: 1.8;
      color: var(--text-gray);
      padding-left: 42px;
    }

    /* Footer Note */
    .footer-note {
      margin-top: 60px;
      padding-top: 32px;
      border-top: 1px solid var(--border);
      text-align: center;
      font-size: 14px;
      color: var(--text-muted);
    }

    .footer-note a {
      color: var(--accent);
      text-decoration: none;
    }

    .footer-note a:hover {
      text-decoration: underline;
    }

    /* Footer */
    .footer {
      background: #08080c;
      border-top: 1px solid var(--border);
      padding: 48px 24px 32px;
    }

    .footer-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    /* Main footer content */
    .footer-main {
      display: grid;
      grid-template-columns: 1.5fr 1fr 1fr 1fr;
      gap: 48px;
      padding-bottom: 40px;
      border-bottom: 1px solid var(--border);
    }

    /* Brand column */
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
      fill: var(--accent);
    }

    .footer-logo span {
      font-size: 18px;
      font-weight: 700;
      color: var(--text-white);
    }

    .footer-tagline {
      font-size: 14px;
      color: #58585f;
      line-height: 1.6;
      max-width: 280px;
    }

    /* Link columns */
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
      color: var(--text-white);
    }

    .footer-links {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .footer-links a {
      font-size: 14px;
      color: var(--text-gray);
      text-decoration: none;
      transition: color 0.2s;
    }

    .footer-links a:hover {
      color: var(--text-white);
    }

    /* Bottom bar */
    .footer-bottom {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-top: 24px;
      flex-wrap: wrap;
      gap: 16px;
    }

    .footer-copy {
      font-size: 13px;
      color: #58585f;
    }

    /* Responsive */
    @media (max-width: 900px) {
      .footer-main {
        grid-template-columns: 1fr 1fr;
        gap: 40px;
      }

      .footer-brand {
        grid-column: 1 / -1;
      }
    }

    @media (max-width: 768px) {
      .page-title h1 {
        font-size: 32px;
      }

      .faq-item {
        padding: 20px;
      }

      .faq-item h3 {
        font-size: 16px;
      }

      .faq-item p {
        padding-left: 0;
        margin-top: 12px;
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
      }

      .footer-bottom {
        flex-direction: column;
        text-align: center;
      }

      .footer-bottom-links {
        flex-wrap: wrap;
        justify-content: center;
      }
    }
  </style>

  <!-- Яндекс.Метрика -->
  <script type="text/javascript" >
     (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
     m[i].l=1*new Date();
     for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
     k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
     (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

     ym(99334173, "init", {
          clickmap:true,
          trackLinks:true,
          accurateTrackBounce:true,
          webvisor:true
     });
  </script>
  <noscript><div><img src="https://mc.yandex.ru/watch/99334173" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
  <!-- /Яндекс.Метрика -->
</head>
<body>
  <!-- Navigation -->
  <nav style="position: fixed; top: 0; left: 0; right: 0; z-index: 100; padding: 1rem 2rem; background: rgba(10, 10, 15, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border);">
    <div style="max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center;">
      <a href="/" style="display: flex; align-items: center; gap: 1rem; text-decoration: none;">
        <div style="width: 40px; height: 40px;"><img src="/images/Q.svg" alt="IQOT" style="width: 100%; height: 100%;"></div>
        <div style="height: 14px;"><img src="/images/IQOT.svg" alt="IQOT" style="height: 100%; width: auto;"></div>
      </a>
      <div style="display: flex; gap: 2.5rem; align-items: center;">
        <a href="/" style="color: var(--text-gray); text-decoration: none; font-weight: 500; font-size: 0.95rem; transition: color 0.2s;">Главная</a>
        <a href="{{ route('catalog.index') }}" style="color: var(--text-gray); text-decoration: none; font-weight: 500; font-size: 0.95rem; transition: color 0.2s;">Каталог</a>
        <a href="{{ route('pricing') }}" style="color: var(--text-gray); text-decoration: none; font-weight: 500; font-size: 0.95rem; transition: color 0.2s;">Тарифы</a>
        @auth
          <a href="{{ route('cabinet.dashboard') }}" style="padding: 0.75rem 1.75rem; border-radius: 10px; background: transparent; border: 1px solid var(--border); color: var(--text-white); text-decoration: none; font-weight: 600; font-size: 0.95rem;">Личный кабинет</a>
        @else
          <a href="{{ route('login') }}" style="padding: 0.75rem 1.75rem; border-radius: 10px; background: transparent; border: 1px solid var(--border); color: var(--text-white); text-decoration: none; font-weight: 600; font-size: 0.95rem;">Войти</a>
          <a href="{{ route('register') }}" style="padding: 0.75rem 1.75rem; border-radius: 10px; background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%); color: white; text-decoration: none; font-weight: 600; font-size: 0.95rem;">Регистрация</a>
        @endauth
      </div>
    </div>
  </nav>

  <div class="container" style="margin-top: 80px;">
    <!-- Title -->
    <div class="page-title">
      <h1>Частые <span>вопросы</span></h1>
      <p>Ответы на основные вопросы о работе IQOT</p>
    </div>

    <!-- FAQ List -->
    <div class="faq-list">
      <div class="faq-item">
        <h3>Как начать пользоваться IQOT?</h3>
        <p>Зарегистрируйтесь на сайте и отправьте первую заявку — список позиций с названиями, артикулами и количеством. Никаких интеграций и настроек не требуется — система готова к работе сразу.</p>
      </div>

      <div class="faq-item">
        <h3>Откуда берутся поставщики?</h3>
        <p>IQOT постоянно пополняет базу поставщиков. Для каждой позиции, которая проходит через систему, выполняется поиск через Яндекс. Если находится поставщик, о котором IQOT ещё не знает — он автоматически добавляется в базу.</p>
      </div>

      <div class="faq-item">
        <h3>Можно ли использовать свою базу поставщиков?</h3>
        <p>IQOT — полностью автоматизированная система, которая сама находит и добавляет поставщиков. Загрузка собственной базы не предусмотрена и не требуется. Но если вы хотите, чтобы в системе были конкретные поставщики — свяжитесь с нами, укажите email, сайт и категории оборудования, и мы их добавим.</p>
      </div>

      <div class="faq-item">
        <h3>Что если поставщик ответит не на почту, а позвонит?</h3>
        <p>В письме поставщику указаны ваши контактные данные. Как правило, поставщики звонят для уточнения технических деталей по заявке. После звонка они обычно направляют коммерческое предложение в ответ на письмо, и система его обработает.</p>
      </div>

      <div class="faq-item">
        <h3>Насколько точно ИИ распознаёт цены из PDF/Excel?</h3>
        <p>Точность распознавания — наш основной приоритет. В отчёт включаются только те ответы, в корректном распознавании которых система уверена. Сомнительные данные не попадают в итоговый отчёт.</p>
      </div>

      <div class="faq-item">
        <h3>Как система понимает, какой поставщик подходит для позиции?</h3>
        <p>IQOT анализирует категорию товара, производителя, артикул и сопоставляет с профилем поставщика — какими товарами он торгует, на какие запросы отвечал раньше. Система обучается: чем больше заявок обработано, тем точнее подбор.</p>
      </div>

      <div class="faq-item">
        <h3>Есть ли пробный период?</h3>
        <p>При регистрации на ваш баланс зачисляется стартовая сумма. Её достаточно, чтобы создать одну пробную заявку и открыть один готовый отчёт — этого хватит, чтобы оценить работу системы на ваших позициях.</p>
      </div>

      <div class="faq-item">
        <h3>В каком формате получу отчёт?</h3>
        <p>Отчёт доступен в веб-интерфейсе — сводная таблица с ценами, сроками и сравнением поставщиков. По каждой позиции есть детализация: кто ответил, какие цены, какие условия. На платных тарифах доступен экспорт в PDF.</p>
      </div>
    </div>

    <!-- Footer Note -->
    <div class="footer-note">
      Остались вопросы? Напишите нам — <a href="mailto:info@iqot.ru">info@iqot.ru</a>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-container">

      <!-- Main content -->
      <div class="footer-main">

        <!-- Brand -->
        <div class="footer-brand">
          <div class="footer-logo">
            <svg viewBox="0 0 119.81 119.81" xmlns="http://www.w3.org/2000/svg">
              <path d="M59.9,0C26.82,0,0,26.82,0,59.9s26.82,59.9,59.9,59.9,59.9-26.82,59.9-59.9S92.99,0,59.9,0ZM22.91,60.5c0-20.43,16.56-36.99,36.99-36.99s36.99,16.56,36.99,36.99c0,9.7-3.74,18.52-9.84,25.12l-17.05-15.39-3.36,3.73,16.75,15.12c-6.39,5.26-14.57,8.41-23.49,8.41-20.43,0-36.99-16.56-36.99-36.99Z"/>
            </svg>
            <span>IQOT</span>
          </div>
          <p class="footer-tagline">
            Интеллектуальный сбор и анализ коммерческих предложений для B2B-закупок
          </p>
        </div>

        <!-- Column: Информация -->
        <div class="footer-column">
          <div class="footer-column-title">Информация</div>
          <div class="footer-links">
            <a href="{{ route('faq') }}">Частые вопросы</a>
            <a href="{{ route('why-it-works') }}">Как это работает</a>
            <a href="{{ route('pricing') }}">Тарифы</a>
          </div>
        </div>

        <!-- Column: Юридическое -->
        <div class="footer-column">
          <div class="footer-column-title">Юридическое</div>
          <div class="footer-links">
            <a href="/terms">Условия использования</a>
            <a href="/privacy">Политика конфиденциальности</a>
            <a href="/contract">Договор-оферта</a>
          </div>
        </div>

        <!-- Column: Контакты -->
        <div class="footer-column">
          <div class="footer-column-title">Контакты</div>
          <div class="footer-links">
            <a href="mailto:info@iqot.ru">info@iqot.ru</a>
            <a href="https://t.me/iqot_support">Telegram</a>
          </div>
        </div>

      </div>

      <!-- Bottom bar -->
      <div class="footer-bottom">
        <div class="footer-copy">© 2025 IQOT. Все права защищены</div>
      </div>

    </div>
  </footer>
</body>
</html>
