<?php

namespace App\Services\Generate;

/**
 * Видимая строка с номером запроса (трекинг-токен) вместо скрытого текста.
 *
 * Раньше токен вставлялся невидимым (белый шрифт 1px, `mso-hide:all`) — классический признак
 * спама; после сбоя Яндекса 12.10.2026 тот отклонял ~половину наших писем «under suspicion of SPAM».
 * Решение владельца 16.10.2026: номер — видимый, но формулировка и оформление РАЗНЫЕ у разных
 * шаблонов (анти-фингерпринтинг: одинаковая строка во всех письмах сама стала бы отпечатком).
 * Выбор стабилен для шаблона (по его id) → у ящика из письма в письмо одна и та же манера.
 * Шаблон может задать свою фразу в скелете: {"type":"token","label":"Наш № {token}"}.
 *
 * Распознавание ответов не меняется: IncomingEmailRouter ищет токен подстрокой в теме и теле
 * (поставщик отвечает с цитатой письма), видимый он или нет.
 */
final class VisibleTokenLine
{
    /** Формулировки ({token} — номер). */
    private const PHRASES = [
        'Номер запроса: {token}',
        'Наш номер: {token}',
        '№ запроса {token} — укажите его, пожалуйста, в ответе',
        'Реф.: {token}',
        'При ответе сошлитесь, пожалуйста, на номер {token}',
        'Запрос № {token}',
        'Внутр. номер: {token}',
        'Код запроса — {token}',
        'Для ответа: № {token}',
        'Исх. № {token}',
        'Наш реф. {token}',
        'Номер обращения: {token}',
        'Просим указать номер {token} в ответе',
        'Заявка № {token}',
    ];

    /** Оформление: [отступ сверху, стиль текста (null — как основной текст письма)]. */
    private const STYLES = [
        ['16px', 'font-size:9pt;color:#888;'],
        ['14px', null],
        ['18px', 'font-size:9pt;color:#777;font-style:italic;'],
        ['12px', 'font-size:8.5pt;color:#999;'],
    ];

    /**
     * @param array<string,mixed> $template строка email_templates (нужен id)
     * @param array<string,mixed> $block    блок скелета {"type":"token", "label"?: "..."}
     * @param string $textStyles стиль основного текста письма (для варианта «как текст»)
     */
    public static function render(string $token, array $template, array $block = [], string $textStyles = ''): string
    {
        if (trim($token) === '') {
            return '';
        }
        $seed = (int) ($template['id'] ?? 0) ?: crc32((string) ($template['name'] ?? ''));
        $label = is_string($block['label'] ?? null) && str_contains($block['label'], '{token}')
            ? $block['label']
            : self::PHRASES[$seed % count(self::PHRASES)];
        [$margin, $style] = self::STYLES[intdiv($seed, count(self::PHRASES)) % count(self::STYLES)];
        // Фигурные скобки htmlspecialchars не трогает — подстановка после экранирования безопасна.
        $text = str_replace('{token}', htmlspecialchars($token, ENT_QUOTES, 'UTF-8'), htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));

        return "<div style=\"margin-top:{$margin};\"><p style=\"margin:0;" . ($style ?? $textStyles) . "\">{$text}</p></div>";
    }

    /** Формулировка шаблона без HTML (для превью и тестов). */
    public static function phraseFor(array $template, array $block = []): string
    {
        $seed = (int) ($template['id'] ?? 0) ?: crc32((string) ($template['name'] ?? ''));

        return is_string($block['label'] ?? null) && str_contains($block['label'], '{token}')
            ? $block['label']
            : self::PHRASES[$seed % count(self::PHRASES)];
    }
}
