<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalRequest;
use App\Models\ExternalRequestItem;
use Illuminate\Http\Request;

class ExternalRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = ExternalRequest::with(['items' => function ($query) {
            $query->orderBy('position_number')->limit(5);
        }])->orderBy('created_at', 'desc');

        // Фильтр по статусу
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Фильтр по типу заявки
        if ($request->filled('is_customer_request')) {
            $query->where('is_customer_request', $request->is_customer_request);
        }

        $requests = $query->paginate(20)->withQueryString();

        return view('admin.external-requests.index', compact('requests'));
    }

    public function show(ExternalRequest $externalRequest)
    {
        // Загружаем позиции с предложениями и поставщиками
        $externalRequest->load([
            'items' => function ($query) {
                $query->orderBy('position_number');
            },
            'items.offers' => function ($query) {
                $query->whereIn('status', ['received', 'processed'])
                      ->whereNotNull('price_per_unit')
                      ->where('price_per_unit', '>', 0);
            },
            'items.offers.supplier'
        ]);

        // Сортируем предложения для каждой позиции по цене в рублях
        foreach ($externalRequest->items as $item) {
            $item->setRelation('offers', $item->offers->sortBy(function ($offer) {
                return $offer->price_per_unit_in_rub;
            })->values());
        }

        return view('admin.external-requests.show', compact('externalRequest'));
    }

    public function items(Request $request)
    {
        $query = ExternalRequestItem::with(['request'])
            ->orderBy('created_at', 'desc');

        // Фильтр по названию
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Фильтр по бренду
        if ($request->filled('brand')) {
            $query->where('brand', 'like', '%' . $request->brand . '%');
        }

        // Фильтр по статусу
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Фильтр - только с предложениями
        if ($request->filled('has_offers') && $request->has_offers) {
            $query->where('offers_count', '>', 0);
        }

        $items = $query->paginate(50)->withQueryString();

        return view('admin.external-requests.items', compact('items'));
    }

    public function itemShow(ExternalRequestItem $item)
    {
        $item->load([
            'request',
            'offers' => function ($query) {
                $query->whereIn('status', ['received', 'processed'])
                      ->whereNotNull('price_per_unit')
                      ->where('price_per_unit', '>', 0);
            },
            'offers.supplier'
        ]);

        // Сортируем предложения по цене за единицу в рублях (через accessor)
        $item->setRelation('offers', $item->offers->sortBy(function ($offer) {
            return $offer->price_per_unit_in_rub;
        })->values());

        return view('admin.external-requests.item-show', compact('item'));
    }
}
