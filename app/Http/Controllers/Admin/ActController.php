<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Act;
use App\Models\User;
use App\Services\ActGenerationService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ActController extends Controller
{
    protected ActGenerationService $actService;

    public function __construct(ActGenerationService $actService)
    {
        $this->actService = $actService;
    }

    /**
     * Список всех актов
     */
    public function index(Request $request)
    {
        $query = Act::with('user')->orderBy('act_date', 'desc')->orderBy('id', 'desc');

        // Фильтр по статусу
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Фильтр по периоду
        if ($request->filled('year')) {
            $query->where('period_year', $request->year);
        }
        if ($request->filled('month')) {
            $query->where('period_month', $request->month);
        }

        // Поиск по номеру или пользователю
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        $acts = $query->paginate(20);

        // Статистика
        $stats = [
            'total' => Act::count(),
            'draft' => Act::where('status', 'draft')->count(),
            'generated' => Act::where('status', 'generated')->count(),
            'sent' => Act::where('status', 'sent')->count(),
            'signed' => Act::where('status', 'signed')->count(),
        ];

        return view('admin.billing.acts.index', compact('acts', 'stats'));
    }

    /**
     * Форма генерации актов за период
     */
    public function create()
    {
        return view('admin.billing.acts.create');
    }

    /**
     * Генерация акта за период для пользователя
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $user = User::findOrFail($validated['user_id']);
        
        $act = $this->actService->generateForPeriod($user, $validated['year'], $validated['month']);

        if (!$act) {
            return redirect()->back()->with('error', 'Нет списаний за указанный период для формирования акта');
        }

        return redirect()->route('admin.billing.acts.show', $act->id)
            ->with('success', "Акт #{$act->number} успешно сформирован");
    }

    /**
     * Просмотр акта
     */
    public function show($id)
    {
        $act = Act::with(['user', 'items'])->findOrFail($id);

        return view('admin.billing.acts.show', compact('act'));
    }

    /**
     * Скачивание акта в PDF
     */
    public function download($id)
    {
        $act = Act::with(['user', 'items'])->findOrFail($id);

        $seller = \App\Models\BillingSettings::current();
        $buyer = $act->user;

        $pdf = Pdf::loadView('acts.period-act', compact('act', 'seller', 'buyer'));

        return $pdf->download('Акт_' . $act->number . '.pdf');
    }

    /**
     * Список актов пользователя
     */
    public function userActs(User $user)
    {
        $acts = Act::where('user_id', $user->id)
            ->with('items')
            ->orderBy('act_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        // Статистика
        $stats = [
            'total' => Act::where('user_id', $user->id)->count(),
            'generated' => Act::where('user_id', $user->id)->where('status', 'generated')->count(),
            'sent' => Act::where('user_id', $user->id)->where('status', 'sent')->count(),
            'signed' => Act::where('user_id', $user->id)->where('status', 'signed')->count(),
            'total_amount' => Act::where('user_id', $user->id)->whereIn('status', ['generated', 'sent', 'signed'])->sum('total'),
        ];

        return view('admin.users.acts', compact('user', 'acts', 'stats'));
    }
}
