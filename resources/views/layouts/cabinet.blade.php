<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Личный кабинет') - IQOT</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- IQOT Design System -->
    <link rel="stylesheet" href="{{ asset('css/iqot-design-tokens.css') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

    <style>
        /* Additional utility classes */
        .text-secondary {
            color: var(--neutral-500);
            font-size: var(--text-sm);
        }

        /* Sidebar collapsed state for desktop */
        :root {
            --sidebar-collapsed-width: 72px;
        }

        /* Sidebar logo styles */
        .sidebar-logo {
            display: flex !important;
            align-items: center !important;
            gap: var(--space-3) !important;
            text-decoration: none !important;
        }

        .sidebar-logo-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar-logo-icon svg {
            width: 36px;
            height: 36px;
            fill: var(--neutral-0);
        }

        .sidebar-logo-text {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--neutral-0);
            letter-spacing: -0.02em;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 150ms ease, width 250ms ease;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar.collapsed .sidebar-logo-text,
        .sidebar.collapsed .sidebar-section-title,
        .sidebar.collapsed .sidebar-item-text,
        .sidebar.collapsed .sidebar-item-badge,
        .sidebar.collapsed .sidebar-user-info {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        /* Sidebar toggle button */
        .sidebar-toggle {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: var(--radius-md);
            color: var(--primary-300);
            cursor: pointer;
            transition: all 150ms ease;
            flex-shrink: 0;
            margin-left: auto;
        }

        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.15);
            color: var(--neutral-0);
        }

        .sidebar-toggle-icon {
            transition: transform 250ms ease;
        }

        .sidebar.collapsed .sidebar-toggle-icon {
            transform: rotate(180deg);
        }

        /* Sidebar close button (mobile) */
        .sidebar-close {
            width: 32px;
            height: 32px;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: var(--radius-md);
            color: var(--primary-200);
            cursor: pointer;
            transition: all 150ms ease;
            flex-shrink: 0;
            margin-left: auto;
        }

        .sidebar-close:hover {
            background: rgba(255, 255, 255, 0.15);
            color: var(--neutral-0);
        }

        .sidebar.collapsed .sidebar-item {
            justify-content: center;
            position: relative;
        }

        .sidebar.collapsed .sidebar-item:hover .sidebar-item-tooltip {
            opacity: 1;
            visibility: visible;
        }

        /* Sidebar item tooltip */
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

        /* Mobile sidebar overlay */
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

        /* Main content adjustments */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 250ms cubic-bezier(0, 0, 0.2, 1);
        }

        .main-content.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Top header for mobile */
        .top-header {
            height: var(--header-height);
            background: var(--neutral-0);
            border-bottom: 1px solid var(--neutral-200);
            display: none;
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

        .page-content {
            flex: 1;
            padding: var(--space-6);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width);
                transition: transform 250ms cubic-bezier(0, 0, 0.2, 1);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

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

            .sidebar-toggle {
                display: none !important;
            }

            .sidebar-close {
                display: flex !important;
            }

            .top-header {
                display: flex;
            }

            .main-content,
            .main-content.sidebar-collapsed {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .page-content {
                padding: var(--space-4);
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 100%;
            }

            .page-content {
                padding: var(--space-3);
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
    <div class="app-layout">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="{{ route('cabinet.dashboard') }}" class="sidebar-logo">
                    <div class="sidebar-logo-icon">
                        <svg viewBox="0 0 119.81 119.81" xmlns="http://www.w3.org/2000/svg" style="width: 36px; height: 36px; fill: var(--neutral-0);">
                            <path d="M59.9,0C26.82,0,0,26.82,0,59.9s26.82,59.9,59.9,59.9,59.9-26.82,59.9-59.9S92.99,0,59.9,0ZM22.91,60.5c0-20.43,16.56-36.99,36.99-36.99s36.99,16.56,36.99,36.99c0,9.7-3.74,18.52-9.84,25.12l-17.05-15.39-3.36,3.73,16.75,15.12c-6.39,5.26-14.57,8.41-23.49,8.41-20.43,0-36.99-16.56-36.99-36.99Z"/>
                        </svg>
                    </div>
                    <span class="sidebar-logo-text">IQOT</span>
                </a>

                <!-- Desktop: toggle button -->
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i data-lucide="chevrons-left" class="sidebar-toggle-icon" style="width: 18px; height: 18px;"></i>
                </button>

                <!-- Mobile: close button -->
                <button class="sidebar-close" id="sidebarClose">
                    <i data-lucide="x" style="width: 18px; height: 18px;"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <div class="sidebar-section">
                    <a href="{{ route('cabinet.dashboard') }}" class="sidebar-item {{ request()->routeIs('cabinet.dashboard') ? 'active' : '' }}">
                        <i data-lucide="home" class="sidebar-item-icon"></i>
                        <span class="sidebar-item-text">Главная</span>
                        <span class="sidebar-item-tooltip">Главная</span>
                    </a>
                    <a href="{{ route('cabinet.requests') }}" class="sidebar-item {{ request()->routeIs('cabinet.requests*') ? 'active' : '' }}">
                        <i data-lucide="file-text" class="sidebar-item-icon"></i>
                        <span class="sidebar-item-text">Мои заявки</span>
                        <span class="sidebar-item-tooltip">Мои заявки</span>
                    </a>
                    <a href="{{ route('cabinet.items.index') }}" class="sidebar-item {{ request()->routeIs('cabinet.items*') ? 'active' : '' }}">
                        <i data-lucide="package" class="sidebar-item-icon"></i>
                        <span class="sidebar-item-text">Мониторинг позиций</span>
                        <span class="sidebar-item-tooltip">Мониторинг позиций</span>
                    </a>
                    <a href="{{ route('cabinet.tariff.index') }}" class="sidebar-item {{ request()->routeIs('cabinet.tariff*') ? 'active' : '' }}">
                        <i data-lucide="credit-card" class="sidebar-item-icon"></i>
                        <span class="sidebar-item-text">Мой тариф</span>
                        <span class="sidebar-item-tooltip">Мой тариф</span>
                    </a>
                    <a href="{{ route('cabinet.settings') }}" class="sidebar-item {{ request()->routeIs('cabinet.settings') ? 'active' : '' }}">
                        <i data-lucide="settings" class="sidebar-item-icon"></i>
                        <span class="sidebar-item-text">Настройки</span>
                        <span class="sidebar-item-tooltip">Настройки</span>
                    </a>
                </div>

                @if(auth()->user()->is_admin)
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Администрирование</div>
                        <a href="{{ route('admin.manage.requests.index') }}" class="sidebar-item {{ request()->routeIs('admin.manage.requests*') ? 'active' : '' }}">
                            <i data-lucide="clipboard-list" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Управление заявками</span>
                            <span class="sidebar-item-tooltip">Управление заявками</span>
                        </a>
                        <a href="{{ route('admin.questions.index') }}" class="sidebar-item {{ request()->routeIs('admin.questions.index') ? 'active' : '' }}">
                            <i data-lucide="message-circle-question" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Вопросы от поставщиков</span>
                            <span class="sidebar-item-tooltip">Вопросы от поставщиков</span>
                        </a>
                        <a href="{{ route('admin.questions.consolidated') }}" class="sidebar-item {{ request()->routeIs('admin.questions.consolidated*') ? 'active' : '' }}">
                            <i data-lucide="link" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Консолидированные вопросы</span>
                            <span class="sidebar-item-tooltip">Консолидированные вопросы</span>
                        </a>
                        <a href="{{ route('admin.requests.index') }}" class="sidebar-item {{ request()->routeIs('admin.requests*') ? 'active' : '' }}">
                            <i data-lucide="file-check" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Модерация заявок</span>
                            <span class="sidebar-item-tooltip">Модерация заявок</span>
                        </a>
                        <a href="{{ route('admin.items.index') }}" class="sidebar-item {{ request()->routeIs('admin.items*') ? 'active' : '' }}">
                            <i data-lucide="package-search" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Мониторинг позиций</span>
                            <span class="sidebar-item-tooltip">Мониторинг позиций</span>
                        </a>
                        <a href="{{ route('admin.demo-requests.index') }}" class="sidebar-item {{ request()->routeIs('admin.demo-requests*') ? 'active' : '' }}">
                            <i data-lucide="target" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Демо-заявки</span>
                            <span class="sidebar-item-tooltip">Демо-заявки</span>
                        </a>
                        <a href="{{ route('admin.users.index') }}" class="sidebar-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                            <i data-lucide="users" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Пользователи</span>
                            <span class="sidebar-item-tooltip">Пользователи</span>
                        </a>
                        <a href="{{ route('admin.tariff-plans.index') }}" class="sidebar-item {{ request()->routeIs('admin.tariff-plans*') ? 'active' : '' }}">
                            <i data-lucide="credit-card" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Тарифные планы</span>
                            <span class="sidebar-item-tooltip">Тарифные планы</span>
                        </a>
                        <a href="{{ route('admin.taxonomy.index') }}" class="sidebar-item {{ request()->routeIs('admin.taxonomy*') ? 'active' : '' }}">
                            <i data-lucide="tags" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Модерация категорий</span>
                            <span class="sidebar-item-tooltip">Модерация категорий</span>
                        </a>

                        {{-- Секция Биллинг --}}
                        <div class="sidebar-section-title">
                            <span>Биллинг</span>
                        </div>
                        <a href="{{ route('admin.billing.invoices.index') }}" class="sidebar-item {{ request()->routeIs('admin.billing.invoices*') ? 'active' : '' }}">
                            <i data-lucide="file-text" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Счета</span>
                            <span class="sidebar-item-tooltip">Счета</span>
                        </a>
                        <a href="{{ route('admin.promo-codes.index') }}" class="sidebar-item {{ request()->routeIs('admin.promo-codes*') ? 'active' : '' }}">
                            <i data-lucide="gift" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Промокоды</span>
                            <span class="sidebar-item-tooltip">Промокоды</span>
                        </a>

                        <a href="{{ route('admin.campaigns.index') }}" class="sidebar-item {{ request()->routeIs('admin.campaigns*') ? 'active' : '' }}">
                            <i data-lucide="mail" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Рассылки</span>
                            <span class="sidebar-item-tooltip">Рассылки</span>
                        </a>

                        <a href="{{ route('admin.billing.settings.index') }}" class="sidebar-item {{ request()->routeIs('admin.billing.settings*') ? 'active' : '' }}">
                            <i data-lucide="settings" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Настройки биллинга</span>
                            <span class="sidebar-item-tooltip">Настройки биллинга</span>
                        </a>

                        <a href="{{ route('admin.settings.index') }}" class="sidebar-item {{ request()->routeIs('admin.settings.index') ? 'active' : '' }}">
                            <i data-lucide="settings-2" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Настройки системы</span>
                            <span class="sidebar-item-tooltip">Настройки системы</span>
                        </a>
                        <a href="/admin" class="sidebar-item" target="_blank">
                            <i data-lucide="wrench" class="sidebar-item-icon"></i>
                            <span class="sidebar-item-text">Filament Admin</span>
                            <span class="sidebar-item-tooltip">Filament Admin</span>
                        </a>
                    </div>
                @endif
            </nav>

            <div class="sidebar-footer">
                @php
                    $user = auth()->user();
                @endphp

                @if($user->is_admin)
                    <div class="sidebar-tariff-card" style="cursor: default; background: linear-gradient(135deg, var(--danger-600), var(--warning-600)); border-color: var(--danger-400);">
                        <div style="display: flex; align-items: center; gap: var(--space-3);">
                            <div style="width: 40px; height: 40px; border-radius: var(--radius-lg); background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i data-lucide="shield-check" style="width: 20px; height: 20px; color: var(--neutral-0);"></i>
                            </div>
                            <div class="sidebar-tariff-info">
                                <div style="font-size: var(--text-sm); font-weight: 600; color: var(--neutral-0); margin-bottom: 2px;">
                                    АДМИНИСТРАТОР
                                </div>
                                <div style="font-size: var(--text-xs); color: rgba(255, 255, 255, 0.8);">
                                    {{ $user->name }}
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    @php
                        $tariff = $user->getActiveTariff();
                        $limitsInfo = app(\App\Services\TariffService::class)->getUserLimitsInfo($user);
                    @endphp

                    <a href="{{ route('cabinet.tariff.index') }}" class="sidebar-tariff-card">
                    <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-2);">
                        <div style="width: 40px; height: 40px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--primary-500), var(--accent-500)); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i data-lucide="zap" style="width: 20px; height: 20px; color: var(--neutral-0);"></i>
                        </div>
                        <div class="sidebar-tariff-info">
                            <div style="font-size: var(--text-sm); font-weight: 600; color: var(--neutral-0); margin-bottom: 2px;">
                                {{ $tariff ? $tariff->tariffPlan->name : 'Нет тарифа' }}
                            </div>
                            @if($tariff)
                                <div style="font-size: var(--text-xs); color: var(--primary-200);">
                                    @if($limitsInfo['items_limit'] !== null || $limitsInfo['reports_limit'] !== null)
                                        @if($limitsInfo['items_limit'] !== null)
                                            {{ $limitsInfo['items_remaining'] }} из {{ $limitsInfo['items_limit'] }} поз.
                                        @endif
                                        @if($limitsInfo['reports_limit'] !== null)
                                            @if($limitsInfo['items_limit'] !== null) · @endif
                                            {{ $limitsInfo['reports_remaining'] }} отч.
                                        @endif
                                    @else
                                        Без включенных кредитов
                                    @endif
                                </div>
                            @else
                                <div style="font-size: var(--text-xs); color: var(--primary-200);">
                                    Выберите тариф
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($tariff && ($limitsInfo['items_limit'] !== null || $limitsInfo['reports_limit'] !== null))
                    <div class="sidebar-tariff-progress">
                        @if($limitsInfo['items_limit'] !== null)
                        <div style="margin-bottom: var(--space-1);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <span style="font-size: var(--text-xs); color: var(--primary-200);">Позиции</span>
                                <span style="font-size: var(--text-xs); color: var(--neutral-0); font-weight: 500;">{{ $limitsInfo['items_used'] }}/{{ $limitsInfo['items_limit'] }}</span>
                            </div>
                            <div style="height: 4px; background: rgba(255,255,255,0.15); border-radius: var(--radius-full); overflow: hidden;">
                                <div style="height: 100%; background: linear-gradient(90deg, var(--success-400), var(--primary-400)); border-radius: var(--radius-full); width: {{ min(100, $limitsInfo['items_used_percentage'] ?? 0) }}%; transition: width 0.3s ease;"></div>
                            </div>
                        </div>
                        @endif

                        @if($limitsInfo['reports_limit'] !== null)
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <span style="font-size: var(--text-xs); color: var(--primary-200);">Отчеты</span>
                                <span style="font-size: var(--text-xs); color: var(--neutral-0); font-weight: 500;">{{ $limitsInfo['reports_used'] }}/{{ $limitsInfo['reports_limit'] }}</span>
                            </div>
                            <div style="height: 4px; background: rgba(255,255,255,0.15); border-radius: var(--radius-full); overflow: hidden;">
                                <div style="height: 100%; background: linear-gradient(90deg, var(--accent-400), var(--warning-400)); border-radius: var(--radius-full); width: {{ min(100, $limitsInfo['reports_used_percentage'] ?? 0) }}%; transition: width 0.3s ease;"></div>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif
                    </a>
                @endif
            </div>
        </aside>


        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Mobile Header -->
            <header class="top-header">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i data-lucide="menu" class="icon-md"></i>
                </button>
                <div class="mobile-logo" style="font-size: var(--text-lg); font-weight: var(--font-bold); color: var(--neutral-900);">
                    IQOT
                </div>
                <div class="mobile-actions">
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">
                            <i data-lucide="log-out" class="icon-sm"></i>
                        </button>
                    </form>
                </div>
            </header>

            <div class="page-content">
                <!-- Alerts -->
                @if(session('success'))
                    <div class="alert alert-success">
                        <i data-lucide="check-circle" class="alert-icon"></i>
                        <div class="alert-content">
                            {{ session('success') }}
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-error">
                        <i data-lucide="x-circle" class="alert-icon"></i>
                        <div class="alert-content">
                            {{ session('error') }}
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-error">
                        <i data-lucide="alert-triangle" class="alert-icon"></i>
                        <div class="alert-content">
                            <ul style="margin: 0; padding-left: 1.25rem;">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
    
    @stack('scripts')

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

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

        // Load saved state (desktop only)
        if (!isMobile()) {
            const saved = localStorage.getItem('sidebarCollapsed') === 'true';
            if (saved) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
                isSidebarCollapsed = true;
            }
        }

        // Desktop: Toggle collapsed
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                if (!isMobile()) {
                    isSidebarCollapsed = !isSidebarCollapsed;
                    sidebar.classList.toggle('collapsed', isSidebarCollapsed);
                    mainContent.classList.toggle('sidebar-collapsed', isSidebarCollapsed);
                    localStorage.setItem('sidebarCollapsed', isSidebarCollapsed);
                }
            });
        }

        // Mobile: Open sidebar
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                isMobileSidebarOpen = true;
                sidebar.classList.add('mobile-open');
                sidebarOverlay.classList.add('visible');
                document.body.style.overflow = 'hidden';
            });
        }

        // Mobile: Close sidebar
        function closeMobileSidebar() {
            isMobileSidebarOpen = false;
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('visible');
            document.body.style.overflow = '';
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', closeMobileSidebar);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeMobileSidebar);
        }

        // Close on nav item click (mobile)
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', () => {
                if (isMobile() && isMobileSidebarOpen) {
                    closeMobileSidebar();
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Escape — close mobile menu
            if (e.key === 'Escape' && isMobileSidebarOpen) {
                closeMobileSidebar();
            }

            // Ctrl/Cmd + B — toggle sidebar
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                if (isMobile()) {
                    isMobileSidebarOpen ? closeMobileSidebar() : mobileMenuBtn.click();
                } else if (sidebarToggle) {
                    sidebarToggle.click();
                }
            }
        });

        // Handle resize
        window.addEventListener('resize', () => {
            if (!isMobile()) {
                closeMobileSidebar();
                // Restore saved state
                const saved = localStorage.getItem('sidebarCollapsed') === 'true';
                sidebar.classList.toggle('collapsed', saved);
                mainContent.classList.toggle('sidebar-collapsed', saved);
            }
        });
    </script>
</body>
</html>
