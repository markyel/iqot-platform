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

            <div>
                <div style="font-size: var(--text-sm); color: var(--gray-500); margin-bottom: var(--space-1);">Количество предложений</div>
                <div style="font-weight: 600; color: var(--success-600);">{{ $item->offers_count }}</div>
            </div>
        </div>
    </div>

    @if(!$isAuthorized)
    <!-- Призыв к регистрации -->
    <div style="background: linear-gradient(135deg, var(--primary-600), var(--primary-700)); color: white; border-radius: var(--radius-lg); padding: var(--space-6); text-align: center; box-shadow: var(--shadow-sm);">
        <div style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-3);">
            Для полного доступа к информации о поставщиках и всем ценам
        </div>
        <div style="font-size: var(--text-base); margin-bottom: var(--space-4); opacity: 0.9;">
            Зарегистрируйтесь и разблокируйте этот отчет
        </div>
        <div style="display: flex; gap: var(--space-3); justify-content: center;">
            <a href="{{ route('register') }}" style="display: inline-block; padding: var(--space-3) var(--space-6); background: white; color: var(--primary-600); border-radius: var(--radius-md); text-decoration: none; font-weight: 600; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                Регистрация
            </a>
            <a href="{{ route('login') }}" style="display: inline-block; padding: var(--space-3) var(--space-6); background: rgba(255,255,255,0.2); color: white; border-radius: var(--radius-md); text-decoration: none; font-weight: 600; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                Вход
            </a>
        </div>
    </div>
    @else
    <!-- Таблица предложений (для авторизованных) -->
    @if($offers->isNotEmpty())
    <div style="background: white; border-radius: var(--radius-lg); padding: var(--space-6); box-shadow: var(--shadow-sm);">
        <h2 style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-4);">
            Предложения поставщиков ({{ $offers->count() }})
        </h2>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--gray-200);">
                    <th style="padding: var(--space-3); text-align: left; font-weight: 600;">Поставщик</th>
                    <th style="padding: var(--space-3); text-align: right; font-weight: 600);">Цена за единицу</th>
                    <th style="padding: var(--space-3); text-align: right; font-weight: 600;">Общая стоимость</th>
                    <th style="padding: var(--space-3); text-align: center; font-weight: 600;">Срок поставки</th>
                    <th style="padding: var(--space-3); text-align: left; font-weight: 600;">Условия оплаты</th>
                </tr>
            </thead>
            <tbody>
                @foreach($offers as $offer)
                <tr style="border-bottom: 1px solid var(--gray-100);">
                    <td style="padding: var(--space-3); font-weight: 500;">
                        {{ $offer->supplier->name ?? 'Поставщик #' . $offer->supplier_id }}
                    </td>
                    <td style="padding: var(--space-3); text-align: right; font-family: 'JetBrains Mono', monospace;">
                        {{ number_format($offer->price_per_unit, 2, ',', ' ') }} ₽
                    </td>
                    <td style="padding: var(--space-3); text-align: right; font-weight: 600; font-family: 'JetBrains Mono', monospace;">
                        {{ number_format($offer->total_price, 2, ',', ' ') }} ₽
                    </td>
                    <td style="padding: var(--space-3); text-align: center;">
                        {{ $offer->delivery_days ? $offer->delivery_days . ' дн.' : '—' }}
                    </td>
                    <td style="padding: var(--space-3); font-size: var(--text-sm); color: var(--gray-600);">
                        {{ $offer->payment_terms ?? '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div style="background: white; border-radius: var(--radius-lg); padding: var(--space-8); text-align: center; box-shadow: var(--shadow-sm);">
        <p style="color: var(--gray-500);">Информация о предложениях недоступна</p>
    </div>
    @endif
    @endif

    <!-- Кнопка назад -->
    <div style="margin-top: var(--space-6);">
        <a href="{{ route('catalog.index') }}" style="display: inline-block; padding: var(--space-3) var(--space-4); background: var(--gray-100); color: var(--gray-700); border-radius: var(--radius-md); text-decoration: none; font-weight: 500;">
            ← Вернуться в каталог
        </a>
    </div>
</div>
@endsection
