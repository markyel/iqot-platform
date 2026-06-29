<?php

namespace App\Services\Senders;

use OpenSpout\Reader\XLSX\Reader;

/**
 * Парсер выгрузки организаций ExportBase (export-base_*.xlsx).
 *
 * Формат: строка 1 — заголовки, далее рекламные блоки ExportBase и строки
 * компаний. Реальной строкой считается та, где в колонке ИНН стоит 10-12 цифр.
 *
 * Колонки определяются ПО ЗАГОЛОВКАМ (строка 1), а не по фиксированным индексам —
 * ExportBase периодически меняет порядок/состав колонок (напр. ИНН переезжал с
 * индекса 4 на 27). Если заголовок ИНН не найден — фолбэк на старые фикс-индексы.
 */
class OrganizationExcelParser
{
    /** Значения-заглушки ExportBase, которые трактуем как пустые. */
    private const PLACEHOLDERS = ['-', '—', 'не выбрано в конфигураторе', 'нет', 'n/a'];

    /** Старые фиксированные индексы (фолбэк, если нет распознаваемых заголовков). */
    private const FALLBACK_COL = [
        'name' => 0, 'full_name' => 1, 'okved' => 3, 'inn' => 4, 'ogrn' => 7,
        'kpp' => 8, 'phone' => 10, 'mobile' => 11, 'legal_address' => 15,
        'postal_index' => 16, 'city' => 19, 'region' => 20, 'email' => 23,
        'director_name' => 25, 'director_position' => 26,
    ];

    /**
     * Подстроки заголовков → поле. Для каждого поля берётся первая колонка, чей
     * заголовок совпадает точно либо содержит одну из подстрок (точное — в приоритете).
     *
     * @var array<string,array<int,string>>
     */
    private const HEADER_PATTERNS = [
        'name' => ['название компании', 'наименование компании', 'название организации', 'краткое наименование'],
        'full_name' => ['полное наименование', 'полное название'],
        'inn' => ['инн'],
        'ogrn' => ['огрн'],
        'kpp' => ['кпп'],
        'okved' => ['оквэд (код)', 'главный оквэд (код)', 'оквэд'],
        'phone' => ['стационарный телефон', 'телефон компании', 'телефон'],
        'mobile' => ['мобильный телефон'],
        'email' => ['эл. почта', 'электронная почта', 'email', 'e-mail', 'почта'],
        'legal_address' => ['адрес компании', 'юридический адрес', 'адрес и почтовый'],
        'postal_index' => ['почтовый индекс'],
        'city' => ['город'],
        'region' => ['регион'],
        'director_name' => ['фио руководителя', 'руководитель', 'директор'],
        'director_position' => ['должность руководителя', 'должность'],
    ];

    /**
     * @return array<int,array<string,string|null>>
     */
    public function parse(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);

        $orgs = [];
        $map = null;
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(
                        static fn ($cell) => trim((string) $cell->getValue()),
                        $row->getCells()
                    );

                    if ($map === null) {
                        $map = $this->resolveColumns($cells);
                        continue; // строка заголовков — не данные
                    }

                    $innIdx = $map['inn'] ?? null;
                    $inn = $innIdx !== null ? ($cells[$innIdx] ?? '') : '';
                    if (!preg_match('/^\d{10,12}$/', $inn)) {
                        continue;
                    }

                    $org = [];
                    foreach ($map as $key => $idx) {
                        $org[$key] = $idx !== null ? $this->clean($cells[$idx] ?? null) : null;
                    }
                    $org['inn'] = $inn;
                    $orgs[] = $org;
                }
                break; // только первый лист
            }
        } finally {
            $reader->close();
        }

        return $orgs;
    }

    /**
     * Сопоставить заголовки колонкам. Если ИНН по заголовкам не найден — фолбэк
     * на старые фиксированные индексы.
     *
     * @param array<int,string> $headers
     * @return array<string,int|null>
     */
    private function resolveColumns(array $headers): array
    {
        $norm = [];
        foreach ($headers as $i => $h) {
            $norm[$i] = preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $h)));
        }

        $map = [];
        foreach (self::HEADER_PATTERNS as $field => $patterns) {
            $map[$field] = null;
            // 1) точное совпадение
            foreach ($norm as $i => $h) {
                if ($h !== '' && in_array($h, $patterns, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
            if ($map[$field] !== null) {
                continue;
            }
            // 2) вхождение подстроки
            foreach ($patterns as $p) {
                foreach ($norm as $i => $h) {
                    if ($h !== '' && mb_strpos($h, $p) !== false) {
                        $map[$field] = $i;
                        break 2;
                    }
                }
            }
        }

        if ($map['inn'] === null) {
            // заголовки не распознаны — старая раскладка по индексам
            return self::FALLBACK_COL + ['inn' => self::FALLBACK_COL['inn']];
        }

        return $map;
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || in_array(mb_strtolower($value), self::PLACEHOLDERS, true)) {
            return null;
        }

        return $value;
    }
}
