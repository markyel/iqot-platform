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
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
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
            padding: 6rem 2rem 3rem;
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
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>

    <nav class="nav">
        <div class="nav-container">
            <a href="/" class="logo">IQOT</a>
            <div class="nav-right">
                @auth
                    <a href="{{ route('cabinet.dashboard') }}" class="btn btn-outline">Личный кабинет</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline">Вход</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Регистрация</a>
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
        </div>

        <div class="pagination">
            {{ $items->links() }}
        </div>
        @endif
    </div>
</body>
</html>
