@extends('layouts.app')

@section('content')
<div style="padding: var(--space-6); max-width: 1200px; margin: 0 auto;">
    <!-- Breadcrumbs -->
    <div style="margin-bottom: var(--space-4); font-size: var(--text-sm); color: var(--gray-600);">
        <a href="{{ route('catalog.index') }}" style="color: var(--primary-600); text-decoration: none;">Каталог</a>
        <span style="margin: 0 var(--space-2);">/</span>
        <span>{{ $item->name }}</span>
    </div>

    <!-- Основная информация -->
    <div style="background: white; border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-4); box-shadow: var(--shadow-sm);">
        <h1 style="font-size: var(--text-2xl); font-weight: 700; margin-bottom: var(--space-4);">{{ $item->name }}</h1>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-4);">
            @if($item->brand)
            <div>
                <div style="font-size: var(--text-sm); color: var(--gray-500); margin-bottom: var(--space-1);">Марка</div>
                <div style="font-weight: 600;">{{ $item->brand }}</div>
            </div>
            @endif

            @if($item->article)
            <div>
                <div style="font-size: var(--text-sm); color: var(--gray-500); margin-bottom: var(--space-1);">Артикул</div>
                <div style="font-weight: 600; font-family: 'JetBrains Mono', monospace;">{{ $item->article }}</div>
            </div>
            @endif

            @if($item->category)
            <div>
                <div style="font-size: var(--text-sm); color: var(--gray-500); margin-bottom: var(--space-1);">Категория</div>
                <div style="font-weight: 600;">{{ $item->category }}</div>
            </div>
            @endif

            @if($item->product_type_name)
            <div>
                <div style="font-size: var(--text-sm); color: var(--gray-500); margin-bottom: var(--space-1);">Тип оборудования</div>
                <div style="font-weight: 600;">{{ $item->product_type_name }}</div>
            </div>
            @endif

            @if($item->domain_name)
            <div>
                <div style="font-size: var(--text-sm); color: var(--gray-500); margin-bottom: var(--space-1);">Область применения</div>
                <div style="font-weight: 600;">{{ $item->domain_name }}</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Превью предложений -->
    <div style="background: white; border-radius: var(--radius-lg); padding: var(--space-6); box-shadow: var(--shadow-sm);">
        <h2 style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-4);">Предложения поставщиков</h2>

        @if($item->offers_count > 0)
        <div style="background: var(--success-50); border: 2px solid var(--success-200); border-radius: var(--radius-md); padding: var(--space-4); margin-bottom: var(--space-4);">
            <div style="font-size: var(--text-lg); font-weight: 600; color: var(--success-700); margin-bottom: var(--space-2);">
                ✓ По этой позиции получено {{ $item->offers_count }} {{ $item->offers_count == 1 ? 'предложение' : 'предложений' }}
            </div>
            @if($item->min_price && $item->max_price)
            <div style="font-size: var(--text-sm); color: var(--gray-600);">
                Диапазон цен: от {{ number_format($item->min_price, 2, ',', ' ') }} ₽ до {{ number_format($item->max_price, 2, ',', ' ') }} ₽
            </div>
            @endif
        </div>

        @if(!$isAuthorized)
        <div style="background: linear-gradient(135deg, var(--primary-600), var(--primary-700)); color: white; border-radius: var(--radius-lg); padding: var(--space-6); text-align: center;">
            <div style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-3);">
                Для полного доступа к деталям предложений
            </div>
            <div style="font-size: var(--text-base); margin-bottom: var(--space-4); opacity: 0.9;">
                Зарегистрируйтесь и получите доступ ко всем ценам и контактам поставщиков
            </div>
            <div style="display: flex; gap: var(--space-3); justify-content: center;">
                <a href="{{ route('register') }}" style="display: inline-block; padding: var(--space-3) var(--space-6); background: white; color: var(--primary-600); border-radius: var(--radius-md); text-decoration: none; font-weight: 600;">Регистрация</a>
                <a href="{{ route('login') }}" style="display: inline-block; padding: var(--space-3) var(--space-6); background: rgba(255,255,255,0.2); color: white; border-radius: var(--radius-md); text-decoration: none; font-weight: 600;">Вход</a>
            </div>
        </div>
        @else
        <div style="background: var(--primary-50); border: 2px solid var(--primary-200); border-radius: var(--radius-md); padding: var(--space-4); text-align: center;">
            <div style="font-size: var(--text-base); color: var(--gray-700); margin-bottom: var(--space-3);">
                Детальная информация о предложениях доступна в личном кабинете
            </div>
            <a href="{{ route('cabinet.items.show', $item->external_item_id) }}" style="display: inline-block; padding: var(--space-3) var(--space-6); background: var(--primary-600); color: white; border-radius: var(--radius-md); text-decoration: none; font-weight: 600;">Открыть в кабинете</a>
        </div>
        @endif
        @else
        <p style="color: var(--gray-500); text-align: center; padding: var(--space-4);">По этой позиции пока нет предложений</p>
        @endif
    </div>

    <!-- Кнопка назад -->
    <div style="margin-top: var(--space-6);">
        <a href="{{ route('catalog.index') }}" style="display: inline-block; padding: var(--space-3) var(--space-4); background: var(--gray-100); color: var(--gray-700); border-radius: var(--radius-md); text-decoration: none; font-weight: 500;">← Вернуться в каталог</a>
    </div>
</div>
@endsection
