<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reports\Sender;
use App\Services\Senders\BulkSenderImporter;
use App\Services\Senders\SenderWizardImporter;
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
            'activeTab' => 'manual',
        ]);
    }

    /**
     * Помощник: список «email password» + общие SMTP/IMAP + выгрузка организаций.
     * Организация берётся из Excel (неиспользованная по ИНН), остальное — LLM.
     */
    public function wizard(Request $request, SenderWizardImporter $wizard): View
    {
        $validated = $request->validate([
            'credentials' => 'required|string|max:100000',
            'smtp' => 'required|string|max:255',
            'imap' => 'required|string|max:255',
            'smtp_enc' => 'nullable|in:none,ssl,tls',
            'imap_enc' => 'nullable|in:none,ssl,tls',
            'excel' => 'required|file|mimes:xlsx|max:10240',
        ], [
            'credentials.required' => 'Вставьте хотя бы одну учётку «email password».',
            'smtp.required' => 'Укажите SMTP-сервер (host:port).',
            'imap.required' => 'Укажите IMAP-сервер (host:port).',
            'excel.required' => 'Загрузите выгрузку организаций (.xlsx).',
            'excel.mimes' => 'Файл должен быть в формате .xlsx.',
        ]);

        $summary = $wizard->import(
            $validated['credentials'],
            $validated['smtp'],
            $validated['imap'],
            $request->file('excel')->getRealPath(),
            $validated['smtp_enc'] ?? null,
            $validated['imap_enc'] ?? null,
        );

        return view('admin.senders.create', [
            'totalSenders' => $this->safeCount(),
            'summary' => $summary,
            'wizardInput' => [
                'credentials' => $validated['credentials'],
                'smtp' => $validated['smtp'],
                'imap' => $validated['imap'],
                'smtp_enc' => $validated['smtp_enc'] ?? null,
                'imap_enc' => $validated['imap_enc'] ?? null,
            ],
            'activeTab' => 'wizard',
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
