<?php

namespace App\Services\AliasExp;

/**
 * Вымышленная персона ящика «ящик на батч»: одно ФИО и для подписи, и для адреса (решение владельца 16.10.2026 —
 * адрес и подпись не должны расходиться; реальные ФИО руководителей из выгрузки не используются).
 * Телефон легенды — телефон организации с изменённой одной цифрой (стабильно для организации).
 */
class PersonaFactory
{
    /** [имя, транслит] */
    private const MALE = [
        ['Александр', 'aleksandr'], ['Сергей', 'sergey'], ['Дмитрий', 'dmitry'], ['Андрей', 'andrey'], ['Алексей', 'aleksey'],
        ['Михаил', 'mikhail'], ['Павел', 'pavel'], ['Игорь', 'igor'], ['Роман', 'roman'], ['Олег', 'oleg'], ['Денис', 'denis'],
        ['Евгений', 'evgeny'], ['Максим', 'maksim'], ['Николай', 'nikolay'], ['Владимир', 'vladimir'], ['Константин', 'konstantin'],
        ['Виктор', 'viktor'], ['Артём', 'artem'], ['Илья', 'ilya'], ['Антон', 'anton'], ['Вадим', 'vadim'], ['Юрий', 'yury'],
    ];

    private const FEMALE = [
        ['Елена', 'elena'], ['Ольга', 'olga'], ['Наталья', 'natalya'], ['Ирина', 'irina'], ['Анна', 'anna'], ['Мария', 'maria'],
        ['Татьяна', 'tatyana'], ['Екатерина', 'ekaterina'], ['Светлана', 'svetlana'], ['Юлия', 'yulia'], ['Марина', 'marina'],
        ['Анастасия', 'anastasia'], ['Оксана', 'oksana'], ['Людмила', 'lyudmila'], ['Виктория', 'viktoria'], ['Надежда', 'nadezhda'],
    ];

    /** Отчество от имени отца: [мужское, женское] */
    private const PATRONYMICS = [
        ['Александрович', 'Александровна'], ['Сергеевич', 'Сергеевна'], ['Дмитриевич', 'Дмитриевна'], ['Андреевич', 'Андреевна'],
        ['Алексеевич', 'Алексеевна'], ['Михайлович', 'Михайловна'], ['Павлович', 'Павловна'], ['Игоревич', 'Игоревна'],
        ['Николаевич', 'Николаевна'], ['Владимирович', 'Владимировна'], ['Викторович', 'Викторовна'], ['Юрьевич', 'Юрьевна'],
        ['Олегович', 'Олеговна'], ['Евгеньевич', 'Евгеньевна'], ['Анатольевич', 'Анатольевна'], ['Васильевич', 'Васильевна'],
    ];

    /** Фамилии: [мужская, женская, транслит мужской, транслит женской] */
    private const SURNAMES = [
        ['Королёв', 'Королёва', 'korolev', 'koroleva'], ['Смирнов', 'Смирнова', 'smirnov', 'smirnova'],
        ['Кузнецов', 'Кузнецова', 'kuznetsov', 'kuznetsova'], ['Соколов', 'Соколова', 'sokolov', 'sokolova'],
        ['Лебедев', 'Лебедева', 'lebedev', 'lebedeva'], ['Новиков', 'Новикова', 'novikov', 'novikova'],
        ['Морозов', 'Морозова', 'morozov', 'morozova'], ['Волков', 'Волкова', 'volkov', 'volkova'],
        ['Зайцев', 'Зайцева', 'zaytsev', 'zaytseva'], ['Павлов', 'Павлова', 'pavlov', 'pavlova'],
        ['Семёнов', 'Семёнова', 'semenov', 'semenova'], ['Голубев', 'Голубева', 'golubev', 'golubeva'],
        ['Виноградов', 'Виноградова', 'vinogradov', 'vinogradova'], ['Богданов', 'Богданова', 'bogdanov', 'bogdanova'],
        ['Воробьёв', 'Воробьёва', 'vorobev', 'vorobeva'], ['Фёдоров', 'Фёдорова', 'fedorov', 'fedorova'],
        ['Михайлов', 'Михайлова', 'mikhaylov', 'mikhaylova'], ['Беляев', 'Беляева', 'belyaev', 'belyaeva'],
        ['Тарасов', 'Тарасова', 'tarasov', 'tarasova'], ['Белов', 'Белова', 'belov', 'belova'],
        ['Комаров', 'Комарова', 'komarov', 'komarova'], ['Орлов', 'Орлова', 'orlov', 'orlova'],
        ['Киселёв', 'Киселёва', 'kiselev', 'kiseleva'], ['Макаров', 'Макарова', 'makarov', 'makarova'],
        ['Андреев', 'Андреева', 'andreev', 'andreeva'], ['Ковалёв', 'Ковалёва', 'kovalev', 'kovaleva'],
        ['Гусев', 'Гусева', 'gusev', 'guseva'], ['Титов', 'Титова', 'titov', 'titova'], ['Кузьмин', 'Кузьмина', 'kuzmin', 'kuzmina'],
        ['Баранов', 'Баранова', 'baranov', 'baranova'], ['Осипов', 'Осипова', 'osipov', 'osipova'],
        ['Борисов', 'Борисова', 'borisov', 'borisova'], ['Никитин', 'Никитина', 'nikitin', 'nikitina'],
        ['Захаров', 'Захарова', 'zakharov', 'zakharova'], ['Соловьёв', 'Соловьёва', 'solovev', 'soloveva'],
        ['Ершов', 'Ершова', 'ershov', 'ershova'], ['Калинин', 'Калинина', 'kalinin', 'kalinina'],
        ['Громов', 'Громова', 'gromov', 'gromova'], ['Фролов', 'Фролова', 'frolov', 'frolova'],
        ['Жуков', 'Жукова', 'zhukov', 'zhukova'], ['Сорокин', 'Сорокина', 'sorokin', 'sorokina'],
    ];

    /**
     * @param array<string,bool> $taken занятые адреса (нижним регистром)
     * @return array{full_name:string, display_name:string, email:string, local:string}
     */
    public function make(string $domain, array $taken = []): array
    {
        $female = random_int(0, 99) < 35;
        [$first, $firstT] = ($female ? self::FEMALE : self::MALE)[array_rand($female ? self::FEMALE : self::MALE)];
        $pat = self::PATRONYMICS[array_rand(self::PATRONYMICS)][$female ? 1 : 0];
        $sur = self::SURNAMES[array_rand(self::SURNAMES)];
        $surname = $female ? $sur[1] : $sur[0];
        $surT = $female ? $sur[3] : $sur[2];

        $variants = [$firstT[0] . '.' . $surT, $firstT . '.' . $surT, $surT . '.' . $firstT[0], $surT, $firstT . '_' . $surT];
        $base = $variants[array_rand($variants)];
        $local = $base;
        for ($n = 2; isset($taken[mb_strtolower("{$local}@{$domain}")]); $n++) {
            $local = $base . $n;
        }

        return ['full_name' => "{$surname} {$first} {$pat}", 'display_name' => "{$first} {$surname}",
            'email' => "{$local}@{$domain}", 'local' => $local];
    }

    /**
     * Телефон легенды: первый номер из реквизитов с одной изменённой цифрой среди последних четырёх
     * (решение владельца — не светить реальный номер организации). Стабилен для организации ($seed = ИНН).
     */
    public static function legendPhone(?string $phone, string $seed): ?string
    {
        $phone = trim((string) preg_split('/[,;\/]| {2,}/u', (string) $phone)[0]);
        if (strlen(preg_replace('/\D/', '', $phone)) < 7) {
            return null;
        }
        preg_match_all('/\d/', $phone, $m, PREG_OFFSET_CAPTURE);
        $digits = $m[0];
        $h = crc32($seed);
        [$d, $pos] = $digits[count($digits) - 1 - ($h % 4)];
        $new = ((int) $d + 1 + intdiv($h, 4) % 9) % 10;

        return substr_replace($phone, (string) $new, $pos, 1);
    }
}
