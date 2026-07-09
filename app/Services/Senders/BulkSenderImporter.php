<?php

namespace App\Services\Senders;

use App\Models\ClientOrganization;
use App\Models\Reports\Sender;
use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Групповое добавление отправителей (reports.senders).
 *
 * Аналог Telegram-команды /addmail, но пачкой: админ вставляет несколько блоков
 * вида
 *
 *   email: glavlift@email.ru
 *   smtp: smtp.yandex.ru:465
 *   imap: imap.yandex.ru:993
 *   user: glavlift@email.ru
 *   password: qwerty12345
 *   name: Отдел закупок          (опционально)
 *   fullname: Иванов Иван Иванович (опционально)
 *   phone: +7 (999) 123-45-67    (опционально)
 *   company: ООО Компания         (опционально)
 *   inn: 7701234567              (опционально)
 *   kpp: 770101001               (опционально)
 *   address: г. Москва, ...      (опционально)
 *
 * Блоки разделяются пустой строкой (или строкой «---»).
 *
 * Недостающие поля (ФИО, телефон, стиль письма, реквизиты организации)
 * догенерирует LLM через OpenAI-совместимый прокси (как AI-агент в боте).
 * Для каждого блока создаётся/находится client_organization по company+inn.
 */
class BulkSenderImporter
{
    /** Разрешённые значения enum senders.email_style. */
    private const EMAIL_STYLES = ['formal', 'friendly', 'technical'];

    private const REQUIRED = ['email', 'smtp', 'imap', 'user', 'password'];

    private ?OpenAIClassifierClient $ai;

    /** @var int[] Кэш id активных token_templates. */
    private array $tokenTemplateIds;

    /** @var int[] Кэш id активных email_templates. */
    private array $emailTemplateIds;

    public function __construct(?OpenAIClassifierClient $ai = null)
    {
        $client = $ai ?? OpenAIClassifierClient::fromConfig();
        $this->ai = $client->isConfigured() ? $client : null;
    }

    /**
     * Разобрать сырой текст на блоки «ключ: значение».
     *
     * @return array<int,array<string,string>>
     */
    public function parseBlocks(string $raw): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        // Разделители блоков: пустая строка или строка из дефисов.
        $chunks = preg_split('/\n\s*(?:-{3,}\s*)?\n/', trim($normalized)) ?: [];

        $blocks = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $fields = [];
            foreach (explode("\n", $chunk) as $line) {
                if (!preg_match('/^\s*([A-Za-zА-Яа-я_]+)\s*:\s*(.+?)\s*$/u', $line, $m)) {
                    continue;
                }
                $fields[mb_strtolower($m[1])] = trim($m[2]);
            }

            if ($fields !== []) {
                $blocks[] = $fields;
            }
        }

        return $blocks;
    }

    /**
     * Импортировать все блоки. Каждый отправитель обрабатывается в собственной
     * транзакции — ошибка одного не откатывает остальных.
     *
     * @return array{rows: array<int,array<string,mixed>>, created: int, skipped: int, failed: int}
     */
    public function import(string $raw): array
    {
        return $this->importBlocks($this->parseBlocks($raw));
    }

    /**
     * Импортировать уже разобранные блоки полей. Используется и ручной формой
     * (после parseBlocks), и помощником (блоки собираются из Excel + учёток).
     *
     * @param array<int,array<string,mixed>> $blocks
     * @return array{rows: array<int,array<string,mixed>>, created: int, skipped: int, failed: int}
     */
    public function importBlocks(array $blocks): array
    {
        $this->loadTemplateIds();

        $rows = [];
        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($blocks as $i => $fields) {
            $result = $this->importBlock($fields);
            $result['index'] = $i + 1;
            $rows[] = $result;

            match ($result['status']) {
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
     * @param array<string,string> $fields
     * @return array<string,mixed>
     */
    private function importBlock(array $fields): array
    {
        $email = $fields['email'] ?? '';

        // 1. Проверка обязательных полей.
        $missing = array_values(array_filter(
            self::REQUIRED,
            static fn ($k) => trim((string) ($fields[$k] ?? '')) === ''
        ));
        if ($missing !== []) {
            return [
                'status' => 'failed',
                'email' => $email,
                'message' => 'Нет обязательных полей: ' . implode(', ', $missing),
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'failed',
                'email' => $email,
                'message' => 'Некорректный email',
            ];
        }

        // 2. Дубликат по email.
        if (Sender::where('email', $email)->exists()) {
            return [
                'status' => 'skipped',
                'email' => $email,
                'message' => 'Отправитель с таким email уже существует',
            ];
        }

        [$smtpServer, $smtpPort] = $this->splitHostPort($fields['smtp'], 465);
        [$imapServer, $imapPort] = $this->splitHostPort($fields['imap'], 993);

        try {
            // 3. Догенерация недостающих данных через AI (best-effort).
            $ai = $this->generateWithAi($fields, $smtpServer);

            // 4. Организация: приоритет ввода админа над AI.
            $org = ClientOrganization::findOrCreateForImport([
                'name' => $fields['company'] ?? ($ai['company_name'] ?? null),
                'inn' => $fields['inn'] ?? ($ai['inn'] ?? null),
                'kpp' => $fields['kpp'] ?? ($ai['kpp'] ?? null),
                'ogrn' => $fields['ogrn'] ?? ($ai['ogrn'] ?? null),
                'legal_address' => $fields['address'] ?? ($ai['legal_address'] ?? null),
                'actual_address' => $fields['address'] ?? ($ai['actual_address'] ?? null),
                'contact_person' => $fields['fullname'] ?? $fields['name'] ?? ($ai['sender_full_name'] ?? null),
                // Телефон — ТОЛЬКО из ввода/Excel (реальный), НЕ от AI: мини-модель
                // выдаёт палевные последовательности (123-45-67, 345-67-89 и т.п.).
                'phone' => $this->firstCleanPhone($fields['company_phone'] ?? null),
                'email' => $ai['company_email'] ?? $email,
                'director_name' => $fields['director'] ?? ($ai['director_name'] ?? null),
            ]);

            // 5. Вставка отправителя. Личный телефон ящика НЕ заполняем: в подписи он
            // отключён во всех шаблонах, а номера из Excel — это телефоны реальных
            // сторонних компаний (звонок ушёл бы не туда). Ручной ввод phone — уважаем.
            $phone = $this->firstCleanPhone($fields['phone'] ?? null);

            $sender = DB::connection('reports')->transaction(function () use ($fields, $email, $phone, $smtpServer, $smtpPort, $imapServer, $imapPort, $org, $ai) {
                return Sender::create([
                    'sender_name' => $fields['name'] ?? ($ai['sender_name'] ?? 'Отдел закупок'),
                    'sender_full_name' => $fields['fullname'] ?? ($ai['sender_full_name'] ?? 'Не указано'),
                    'phone' => $phone,
                    'phone_normalized' => $this->normalizePhone($phone),
                    'email' => $email,
                    'smtp_server' => $smtpServer,
                    'smtp_port' => $smtpPort,
                    'smtp_user' => $fields['user'],
                    'smtp_password' => $fields['password'],
                    'smtp_encryption' => $this->encryptionFor($fields['smtp_enc'] ?? null, $smtpPort),
                    'imap_server' => $imapServer,
                    'imap_port' => $imapPort,
                    'imap_user' => $fields['user'],
                    'imap_password' => $fields['password'],
                    'imap_encryption' => $this->encryptionFor($fields['imap_enc'] ?? null, $imapPort),
                    'client_organization_id' => $org->id,
                    'token_template_id' => $this->randomId($this->tokenTemplateIds) ?? 1,
                    'preferred_template_id' => $this->randomId($this->emailTemplateIds),
                    'daily_limit' => 100,
                    'is_active' => true,
                    'is_verified' => false,
                    'email_style' => $this->normalizeEmailStyle($ai['email_style'] ?? null),
                    'email_greeting' => $ai['email_greeting'] ?? 'Здравствуйте',
                    'token_style' => 'technical',
                ]);
            });

            return [
                'status' => 'created',
                'email' => $email,
                'sender_id' => $sender->id,
                'organization' => $org->name,
                'organization_id' => $org->id,
                'ai_used' => $ai !== [],
                'message' => 'Отправитель создан',
            ];
        } catch (Throwable $e) {
            Log::error('BulkSenderImporter: import failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'email' => $email,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Догенерировать недостающие поля отправителя и организации через LLM.
     * Возвращает [] если AI недоступен или вернул ошибку (тогда сработают дефолты).
     *
     * @param array<string,string> $fields
     * @return array<string,mixed>
     */
    private function generateWithAi(array $fields, string $smtpServer): array
    {
        if ($this->ai === null) {
            return [];
        }

        try {
            $decoded = $this->ai->jsonCompletion(
                $this->ai->modelMini(),
                'Ты — система генерации реалистичных данных корпоративных email-отправителей в России. Возвращай только валидный JSON.',
                $this->buildAiPrompt($fields),
                700,
            );

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            Log::warning('BulkSenderImporter: AI generation failed, using defaults', [
                'email' => $fields['email'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Промпт, портированный из n8n workflow (узел «PREPARE AI PROMPT»).
     *
     * @param array<string,string> $fields
     */
    private function buildAiPrompt(array $fields): string
    {
        $email = $fields['email'] ?? '';
        $emailLocal = $email !== '' ? explode('@', $email)[0] : 'user';
        $emailDomain = $email !== '' ? (explode('@', $email)[1] ?? 'example.com') : 'example.com';
        $company = $fields['company'] ?? '';
        $address = $fields['address'] ?? '';

        return <<<PROMPT
Дано:
- Email: {$email}
- Локальная часть: {$emailLocal}
- Домен: {$emailDomain}
- Название компании: {$company}
- Адрес (если есть): {$address}

ЗАДАЧА: сгенерируй реалистичные данные отправителя и его организации (Россия).

Требования к отправителю:
1. sender_name — краткое название (например «Отдел закупок», «Тендерный отдел»).
2. sender_full_name — обычно полное ФИО (Фамилия Имя Отчество), но НЕ всегда.
   - Если локальная часть «{$emailLocal}» похожа на имя, фамилию или их часть
     (например «segio» → Сергей, «baev» → Баев, «evgen» → Евгений, «a.petrov» → Петров,
     «i.ivanova» → Иванова), то это ДОЛЖНО быть ФИО, перекликающееся с ней: бери намёк
     из локальной части и достраивай реалистичные имя/отчество (учитывай род).
   - Если локальная часть обезличена (например «info», «sales», «zakupki», «snab»,
     «office123»), то ПРИМЕРНО в половине случаев вместо ФИО используй название отдела
     или должность, по возможности с привязкой к компании (например
     «Отдел снабжения {$company}», «Тендерный отдел», «Отдел закупок», «Служба снабжения»);
     в остальных случаях придумай живое ФИО.
   Имена бери ЖИВЫЕ и РАЗНООБРАЗНЫЕ — не используй шаблонные
   «Иванов Иван Иванович» / «Сидоров Алексей Иванович».
3. phone — прямой телефон с добавочным, формат +7 (XXX) XXX-XX-XX, доб.XX.
   Код города бери из адреса, если он указан. Цифры случайные, без повторов/последовательностей.
4. email_style — одно из: "formal", "friendly", "technical" (выбирай случайно, не всегда formal).
5. email_greeting — одно из: "Здравствуйте", "Добрый день", "Приветствую", "Уважаемые коллеги".

Требования к организации:
6. company_name — если задано выше, используй его; иначе придумай разнообразное название (без домена).
7. inn — ИНН (10 цифр), код региона по городу из адреса.
8. kpp — КПП (9 цифр): первые 4 цифры ИНН + 01001.
9. legal_address — юридический адрес (используй адрес выше, если задан).
10. actual_address — как legal_address.
11. director_name — ФИО директора в формате «Фамилия И.О.».
12. company_phone — как phone, но без добавочного.
13. company_email — ровно {$email}.

Верни ТОЛЬКО валидный JSON с ключами:
sender_name, sender_full_name, phone, email_style, email_greeting,
company_name, inn, kpp, legal_address, actual_address, director_name, company_phone, company_email.
Все значения на русском языке.
PROMPT;
    }

    /**
     * Из строки телефонов (часто несколько номеров через запятую + пометки вроде
     * «(Секретариат/приёмная)») взять ОДИН чистый номер для показа в подписи:
     * первый номер, без скобочных примечаний. Пусто/меньше 6 цифр → null.
     */
    private function firstCleanPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        // Первый номер до разделителя нескольких номеров.
        $first = preg_split('/[,;]| или /u', trim($phone))[0] ?? '';
        // Убрать скобочные примечания «(Секретариат/приёмная)» и т.п. (но НЕ код города
        // в скобках вида «(495)» — его оставляем: там только цифры).
        $first = (string) preg_replace('/\((?=[^)]*[^\d\s)])[^)]*\)/u', '', $first);
        $first = trim(preg_replace('/\s{2,}/u', ' ', $first) ?? '');
        if (preg_match_all('/\d/', $first) < 6) {
            return null;
        }
        return mb_substr($first, 0, 50);
    }

    /**
     * Нормализовать телефон в индексируемый вид (как в n8n): оставляем только
     * цифры (и запятые-разделители для нескольких номеров), обрезаем до 20
     * символов под varchar(20). Пустое/только-разделители → null.
     */
    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d,]+/', '', $phone) ?? '';
        $normalized = trim($normalized, ',');
        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, 20);
    }

    /**
     * @return array{0:string,1:int} [server, port]
     */
    private function splitHostPort(string $value, int $defaultPort): array
    {
        $parts = explode(':', trim($value));
        $server = trim($parts[0]);
        $port = isset($parts[1]) ? (int) trim($parts[1]) : 0;

        return [$server, $port > 0 ? $port : $defaultPort];
    }

    private function encryptionFor(?string $explicit, int $port): string
    {
        $explicit = $explicit !== null ? mb_strtolower(trim($explicit)) : null;
        if (in_array($explicit, ['none', 'ssl', 'tls'], true)) {
            return $explicit;
        }

        return $port === 587 ? 'tls' : 'ssl';
    }

    private function normalizeEmailStyle(?string $style): string
    {
        $style = $style !== null ? mb_strtolower(trim($style)) : '';

        return in_array($style, self::EMAIL_STYLES, true) ? $style : 'formal';
    }

    private function loadTemplateIds(): void
    {
        $this->tokenTemplateIds = $this->activeIds('token_templates');
        $this->emailTemplateIds = $this->activeIds('email_templates');
    }

    /**
     * @return int[]
     */
    private function activeIds(string $table): array
    {
        try {
            return DB::connection('reports')
                ->table($table)
                ->where('is_active', 1)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param int[] $ids
     */
    private function randomId(array $ids): ?int
    {
        return $ids === [] ? null : $ids[array_rand($ids)];
    }
}
