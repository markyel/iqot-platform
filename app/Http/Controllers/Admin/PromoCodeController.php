<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromoCodeController extends Controller
{
    /**
     * Список промокодов
     */
    public function index(Request $request)
    {
        $query = PromoCode::with('usedByUser');

        // Фильтр по статусу
        if ($request->has('status')) {
            if ($request->status === 'used') {
                $query->where('is_used', true);
            } elseif ($request->status === 'available') {
                $query->where('is_used', false);
            }
        }

        // Поиск по коду
        if ($request->has('search') && $request->search) {
            $query->where('code', 'like', '%' . $request->search . '%');
        }

        $promoCodes = $query->orderBy('created_at', 'desc')->paginate(50);

        $stats = [
            'total' => PromoCode::count(),
            'used' => PromoCode::where('is_used', true)->count(),
            'available' => PromoCode::where('is_used', false)->count(),
            'total_amount' => PromoCode::sum('amount'),
            'used_amount' => PromoCode::where('is_used', true)->sum('amount'),
        ];

        return view('admin.promo-codes.index', compact('promoCodes', 'stats'));
    }

    /**
     * Форма генерации промокодов
     */
    public function create()
    {
        return view('admin.promo-codes.create');
    }

    /**
     * Генерация промокодов
     */
    public function store(Request $request)
    {
        $request->validate([
            'count' => 'required|integer|min:1|max:1000',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
        ]);

        $promoCodes = [];

        DB::transaction(function () use ($request, &$promoCodes) {
            for ($i = 0; $i < $request->count; $i++) {
                $promoCodes[] = PromoCode::create([
                    'code' => PromoCode::generateCode(),
                    'amount' => $request->amount,
                    'description' => $request->description,
                ]);
            }
        });

        return redirect()
            ->route('admin.promo-codes.index')
            ->with('success', "Успешно сгенерировано {$request->count} промокодов на сумму {$request->amount} ₽ каждый");
    }

    /**
     * Удаление промокода
     */
    public function destroy(PromoCode $promoCode)
    {
        if ($promoCode->is_used) {
            return redirect()
                ->back()
                ->with('error', 'Нельзя удалить использованный промокод');
        }

        $promoCode->delete();

        return redirect()
            ->back()
            ->with('success', 'Промокод удален');
    }

    /**
     * Экспорт промокодов в CSV
     */
    public function export(Request $request)
    {
        $query = PromoCode::query();

        if ($request->has('status')) {
            if ($request->status === 'used') {
                $query->where('is_used', true);
            } elseif ($request->status === 'available') {
                $query->where('is_used', false);
            }
        }

        $promoCodes = $query->orderBy('created_at', 'desc')->get();

        $filename = 'promo-codes-' . date('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($promoCodes) {
            $file = fopen('php://output', 'w');

            // BOM для корректной работы с кириллицей в Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Заголовки
            fputcsv($file, ['Код', 'Сумма', 'Статус', 'Использован', 'Дата использования', 'Описание'], ';');

            // Данные
            foreach ($promoCodes as $promoCode) {
                fputcsv($file, [
                    $promoCode->code,
                    $promoCode->amount,
                    $promoCode->is_used ? 'Использован' : 'Доступен',
                    $promoCode->usedByUser?->email ?? '-',
                    $promoCode->used_at?->format('d.m.Y H:i') ?? '-',
                    $promoCode->description ?? '-',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
