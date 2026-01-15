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

        .sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
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
                        <img src="{{ asset('iqot-logo-icon.svg') }}" alt="IQOT" style="width: 36px; height: 36px; filter: brightness(0) invert(1);">
                    </div>
                    <span class="sidebar-logo-text">IQOT</span>
                </a>

                <!-- Desktop: toggle button -->
                <button class="sidebar-toggle" id="sidebarToggle" style="display: flex; width: 32px; height: 32px; align-items: center; justify-content: center; background: transparent; border: none; border-radius: var(--radius-md); color: var(--primary-200); cursor: pointer; margin-left: auto;">
                    <i data-lucide="chevrons-left" class="icon-md"></i>
                </button>

                <!-- Mobile: close button -->
                <button class="sidebar-close" id="sidebarClose" style="display: none; width: 32px; height: 32px; align-items: center; justify-content: center; background: transparent; border: none; border-radius: var(--radius-md); color: var(--primary-200); cursor: pointer; margin-left: auto;">
                    <i data-lucide="x" class="icon-md"></i>
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
                    <a href="{{ route('cabinet.suppliers') }}" class="sidebar-item {{ request()->routeIs('cabinet.suppliers*') ? 'active' : '' }}">
                        <i data-lucide="building-2" class="sidebar-item-icon"></i>
                        <span class="sidebar-item-text">Поставщики</span>
                        <span class="sidebar-item-tooltip">Поставщики</span>
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
                        <a href="{{ route('admin.settings.index') }}" class="sidebar-item {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
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
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name">{{ auth()->user()->name }}</div>
                        <div class="sidebar-user-email">{{ auth()->user()->email }}</div>
                    </div>
                </div>
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
