<?php

namespace App\Support\Monitoring;

/**
 * Ключевые признаки товара из его наименования: сторона, исполнение, типоразмер, номер этажа, модель/серия,
 * бренд… — то, при расхождении чего на странице поставщика это уже другой вариант детали.
 * Признаки выделяет ИИ один раз на товар (KeyAttributeExtractor), здесь — нормализация и проверка без ИИ.
 *
 * Формат признака: {key, value, kind, match: [написания того же значения], conflict: [написания
 * взаимоисключающих значений]}. conflict заполняется только для перечислимых признаков (правая/левая,
 * механическая/электрическая, вверх/вниз); числа и бренды проверяет модель при чтении страницы.
 */
final class KeyAttributes
{
    public const KINDS = ['side', 'variant', 'size', 'number', 'model', 'brand', 'electrical', 'other'];

    /**
     * @param mixed $raw ответ ИИ (attrs)
     * @return array<int,array{key:string,value:string,kind:string,match:array<int,string>,conflict:array<int,string>}>
     */
    public static function normalize(mixed $raw): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $a) {
            if (!is_array($a)) {
                continue;
            }
            $key = trim((string) ($a['key'] ?? ''));
            $value = trim((string) ($a['value'] ?? ''));
            if ($key === '' || $value === '') {
                continue;
            }
            $kind = in_array($a['kind'] ?? null, self::KINDS, true) ? $a['kind'] : 'other';
            $match = self::terms(array_merge([$value], (array) ($a['match'] ?? [])));
            $conflict = array_values(array_diff(self::terms((array) ($a['conflict'] ?? [])), $match));
            $out[] = ['key' => mb_substr($key, 0, 60), 'value' => mb_substr($value, 0, 120), 'kind' => $kind,
                'match' => $match, 'conflict' => $conflict];
            if (count($out) >= 8) {
                break;
            }
        }

        return $out;
    }

    /**
     * Признак, явно противоречащий тексту: есть написание взаимоисключающего значения и нет ни одного
     * написания нашего. Текст — адрес и заголовок карточки, цитаты модели (не всё окно страницы: там
     * бывают соседние товары «Отводка левая» рядом с нашей правой).
     *
     * @param array<int,array<string,mixed>> $attrs
     * @return string|null «сторона: левая» — что расходится
     */
    public static function conflictIn(array $attrs, string $text): ?string
    {
        if ($attrs === [] || trim($text) === '') {
            return null;
        }
        $words = self::stems($text);
        if ($words === []) {
            return null;
        }
        $hay = ' ' . implode(' ', $words) . ' ';
        foreach ($attrs as $a) {
            $conflict = (array) ($a['conflict'] ?? []);
            if ($conflict === []) {
                continue;
            }
            $hit = null;
            foreach ($conflict as $t) {
                if (self::contains($hay, (string) $t)) {
                    $hit = (string) $t;
                    break;
                }
            }
            if ($hit === null) {
                continue;
            }
            foreach ((array) ($a['match'] ?? []) as $t) {
                if (self::contains($hay, (string) $t)) {
                    continue 2; // на странице названы оба варианта («левая/правая») — не противоречие
                }
            }

            return ($a['key'] ?? '?') . ': ' . $hit;
        }

        return null;
    }

    /**
     * Значение со страницы — это наше значение признака: есть написание из match и нет из conflict.
     *
     * @param array<int,array<string,mixed>> $attrs
     */
    public static function sameValue(array $attrs, string $key, string $pageValue): bool
    {
        $k = mb_strtolower(trim($key));
        foreach ($attrs as $a) {
            if (mb_strtolower((string) ($a['key'] ?? '')) !== $k) {
                continue;
            }
            $hay = ' ' . implode(' ', self::stems($pageValue)) . ' ';
            foreach ((array) ($a['conflict'] ?? []) as $t) {
                if (self::contains($hay, (string) $t)) {
                    return false;
                }
            }
            foreach ((array) ($a['match'] ?? []) as $t) {
                if (self::contains($hay, (string) $t)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Строки признаков для промпта извлечения цены. @param array<int,array<string,mixed>> $attrs */
    public static function promptLines(array $attrs): string
    {
        return implode("\n", array_map(fn ($a) => '- ' . ($a['key'] ?? '') . ': ' . ($a['value'] ?? ''), $attrs));
    }

    /** @param array<int,mixed> $list @return array<int,string> */
    private static function terms(array $list): array
    {
        $out = [];
        foreach ($list as $t) {
            $t = trim(mb_strtolower(str_replace('ё', 'е', (string) $t)));
            // Однобуквенные «R»/«L»/«П» ловят что угодно (размер L, «R» в артикуле) — не берём.
            if (mb_strlen(preg_replace('/[^\p{L}\p{N}]+/u', '', $t) ?? '') < 2) {
                continue;
            }
            $out[$t] = true;
        }

        return array_slice(array_keys($out), 0, 16);
    }

    private static function contains(string $hay, string $term): bool
    {
        $stems = self::stems($term);

        return $stems !== [] && str_contains($hay, ' ' . implode(' ', $stems) . ' ');
    }

    /**
     * Слова текста в нижнем регистре с отброшенными окончаниями (правая/правый/правой → «прав»),
     * латиница и числа — как есть.
     *
     * @return array<int,string>
     */
    private static function stems(string $text): array
    {
        $text = str_replace('ё', 'е', mb_strtolower($text));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(function (string $w) {
            if (!preg_match('/^\p{Cyrillic}+$/u', $w) || mb_strlen($w) < 5) {
                return $w;
            }
            $s = preg_replace('/(ого|его|ому|ему|ая|яя|ый|ий|ой|ое|ее|ые|ие|ую|юю|ым|им|ом|ем|ых|их|а|я|ы|и|о|е|у|ю)$/u', '', $w) ?? $w;

            return mb_strlen($s) >= 3 ? $s : $w;
        }, $parts);
    }
}
