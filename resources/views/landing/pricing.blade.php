<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тарифы и оплата — IQOT</title>
    <meta name="description" content="Тарифные планы IQOT: Стартовый, Стандарт и Профессионал. Гибкая оплата за заявки, прозрачные цены. Начните с бесплатного тестового периода.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f1117;
            --bg-secondary: #0a0c10;
            --bg-card: #161a22;
            --accent-primary: #10b981;
            --accent-secondary: #34d399;
            --accent-gradient: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            --accent-gradient-subtle: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(52, 211, 153, 0.15) 100%);
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --border-color: rgba(255, 255, 255, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.8;
        }

        /* Navigation */
        .nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 1rem 2rem;
            background: rgba(10, 12, 16, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
        }

        .logo-text {
            height: 14px;
        }

        .logo-text img {
            height: 100%;
            width: auto;
        }

        .nav-links {
            display: flex;
            gap: 2.5rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--text-primary);
        }

        .nav-btn {
            padding: 0.75rem 1.75rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s;
        }

        .nav-btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .nav-btn-primary {
            background: var(--accent-gradient);
            color: white !important;
        }

        .nav-btn-primary:hover {
            color: white !important;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
            margin-top: 80px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .page-title {
            color: var(--text-primary);
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 800;
        }

        .page-subtitle {
            font-size: 1.25rem;
            color: var(--text-secondary);
        }

        .section {
            margin-bottom: 5rem;
        }

        .section-title {
            color: var(--text-primary);
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .pricing-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .pricing-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .pricing-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 0 80px rgba(16, 185, 129, 0.12);
        }

        .pricing-card-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .pricing-card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .pricing-card-price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--accent-primary);
            font-family: 'JetBrains Mono', monospace;
        }

        .pricing-card-body {
            padding: 2rem;
        }

        .pricing-includes {
            margin-bottom: 1.5rem;
        }

        .pricing-includes-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
        }

        .pricing-includes-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .pricing-includes-list li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
        }

        .pricing-includes-list li::before {
            content: '•';
            color: var(--accent-primary);
            font-weight: 700;
            font-size: 1.25rem;
        }

        .pricing-table {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
        }

        .pricing-table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: rgba(16, 185, 129, 0.05);
        }

        th, td {
            padding: 1.5rem 1.25rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.95rem;
        }

        td {
            color: var(--text-secondary);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: rgba(16, 185, 129, 0.02);
        }

        .price-value {
            color: var(--accent-primary);
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .highlight-row {
            background: var(--accent-gradient-subtle);
        }

        .info-note {
            background: rgba(16, 185, 129, 0.05);
            border-left: 3px solid var(--accent-primary);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .balance-info {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .balance-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }

        .balance-text {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .cta-block {
            background: var(--accent-gradient);
            border-radius: 24px;
            padding: 4rem 3rem;
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--bg-primary);
            margin-bottom: 1rem;
        }

        .cta-subtitle {
            font-size: 1.25rem;
            color: rgba(15, 17, 23, 0.8);
            margin-bottom: 2.5rem;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary, .btn-secondary {
            padding: 1rem 2.5rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-primary {
            background: var(--bg-primary);
            color: var(--accent-primary);
            border: 2px solid var(--bg-primary);
        }

        .btn-primary:hover {
            background: transparent;
            color: var(--bg-primary);
            border-color: var(--bg-primary);
        }

        .btn-secondary {
            background: transparent;
            color: var(--bg-primary);
            border: 2px solid var(--bg-primary);
        }

        .btn-secondary:hover {
            background: var(--bg-primary);
            color: var(--accent-primary);
        }

        /* Footer */
        .footer {
            background: #08080c;
            border-top: 1px solid var(--border-color);
            padding: 48px 24px 32px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-main {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 48px;
            padding-bottom: 40px;
            border-bottom: 1px solid var(--border-color);
        }

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
            fill: var(--accent-primary);
        }

        .footer-logo span {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .footer-tagline {
            font-size: 14px;
            color: #58585f;
            line-height: 1.6;
            max-width: 280px;
        }

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
            color: var(--text-primary);
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links a {
            font-size: 14px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--text-primary);
        }

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
            .container {
                padding: 2rem 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .pricing-cards {
                grid-template-columns: 1fr;
            }

            .pricing-table-wrapper {
                overflow-x: scroll;
            }

            table {
                min-width: 800px;
            }

            .cta-block {
                padding: 2.5rem 1.5rem;
            }

            .cta-title {
                font-size: 1.75rem;
            }

            .cta-subtitle {
                font-size: 1rem;
                margin-bottom: 1.5rem;
            }

            .cta-buttons {
                flex-direction: column;
            }

            .btn-primary, .btn-secondary {
                width: 100%;
                padding: 0.875rem 1.5rem;
                font-size: 1rem;
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
        }
    </style>

    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=106418920', 'ym');

        ym(106418920, 'init', {ssr:true, webvisor:true, trackHash:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/106418920" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
</head>
<body>
    <!-- Navigation -->
    <nav class="nav">
        <div class="nav-container">
            <a href="/" class="logo">
                <div class="logo-icon"><img src="/images/Q.svg" alt="IQOT"></div>
                <div class="logo-text"><img src="/images/IQOT.svg" alt="IQOT"></div>
            </a>
            <div class="nav-links">
                <a href="/">Главная</a>
                <a href="{{ route('catalog.index') }}">Каталог</a>
                <a href="{{ route('pricing') }}">Тарифы</a>
                @auth
                    <a href="{{ route('cabinet.dashboard') }}" class="nav-btn nav-btn-outline">Личный кабинет</a>
                @else
                    <a href="{{ route('login') }}" class="nav-btn nav-btn-outline">Войти</a>
                    <a href="{{ route('register') }}" class="nav-btn nav-btn-primary">Регистрация</a>
                @endauth
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Тарифы и оплата</h1>
            <p class="page-subtitle">Прозрачные условия без скрытых платежей</p>
        </div>

        <!-- Разовая оплата -->
        <section class="section">
            <h2 class="section-title">Разовая оплата (Pay-as-you-go)</h2>
            <p class="section-subtitle">Подходит для нерегулярных закупок и тестирования сервиса</p>

            <div class="pricing-cards">
                <div class="pricing-card">
                    <div class="pricing-card-header">
                        <div class="pricing-card-title">Мониторинг позиции в заявке</div>
                        <div class="pricing-card-price">{{ number_format($pricing['monitoring'], 0, ',', ' ') }} ₽</div>
                    </div>
                    <div class="pricing-card-body">
                        <div class="pricing-includes">
                            <div class="pricing-includes-title">Включает:</div>
                            <ul class="pricing-includes-list">
                                <li>Структурирование позиции</li>
                                <li>Подбор поставщиков</li>
                                <li>Рассылку запросов</li>
                                <li>Сбор и обработку ответов</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="pricing-card">
                    <div class="pricing-card-header">
                        <div class="pricing-card-title">Разблокировка отчёта по позиции</div>
                        <div class="pricing-card-price">{{ number_format($pricing['report_unlock'], 0, ',', ' ') }} ₽</div>
                    </div>
                    <div class="pricing-card-body">
                        <div class="pricing-includes">
                            <div class="pricing-includes-title">Включает:</div>
                            <ul class="pricing-includes-list">
                                <li>Доступ к уже готовому отчёту</li>
                                <li>Без повторного запроса рынка</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="info-note">
                <strong>Важно:</strong> Оплата списывается только после обработки позиции.
            </div>
        </section>

        <!-- Стартовый баланс -->
        <section class="section">
            <h2 class="section-title">Стартовый баланс</h2>

            <div class="balance-info">
                <div class="balance-title">Тестирование без оплаты</div>
                <div class="balance-text">
                    При регистрации на счёт зачисляется стартовый баланс, позволяющий протестировать работу системы без оплаты.
                    <br><br>
                    <em>Размер стартового баланса может изменяться.</em>
                </div>
            </div>
        </section>

        <!-- Подписки -->
        <section class="section">
            <h2 class="section-title">Подписки для постоянных заказчиков</h2>
            <p class="section-subtitle">Подписка снижает стоимость обработки позиций и подходит для регулярных закупок</p>

            <div class="pricing-table">
                <div class="pricing-table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Тариф</th>
                                <th>Стоимость в месяц</th>
                                <th>Включено позиций в заявках</th>
                                <th>Включено отчётов</th>
                                <th>Стоимость сверх лимита</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Базовый</strong></td>
                                <td><span class="price-value">{{ number_format($pricing['subscription_basic']['price'], 0, ',', ' ') }} ₽</span></td>
                                <td>{{ $pricing['subscription_basic']['positions'] }} позиций</td>
                                <td>{{ $pricing['subscription_basic']['reports'] }} отчётов</td>
                                <td>
                                    Мониторинг: <span class="price-value">{{ number_format($pricing['subscription_basic']['overlimit_position'], 0, ',', ' ') }} ₽</span><br>
                                    Отчёт: <span class="price-value">{{ number_format($pricing['subscription_basic']['overlimit_report'], 0, ',', ' ') }} ₽</span>
                                </td>
                            </tr>
                            <tr class="highlight-row">
                                <td><strong>Расширенный</strong></td>
                                <td><span class="price-value">{{ number_format($pricing['subscription_advanced']['price'], 0, ',', ' ') }} ₽</span></td>
                                <td>{{ $pricing['subscription_advanced']['positions'] }} позиций</td>
                                <td>{{ $pricing['subscription_advanced']['reports'] }} отчётов</td>
                                <td>
                                    Мониторинг: <span class="price-value">{{ number_format($pricing['subscription_advanced']['overlimit_position'], 0, ',', ' ') }} ₽</span><br>
                                    Отчёт: <span class="price-value">{{ number_format($pricing['subscription_advanced']['overlimit_report'], 0, ',', ' ') }} ₽</span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Профессиональный</strong></td>
                                <td><span class="price-value">{{ number_format($pricing['subscription_pro']['price'], 0, ',', ' ') }} ₽</span></td>
                                <td>{{ $pricing['subscription_pro']['positions'] }} позиций</td>
                                <td>{{ $pricing['subscription_pro']['reports'] }} отчётов</td>
                                <td>
                                    Мониторинг: <span class="price-value">{{ number_format($pricing['subscription_pro']['overlimit_position'], 0, ',', ' ') }} ₽</span><br>
                                    Отчёт: <span class="price-value">{{ number_format($pricing['subscription_pro']['overlimit_report'], 0, ',', ' ') }} ₽</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="info-note">
                <strong>Обратите внимание:</strong> Лимиты обновляются ежемесячно. Неиспользованные лимиты не переносятся на следующий месяц. При превышении лимитов списываются средства по тарифу "сверх лимита".
            </div>
        </section>

        <!-- Финальный CTA -->
        <section class="section">
            <div class="cta-block">
                <h2 class="cta-title">Готовы начать с реальной заявки?</h2>
                <p class="cta-subtitle">Используйте стартовый баланс и оцените результат на своей закупке.</p>
                <div class="cta-buttons">
                    <a href="/cabinet/my/requests/create" class="btn-primary">Создать заявку</a>
                    <a href="https://t.me/iqot_support" target="_blank" class="btn-secondary">Написать в Telegram</a>
                </div>
            </div>
        </section>
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
