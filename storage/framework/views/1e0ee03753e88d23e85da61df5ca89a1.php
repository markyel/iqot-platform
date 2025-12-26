<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Личный кабинет'); ?> - IQOT</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php echo $__env->yieldPushContent('styles'); ?>
    
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
        }
        
        .header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.25rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">IQOT</div>
        </div>
        
        <nav class="nav-menu">
            <a href="<?php echo e(route('cabinet.dashboard')); ?>" class="nav-item <?php echo e(request()->routeIs('cabinet.dashboard') ? 'active' : ''); ?>">
                📊 Главная
            </a>
            <a href="<?php echo e(route('cabinet.requests')); ?>" class="nav-item <?php echo e(request()->routeIs('cabinet.requests*') ? 'active' : ''); ?>">
                📝 Мои заявки
            </a>
            <a href="<?php echo e(route('cabinet.items.index')); ?>" class="nav-item <?php echo e(request()->routeIs('cabinet.items*') ? 'active' : ''); ?>">
                📦 Мониторинг позиций
            </a>
            <a href="<?php echo e(route('cabinet.suppliers')); ?>" class="nav-item <?php echo e(request()->routeIs('cabinet.suppliers*') ? 'active' : ''); ?>">
                🏢 Поставщики
            </a>
            <a href="<?php echo e(route('cabinet.settings')); ?>" class="nav-item <?php echo e(request()->routeIs('cabinet.settings') ? 'active' : ''); ?>">
                ⚙️ Настройки
            </a>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->is_admin): ?>
                <div style="border-top: 1px solid #e5e7eb; margin: 1rem 0; padding-top: 1rem;">
                    <div style="padding: 0 1.5rem; font-size: 0.75rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem;">
                        Администрирование
                    </div>
                    <a href="<?php echo e(route('admin.external-requests.index')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.external-requests*') ? 'active' : ''); ?>">
                        📋 Заявки
                    </a>
                    <a href="<?php echo e(route('admin.items.index')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.items*') ? 'active' : ''); ?>">
                        📦 Мониторинг позиций (Админ)
                    </a>
                    <a href="<?php echo e(route('admin.demo-requests.index')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.demo-requests*') ? 'active' : ''); ?>">
                        🎯 Демо-заявки
                    </a>
                    <a href="<?php echo e(route('admin.users.index')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.users*') ? 'active' : ''); ?>">
                        👥 Пользователи
                    </a>
                    <a href="<?php echo e(route('admin.settings.index')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.settings*') ? 'active' : ''); ?>">
                        ⚙️ Настройки системы
                    </a>
                    <a href="/admin" class="nav-item" target="_blank">
                        🔧 Filament Admin
                    </a>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </nav>
    </div>
    
    <div class="main-content">
        <header class="header">
            <h1><?php echo $__env->yieldContent('header', 'Личный кабинет'); ?></h1>
            <div>
                <span style="color: #6b7280; margin-right: 1rem;"><?php echo e(auth()->user()->name); ?></span>
                <form method="POST" action="<?php echo e(route('logout')); ?>" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn" style="background: #f3f4f6; color: #374151;">Выход</button>
                </form>
            </div>
        </header>
        
        <main class="content">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
                <div class="alert alert-success">
                    <?php echo e(session('success')); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
                <div class="alert alert-error">
                    <?php echo e(session('error')); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 1.25rem;">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            
            <?php echo $__env->yieldContent('content'); ?>
        </main>
    </div>
    
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/layouts/cabinet.blade.php ENDPATH**/ ?>