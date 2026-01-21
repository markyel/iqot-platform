<?php

namespace App\Http\Controllers;

use App\Models\PublicCatalogItem;
use App\Models\ProductType;
use App\Models\ApplicationDomain;
use App\Models\ExternalRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CatalogController extends Controller
{
    /**
     * Список товаров в публичном каталоге
     */
    public function index(Request $request)
    {
        $query = PublicCatalogItem::published()->withOffers();

        // Фильтр по типу оборудования
        if ($request->has('product_type') && $request->product_type) {
            $query->byProductType($request->product_type);
        }

        // Фильтр по области применения
        if ($request->has('domain') && $request->domain) {
            $query->byDomain($request->domain);
        }

        // Поиск
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Сортировка (по умолчанию по дате создания позиции - самые свежие первыми)
        $sortBy = $request->get('sort', 'item_created_at');
        $sortDir = $request->get('dir', 'desc');

        if (in_array($sortBy, ['name', 'offers_count', 'min_price', 'item_created_at', 'published_at'])) {
            $query->orderBy($sortBy, $sortDir);
        }

        $items = $query->with(['productType', 'applicationDomain'])
            ->paginate(24)
            ->withQueryString();

        // Получаем категории с количеством товаров
        $categories = PublicCatalogItem::getCategoriesWithCounts();

        // Для фильтров
        $productTypes = ProductType::getActiveForSelect();
        $applicationDomains = ApplicationDomain::getActiveForSelect();

        return view('catalog.index', [
            'items' => $items,
            'categories' => $categories,
            'productTypes' => $productTypes,
            'applicationDomains' => $applicationDomains,
            'filters' => $request->only(['product_type', 'domain', 'search', 'sort', 'dir']),
        ]);
    }

    /**
     * Детальная страница товара
     */
    public function show($id)
    {
        // Быстрая загрузка только из локальной БД (без external запросов)
        $item = PublicCatalogItem::published()->withOffers()->findOrFail($id);

        $isAuthorized = Auth::check();

        // Для авторизованных показываем превью без цен (как в кабинете /items/401)
        // Полный доступ - через покупку в кабинете
        return view('catalog.show', [
            'item' => $item,
            'isAuthorized' => $isAuthorized,
        ]);
    }
}
