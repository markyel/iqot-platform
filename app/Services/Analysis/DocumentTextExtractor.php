<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Извлечение текста из вложений входящего письма (КП в Excel/PDF/Word) ЛОКАЛЬНО.
 *
 * Замена n8n-цепочки (Google Docs / Extract from File): читаем файл с локального
 * диска `public` по email_attachments.local_path и парсим PHP-либами:
 *  - Excel (xls/xlsx/csv) → PhpSpreadsheet, листы построчно в TSV-подобный текст;
 *  - PDF → smalot/pdfparser;
 *  - Word (doc/docx) → PhpOffice\PhpWord, рекурсивный обход элементов;
 *  - text/csv/html и пр. → как есть (html — strip_tags).
 *
 * Каждый файл в своём try/catch: битое вложение не валит анализ письма, в текст
 * подставляется маркер `[вложение не распознано: name]`. Итог обрезается до
 * doc_max_chars (начало+конец, как в n8n «Prepare for AI»).
 */
class DocumentTextExtractor
{
    private const DISK = 'public';

    /** Расширения, которые парсим как таблицы. */
    private const EXCEL_EXT = ['xlsx', 'xlsm', 'xls', 'ods'];
    private const WORD_EXT = ['docx', 'doc', 'rtf', 'odt'];
    private const PLAIN_EXT = ['txt', 'csv', 'tsv', 'xml', 'json'];
    private const HTML_EXT = ['html', 'htm'];

    /**
     * @param int $maxChars   потолок итогового текста (обрезка начало+конец)
     * @param int $pdfMaxBytes потолок размера PDF в байтах; 0 = без лимита. Большие
     *   PDF-каталоги (напр. 6.5 МБ) виснут в LZW-декодере smalot/pdfparser дольше
     *   таймаута джоба и делают письмо «ядовитым» — пропускаем их без парсинга.
     */
    public function __construct(
        private readonly int $maxChars = 30000,
        private readonly int $pdfMaxBytes = 0,
    ) {
    }

    /**
     * @param iterable<object|array<string,mixed>> $attachments строки email_attachments
     *        (нужны поля: file_name, mime_type, local_path)
     * @return string склеенный текст всех распознанных вложений (может быть пустым)
     */
    public function extractFromAttachments(iterable $attachments): string
    {
        $parts = [];

        foreach ($attachments as $att) {
            $name = (string) ($this->get($att, 'file_name') ?? 'attachment');
            $localPath = (string) ($this->get($att, 'local_path') ?? '');
            $mime = (string) ($this->get($att, 'mime_type') ?? '');

            if ($localPath === '') {
                continue;
            }

            try {
                $text = $this->extractOne($localPath, $name, $mime);
            } catch (\Throwable $e) {
                $text = "[вложение не распознано: {$name}]";
            }

            $text = trim($text);
            if ($text !== '') {
                $parts[] = "=== {$name} ===\n{$text}";
            }
        }

        return $this->capText(implode("\n\n", $parts));
    }

    private function extractOne(string $localPath, string $name, string $mime): string
    {
        if (!Storage::disk(self::DISK)->exists($localPath)) {
            return "[вложение недоступно: {$name}]";
        }

        $absolute = Storage::disk(self::DISK)->path($localPath);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        }

        if (in_array($ext, self::EXCEL_EXT, true)) {
            return $this->extractSpreadsheet($absolute);
        }
        if ($ext === 'pdf' || str_contains($mime, 'pdf')) {
            return $this->extractPdf($absolute, $name);
        }
        if (in_array($ext, self::WORD_EXT, true)) {
            return $this->extractWord($absolute, $ext);
        }
        if (in_array($ext, self::HTML_EXT, true) || str_contains($mime, 'html')) {
            return $this->htmlToText((string) Storage::disk(self::DISK)->get($localPath));
        }
        if (in_array($ext, self::PLAIN_EXT, true) || str_starts_with($mime, 'text/')) {
            return (string) Storage::disk(self::DISK)->get($localPath);
        }

        // Неизвестный тип (архив/картинка и т.п.) — текст не извлекаем.
        return '';
    }

    private function extractSpreadsheet(string $absolute): string
    {
        $reader = SpreadsheetIOFactory::createReaderForFile($absolute);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($absolute);

        $lines = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title = $sheet->getTitle();
            $lines[] = "[Лист: {$title}]";
            foreach ($sheet->toArray(null, true, false, false) as $row) {
                $cells = array_map(
                    static fn ($c) => $c === null ? '' : trim((string) $c),
                    $row
                );
                // Пропускаем полностью пустые строки.
                if (implode('', $cells) === '') {
                    continue;
                }
                $lines[] = implode("\t", $cells);
            }
        }

        $spreadsheet->disconnectWorksheets();

        return implode("\n", $lines);
    }

    private function extractPdf(string $absolute, string $name): string
    {
        if ($this->pdfMaxBytes > 0) {
            $size = @filesize($absolute);
            if ($size !== false && $size > $this->pdfMaxBytes) {
                return "[PDF пропущен — слишком большой ({$size} байт): {$name}]";
            }
        }

        $parser = new PdfParser();
        $pdf = $parser->parseFile($absolute);

        return $pdf->getText();
    }

    private function extractWord(string $absolute, string $ext): string
    {
        $readerType = match ($ext) {
            'docx' => 'Word2007',
            'doc' => 'MsDoc',
            'rtf' => 'RTF',
            'odt' => 'ODText',
            default => 'Word2007',
        };

        $phpWord = WordIOFactory::createReader($readerType)->load($absolute);

        $out = [];
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $this->collectWordText($element, $out);
            }
        }

        return implode("\n", $out);
    }

    /**
     * Рекурсивно собирает текст из элементов PhpWord (TextRun → Text, таблицы, ячейки).
     *
     * @param array<int,string> $out
     */
    private function collectWordText(object $element, array &$out): void
    {
        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_string($text) && trim($text) !== '') {
                $out[] = $text;
            }
            return;
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $this->collectWordText($child, $out);
            }
            return;
        }

        if (method_exists($element, 'getRows')) {
            foreach ($element->getRows() as $row) {
                $rowCells = [];
                foreach ($row->getCells() as $cell) {
                    $cellOut = [];
                    foreach ($cell->getElements() as $child) {
                        $this->collectWordText($child, $cellOut);
                    }
                    $rowCells[] = implode(' ', $cellOut);
                }
                $out[] = implode("\t", $rowCells);
            }
        }
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style)[^>]*>[\s\S]*?<\/\1>/i', ' ', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
    }

    /**
     * Обрезает длинный текст: начало + конец, чтобы влезть в промпт и не потерять
     * хвост документа (часто там итоги/НДС).
     */
    private function capText(string $text): string
    {
        if ($this->maxChars <= 0 || mb_strlen($text) <= $this->maxChars) {
            return $text;
        }

        $head = (int) floor($this->maxChars * 0.7);
        $tail = $this->maxChars - $head;

        return mb_substr($text, 0, $head)
            . "\n\n...[документ обрезан]...\n\n"
            . mb_substr($text, -$tail);
    }

    private function get(object|array $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return $row->{$key} ?? null;
    }
}
