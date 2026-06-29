<?php

namespace App\Services\Analysis;

/**
 * Очистка тела входящего письма перед отправкой в AI.
 *
 * Порт n8n-кода «Prepare for AI»: берём body_text, а при его отсутствии —
 * вытаскиваем текст из body_html (срезаем blockquote/style/script, теги,
 * декодируем сущности), затем срезаем цитаты предыдущей переписки и форварды,
 * `>`-строки. Если осмысленного текста почти нет — отдаём явный маркер, чтобы
 * AI не выдумывал офферы из пустого ответа.
 */
class EmailBodyCleaner
{
    private const MIN_MEANINGFUL_CHARS = 15;

    public function clean(?string $bodyText, ?string $bodyHtml): string
    {
        $text = trim((string) $bodyText);

        if ($text === '' && (string) $bodyHtml !== '') {
            $text = $this->htmlToText((string) $bodyHtml);
        }

        if ($text === '') {
            return '[ПИСЬМО БЕЗ СОДЕРЖАНИЯ]';
        }

        $text = $this->stripQuotedHistory($text);

        if (mb_strlen(trim($text)) < self::MIN_MEANINGFUL_CHARS) {
            return '[ПИСЬМО БЕЗ НОВОГО СОДЕРЖАНИЯ - поставщик ответил без текста (возможно, КП во вложении)]';
        }

        return trim($text);
    }

    private function htmlToText(string $html): string
    {
        // Цитаты прошлой переписки в HTML почти всегда в <blockquote> — режем целиком.
        $html = preg_replace('/<blockquote[\s\S]*?<\/blockquote>/i', ' ', $html) ?? $html;
        $html = preg_replace('/<(script|style)[^>]*>[\s\S]*?<\/\1>/i', ' ', $html) ?? $html;
        $html = preg_replace('/<\/div>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Схлопываем пробелы/табы, но переносы строк сохраняем для срезки цитат.
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Срезает цитаты предыдущей переписки и форварды (русские/английские маркеры),
     * а также `>`-цитированные строки.
     */
    private function stripQuotedHistory(string $text): string
    {
        // Русская цитата: «Иван, 5 июня 2024 г., 12:30 от Имя <addr>...» и всё после.
        $text = preg_replace(
            '/[\r\n]*[А-Яа-яЁё]{2,3},\s*\d{1,2}\s+[а-яё]+\.?\s+\d{4}\s+г\.,\s+\d{1,2}:\d{2}\s+от\s+[^<\r\n]+<[^>\r\n]+>[\s\S]*/iu',
            '',
            $text
        ) ?? $text;

        // Английская цитата: «On Mon, 5 Jun 2024, at 12:30, Name wrote:» и всё после.
        $text = preg_replace(
            '/[\r\n]*On\s+.{0,80}\d{4}.{0,40}wrote:[\s\S]*/i',
            '',
            $text
        ) ?? $text;

        // Маркер форварда/исходного сообщения и всё после.
        $text = preg_replace(
            '/[\r\n]*-{2,}\s*(Forwarded message|Original Message|Пересланное сообщение|Исходное сообщение)[\s\S]*/i',
            '',
            $text
        ) ?? $text;

        // Outlook/стандартный reply-заголовок: «From:/От: …», за которым в пределах
        // нескольких строк идут Sent/Отправлено/To/Кому/Subject/Тема — режем заголовок
        // и всю процитированную переписку ниже (наш исходный запрос не должен попасть в AI).
        $text = preg_replace(
            '/(?:^|[\r\n]+)\s*(From|От)\s*:\s.+?(?:[\r\n]+.*){0,4}?(?:Sent|Отправлено|To|Кому|Subject|Тема)\s*:[\s\S]*/iu',
            '',
            $text
        ) ?? $text;

        // `>`-цитированные строки.
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $kept = array_filter(
            $lines,
            static fn ($line) => !preg_match('/^\s*>/', $line)
        );

        $text = implode("\n", $kept);
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
