<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $item->name }} — Каталог IQOT</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 6rem 2rem 3rem;
        }

        .breadcrumbs {
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .breadcrumbs a {
            color: var(--accent-primary);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .breadcrumbs a:hover {
            color: var(--accent-secondary);
        }

        .breadcrumbs span {
            margin: 0 0.5rem;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .card h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--text-primary);
        }

        .card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .code-font {
            font-family: 'JetBrains Mono', monospace;
        }

        .success-box {
            background: var(--accent-gradient-subtle);
            border: 2px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .success-box-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--accent-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .success-box-text {
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .cta-box {
            background: var(--accent-gradient);
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
            color: var(--bg-primary);
        }

        .cta-box h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .cta-box p {
            font-size: 1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-btn-white {
            padding: 0.875rem 2rem;
            background: white;
            color: var(--bg-primary);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .cta-btn-white:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .cta-btn-transparent {
            padding: 0.875rem 2rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .cta-btn-transparent:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .info-box {
            background: rgba(16, 185, 129, 0.05);
            border: 2px solid rgba(16, 185, 129, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }

        .info-box p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            border-color: var(--accent-primary);
            color: var(--accent-primary);
            transform: translateX(-4px);
        }

        .empty-text {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
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
        <div class="breadcrumbs">
            <a href="{{ route('catalog.index') }}">Каталог</a>
            <span>/</span>
            <span>{{ $item->name }}</span>
        </div>

        <div class="card">
            <h1>{{ $item->name }}</h1>

            <div class="info-grid">
                @if($item->brand)
                <div class="info-item">
                    <div class="info-label">Марка</div>
                    <div class="info-value">{{ $item->brand }}</div>
                </div>
                @endif

                @if($item->article)
                <div class="info-item">
                    <div class="info-label">Артикул</div>
                    <div class="info-value code-font">{{ $item->article }}</div>
                </div>
                @endif

                @if($item->category)
                <div class="info-item">
                    <div class="info-label">Категория</div>
                    <div class="info-value">{{ $item->category }}</div>
                </div>
                @endif

                @if($item->product_type_name)
                <div class="info-item">
                    <div class="info-label">Тип оборудования</div>
                    <div class="info-value">{{ $item->product_type_name }}</div>
                </div>
                @endif

                @if($item->domain_name)
                <div class="info-item">
                    <div class="info-label">Область применения</div>
                    <div class="info-value">{{ $item->domain_name }}</div>
                </div>
                @endif
            </div>
        </div>

        <div class="card">
            <h2>Предложения поставщиков</h2>

            @if($item->offers_count > 0)
            <div class="success-box">
                <div class="success-box-title">
                    <span>✓</span>
                    <span>По этой позиции получено {{ $item->offers_count }} {{ $item->offers_count == 1 ? 'предложение' : 'предложений' }}</span>
                </div>
                @if($item->min_price && $item->max_price)
                <div class="success-box-text">
                    Цена за единицу: от {{ number_format($item->min_price, 2, ',', ' ') }} ₽ до {{ number_format($item->max_price, 2, ',', ' ') }} ₽
                </div>
                @endif
            </div>

            @if(!$isAuthorized)
            <div class="cta-box">
                <h3>Для полного доступа к деталям предложений</h3>
                <p>Зарегистрируйтесь и получите доступ ко всем ценам и контактам поставщиков</p>
                <div class="cta-buttons">
                    <a href="{{ route('register') }}" class="cta-btn-white">Регистрация</a>
                    <a href="{{ route('login') }}" class="cta-btn-transparent">Вход</a>
                </div>
            </div>
            @else
            <div class="info-box">
                <p>Детальная информация о предложениях доступна в личном кабинете</p>
                <a href="{{ route('cabinet.items.show', $item->external_item_id) }}" class="btn btn-primary">Открыть в кабинете</a>
            </div>
            @endif
            @else
            <div class="empty-text">По этой позиции пока нет предложений</div>
            @endif
        </div>

        <div style="margin-top: 3rem;">
            <a href="{{ route('catalog.index') }}" class="back-link">← Вернуться в каталог</a>
        </div>
    </div>
</body>
</html>
