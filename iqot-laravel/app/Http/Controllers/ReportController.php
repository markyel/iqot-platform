<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /**
     * Список отчётов
     */
    public function index(Request $request): View
    {
        $reports = auth()->user()->reports()
            ->with('request')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15);

        return view('cabinet.reports.index', compact('reports'));
    }

    /**
     * Просмотр отчёта
     */
    public function show(Report $report): View
    {
        $this->authorize('view', $report);
        
        $report->load(['request.items.offers.supplier']);

        return view('cabinet.reports.show', compact('report'));
    }

    /**
     * Скачивание отчёта (PDF)
     */
    public function download(Report $report): BinaryFileResponse
    {
        $this->authorize('view', $report);

        if (!$report->hasFile()) {
            abort(404, 'Файл отчёта не найден');
        }

        $filePath = storage_path('app/' . $report->file_path);
        $fileName = "Отчет_{$report->code}.pdf";

        return response()->download($filePath, $fileName);
    }
}
