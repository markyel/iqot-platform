<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Почему это работает — IQOT</title>
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

        .header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            text-decoration: none;
        }

        .back-link {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: var(--accent-secondary);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        h1 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 3rem;
            text-align: center;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: 4rem;
            margin-bottom: 1.5rem;
        }

        h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: 3rem;
            margin-bottom: 1rem;
        }

        p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        .section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2.5rem;
            margin-bottom: 2rem;
        }

        .highlight-box {
            background: var(--accent-gradient-subtle);
            border-left: 4px solid var(--accent-primary);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin: 2rem 0;
        }

        .highlight-box p:last-child {
            margin-bottom: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        ul {
            list-style: none;
            margin: 1.5rem 0;
        }

        ul li {
            padding-left: 2rem;
            position: relative;
            margin-bottom: 1rem;
        }

        ul li::before {
            content: "→";
            color: var(--accent-primary);
            font-weight: 700;
            position: absolute;
            left: 0;
        }

        .cta-section {
            background: var(--accent-gradient);
            border-radius: 1rem;
            padding: 3rem;
            text-align: center;
            margin-top: 4rem;
        }

        .cta-section p {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            h3 {
                font-size: 1.25rem;
            }

            .section {
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <a href="/" class="logo">IQOT</a>
            <a href="/" class="back-link">← На главную</a>
        </div>
    </header>

    <main class="container">
        <h1>Почему это работает?</h1>

        <div class="section">
            <h2>Проблема, которую мы решаем</h2>
            <p>Сбор коммерческих предложений в B2B — это рутина, съедающая часы рабочего времени. Менеджер вручную составляет запросы, ищет поставщиков, рассылает письма десяткам контрагентов, ждёт ответы в разных форматах, отвечает на уточняющие вопросы, переносит цены в таблицы, сравнивает условия. На одну заявку из 10 позиций для 100 поставщиков может уйти несколько дней работы. При этом часть ответов теряется в почте, а ошибки при ручном переносе данных приводят к неверным решениям.</p>
        </div>

        <h2>Как IQOT меняет процесс</h2>

        <div class="section">
            <h3>1. Умный подбор поставщиков</h3>
            <p>Вы загружаете список позиций. Для каждой позиции система автоматически определяет тематику и подбирает релевантный пул поставщиков. IQOT постоянно обучается: встречая новую номенклатуру, система расширяет список товарных категорий и находит соответствующих поставщиков.</p>
        </div>

        <div class="section">
            <h3>2. ИИ читает любые ответы</h3>
            <p>Поставщики отвечают как им удобно: кто-то присылает PDF с печатью, кто-то — Excel-таблицу, кто-то пишет цены прямо в теле письма. Наш ИИ извлекает данные из любого формата, распознаёт позиции, сопоставляет с вашим запросом и структурирует информацию.</p>
        </div>

        <div class="section">
            <h3>3. Автоматическая переписка с поставщиками</h3>
            <p>Если поставщик задаёт уточняющие вопросы, система анализирует их и отвечает самостоятельно, когда это возможно. Если вопрос требует участия заказчика — он будет адресован вам. Но получив ответ однажды, система запоминает его и в будущем ответит сама. Чем дольше вы работаете с IQOT, тем меньше вопросов требуют вашего внимания.</p>
        </div>

        <div class="section">
            <h3>4. Готовый отчёт для принятия решений</h3>
            <p>На выходе вы получаете сводную таблицу: все позиции, все поставщики, все цены — в едином формате. Лучшие предложения подсвечены. Вы видите полную картину и можете принять решение за минуты, а не за дни.</p>
        </div>

        <h2>Почему именно такой подход</h2>

        <div class="section">
            <h3>Мы не заменяем людей — мы убираем рутину</h3>
            <p>IQOT не принимает решения за вас. Система берёт на себя механическую работу: поиск поставщиков, отправку писем, чтение ответов, ведение переписки, заполнение таблиц. Экспертиза и финальный выбор остаются за специалистом.</p>
        </div>

        <div class="section">
            <h3>Работает с вашими поставщиками</h3>
            <p>Вам не нужно переводить поставщиков на новую платформу или просить их менять формат ответов. IQOT подстраивается под существующие процессы: поставщики продолжают отвечать как обычно, а система обрабатывает их ответы.</p>
        </div>

        <div class="section">
            <h3>Контроль там, где нужно</h3>
            <p>Вы видите кто из поставщиков ответил и какие вопросы требуют вашего участия. Система не перегружает вас лишней информацией — только то, что действительно важно для принятия решений.</p>
        </div>

        <div class="highlight-box">
            <h3>Экономика времени</h3>
            <p>Представьте: заявка из 10 позиций, которую нужно разослать 100 поставщикам, ответить на уточняющие вопросы, консолидировать ответы по мере их получения. Сколько времени это займёт у специалиста? День? Два? Неделю?</p>
            <p>В IQOT это <strong style="color: var(--accent-primary);">3 минуты</strong>: 2 минуты на создание и подтверждение заявки. Готовый отчёт приходит через 1-5 дней — когда поставщики ответят. Всё это время вы занимаетесь другими задачами.</p>
        </div>

        <h2>Результаты</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">90%</div>
                <div class="stat-label">экономии времени на сбор КП</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">3 мин</div>
                <div class="stat-label">вместо часов на заявку</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">50+</div>
                <div class="stat-label">поставщиков одновременно</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">0</div>
                <div class="stat-label">потерянных ответов</div>
            </div>
        </div>

        <div class="section">
            <h2>Для кого это</h2>
            <p>IQOT создан для B2B-компаний, которые:</p>
            <ul>
                <li>Регулярно закупают товары и материалы у множества поставщиков</li>
                <li>Тратят значительное время на сбор и сравнение цен</li>
                <li>Хотят принимать решения на основе полных данных, а не выборочных</li>
                <li>Ценят время своих сотрудников и готовы автоматизировать рутину</li>
            </ul>
        </div>

        <div class="cta-section">
            <p><em>IQOT — интеллектуальный сбор коммерческих предложений. Система сама найдёт поставщиков, отправит запросы, соберёт ответы и подготовит сводный отчёт.</em></p>
        </div>
    </main>
</body>
</html>
