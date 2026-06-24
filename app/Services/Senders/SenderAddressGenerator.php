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
 * SMTP/IMAP — beget по умолчанию (ящики админ заводит вручную после превью).
 */
class SenderAddressGenerator
{
    private const SMTP = 'smtp.beget.com:465';
    private const IMAP = 'imap.beget.com:993';

    /** Обезличенные отделы для логинов вида zakupki@, snab@. */
    private const DEPTS = ['zakupki', 'snab', 'sales', 'office', 'info', 'opt', 'sbyt', 'tender', 'trade'];

    /** Имена для шаблона «имя.фамилия», если у организации нет директора. */
    private const FIRST_NAMES = [
        'aleksandr', 'sergey', 'dmitry', 'andrey', 'mikhail', 'pavel', 'igor', 'roman',
        'elena', 'olga', 'natalia', 'irina', 'anna', 'maria', 'tatiana', 'ekaterina',
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
     * @param string[] $domains
     * @return array<int,array<string,mixed>>
     */
    public function generate(string $excelPath, array $domains): array
    {
        $domains = $this->normalizeDomains($domains);
        if ($domains === []) {
            return [];
        }

        $orgs = $this->pool->unused($excelPath);
        $used = $this->existingEmails();

        $rows = [];
        $domainIdx = 0;
        foreach ($orgs as $org) {
            $domain = $domains[$domainIdx % count($domains)];
            $domainIdx++;

            $email = $this->uniqueEmail($org, $domain, $used);
            $used[mb_strtolower($email)] = true;

            $rows[] = [
                'email' => $email,
                'password' => $this->password(),
                'domain' => $domain,
                'smtp' => self::SMTP,
                'imap' => self::IMAP,
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
     * Привести список доменов к чистому уникальному виду.
     *
     * @param string[] $domains
     * @return string[]
     */
    private function normalizeDomains(array $domains): array
    {
        $clean = [];
        foreach ($domains as $domain) {
            $domain = mb_strtolower(trim((string) $domain));
            $domain = ltrim($domain, '@');
            if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
                continue;
            }
            $clean[$domain] = true;
        }

        return array_keys($clean);
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

        if ($surnameT !== '') {
            $candidates[] = $surnameT;
            $fn = $firstT !== '' ? $firstT : self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $candidates[] = $fn[0] . '.' . $surnameT;
            $candidates[] = $fn . '.' . $surnameT;
            $candidates[] = $surnameT . '.' . $fn[0];
        }

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
