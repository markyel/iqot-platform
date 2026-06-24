<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reports\Sender;
use App\Services\Senders\BulkSenderImporter;
use App\Services\Senders\SenderAddressGenerator;
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

    /**
     * Генератор адресов, шаг 1: выгрузка организаций + список доменов →
     * кандидаты (email/пароль/организация) для предпросмотра с чекбоксами.
     */
    public function generate(Request $request, SenderAddressGenerator $generator): View
    {
        $validated = $request->validate([
            'domains' => 'required|string|max:5000',
            'excel' => 'required|file|mimes:xlsx|max:10240',
        ], [
            'domains.required' => 'Укажите хотя бы один домен почты.',
            'excel.required' => 'Загрузите выгрузку организаций (.xlsx).',
            'excel.mimes' => 'Файл должен быть в формате .xlsx.',
        ]);

        $domains = preg_split('/[\s,;]+/', $validated['domains']) ?: [];
        $generated = $generator->generate($request->file('excel')->getRealPath(), $domains);

        return view('admin.senders.create', [
            'totalSenders' => $this->safeCount(),
            'generated' => $generated,
            'generatorInput' => ['domains' => $validated['domains']],
            'activeTab' => 'generator',
        ]);
    }

    /**
     * Генератор адресов, шаг 2: добавить отмеченные галочками адреса в систему,
     * затем показать список email/паролей с группировкой по домену.
     */
    public function generateStore(Request $request, BulkSenderImporter $importer): View
    {
        $validated = $request->validate([
            'blocks' => 'required|array',
            'selected' => 'required|array|min:1',
        ], [
            'selected.required' => 'Отметьте хотя бы один адрес для добавления.',
            'selected.min' => 'Отметьте хотя бы один адрес для добавления.',
        ]);

        $blocks = [];
        $byKey = [];
        foreach ($validated['selected'] as $key) {
            $raw = $request->input('blocks.' . $key);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($data) || empty($data['email'])) {
                continue;
            }

            $block = array_filter([
                'email' => $data['email'],
                'password' => $data['password'] ?? '',
                'user' => $data['email'],
                'smtp' => $data['smtp'] ?? 'smtp.beget.com:465',
                'imap' => $data['imap'] ?? 'imap.beget.com:993',
                'company' => $data['company'] ?? null,
                'inn' => $data['inn'] ?? null,
                'kpp' => $data['kpp'] ?? null,
                'ogrn' => $data['ogrn'] ?? null,
                'address' => $data['address'] ?? null,
                'company_phone' => $data['company_phone'] ?? null,
                'director' => $data['director'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');

            $blocks[] = $block;
            $byKey[mb_strtolower($data['email'])] = $data['password'] ?? '';
        }

        $summary = $importer->importBlocks($blocks);

        // Список «email — пароль» с группировкой по домену только для созданных.
        $credentialsByDomain = [];
        foreach ($summary['rows'] as $row) {
            if (($row['status'] ?? '') !== 'created') {
                continue;
            }
            $email = (string) ($row['email'] ?? '');
            $domain = strstr($email, '@') ? ltrim(strstr($email, '@'), '@') : '—';
            $credentialsByDomain[$domain][] = [
                'email' => $email,
                'password' => $byKey[mb_strtolower($email)] ?? '',
            ];
        }
        ksort($credentialsByDomain);

        return view('admin.senders.create', [
            'totalSenders' => $this->safeCount(),
            'summary' => $summary,
            'credentialsByDomain' => $credentialsByDomain,
            'activeTab' => 'generator',
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
