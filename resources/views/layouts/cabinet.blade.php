<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Личный кабинет') - IQOT</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Manrope', sans-serif;
            background: #f9fafb;
            color: #111827;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding: 2rem 0;
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .sidebar.collapsed {
            transform: translateX(-260px);
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
        }

        .nav-menu {
            padding: 1.5rem 0;
            overflow-y: auto;
            max-height: calc(100vh - 120px);
        }

        .nav-item {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-item:hover, .nav-item.active {
            background: #f3f4f6;
            color: #10b981;
        }

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.25rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .menu-toggle {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .menu-toggle:hover {
            background: #e5e7eb;
        }

        .menu-toggle span {
            display: block;
            width: 20px;
            height: 2px;
            background: #6b7280;
            position: relative;
            transition: all 0.3s;
        }

        .menu-toggle span::before,
        .menu-toggle span::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 2px;
            background: #6b7280;
            transition: all 0.3s;
        }

        .menu-toggle span::before {
            top: -6px;
        }

        .menu-toggle span::after {
            top: 6px;
        }

        .menu-toggle.active span {
            background: transparent;
        }

        .menu-toggle.active span::before {
            top: 0;
            transform: rotate(45deg);
        }

        .menu-toggle.active span::after {
            top: 0;
            transform: rotate(-45deg);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-260px);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }
        
        .content {
            padding: 2rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
        }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">IQOT</div>
        </div>
        
        <nav class="nav-menu">
            <a href="{{ route('cabinet.dashboard') }}" class="nav-item {{ request()->routeIs('cabinet.dashboard') ? 'active' : '' }}">
                📊 Главная
            </a>
            <a href="{{ route('cabinet.requests') }}" class="nav-item {{ request()->routeIs('cabinet.requests*') ? 'active' : '' }}">
                📝 Мои заявки
            </a>
            <a href="{{ route('cabinet.items.index') }}" class="nav-item {{ request()->routeIs('cabinet.items*') ? 'active' : '' }}">
                📦 Мониторинг позиций
            </a>
            <a href="{{ route('cabinet.suppliers') }}" class="nav-item {{ request()->routeIs('cabinet.suppliers*') ? 'active' : '' }}">
                🏢 Поставщики
            </a>
            <a href="{{ route('cabinet.settings') }}" class="nav-item {{ request()->routeIs('cabinet.settings') ? 'active' : '' }}">
                ⚙️ Настройки
            </a>

            @if(auth()->user()->is_admin)
                <div style="border-top: 1px solid #e5e7eb; margin: 1rem 0; padding-top: 1rem;">
                    <div style="padding: 0 1.5rem; font-size: 0.75rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem;">
                        Администрирование
                    </div>
                    <a href="{{ route('admin.external-requests.index') }}" class="nav-item {{ request()->routeIs('admin.external-requests*') ? 'active' : '' }}">
                        📋 Заявки
                    </a>
                    <a href="{{ route('admin.items.index') }}" class="nav-item {{ request()->routeIs('admin.items*') ? 'active' : '' }}">
                        📦 Мониторинг позиций (Админ)
                    </a>
                    <a href="{{ route('admin.demo-requests.index') }}" class="nav-item {{ request()->routeIs('admin.demo-requests*') ? 'active' : '' }}">
                        🎯 Демо-заявки
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="nav-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                        👥 Пользователи
                    </a>
                    <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                        ⚙️ Настройки системы
                    </a>
                    <a href="/admin" class="nav-item" target="_blank">
                        🔧 Filament Admin
                    </a>
                </div>
            @endif
        </nav>
    </div>
    
    <div class="main-content" id="mainContent">
        <header class="header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="menu-toggle" id="menuToggle">
                    <span></span>
                </button>
                <h1>@yield('header', 'Личный кабинет')</h1>
            </div>
            <div>
                <span style="color: #6b7280; margin-right: 1rem;">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn" style="background: #f3f4f6; color: #374151;">Выход</button>
                </form>
            </div>
        </header>
        
        <main class="content">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            
            @if(session('error'))
                <div class="alert alert-error">
                    {{ session('error') }}
                </div>
            @endif
            
            @if($errors->any())
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 1.25rem;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            @yield('content')
        </main>
    </div>
    
    @stack('scripts')

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        // Load saved state from localStorage
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
            menuToggle.classList.add('active');
        }

        menuToggle.addEventListener('click', function() {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            menuToggle.classList.toggle('active');

            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', isCollapsed);

            // On mobile, show overlay
            if (window.innerWidth <= 768) {
                sidebarOverlay.classList.toggle('active');
            }
        });

        // Close sidebar on overlay click (mobile)
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });

        // Handle mobile behavior
        function handleMobile() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');

                // On mobile, toggle active class instead
                menuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                });
            }
        }

        handleMobile();
        window.addEventListener('resize', handleMobile);
    </script>
</body>
</html>
