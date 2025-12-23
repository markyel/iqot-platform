<?php

namespace App\Http\Controllers;

use App\Models\Request;
use App\Models\Supplier;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\View\View;

class CabinetController extends Controller
{
    /**
     * Дашборд личного кабинета
     */
    public function dashboard(): View
    {
        $user = auth()->user();
        
        $stats = [
            'total_requests' => $user->requests()->count(),
            'active_requests' => $user->requests()->whereIn('status', ['pending', 'sending', 'collecting'])->count(),
            'completed_requests' => $user->requests()->where('status', 'completed')->count(),
            'total_reports' => $user->reports()->count(),
        ];

        $recentRequests = $user->requests()
            ->with(['items', 'offers'])
            ->latest()
            ->take(5)
            ->get();

        $recentReports = $user->reports()
            ->where('status', 'ready')
            ->latest()
            ->take(5)
            ->get();

        return view('cabinet.dashboard', compact('stats', 'recentRequests', 'recentReports'));
    }

    /**
     * Список заявок
     */
    public function requests(HttpRequest $request): View
    {
        $user = auth()->user();
        
        $query = $user->requests()->with(['items']);

        // Фильтр по статусу
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Поиск
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', "%{$request->search}%")
                  ->orWhere('title', 'like', "%{$request->search}%");
            });
        }

        $requests = $query->latest()->paginate(15);

        return view('cabinet.requests.index', compact('requests'));
    }

    /**
     * Просмотр заявки
     */
    public function showRequest(Request $request): View
    {
        $this->authorize('view', $request);
        
        $request->load(['items.offers.supplier', 'suppliers', 'report']);

        return view('cabinet.requests.show', compact('request'));
    }

    /**
     * Создание заявки
     */
    public function createRequest(HttpRequest $httpRequest)
    {
        $validated = $httpRequest->validate([
            'title' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.article' => 'nullable|string|max:100',
            'items.*.brand' => 'nullable|string|max:100',
            'items.*.quantity' => 'nullable|integer|min:1',
        ]);

        $request = auth()->user()->requests()->create([
            'code' => Request::generateCode(),
            'title' => $validated['title'] ?? 'Заявка от ' . now()->format('d.m.Y'),
            'status' => Request::STATUS_DRAFT,
            'items_count' => count($validated['items']),
        ]);

        foreach ($validated['items'] as $itemData) {
            $request->items()->create($itemData);
        }

        return redirect()
            ->route('cabinet.requests.show', $request)
            ->with('success', 'Заявка создана');
    }

    /**
     * Список поставщиков
     */
    public function suppliers(HttpRequest $request): View
    {
        $query = Supplier::active();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('brand')) {
            $query->whereJsonContains('brands', $request->brand);
        }

        $suppliers = $query->orderBy('rating', 'desc')->paginate(20);

        return view('cabinet.suppliers.index', compact('suppliers'));
    }

    /**
     * Настройки профиля
     */
    public function settings(): View
    {
        return view('cabinet.settings', [
            'user' => auth()->user(),
        ]);
    }

    /**
     * Обновление настроек
     */
    public function updateSettings(HttpRequest $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        auth()->user()->update($validated);

        return back()->with('success', 'Настройки сохранены');
    }
}
