<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportSenderBlockJob;
use App\Models\Reports\Sender;
use App\Services\Senders\BulkSenderImporter;
use App\Services\Senders\SenderAddressGenerator;
use App\Services\Senders\SenderWizardImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
     * Генератор адресов, шаг 2: отмеченные галочками адреса уходят в фоновый
     * батч задач (на каждый адрес — вызов AI), запрос сразу редиректит на
     * страницу статуса. Так массовое добавление не упирается в таймаут прокси.
     */
    public function generateStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'blocks' => 'required|array',
            'selected' => 'required|array|min:1',
        ], [
            'selected.required' => 'Отметьте хотя бы один адрес для добавления.',
            'selected.min' => 'Отметьте хотя бы один адрес для добавления.',
        ]);

        $blocks = [];
        $credentials = [];
        foreach ($validated['selected'] as $key) {
            $raw = $request->input('blocks.' . $key);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($data) || empty($data['email'])) {
                continue;
            }

            $blocks[] = array_filter([
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

            $credentials[] = [
                'email' => (string) $data['email'],
                'password' => (string) ($data['password'] ?? ''),
            ];
        }

        if ($blocks === []) {
            return redirect()
                ->route('admin.senders.import.create')
                ->with('error', 'Не удалось разобрать выбранные адреса.');
        }

        $runId = (string) Str::uuid();

        // Учётки (email/пароль) известны сразу — кладём их в кэш для страницы
        // статуса, чтобы админ мог завести ящики, не дожидаясь конца импорта.
        $credentialsByDomain = $this->groupByDomain($credentials);

        Cache::put("senders_gen:{$runId}:meta", [
            'total' => count($blocks),
            'credentialsByDomain' => $credentialsByDomain,
        ], now()->addDay());

        $jobs = [];
        foreach ($blocks as $i => $block) {
            $jobs[] = new ImportSenderBlockJob($runId, $i, $block);
        }

        $batch = Bus::batch($jobs)
            ->name("senders-gen:{$runId}")
            ->allowFailures()
            ->dispatch();

        Cache::put("senders_gen:{$runId}:batch", $batch->id, now()->addDay());

        return redirect()->route('admin.senders.import.generate.status', ['run' => $runId]);
    }

    /**
     * Страница статуса фонового импорта: прогресс (создано/пропущено/ошибки) и
     * список учёток для заведения ящиков. Обновляется опросом до завершения.
     */
    public function generateStatus(Request $request): View
    {
        $runId = (string) $request->query('run', '');
        $meta = Cache::get("senders_gen:{$runId}:meta");

        if (!is_array($meta)) {
            return view('admin.senders.create', [
                'totalSenders' => $this->safeCount(),
                'activeTab' => 'generator',
                'genStatusMissing' => true,
            ]);
        }

        $total = (int) ($meta['total'] ?? 0);

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $rows = [];
        for ($i = 0; $i < $total; $i++) {
            $row = Cache::get("senders_gen:{$runId}:row:{$i}");
            if (!is_array($row)) {
                continue;
            }
            $rows[] = $row;
            match ($row['status'] ?? '') {
                'created' => $created++,
                'skipped' => $skipped++,
                default => $failed++,
            };
        }

        $processed = count($rows);

        $batchId = Cache::get("senders_gen:{$runId}:batch");
        $batch = $batchId ? Bus::findBatch($batchId) : null;
        $finished = $batch ? $batch->finished() : ($processed >= $total);

        return view('admin.senders.create', [
            'totalSenders' => $this->safeCount(),
            'activeTab' => 'generator',
            'credentialsByDomain' => $meta['credentialsByDomain'] ?? [],
            'genStatus' => [
                'run' => $runId,
                'total' => $total,
                'processed' => $processed,
                'created' => $created,
                'skipped' => $skipped,
                'failed' => $failed,
                'finished' => $finished,
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * Сгруппировать учётки по домену для вывода списком.
     *
     * @param array<int,array{email:string,password:string}> $credentials
     * @return array<string,array<int,array{email:string,password:string}>>
     */
    private function groupByDomain(array $credentials): array
    {
        $byDomain = [];
        foreach ($credentials as $cred) {
            $email = $cred['email'];
            $domain = strstr($email, '@') ? ltrim(strstr($email, '@'), '@') : '—';
            $byDomain[$domain][] = $cred;
        }
        ksort($byDomain);

        return $byDomain;
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
