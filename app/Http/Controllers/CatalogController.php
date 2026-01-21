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
        $query = PublicCatalogItem::published()->withEnoughOffers();

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

        // Сортировка
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');

        if (in_array($sortBy, ['name', 'offers_count', 'min_price', 'created_at'])) {
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
        $item = PublicCatalogItem::published()->withEnoughOffers()->findOrFail($id);

        // Если пользователь авторизован - показываем полную информацию
        $isAuthorized = Auth::check();

        $externalItem = null;
        $offers = collect();

        if ($isAuthorized) {
            // Получаем полную информацию из external БД
            $externalItem = ExternalRequestItem::with(['request', 'offers.supplier'])
                ->find($item->external_item_id);

            if ($externalItem) {
                $offers = $externalItem->offers()
                    ->with('supplier')
                    ->whereNotNull('total_price')
                    ->where('total_price', '>', 0)
                    ->orderBy('total_price', 'asc')
                    ->get();
            }
        }

        return view('catalog.show', [
            'item' => $item,
            'isAuthorized' => $isAuthorized,
            'externalItem' => $externalItem,
            'offers' => $offers,
        ]);
    }
}
