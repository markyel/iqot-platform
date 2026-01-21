@extends('layouts.app')

@section('content')
<div style="padding: var(--space-6); max-width: 1400px; margin: 0 auto;">
    <!-- Заголовок -->
    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--text-3xl); font-weight: 700; margin-bottom: var(--space-2);">
            Каталог товаров
        </h1>
        <p style="color: var(--gray-600); font-size: var(--text-base);">
            Товары с готовыми предложениями от поставщиков
        </p>
    </div>

    <!-- Категории вверху -->
    @if($categories->isNotEmpty())
    <div style="background: white; border-radius: var(--radius-lg); padding: var(--space-4); margin-bottom: var(--space-6); box-shadow: var(--shadow-sm);">
        <h3 style="font-size: var(--text-lg); font-weight: 600; margin-bottom: var(--space-3);">Категории</h3>
        <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
            @foreach($categories as $category)
            <a href="{{ route('catalog.index', ['product_type' => $category->product_type_id]) }}"
               style="padding: var(--space-2) var(--space-3); background: {{ request('product_type') == $category->product_type_id ? 'var(--primary-600)' : 'var(--gray-100)' }}; color: {{ request('product_type') == $category->product_type_id ? 'white' : 'var(--gray-700)' }}; border-radius: var(--radius-md); text-decoration: none; font-size: var(--text-sm); transition: all 0.2s;">
                {{ $category->product_type_name }} ({{ $category->items_count }})
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Поиск и фильтры -->
    <div style="background: white; border-radius: var(--radius-lg); padding: var(--space-4); margin-bottom: var(--space-6); box-shadow: var(--shadow-sm);">
        <form method="GET" action="{{ route('catalog.index') }}">
            <div style="display: grid; grid-template-columns: 1fr auto; gap: var(--space-3); margin-bottom: var(--space-3);">
                <input type="text" name="search" placeholder="Поиск по названию, бренду, артикулу..." value="{{ $filters['search'] ?? '' }}" style="padding: var(--space-3); border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: var(--text-base);">
                <button type="submit" style="padding: var(--space-3) var(--space-4); background: var(--primary-600); color: white; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer;">Найти</button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-3);">
                <select name="domain" style="padding: var(--space-2); border: 1px solid var(--gray-300); border-radius: var(--radius-md);">
                    <option value="">Все области применения</option>
                    @foreach($applicationDomains as $id => $name)
                    <option value="{{ $id }}" {{ ($filters['domain'] ?? '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                @if(request('product_type') || request('domain') || request('search'))
                <a href="{{ route('catalog.index') }}" style="padding: var(--space-2); text-align: center; color: var(--gray-700); text-decoration: none; border: 1px solid var(--gray-300); border-radius: var(--radius-md);">Сбросить</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Список товаров -->
    @if($items->isEmpty())
    <div style="background: white; border-radius: var(--radius-lg); padding: var(--space-8); text-align: center; box-shadow: var(--shadow-sm);">
        <p style="color: var(--gray-500); font-size: var(--text-lg);">Товаров не найдено</p>
    </div>
    @else
    <div style="background: white; border-radius: var(--radius-lg); padding: var(--space-4); box-shadow: var(--shadow-sm);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--gray-200);">
                    <th style="padding: var(--space-3); text-align: left; font-weight: 600;">Название</th>
                    <th style="padding: var(--space-3); text-align: left; font-weight: 600;">Марка</th>
                    <th style="padding: var(--space-3); text-align: left; font-weight: 600;">Артикул</th>
                    <th style="padding: var(--space-3); text-align: left; font-weight: 600;">Категория</th>
                    <th style="padding: var(--space-3); text-align: left; font-weight: 600;">Область применения</th>
                    <th style="padding: var(--space-3); text-align: center; font-weight: 600;">Предложений</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr style="border-bottom: 1px solid var(--gray-100); transition: background 0.2s;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='white'">
                    <td style="padding: var(--space-3);">
                        <a href="{{ route('catalog.show', $item->id) }}" style="color: var(--primary-600); text-decoration: none; font-weight: 500;">{{ $item->name }}</a>
                    </td>
                    <td style="padding: var(--space-3); color: var(--gray-700);">{{ $item->brand ?? '—' }}</td>
                    <td style="padding: var(--space-3); color: var(--gray-700); font-family: 'JetBrains Mono', monospace;">{{ $item->article ?? '—' }}</td>
                    <td style="padding: var(--space-3); color: var(--gray-600); font-size: var(--text-sm);">{{ $item->category ?? '—' }}</td>
                    <td style="padding: var(--space-3); color: var(--gray-600); font-size: var(--text-sm);">{{ $item->domain_name ?? '—' }}</td>
                    <td style="padding: var(--space-3); text-align: center;">
                        <span style="display: inline-block; padding: var(--space-1) var(--space-2); background: var(--success-100); color: var(--success-700); border-radius: var(--radius-md); font-weight: 600; font-size: var(--text-sm);">
                            {{ $item->offers_count }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Пагинация -->
    <div style="margin-top: var(--space-4);">
        {{ $items->links() }}
    </div>
    @endif
</div>
@endsection
