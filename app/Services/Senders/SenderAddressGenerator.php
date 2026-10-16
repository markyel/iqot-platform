<?php

namespace App\Services\Senders;

use App\Models\Reports\Sender;
use Throwable;

/**
 * Генератор почтовых адресов для отправителей.
 *
 * Берёт неиспользованные организации из выгрузки ExportBase и список доступных
 * доменов, для каждой организации придумывает логин (микс шаблонов: фамилия,
 * имя.фамилия, слаг компании, отдел), пароль и назначает домен по кругу
 * (round-robin). Возвращает кандидатов для предпросмотра — админ отмечает
 * галочками, какие реально завести. Сами отправители создаются позже через
 * BulkSenderImporter::importBlocks(), который дотягивает персону/реквизиты AI.
 *
 * SMTP/IMAP выводятся ПО ДОМЕНУ (мульти-провайдерность против бана): по умолчанию
 * smtp.<домен>:465 / mail.<домен>:993 (паттерн Sprinthost/SpaceWeb), а для доменов
 * на нестандартном хосте (напр. припаркованных на beget — общий smtp.beget.com)
 * берётся явный override из DOMAIN_MAIL. Ящики админ заводит вручную после превью.
 */
class SenderAddressGenerator
{
    /**
     * Явный маппинг домен → [smtp, imap] для хостов, где почта НЕ на smtp.<домен>.
     * Домены на beget: общий smtp.beget.com/imap.beget.com, а не smtp.<домен>.
     * Остальные домены выводятся автоматически (см. mailHostsFor()).
     *
     * @var array<string,array{smtp:string,imap:string}>
     */
    private const DOMAIN_MAIL = [
        // 'inmailbox.ru' => ['smtp' => 'smtp.beget.com:465', 'imap' => 'imap.beget.com:993'],
    ];

    /** Обезличенные отделы для логинов вида zakupki@, snab@. */
    private const DEPTS = ['zakupki', 'snab', 'sales', 'office', 'info', 'opt', 'sbyt', 'tender', 'trade'];

    /** Имена для шаблона «имя.фамилия», если у организации нет директора. */
    private const FIRST_NAMES = [
        'aleksandr', 'sergey', 'dmitry', 'andrey', 'mikhail', 'pavel', 'igor', 'roman',
        'elena', 'olga', 'natalia', 'irina', 'anna', 'maria', 'tatiana', 'ekaterina',
    ];

    /** Фамилии для синтеза логина, когда у организации нет ФИО директора. */
    private const SURNAMES = [
        'ivanov', 'petrov', 'sidorov', 'smirnov', 'kuznetsov', 'popov', 'sokolov',
        'lebedev', 'kozlov', 'novikov', 'morozov', 'volkov', 'alekseev', 'fedorov',
        'mikhaylov', 'belov', 'tarasov', 'belyaev', 'komarov', 'orlov', 'kiselev',
        'makarov', 'andreev', 'kovalev', 'ilyin', 'gusev', 'titov', 'kuzmin',
        'kudryavtsev', 'baranov', 'gerasimov', 'bogdanov', 'osipov', 'sergeev',
        'grigorev', 'romanov', 'borisov', 'zhukov', 'frolov', 'nikitin',
    ];

    /** Карта транслитерации (ГОСТ-подобная, упрощённая). */
    private const TRANSLIT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    /** Организационно-правовые формы, которые выкидываем из слага компании. */
    private const ORG_FORMS = ['ооо', 'оао', 'зао', 'пао', 'ао', 'ип', 'нко', 'нао', 'тоо', 'чоу', 'гуп', 'муп'];

    public function __construct(
        private readonly OrganizationPool $pool,
    ) {
    }

    /**
     * Сгенерировать кандидатов: по одному адресу на неиспользованную организацию.
     *
     * Каждая строка $domainLines — либо просто домен (SMTP/IMAP выводятся авто),
     * либо расширенный синтаксис «домен | smtp.host:port | imap.host:port» с явным
     * указанием почтового сервера домена.
     *
     * @param string[] $domainLines строки textarea доменов (как есть, по \n)
     * @return array<int,array<string,mixed>>
     */
    public function generate(string $excelPath, array $domainLines): array
    {
        $entries = $this->parseDomainEntries($domainLines);
        if ($entries === []) {
            return [];
        }

        $orgs = $this->pool->unused($excelPath);
        $used = $this->existingEmails();

        $rows = [];
        $domainIdx = 0;
        foreach ($orgs as $org) {
            $entry = $entries[$domainIdx % count($entries)];
            $domainIdx++;

            $domain = $entry['domain'];
            $email = $this->uniqueEmail($org, $domain, $used);
            $used[mb_strtolower($email)] = true;

            [$smtp, $imap] = $this->mailHostsFor($entry);

            $rows[] = [
                'email' => $email,
                'password' => $this->password(),
                'domain' => $domain,
                'smtp' => $smtp,
                'imap' => $imap,
                'company' => $org['name'] ?? $org['full_name'] ?? null,
                'inn' => $org['inn'] ?? null,
                'kpp' => $org['kpp'] ?? null,
                'ogrn' => $org['ogrn'] ?? null,
                'address' => $org['legal_address'] ?? null,
                'company_phone' => $org['phone'] ?? $org['mobile'] ?? null,
                'director' => $org['director_name'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Разобрать строки textarea доменов в упорядоченный уникальный список записей.
     *
     * Поддерживается два формата строки:
     *   - «домен» (можно несколько в строке через пробел/запятую/;) — SMTP/IMAP авто;
     *   - «домен | smtp.host[:port] | imap.host[:port]» — явный почтовый сервер домена
     *     (override). Порт необязателен (по умолчанию SMTP 465 / IMAP 993). Битый
     *     host:port в override молча игнорируется → откат на авто-вывод по домену.
     *
     * Дедуп по домену (первое вхождение выигрывает), порядок сохраняется (round-robin).
     *
     * @param string[] $lines
     * @return array<int,array{domain:string,smtp:?string,imap:?string}>
     */
    private function parseDomainEntries(array $lines): array
    {
        $seen = [];
        $entries = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (str_contains($line, '|')) {
                $parts = array_map('trim', explode('|', $line));
                $domain = $this->cleanDomain($parts[0] ?? '');
                if ($domain === '' || isset($seen[$domain])) {
                    continue;
                }
                $seen[$domain] = true;
                $entries[] = [
                    'domain' => $domain,
                    'smtp' => $this->cleanHostPort($parts[1] ?? '', 465),
                    'imap' => $this->cleanHostPort($parts[2] ?? '', 993),
                ];

                continue;
            }

            // Строка без «|» — один или несколько простых доменов (авто SMTP/IMAP).
            foreach (preg_split('/[\s,;]+/', $line) ?: [] as $token) {
                $domain = $this->cleanDomain($token);
                if ($domain === '' || isset($seen[$domain])) {
                    continue;
                }
                $seen[$domain] = true;
                $entries[] = ['domain' => $domain, 'smtp' => null, 'imap' => null];
            }
        }

        return $entries;
    }

    /**
     * Нормализовать и провалидировать домен. Возвращает '' если домен невалиден.
     */
    private function cleanDomain(string $domain): string
    {
        $domain = ltrim(mb_strtolower(trim($domain)), '@');

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain) ? $domain : '';
    }

    /**
     * Нормализовать «host[:port]» из override; пусто/битьё → null (откат на авто).
     * Без порта подставляется $defaultPort.
     */
    private function cleanHostPort(string $value, int $defaultPort): ?string
    {
        $value = mb_strtolower(trim($value));
        if ($value === '' || !preg_match('/^([a-z0-9.-]+\.[a-z]{2,})(?::(\d{1,5}))?$/', $value, $m)) {
            return null;
        }

        $port = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $defaultPort;

        return $m[1] . ':' . $port;
    }

    /**
     * SMTP/IMAP-хосты для записи домена. Приоритет: явный override из строки textarea
     * → карта DOMAIN_MAIL (для доменов на нестандартном хосте, напр. beget) → авто-вывод
     * smtp.<домен>:465 / mail.<домен>:993 (паттерн Sprinthost/SpaceWeb). Если в override
     * указана только одна сторона, недостающая берётся из авто-вывода.
     *
     * @param array{domain:string,smtp:?string,imap:?string} $entry
     * @return array{0:string,1:string}
     */
    private function mailHostsFor(array $entry): array
    {
        $domain = $entry['domain'];
        $autoSmtp = 'smtp.' . $domain . ':465';
        $autoImap = 'mail.' . $domain . ':993';

        if ($entry['smtp'] !== null || $entry['imap'] !== null) {
            return [$entry['smtp'] ?? $autoSmtp, $entry['imap'] ?? $autoImap];
        }

        if (isset(self::DOMAIN_MAIL[$domain])) {
            return [self::DOMAIN_MAIL[$domain]['smtp'], self::DOMAIN_MAIL[$domain]['imap']];
        }

        // Домен припаркован на beget? (MX → *.beget.com) — тогда общий smtp.beget.com,
        // т.к. smtp.<домен> у beget НЕ резолвится. Детект по MX — чтобы не вести карту
        // DOMAIN_MAIL руками (её забывали пополнять → ящики заводились с мёртвым хостом).
        if ($this->isBegetDomain($domain)) {
            return ['smtp.beget.com:465', 'imap.beget.com:993'];
        }

        return [$autoSmtp, $autoImap];
    }

    /**
     * Домен обслуживается beget (по MX-записи)? Результат кэшируется на прогон.
     */
    private function isBegetDomain(string $domain): bool
    {
        static $cache = [];
        $domain = mb_strtolower(trim($domain));
        if ($domain === '') {
            return false;
        }
        if (array_key_exists($domain, $cache)) {
            return $cache[$domain];
        }
        $hosts = [];
        $beget = false;
        if (@getmxrr($domain, $hosts) && $hosts) {
            foreach ($hosts as $mx) {
                if (stripos((string) $mx, 'beget') !== false) {
                    $beget = true;
                    break;
                }
            }
        }
        return $cache[$domain] = $beget;
    }

    /**
     * Уже занятые email (в senders), нижним регистром, как set.
     *
     * @return array<string,bool>
     */
    private function existingEmails(): array
    {
        try {
            return Sender::query()
                ->pluck('email')
                ->mapWithKeys(static fn ($e) => [mb_strtolower((string) $e) => true])
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Подобрать уникальный адрес: логин по шаблону + домен; при коллизии
     * добавляем числовой суффикс к логину.
     *
     * @param array<string,string|null> $org
     * @param array<string,bool> $used
     */
    private function uniqueEmail(array $org, string $domain, array $used): string
    {
        $base = $this->buildLocalPart($org);
        $local = $base;
        $n = 1;
        while (isset($used[mb_strtolower($local . '@' . $domain)])) {
            $n++;
            $local = $base . $n;
        }

        return $local . '@' . $domain;
    }

    /**
     * Микс шаблонов логина: фамилия, инициал.фамилия, имя.фамилия, слаг компании,
     * отдел, отдел.слаг. Из доступных вариантов берётся случайный.
     *
     * @param array<string,string|null> $org
     */
    private function buildLocalPart(array $org): string
    {
        $candidates = [];

        [$surname, $first] = $this->splitDirector($org['director_name'] ?? null);
        $surnameT = $surname !== null ? $this->translit($surname) : '';
        $firstT = $first !== null ? $this->translit($first) : '';

        // Если ФИО директора нет (в выгрузке ExportBase почти всегда так) —
        // синтезируем фамилию из пула, чтобы именные адреса тоже попадали в микс.
        if ($surnameT === '') {
            $surnameT = self::SURNAMES[array_rand(self::SURNAMES)];
        }
        if ($firstT === '') {
            $firstT = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
        }

        $candidates[] = $surnameT;
        $candidates[] = $firstT[0] . '.' . $surnameT;
        $candidates[] = $firstT . '.' . $surnameT;
        $candidates[] = $surnameT . '.' . $firstT[0];

        $slug = $this->companySlug($org['name'] ?? $org['full_name'] ?? '');
        if ($slug !== '') {
            $candidates[] = $slug;
            $candidates[] = self::DEPTS[array_rand(self::DEPTS)] . '.' . $slug;
        }

        // Всегда есть запасной отдел-логин, чтобы список не был пустым.
        $candidates[] = self::DEPTS[array_rand(self::DEPTS)];

        $local = $candidates[array_rand($candidates)];

        return $this->sanitizeLocal($local);
    }

    /**
     * Разобрать ФИО директора на [фамилия, имя]. Инициалы (И., О.) игнорируем.
     *
     * @return array{0:?string,1:?string}
     */
    private function splitDirector(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $surname = $parts[0] ?? null;

        $first = null;
        if (isset($parts[1]) && mb_strlen(trim($parts[1], '.')) > 1) {
            $first = $parts[1];
        }

        return [$surname, $first];
    }

    /**
     * Слаг названия компании: убираем орг-форму и кавычки, берём первое слово.
     */
    private function companySlug(string $company): string
    {
        $company = mb_strtolower(trim($company));
        $company = str_replace(['«', '»', '"', "'", '`'], ' ', $company);

        $words = array_values(array_filter(
            preg_split('/\s+/', $company) ?: [],
            static fn ($w) => $w !== '' && !in_array($w, self::ORG_FORMS, true)
        ));

        $first = $words[0] ?? '';

        return $this->sanitizeLocal($this->translit($first));
    }

    /**
     * Транслитерация кириллицы в латиницу.
     */
    private function translit(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $out = '';
        $len = mb_strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($value, $i, 1);
            $out .= self::TRANSLIT[$ch] ?? $ch;
        }

        return $out;
    }

    /**
     * Оставить в логине только [a-z0-9._-], схлопнуть повторы разделителей.
     */
    private function sanitizeLocal(string $local): string
    {
        $local = preg_replace('/[^a-z0-9._-]+/', '', mb_strtolower($local)) ?? '';
        $local = preg_replace('/[._-]{2,}/', '.', $local) ?? '';
        $local = trim($local, '._-');

        return $local !== '' ? $local : self::DEPTS[array_rand(self::DEPTS)];
    }

    /**
     * Случайный пароль: 14 символов, гарантированно есть строчная, заглавная,
     * цифра и спецсимвол. Без пробелов и двоеточий (чтобы список читался чисто).
     */
    private function password(): string
    {
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $symbols = '!@#$%&*-_+';
        $all = $lower . $upper . $digits . $symbols;

        $chars = [
            $lower[random_int(0, strlen($lower) - 1)],
            $upper[random_int(0, strlen($upper) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];
        for ($i = count($chars); $i < 14; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        // Перемешать, чтобы обязательные символы не стояли в начале.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
