<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Report;
use App\Models\Request;
use App\Models\RequestItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Обновление статуса заявки от n8n
     */
    public function requestUpdate(HttpRequest $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'request_code' => 'required|string',
            'status' => 'required|string',
            'suppliers_count' => 'nullable|integer',
            'offers_count' => 'nullable|integer',
        ]);

        $request = Request::where('code', $validated['request_code'])->firstOrFail();

        $updateData = ['status' => $validated['status']];

        if (isset($validated['suppliers_count'])) {
            $updateData['suppliers_count'] = $validated['suppliers_count'];
        }
        if (isset($validated['offers_count'])) {
            $updateData['offers_count'] = $validated['offers_count'];
        }

        // Устанавливаем временные метки
        if ($validated['status'] === Request::STATUS_SENDING && !$request->collection_started_at) {
            $updateData['collection_started_at'] = now();
        }
        if ($validated['status'] === Request::STATUS_COMPLETED) {
            $updateData['collection_ended_at'] = now();
        }

        $request->update($updateData);

        return response()->json(['success' => true]);
    }

    /**
     * Получено новое КП от поставщика
     */
    public function offerReceived(HttpRequest $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'request_code' => 'required|string',
            'item_id' => 'required|integer',
            'supplier_id' => 'required|integer',
            'quantity' => 'nullable|integer',
            'price' => 'required|numeric',
            'total_price' => 'nullable|numeric',
            'currency' => 'nullable|string|max:3',
            'vat_included' => 'nullable|boolean',
            'delivery_days' => 'nullable|integer',
            'payment_terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'source_type' => 'nullable|string',
            'raw_data' => 'nullable|array',
        ]);

        $request = Request::where('code', $validated['request_code'])->firstOrFail();
        $item = RequestItem::findOrFail($validated['item_id']);

        // Создаём или обновляем предложение
        $offer = Offer::updateOrCreate(
            [
                'request_id' => $request->id,
                'request_item_id' => $item->id,
                'supplier_id' => $validated['supplier_id'],
            ],
            [
                'quantity' => $validated['quantity'] ?? $item->quantity,
                'price' => $validated['price'],
                'total_price' => $validated['total_price'] ?? $validated['price'] * ($validated['quantity'] ?? $item->quantity),
                'currency' => $validated['currency'] ?? 'RUB',
                'vat_included' => $validated['vat_included'] ?? false,
                'delivery_days' => $validated['delivery_days'],
                'payment_terms' => $validated['payment_terms'],
                'notes' => $validated['notes'],
                'source_type' => $validated['source_type'] ?? 'email',
                'raw_data' => $validated['raw_data'],
            ]
        );

        // Пересчитываем статистику позиции
        $item->recalculatePrices();

        // Обновляем счётчик в заявке
        $request->update([
            'offers_count' => $request->offers()->count(),
        ]);

        return response()->json([
            'success' => true,
            'offer_id' => $offer->id,
        ]);
    }

    /**
     * Отчёт готов
     */
    public function reportReady(HttpRequest $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'request_code' => 'required|string',
            'file_path' => 'nullable|string',
            'summary' => 'nullable|array',
        ]);

        $request = Request::where('code', $validated['request_code'])->firstOrFail();

        // Создаём или обновляем отчёт
        $report = Report::updateOrCreate(
            ['request_id' => $request->id],
            [
                'user_id' => $request->user_id,
                'code' => 'RPT-' . substr($request->code, 4), // REQ -> RPT
                'title' => "Отчёт по заявке {$request->code}",
                'type' => Report::TYPE_SINGLE,
                'status' => Report::STATUS_READY,
                'items_count' => $request->items_count,
                'items_with_offers' => $request->items()->whereHas('offers')->count(),
                'suppliers_contacted' => $request->suppliers_count,
                'suppliers_responded' => $request->suppliers()->wherePivot('status', 'responded')->count(),
                'total_offers' => $request->offers_count,
                'summary' => $validated['summary'],
                'file_path' => $validated['file_path'],
                'generated_at' => now(),
            ]
        );

        // Помечаем заявку как завершённую
        $request->update(['status' => Request::STATUS_COMPLETED]);

        // TODO: Отправить уведомление пользователю

        return response()->json([
            'success' => true,
            'report_id' => $report->id,
        ]);
    }

    /**
     * Статус email-рассылки
     */
    public function emailStatus(HttpRequest $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'request_code' => 'required|string',
            'supplier_id' => 'required|integer',
            'status' => 'required|string|in:sent,delivered,opened,responded,bounced',
        ]);

        $request = Request::where('code', $validated['request_code'])->firstOrFail();

        $pivotData = ['status' => $validated['status']];

        if ($validated['status'] === 'sent') {
            $pivotData['sent_at'] = now();
        }
        if ($validated['status'] === 'responded') {
            $pivotData['responded_at'] = now();
        }

        $request->suppliers()->updateExistingPivot($validated['supplier_id'], $pivotData);

        return response()->json(['success' => true]);
    }

    /**
     * PDF отчёт готов (от n8n Report Management API)
     */
    public function pdfReportReady(HttpRequest $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'event' => 'required|string',
            'report_id' => 'required|integer',
            'status' => 'required|in:completed,failed',
            'request_ids' => 'required|array',
            'user_id' => 'required|integer',
            'file' => 'nullable|array',
            'file.filename' => 'nullable|string',
            'file.content_base64' => 'nullable|string',
            'file.mime_type' => 'nullable|string',
            'file.size_bytes' => 'nullable|integer',
            'metadata' => 'nullable|array',
            'error' => 'nullable|string',
            'message' => 'nullable|string',
        ]);

        try {
            if ($validated['status'] === 'completed' && isset($validated['file'])) {
                // Декодируем PDF
                $pdfContent = base64_decode($validated['file']['content_base64']);
                $filename = $validated['file']['filename'];

                // Сохраняем PDF
                $path = "reports/{$validated['user_id']}/{$filename}";
                Storage::disk('local')->put($path, $pdfContent);

                // Вычисляем срок истечения (7 дней)
                $expiresAt = now()->addDays(7);

                // Формируем код отчета как в контроллере
                $reportCode = 'PDF-' . date('Ymd') . '-' . str_pad($validated['report_id'], 6, '0', STR_PAD_LEFT);

                Log::info('PDF webhook received', [
                    'report_id' => $validated['report_id'],
                    'user_id' => $validated['user_id'],
                    'generated_code' => $reportCode,
                ]);

                // Обновляем или создаём запись по n8n_report_id
                $report = Report::updateOrCreate(
                    ['n8n_report_id' => $validated['report_id']],
                    [
                        'user_id' => $validated['user_id'],
                        'title' => 'PDF отчет',
                        'status' => 'ready',
                        'file_path' => $path,
                        'pdf_content' => $validated['file']['content_base64'], // Сохраняем base64
                        'pdf_expires_at' => $expiresAt,
                        'items_count' => $validated['metadata']['items_total'] ?? null,
                        'items_with_offers' => $validated['metadata']['items_with_offers'] ?? null,
                        'suppliers_contacted' => $validated['metadata']['suppliers_responded'] ?? null,
                        'suppliers_responded' => $validated['metadata']['suppliers_responded'] ?? null,
                        'summary' => [
                            'completion_percentage' => $validated['metadata']['completion_percentage'] ?? null,
                            'requests_count' => $validated['metadata']['requests_count'] ?? null,
                        ],
                        'generated_at' => $validated['metadata']['generated_at'] ?? now(),
                        'error_code' => null,
                        'error_message' => null,
                    ]
                );

                Log::info('PDF report ready', [
                    'report_id' => $validated['report_id'],
                    'user_id' => $validated['user_id'],
                    'filename' => $filename,
                ]);

            } elseif ($validated['status'] === 'failed') {
                // Сохраняем информацию об ошибке
                $report = Report::updateOrCreate(
                    ['id' => $validated['report_id']],
                    [
                        'user_id' => $validated['user_id'],
                        'status' => 'error',
                        'error_code' => $validated['error'] ?? 'UNKNOWN',
                        'error_message' => $validated['message'] ?? 'Неизвестная ошибка',
                    ]
                );

                Log::error('PDF report generation failed', [
                    'report_id' => $validated['report_id'],
                    'error' => $validated['error'] ?? 'UNKNOWN',
                    'message' => $validated['message'] ?? null,
                ]);
            }

            return response()->json(['received' => true]);

        } catch (\Exception $e) {
            Log::error('Error processing PDF report webhook', [
                'report_id' => $validated['report_id'] ?? null,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'received' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
