<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Report;
use App\Models\Request;
use App\Models\RequestItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;

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
}
