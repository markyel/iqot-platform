<?php

namespace App\Services\Senders;

use App\Models\ClientOrganization;

/**
 * Помощник создания отправителей.
 *
 * Админ даёт минимум: список «email password» построчно и общие SMTP/IMAP на
 * всю пачку, плюс загружает выгрузку организаций ExportBase (.xlsx). Для каждой
 * учётки берётся следующая НЕИСПОЛЬЗОВАННАЯ организация из файла (ИНН которой ещё
 * нет в client_organizations); её реквизиты идут в блок, а персону отправителя
 * (ФИО-контакт, отдел, стиль, приветствие, личный телефон) дописывает LLM.
 *
 * Сборка блоков делегируется BulkSenderImporter::importBlocks() — та же логика
 * AI-генерации и вставки, что и у ручной формы.
 */
class SenderWizardImporter
{
    public function __construct(
        private readonly BulkSenderImporter $bulk,
        private readonly OrganizationExcelParser $parser,
    ) {
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, created: int, skipped: int, failed: int}
     */
    public function import(
        string $credentials,
        string $smtp,
        string $imap,
        string $excelPath,
        ?string $smtpEnc = null,
        ?string $imapEnc = null,
    ): array {
        $pairs = $this->parseCredentials($credentials);
        $pool = $this->buildUnusedPool($excelPath);

        // План: каждая учётка → либо готовый блок, либо ошибка ещё до вставки.
        $planned = [];
        $poolIdx = 0;
        foreach ($pairs as [$email, $password]) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $planned[] = ['email' => $email, 'error' => 'Некорректный email'];
                continue;
            }
            if (!isset($pool[$poolIdx])) {
                $planned[] = ['email' => $email, 'error' => 'Нет свободной организации в файле'];
                continue;
            }

            $org = $pool[$poolIdx++];
            $planned[] = [
                'email' => $email,
                'block' => $this->buildBlock($email, $password, $smtp, $imap, $smtpEnc, $imapEnc, $org),
            ];
        }

        // Прогоняем готовые блоки через общий импортёр.
        $blocks = [];
        foreach ($planned as $p) {
            if (isset($p['block'])) {
                $blocks[] = $p['block'];
            }
        }
        $bulkSummary = $this->bulk->importBlocks($blocks);

        // Сшиваем результаты обратно в исходном порядке учёток.
        $rows = [];
        $created = 0;
        $skipped = 0;
        $failed = 0;
        $bi = 0;
        foreach ($planned as $i => $p) {
            if (isset($p['error'])) {
                $rows[] = [
                    'index' => $i + 1,
                    'status' => 'failed',
                    'email' => $p['email'],
                    'message' => $p['error'],
                ];
                $failed++;
                continue;
            }

            $row = $bulkSummary['rows'][$bi++];
            $row['index'] = $i + 1;
            $rows[] = $row;
            match ($row['status']) {
                'created' => $created++,
                'skipped' => $skipped++,
                default => $failed++,
            };
        }

        return [
            'rows' => $rows,
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * Разобрать список «email password» построчно.
     *
     * @return array<int,array{0:string,1:string}>
     */
    private function parseCredentials(string $raw): array
    {
        $pairs = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 2) ?: [];
            $email = trim($parts[0] ?? '');
            $password = isset($parts[1]) ? trim($parts[1]) : '';
            if ($email === '') {
                continue;
            }

            $pairs[] = [$email, $password];
        }

        return $pairs;
    }

    /**
     * Организации из файла, ИНН которых ещё нет в client_organizations.
     * Дубликаты ИНН внутри файла отбрасываются. Порядок сохраняется.
     *
     * @return array<int,array<string,string|null>>
     */
    private function buildUnusedPool(string $excelPath): array
    {
        $orgs = $this->parser->parse($excelPath);

        $existing = ClientOrganization::query()
            ->whereNotNull('inn')
            ->pluck('inn')
            ->map(static fn ($inn) => (string) $inn)
            ->flip();

        $seen = [];
        $pool = [];
        foreach ($orgs as $org) {
            $inn = (string) ($org['inn'] ?? '');
            if ($inn === '' || $existing->has($inn) || isset($seen[$inn])) {
                continue;
            }
            $seen[$inn] = true;
            $pool[] = $org;
        }

        return $pool;
    }

    /**
     * Собрать блок полей для BulkSenderImporter из учётки + организации.
     * Пустые поля организации не передаём — тогда сработает AI-догенерация.
     *
     * @param array<string,string|null> $org
     * @return array<string,string>
     */
    private function buildBlock(
        string $email,
        string $password,
        string $smtp,
        string $imap,
        ?string $smtpEnc,
        ?string $imapEnc,
        array $org,
    ): array {
        $block = [
            'email' => $email,
            'password' => $password,
            'user' => $email,
            'smtp' => $smtp,
            'imap' => $imap,
            'smtp_enc' => $smtpEnc,
            'imap_enc' => $imapEnc,
            'company' => $org['name'] ?? $org['full_name'] ?? null,
            'inn' => $org['inn'] ?? null,
            'kpp' => $org['kpp'] ?? null,
            'ogrn' => $org['ogrn'] ?? null,
            'address' => $org['legal_address'] ?? null,
            'company_phone' => $org['phone'] ?? $org['mobile'] ?? null,
            'director' => $org['director_name'] ?? null,
        ];

        return array_filter(
            $block,
            static fn ($v) => $v !== null && $v !== ''
        );
    }
}
