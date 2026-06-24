<?php

namespace App\Services\Senders;

use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Парсер выгрузки организаций ExportBase (export-base_*.xlsx).
 *
 * Формат: строка 1 — заголовки, строки 2-10 — рекламные блоки ExportBase,
 * далее идут строки компаний. Реальной строкой считается та, где в колонке ИНН
 * стоит 10-12 цифр. Колонки фиксированы (31 шт.), индексы ниже 0-based.
 */
class OrganizationExcelParser
{
    /** Значения-заглушки ExportBase, которые трактуем как пустые. */
    private const PLACEHOLDERS = ['-', '—', 'не выбрано в конфигураторе', 'нет', 'n/a'];

    /** Индексы колонок (0-based) в выгрузке ExportBase. */
    private const COL = [
        'name' => 0,
        'full_name' => 1,
        'okved' => 3,
        'inn' => 4,
        'ogrn' => 7,
        'kpp' => 8,
        'phone' => 10,
        'mobile' => 11,
        'legal_address' => 15,
        'postal_index' => 16,
        'city' => 19,
        'region' => 20,
        'email' => 23,
        'director_name' => 25,
        'director_position' => 26,
    ];

    /**
     * Разобрать xlsx в список организаций.
     *
     * @return array<int,array<string,string|null>> Каждый элемент — поля COL.
     */
    public function parse(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);

        $orgs = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(
                        static fn ($cell) => trim((string) $cell->getValue()),
                        $row->getCells()
                    );

                    $inn = $cells[self::COL['inn']] ?? '';
                    if (!preg_match('/^\d{10,12}$/', $inn)) {
                        continue;
                    }

                    $org = [];
                    foreach (self::COL as $key => $idx) {
                        $org[$key] = $this->clean($cells[$idx] ?? null);
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
     * Привести значение к чистой строке или null (убирает заглушки ExportBase).
     */
    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || in_array(mb_strtolower($value), self::PLACEHOLDERS, true)) {
            return null;
        }

        return $value;
    }
}
