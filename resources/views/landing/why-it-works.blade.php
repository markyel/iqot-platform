<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Почему это работает — IQOT</title>
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

        /* Sections */
        .section {
            margin-bottom: 56px;
        }

        .section-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--accent);
            margin-bottom: 12px;
        }

        .section h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-white);
            margin-bottom: 20px;
            letter-spacing: -0.01em;
        }

        .section p {
            font-size: 16px;
            line-height: 1.8;
            color: var(--text-gray);
        }

        /* Steps */
        .steps {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 28px;
        }

        .step {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            gap: 20px;
        }

        .step-num {
            width: 40px;
            height: 40px;
            background: var(--accent);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }

        .step-content h3 {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-white);
            margin-bottom: 8px;
        }

        .step-content p {
            font-size: 15px;
            line-height: 1.7;
            color: var(--text-gray);
        }

        /* Why Grid */
        .why-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 28px;
        }

        .why-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
        }

        .why-card h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-white);
            margin-bottom: 10px;
        }

        .why-card p {
            font-size: 14px;
            line-height: 1.7;
            color: var(--text-muted);
        }

        /* Economics Box */
        .economics-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            margin-top: 28px;
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .economics-num {
            text-align: center;
        }

        .economics-num .value {
            font-size: 64px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }

        .economics-num .unit {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-gray);
        }

        .economics-text h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-white);
            margin-bottom: 12px;
        }

        .economics-text p {
            font-size: 15px;
            line-height: 1.7;
            color: var(--text-gray);
        }

        .economics-details {
            display: flex;
            gap: 16px;
            margin-top: 16px;
        }

        .economics-item {
            padding: 10px 14px;
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            font-size: 14px;
        }

        .economics-item strong {
            color: var(--accent);
        }

        /* Results */
        .results-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 28px;
        }

        .result-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .result-card .value {
            font-size: 32px;
            font-weight: 800;
            color: var(--accent);
            line-height: 1;
            margin-bottom: 8px;
        }

        .result-card .label {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* Audience */
        .audience-list {
            list-style: none;
            margin-top: 20px;
        }

        .audience-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
            font-size: 15px;
            color: var(--text-gray);
        }

        .audience-list li:last-child {
            border-bottom: none;
        }

        .audience-list li svg {
            width: 20px;
            height: 20px;
            stroke: var(--accent);
            fill: none;
            stroke-width: 2.5;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* CTA Section */
        .cta-section {
            margin-top: 80px;
            margin-bottom: 60px;
            padding: 48px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            text-align: center;
        }

        .cta-section h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-white);
            margin-bottom: 16px;
        }

        .cta-section p {
            font-size: 16px;
            color: var(--text-gray);
            margin-bottom: 32px;
        }

        .cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2.5rem;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .cta-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.35);
        }

        /* Footer */
        .footer {
            background: var(--bg-card);
            padding: 3rem 2rem;
            border-top: 1px solid var(--border);
        }

        .footer-container {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .footer-brand {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .footer-tagline {
            font-size: 0.85rem;
            color: var(--text-gray);
            font-style: italic;
        }

        .footer-copyright {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .footer-links {
            display: flex;
            gap: 2rem;
        }

        .footer-links a {
            color: var(--text-gray);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: var(--accent);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-title h1 {
                font-size: 32px;
            }

            .why-grid {
                grid-template-columns: 1fr;
            }

            .results-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .economics-box {
                flex-direction: column;
                text-align: center;
            }

            .economics-details {
                justify-content: center;
                flex-wrap: wrap;
            }

            .step {
                flex-direction: column;
                gap: 16px;
            }

            .footer-container {
                flex-direction: column;
                gap: 1.5rem;
                text-align: center;
            }

            .footer-left {
                flex-direction: column;
            }

            .footer-links {
                flex-direction: column;
                gap: 1rem;
            }

            .cta-section {
                padding: 32px 24px;
            }

            .cta-section h2 {
                font-size: 24px;
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
            <h1>Почему это <span>работает</span>?</h1>
        </div>

        <!-- Problem -->
        <section class="section">
            <div class="section-label">Проблема</div>
            <h2>Проблема, которую мы решаем</h2>
            <p>Сбор коммерческих предложений в B2B — это рутина, съедающая часы рабочего времени. Менеджер вручную составляет запросы, ищет поставщиков, рассылает письма десяткам контрагентов, ждёт ответы в разных форматах, отвечает на уточняющие вопросы, переносит цены в таблицы, сравнивает условия.</p>
            <br>
            <p>На одну заявку из 10 позиций для 100 поставщиков может уйти несколько дней работы. При этом часть ответов теряется в почте, а ошибки при ручном переносе данных приводят к неверным решениям.</p>
        </section>

        <!-- How it works -->
        <section class="section">
            <div class="section-label">Решение</div>
            <h2>Как IQOT меняет процесс</h2>

            <div class="steps">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <h3>Умный подбор поставщиков</h3>
                        <p>Вы загружаете список позиций. Для каждой позиции система автоматически определяет тематику и подбирает релевантный пул поставщиков. IQOT постоянно обучается: встречая новую номенклатуру, система расширяет список товарных категорий и находит соответствующих поставщиков.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <h3>ИИ читает любые ответы</h3>
                        <p>Поставщики отвечают как им удобно: кто-то присылает PDF с печатью, кто-то — Excel-таблицу, кто-то пишет цены прямо в теле письма. Наш ИИ извлекает данные из любого формата, распознаёт позиции, сопоставляет с вашим запросом и структурирует информацию.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-content">
                        <h3>Автоматическая переписка с поставщиками</h3>
                        <p>Если поставщик задаёт уточняющие вопросы, система анализирует их и отвечает самостоятельно, когда это возможно. Если вопрос требует участия заказчика — он будет адресован вам. Но получив ответ однажды, система запоминает его и в будущем ответит сама.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-num">4</div>
                    <div class="step-content">
                        <h3>Готовый отчёт для принятия решений</h3>
                        <p>На выходе вы получаете сводную таблицу: все позиции, все поставщики, все цены — в едином формате. Лучшие предложения подсвечены. Вы видите полную картину и можете принять решение за минуты, а не за дни.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Why this approach -->
        <section class="section">
            <div class="section-label">Подход</div>
            <h2>Почему именно так</h2>

            <div class="why-grid">
                <div class="why-card">
                    <h3>Не заменяем людей — убираем рутину</h3>
                    <p>IQOT не принимает решения за вас. Система берёт на себя механическую работу: поиск поставщиков, отправку писем, чтение ответов, ведение переписки. Экспертиза и финальный выбор остаются за специалистом.</p>
                </div>

                <div class="why-card">
                    <h3>Работает с вашими поставщиками</h3>
                    <p>Вам не нужно переводить поставщиков на новую платформу или просить их менять формат ответов. IQOT подстраивается под существующие процессы.</p>
                </div>

                <div class="why-card">
                    <h3>Контроль там, где нужно</h3>
                    <p>Вы видите кто из поставщиков ответил и какие вопросы требуют вашего участия. Система не перегружает вас лишней информацией.</p>
                </div>

                <div class="why-card">
                    <h3>Система обучается</h3>
                    <p>Чем дольше вы работаете с IQOT, тем меньше вопросов требуют вашего внимания. Система запоминает ответы и использует их в будущем.</p>
                </div>
            </div>

            <!-- Economics highlight -->
            <div class="economics-box">
                <div class="economics-num">
                    <div class="value">3</div>
                    <div class="unit">минуты</div>
                </div>
                <div class="economics-text">
                    <h3>Экономика времени</h3>
                    <p>Заявка из 10 позиций для 100 поставщиков. Ответить на уточняющие вопросы, консолидировать ответы. Сколько это займёт у менеджера? День? Два?</p>
                    <div class="economics-details">
                        <div class="economics-item"><strong>2 мин</strong> — создание заявки</div>
                        <div class="economics-item"><strong>1-5 дн</strong> — готовый отчёт</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Results -->
        <section class="section">
            <div class="section-label">Результаты</div>
            <h2>Что вы получаете</h2>

            <div class="results-grid">
                <div class="result-card">
                    <div class="value">90%</div>
                    <div class="label">экономия времени</div>
                </div>
                <div class="result-card">
                    <div class="value">3 мин</div>
                    <div class="label">на заявку</div>
                </div>
                <div class="result-card">
                    <div class="value">50+</div>
                    <div class="label">поставщиков</div>
                </div>
                <div class="result-card">
                    <div class="value">0</div>
                    <div class="label">потерянных ответов</div>
                </div>
            </div>
        </section>

        <!-- Audience -->
        <section class="section">
            <div class="section-label">Для кого</div>
            <h2>Кому подходит IQOT</h2>
            <p>IQOT создан для B2B-компаний, которые:</p>

            <ul class="audience-list">
                <li>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Регулярно закупают товары и материалы у множества поставщиков
                </li>
                <li>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Тратят значительное время на сбор и сравнение цен
                </li>
                <li>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Хотят принимать решения на основе полных данных, а не выборочных
                </li>
                <li>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Ценят время своих сотрудников и готовы автоматизировать рутину
                </li>
            </ul>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <h2>Готовы попробовать?</h2>
            <p>Создайте демо-заявку и убедитесь, как IQOT упрощает сбор коммерческих предложений</p>
            <a href="{{ route('register') }}" class="cta-btn">
                Попробовать бесплатно
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </a>
        </section>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-left">
                <a href="/" style="display: flex; align-items: center; gap: 1rem; text-decoration: none;">
                    <div style="width: 40px; height: 40px;"><img src="/images/Q.svg" alt="IQOT" style="width: 100%; height: 100%;"></div>
                    <div style="height: 14px;"><img src="/images/IQOT.svg" alt="IQOT" style="height: 100%; width: auto;"></div>
                </a>
                <div class="footer-brand">
                    <span class="footer-tagline">Intelligent Quotation & Offer Tracking</span>
                    <span class="footer-copyright">© 2025 IQOT. Все права защищены</span>
                </div>
            </div>
            <div class="footer-links">
                <a href="mailto:info@iqot.ru">Связаться</a>
                <a href="/terms">Условия использования</a>
                <a href="/privacy">Политика конфиденциальности</a>
                <a href="/contract">Договор-оферта</a>
            </div>
        </div>
    </footer>
</body>
</html>
