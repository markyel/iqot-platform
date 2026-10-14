<?php

namespace App\Services\Monitoring\Discovery;

use App\Models\Monitoring\MpProduct;
use App\Support\Monitoring\ArticleNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ф2: сопоставление страниц sitemap сайта (mp_site_pages) с товарами — без загрузки страниц.
 *
 *  1. Артикул в адресе (OEM/MPN как отдельный токен; у zaplift/apexx/td-scala/lift-house
 *     так ~50% карточек) — балл 1.0.
 *  2. Название в транслите (у revator/liftcomplete/s-mechanics/delolift артикула в адресе
 *     нет, slug — транслит названия): слова приводятся к общему виду (ja/ya→a, shh/sch→sh,
 *     ts→c, j/y→i, ч/ц→c…) и основе (5 букв), вес слова — IDF по адресам ЭТОГО сайта
 *     («lift», «dla» встречаются везде → почти не весят). Балл = доля веса слов товара,
 *     найденных в адресе; числа товара (размеры, мощность) должны найтись, если в адресе
 *     вообще есть числа.
 * Кандидат — лишь гипотеза: листинг проверяет ListingChecker (артикул/бренд/LLM-сверка).
 */
class SitemapMatcher
{
    private const STOP = ['dla', 'kod', 'sht', 'id', 'html', 'php', 'catal', 'produ', 'shop', 'kupit', 'cena', 'tovar', 'item'];

    private const CYR = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z',
        'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh',
        'ъ' => '', 'ы' => 'i', 'ь' => '', 'э' => 'e', 'ю' => 'u', 'я' => 'a',
    ];

    public function __construct(
        private readonly float $minScore = 0.6,
        private readonly int $perProduct = 2,
    ) {
    }

    /**
     * @param Collection<int, MpProduct> $products (с identifiers)
     * @return array<int, array<int, array{url: string, score: float, via: string}>> product_id => кандидаты
     */
    public function match(int $siteId, Collection $products): array
    {
        $pages = DB::table('mp_site_pages')->where('site_id', $siteId)->pluck('url', 'id')->all();
        if ($pages === []) {
            return [];
        }

        // Инвертированные индексы адресов: код → страницы, слово → страницы; числа страницы.
        $byCode = [];
        $byWord = [];
        $pageWords = [];
        $pageNums = [];
        foreach ($pages as $id => $url) {
            $t = self::urlTokens($url);
            foreach ($t['codes'] as $c => $_) {
                $byCode[$c][] = $id;
            }
            foreach ($t['words'] as $w => $_) {
                $byWord[$w][] = $id;
            }
            $pageWords[$id] = $t['words'];
            $pageNums[$id] = $t['nums'];
        }
        $n = count($pages);
        $idf = fn (string $w) => log(($n + 1) / (count($byWord[$w] ?? []) + 1)) + 0.1;

        $out = [];
        foreach ($products as $product) {
            $cands = [];
            $p = self::productTokens($product);

            foreach ($p['codes'] as $code => $_) {
                foreach ($byCode[$code] ?? [] as $id) {
                    $cands[$id] = ['score' => 1.0, 'via' => 'code'];
                }
            }

            if ($p['words'] !== []) {
                $weights = [];
                foreach ($p['words'] as $w => $_) {
                    $weights[$w] = $idf($w);
                }
                $total = array_sum($weights);
                // Кандидаты — страницы с любым из 3 самых редких слов товара.
                arsort($weights);
                $pool = [];
                foreach (array_slice(array_keys($weights), 0, 3) as $w) {
                    foreach ($byWord[$w] ?? [] as $id) {
                        $pool[$id] = true;
                    }
                }
                foreach (array_keys($pool) as $id) {
                    if (isset($cands[$id])) {
                        continue;
                    }
                    $hit = 0.0;
                    $matched = 0;
                    foreach ($weights as $w => $wt) {
                        if (isset($pageWords[$id][$w])) {
                            $hit += $wt;
                            $matched++;
                        }
                    }
                    $score = $total > 0 ? $hit / $total : 0.0;
                    if ($p['nums'] !== [] && $pageNums[$id] !== [] && array_intersect_key($p['nums'], $pageNums[$id]) === []) {
                        $score *= 0.5; // размеры/мощность в адресе есть, но не наши
                    }
                    if ($matched >= 2 && $score >= $this->minScore) {
                        $cands[$id] = ['score' => round($score, 3), 'via' => 'name'];
                    }
                }
            }

            if ($cands !== []) {
                uasort($cands, fn ($a, $b) => $b['score'] <=> $a['score']);
                $out[$product->id] = [];
                foreach (array_slice($cands, 0, $this->perProduct, true) as $id => $c) {
                    $out[$product->id][] = ['url' => $pages[$id], 'score' => $c['score'], 'via' => $c['via']];
                }
            }
        }

        return $out;
    }

    /** @return array{codes: array<string,true>, words: array<string,true>, nums: array<string,true>} */
    public static function urlTokens(string $url): array
    {
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH) . ' ' . (string) parse_url($url, PHP_URL_QUERY));
        $text = preg_replace('~[/\-_.=&?+%]+~', ' ', $path) ?? $path;

        return self::tokensOf($text);
    }

    /** @return array{codes: array<string,true>, words: array<string,true>, nums: array<string,true>} */
    public static function productTokens(MpProduct $product): array
    {
        // Прямое совпадение — только OEM/MPN из идентификаторов. Коды из названия часто
        // обозначают серию (BFK16, MT42IX, DCSS4, GEN2): они есть у соседних товаров
        // («вызов вниз» вместо «вверх») — поэтому идут в сравнение названий как редкие слова.
        $t = self::tokensOf(trim($product->name . ' ' . $product->brand));
        $codes = [];
        foreach ($product->identifiers as $i) {
            if (in_array($i->kind, ['oem', 'mpn'], true)) {
                $norm = (string) ($i->value_norm ?: ArticleNormalizer::normalize($i->value));
                if (self::isCode($norm)) {
                    $codes[$norm] = true;
                }
            }
        }
        $t['codes'] = $codes;

        return $t;
    }

    /** @return array{codes: array<string,true>, words: array<string,true>, nums: array<string,true>} */
    private static function tokensOf(string $text): array
    {
        $words = [];
        $nums = [];
        foreach (preg_split('~[^\p{L}\p{N}]+~u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $tok) {
            if (preg_match('~^\d+$~', $tok)) {
                if (strlen($tok) >= 2) {
                    $nums[ltrim($tok, '0') ?: '0'] = true;
                }
                continue;
            }
            if (preg_match('~^\p{L}+$~u', $tok) && mb_strlen($tok) >= 3) {
                $w = self::fold($tok);
                if (mb_strlen($w) >= 3 && !in_array($w, self::STOP, true)) {
                    $words[$w] = true;
                }
            }
        }
        $codes = [];
        foreach (ArticleNormalizer::tokens($text) as $c => $_) {
            if (self::isCode($c)) {
                $codes[$c] = true;
                $words['#' . $c] = true; // код как «слово» для сравнения названий (вес — по редкости)
            }
        }

        return ['codes' => $codes, 'words' => $words, 'nums' => $nums];
    }

    /**
     * Код для сопоставления по адресу: буквы+цифры (≥5) или длинное число (≥7). Склейка
     * соседних чисел адреса («rolik-150-38» → 15038) и номера карточек не должны давать
     * ложных совпадений с числовыми артикулами (первый прогон 14.10: 903 «кода» на s-mechanics).
     */
    private static function isCode(string $norm): bool
    {
        $len = mb_strlen($norm);
        if (preg_match('~^\d+$~', $norm)) {
            return $len >= 7;
        }

        return $len >= 5 && preg_match('~\d~', $norm) && preg_match('~[A-Z]~', $norm);
    }

    /** Слово → общий вид транслита + основа 5 букв (ролика/ролики/rolik → rolik). */
    public static function fold(string $word): string
    {
        $w = strtr(mb_strtolower($word), self::CYR);
        $w = strtr($w, ['shch' => 'sh', 'shh' => 'sh', 'sch' => 'sh']);
        $w = strtr($w, ['ch' => 'c', 'kh' => 'h', 'zh' => 'z', 'ts' => 'c', 'tz' => 'c']);
        $w = strtr($w, ['ja' => 'a', 'ya' => 'a', 'ju' => 'u', 'yu' => 'u', 'jo' => 'o', 'yo' => 'o', 'je' => 'e', 'ye' => 'e']);
        $w = strtr($w, ['y' => 'i', 'j' => 'i', 'w' => 'v', 'x' => 'ks', 'q' => 'k']);
        $w = preg_replace('~(.)\1+~', '$1', $w) ?? $w;

        return mb_substr($w, 0, 5);
    }
}
