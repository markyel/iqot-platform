<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reports\Sender;
use App\Services\Senders\BulkSenderImporter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Групповое добавление отправителей рассылки (reports.senders).
 * Админский аналог Telegram-команды /addmail, обрабатывающий пачку блоков.
 */
class SenderImportController extends Controller
{
    public function create(): View
    {
        return view('admin.senders.create', [
            'totalSenders' => $this->safeCount(),
        ]);
    }

    public function store(Request $request, BulkSenderImporter $importer): View
    {
        $validated = $request->validate([
            'senders' => 'required|string|max:100000',
        ], [
            'senders.required' => 'Вставьте хотя бы один блок отправителя.',
        ]);

        $summary = $importer->import($validated['senders']);

        return view('admin.senders.create', [
            'totalSenders' => $this->safeCount(),
            'summary' => $summary,
            'rawInput' => $validated['senders'],
        ]);
    }

    private function safeCount(): ?int
    {
        try {
            return Sender::count();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
