<?php

namespace App\Support\Senders;

/**
 * Реквизиты организации ящика для писем (подпись, ответы с реквизитами). Данные из выгрузок ExportBase бывают
 * неаккуратны: «ООО АДЕПТ ПЛЮС"» (кавычка без пары), «194214, 194214, Г.САНКТ-ПЕТЕРБУРГ…» (индекс дважды).
 */
final class OrgText
{
    public static function name(?string $name): string
    {
        $name = trim((string) $name);
        $plain = substr_count($name, '"');
        $balanced = $plain % 2 === 0 && mb_substr_count($name, '«') === mb_substr_count($name, '»');
        if ($balanced) {
            return $name;
        }
        if (preg_match('/^(ООО|АО|ЗАО|ПАО|ОАО|НАО|ИП)\s+(.+)$/u', $name, $m)) {
            return $m[1] . ' «' . trim(str_replace(['"', '«', '»', '“', '”'], '', $m[2])) . '»';
        }

        return trim(str_replace(['"', '«', '»', '“', '”'], '', $name));
    }

    public static function address(?string $address): string
    {
        $a = trim((string) $address);

        return preg_replace('/^(\d{6}),\s*\1,\s*/u', '$1, ', $a) ?? $a;
    }
}
