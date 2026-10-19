<?php

namespace App\Services\Reports;

use App\Models\ExternalRequest;
use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF-отчёт по заявке (позиции, предложения поставщиков с контактами, сравнение цен) — dompdf из reports.
 * Заменяет n8n-воркфлоу «report-management» + вебхук report-ready-pdf (n8n выключен 10.2026).
 * Пишет готовую строку reports (status=ready, pdf_content base64) — скачивание в контроллерах не менялось.
 */
class RequestPdfReport
{
    public const TTL_DAYS = 30;

    /**
     * @param int $mainRequestId id в reports.requests
     * @param int $localRequestId reports.request_id в iqot (у админки — тот же id заявки reports, у кабинета — id локальной заявки)
     */
    public function generate(int $mainRequestId, int $localRequestId, ?int $userId, string $customerName): Report
    {
        $externalRequest = ExternalRequest::with([
            'items' => fn ($q) => $q->orderBy('position_number'),
            'items.offers' => fn ($q) => $q->whereIn('status', ['received', 'processed'])->whereNotNull('price_per_unit')
                ->orderByRaw('CASE WHEN currency = "RUB" THEN price_per_unit ELSE price_per_unit * 100 END')->orderBy('price_per_unit'),
            'items.offers.supplier',
        ])->findOrFail($mainRequestId);

        $items = $externalRequest->items;
        $offers = $items->flatMap->offers;
        $pdf = Pdf::loadView('reports.request-pdf', [
            'r' => $externalRequest,
            'customerName' => $customerName,
            'generatedAt' => now('Europe/Moscow'),
        ])->setPaper('a4', 'landscape');

        Report::where('request_id', $localRequestId)->whereNotNull('pdf_content')->delete();
        $report = Report::create([
            'request_id' => $localRequestId,
            'user_id' => $userId,
            'code' => 'PDF-' . now('Europe/Moscow')->format('Ymd') . '-' . str_pad((string) $mainRequestId, 6, '0', STR_PAD_LEFT),
            'title' => "PDF отчет по заявке {$externalRequest->request_number}",
            'type' => 'single',
            'report_type' => 'request',
            'status' => 'ready',
            'items_count' => $items->count(),
            'items_with_offers' => $items->filter(fn ($i) => $i->offers->isNotEmpty())->count(),
            'suppliers_responded' => $offers->pluck('supplier_id')->unique()->count(),
            'total_offers' => $offers->count(),
            'file_path' => 'report-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $externalRequest->request_number) . '.pdf',
            'pdf_content' => base64_encode($pdf->output()),
            'pdf_expires_at' => now()->addDays(self::TTL_DAYS),
            'generated_at' => now(),
        ]);

        return $report;
    }
}
