<?php

namespace App\Services\Monitoring;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Чтение номенклатуры из CSV/XLSX: первая строка — заголовки (порядок произвольный,
 * русские и английские синонимы). Возвращает строки в формате API-импорта.
 */
class NomenclatureFileReader
{
    private const HEADERS = [
        'client_ref' => ['client_ref', 'ref', 'код', 'code', 'lw', 'lw_code', 'id', 'код клиента'],
        'name' => ['name', 'наименование', 'название', 'номенклатура', 'товар'],
        'brand' => ['brand', 'бренд', 'производитель', 'марка', 'manufacturer'],
        'article' => ['article', 'артикул', 'oem', 'mpn', 'part_number', 'партномер', 'каталожный номер'],
        'unit' => ['unit', 'ед', 'ед.', 'ед. изм.', 'единица', 'единица измерения'],
    ];

    /** @return array<int,array<string,string|null>> */
    public function read(string $path): array
    {
        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rows = in_array($ext, ['xlsx', 'xls', 'ods'], true) ? $this->readSpreadsheet($path) : $this->readCsv($path);
        if ($rows === []) {
            return [];
        }

        $map = $this->mapHeaders(array_shift($rows));
        $out = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($map as $field => $idx) {
                $v = trim((string) ($row[$idx] ?? ''));
                $item[$field] = $v === '' ? null : $v;
            }
            if (($item['client_ref'] ?? null) === null && ($item['name'] ?? null) === null) {
                continue; // пустая строка
            }
            $out[] = $item;
        }

        return $out;
    }

    /** @param array<int,mixed> $header @return array<string,int> */
    private function mapHeaders(array $header): array
    {
        $map = [];
        foreach ($header as $idx => $title) {
            $t = mb_strtolower(trim((string) $title));
            foreach (self::HEADERS as $field => $aliases) {
                if (!isset($map[$field]) && in_array($t, $aliases, true)) {
                    $map[$field] = (int) $idx;
                }
            }
        }

        return $map;
    }

    /** @return array<int,array<int,string>> */
    private function readCsv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $firstLine = strtok($raw, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        if (substr_count($firstLine, "\t") > substr_count($firstLine, $delimiter)) {
            $delimiter = "\t";
        }

        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $rows = [];
        while (($r = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = $r;
        }
        fclose($fh);

        return $rows;
    }

    /** @return array<int,array<int,string>> */
    private function readSpreadsheet(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = [];
        foreach ($sheet->toArray(null, true, true, false) as $r) {
            $rows[] = array_map(fn ($v) => $v === null ? '' : (string) $v, $r);
        }

        return $rows;
    }
}
