<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Каталог товаров — IQOT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f1117;
            --bg-secondary: #0a0c10;
            --bg-card: #161a22;
            --bg-card-hover: #1c2129;
            --accent-primary: #10b981;
            --accent-secondary: #34d399;
            --accent-tertiary: #6ee7b7;
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
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image:
                linear-gradient(rgba(16, 185, 129, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 185, 129, 0.02) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
            z-index: 0;
        }

        .bg-glow {
            position: fixed;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.4;
            pointer-events: none;
            z-index: 0;
        }

        .bg-glow-1 {
            top: -200px;
            right: -100px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.2) 0%, transparent 70%);
        }

        .bg-glow-2 {
            bottom: 20%;
            left: -200px;
            background: radial-gradient(circle, rgba(52, 211, 153, 0.15) 0%, transparent 70%);
        }

        .nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 1rem 2rem;
            background: rgba(10, 14, 23, 0.8);
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

        .btn {
            padding: 0.75rem 1.75rem;
            border-radius: 10px;
            font-family: 'Manrope', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: var(--bg-primary);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            border-color: var(--accent-primary);
            color: var(--accent-primary);
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 4rem 2rem 3rem;
            margin-top: 80px;
        }

        .header {
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 1.125rem;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .card:hover {
            border-color: rgba(16, 185, 129, 0.3);
            box-shadow: 0 0 40px rgba(16, 185, 129, 0.1);
        }

        .categories {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .category-tag {
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            border: 1px solid var(--border-color);
        }

        .category-tag:not(.active) {
            background: transparent;
            color: var(--text-secondary);
        }

        .category-tag:not(.active):hover {
            background: var(--bg-card-hover);
            border-color: var(--accent-primary);
            color: var(--accent-primary);
        }

        .category-tag.active {
            background: var(--accent-gradient);
            color: var(--bg-primary);
            border-color: transparent;
        }

        .search-form {
            display: grid;
            gap: 1rem;
        }

        .search-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        input[type="text"], select {
            padding: 1rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-family: 'Manrope', sans-serif;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        input[type="text"]:focus, select:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .items-table thead th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .items-table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--border-color);
        }

        .items-table tbody tr:last-child {
            border-bottom: none;
        }

        .items-table tbody tr:hover {
            background: var(--bg-card-hover);
        }

        .items-table tbody td {
            padding: 1.25rem 1rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .items-table tbody td a {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .items-table tbody td a:hover {
            color: var(--accent-secondary);
        }

        .offers-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            background: var(--accent-gradient-subtle);
            color: var(--accent-primary);
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
            font-size: 1.125rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .code-font {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Pagination Styles */
        nav[role="navigation"] {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        nav[role="navigation"] > div:first-child {
            display: none; /* Скрываем текстовое описание "Showing X to Y of Z results" */
        }

        nav[role="navigation"] > div:last-child {
            width: 100%;
        }

        nav[role="navigation"] span,
        nav[role="navigation"] a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.5rem;
            height: 2.5rem;
            padding: 0 0.75rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        nav[role="navigation"] a:hover {
            background: var(--bg-card-hover);
            border-color: var(--accent-primary);
            color: var(--accent-primary);
        }

        nav[role="navigation"] span[aria-current="page"] {
            background: var(--accent-gradient);
            border-color: transparent;
            color: var(--bg-primary);
        }

        nav[role="navigation"] span[aria-disabled="true"] {
            opacity: 0.3;
            cursor: not-allowed;
        }

        nav[role="navigation"] > div:last-child {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        /* Стрелки Previous/Next */
        nav[role="navigation"] a[rel="prev"],
        nav[role="navigation"] a[rel="next"],
        nav[role="navigation"] span[aria-disabled="true"] {
            font-weight: 600;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .container {
                padding: 5rem 1rem 2rem;
            }

            .header h1 {
                font-size: 1.75rem;
            }

            .header p {
                font-size: 1rem;
            }

            .nav {
                padding: 1rem;
            }

            .logo {
                font-size: 1.25rem;
            }

            .nav-right {
                gap: 0.5rem;
            }

            .btn {
                padding: 0.625rem 1rem;
                font-size: 0.875rem;
            }

            .card {
                padding: 1.25rem;
            }

            .search-row {
                grid-template-columns: 1fr;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            /* Скрываем таблицу на мобильных */
            .items-table {
                display: none;
            }

            /* Показываем карточки */
            .mobile-items {
                display: block;
            }

            .mobile-item-card {
                background: var(--bg-secondary);
                border: 1px solid var(--border-color);
                border-radius: 12px;
                padding: 1.25rem;
                margin-bottom: 1rem;
                transition: all 0.3s ease;
            }

            .mobile-item-card:hover {
                border-color: var(--accent-primary);
                box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
            }

            .mobile-item-card a {
                text-decoration: none;
                display: block;
            }

            .mobile-item-title {
                font-size: 1.125rem;
                font-weight: 700;
                color: var(--accent-primary);
                margin-bottom: 1rem;
                line-height: 1.4;
            }

            .mobile-item-row {
                display: flex;
                justify-content: space-between;
                align-items: start;
                margin-bottom: 0.75rem;
                font-size: 0.875rem;
            }

            .mobile-item-label {
                color: var(--text-muted);
                font-size: 0.8125rem;
            }

            .mobile-item-value {
                color: var(--text-primary);
                font-weight: 600;
                text-align: right;
                max-width: 60%;
                word-break: break-word;
            }

            .mobile-offers-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.5rem 1rem;
                background: var(--accent-gradient);
                color: var(--bg-primary);
                border-radius: 8px;
                font-weight: 700;
                font-size: 1rem;
            }
        }

        @media (min-width: 769px) {
            .mobile-items {
                display: none;
            }
        }

        /* Footer */
        .footer {
            background: #08080c;
            border-top: 1px solid var(--border-color);
            padding: 48px 24px 32px;
            margin-top: 5rem;
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
    <div class="bg-grid"></div>
    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>

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
        <div class="header">
            <h1>Каталог товаров</h1>
            <p>Товары с готовыми предложениями от поставщиков</p>
        </div>

        @if($categories->isNotEmpty())
        <div class="card">
            <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-primary);">Категории</h3>
            <div class="categories">
                @foreach($categories as $category)
                <a href="{{ route('catalog.index', ['product_type' => $category->product_type_id]) }}"
                   class="category-tag {{ request('product_type') == $category->product_type_id ? 'active' : '' }}">
                    {{ $category->product_type_name }} ({{ $category->items_count }})
                </a>
                @endforeach
            </div>
        </div>
        @endif

        <div class="card">
            <form method="GET" action="{{ route('catalog.index') }}" class="search-form">
                <div class="search-row">
                    <input type="text" name="search" placeholder="Поиск по названию, бренду, артикулу..." value="{{ $filters['search'] ?? '' }}">
                    <button type="submit" class="btn btn-primary">Найти</button>
                </div>
                <div class="filters-row">
                    <select name="domain">
                        <option value="">Все области применения</option>
                        @foreach($applicationDomains as $id => $name)
                        <option value="{{ $id }}" {{ ($filters['domain'] ?? '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    @if(request('product_type') || request('domain') || request('search'))
                    <a href="{{ route('catalog.index') }}" class="btn btn-outline">Сбросить фильтры</a>
                    @endif
                </div>
            </form>
        </div>

        @if($items->isEmpty())
        <div class="card">
            <div class="empty-state">Товаров не найдено</div>
        </div>
        @else
        <div class="card">
            <!-- Desktop Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Марка</th>
                        <th>Артикул</th>
                        <th>Категория</th>
                        <th>Область применения</th>
                        <th style="text-align: center;">Предложений</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td>
                            <a href="{{ route('catalog.show', $item->id) }}">{{ $item->name }}</a>
                        </td>
                        <td>{{ $item->brand ?? '—' }}</td>
                        <td class="code-font">{{ $item->article ?? '—' }}</td>
                        <td>{{ $item->category ?? '—' }}</td>
                        <td>{{ $item->domain_name ?? '—' }}</td>
                        <td style="text-align: center;">
                            <span class="offers-badge">{{ $item->offers_count }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Mobile Cards -->
            <div class="mobile-items">
                @foreach($items as $item)
                <div class="mobile-item-card">
                    <a href="{{ route('catalog.show', $item->id) }}">
                        <div class="mobile-item-title">{{ $item->name }}</div>

                        @if($item->brand)
                        <div class="mobile-item-row">
                            <span class="mobile-item-label">Марка:</span>
                            <span class="mobile-item-value">{{ $item->brand }}</span>
                        </div>
                        @endif

                        @if($item->article)
                        <div class="mobile-item-row">
                            <span class="mobile-item-label">Артикул:</span>
                            <span class="mobile-item-value code-font">{{ $item->article }}</span>
                        </div>
                        @endif

                        @if($item->category)
                        <div class="mobile-item-row">
                            <span class="mobile-item-label">Категория:</span>
                            <span class="mobile-item-value">{{ $item->category }}</span>
                        </div>
                        @endif

                        @if($item->domain_name)
                        <div class="mobile-item-row">
                            <span class="mobile-item-label">Область применения:</span>
                            <span class="mobile-item-value">{{ $item->domain_name }}</span>
                        </div>
                        @endif

                        <div class="mobile-item-row" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                            <span class="mobile-item-label">Предложений:</span>
                            <span class="mobile-offers-badge">{{ $item->offers_count }}</span>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>

        <div class="pagination">
            {{ $items->links() }}
        </div>
        @endif
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
