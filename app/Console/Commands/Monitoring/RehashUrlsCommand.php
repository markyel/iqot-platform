<?php

namespace App\Console\Commands\Monitoring;

use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Пересчёт отпечатков адресов после смены нормализации (25.11.2026: единая процентная кодировка пути). Только адреса
 * с кириллицей или строчной %-кодировкой. Отпечаток уже занят другой записью (тот же адрес в другой форме) — запись
 * не трогаем: дубль безвреден, данных не теряем.
 */
class RehashUrlsCommand extends Command
{
    protected $signature = 'monitoring:rehash-urls {--dry-run}';

    protected $description = 'Мониторинг: пересчитать url_hash после смены нормализации адресов';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $where = "CAST(url AS BINARY) REGEXP CAST('%[0-9A-Fa-f]?[a-f]' AS BINARY) OR url <> CONVERT(url USING ascii)";

        // Индекс сайтов: url_hash уникален глобально.
        [$pu, $ps] = [0, 0];
        foreach (DB::table('mp_site_pages')->whereRaw($where)->orderBy('id')->lazyById(1000, 'id') as $r) {
            $h = UrlCanonicalizer::hash($r->url);
            if ($h === $r->url_hash) {
                continue;
            }
            if (DB::table('mp_site_pages')->where('url_hash', $h)->exists()) {
                $ps++;
                continue;
            }
            $pu++;
            if (!$dry) {
                DB::table('mp_site_pages')->where('id', $r->id)->update(['url_hash' => $h]);
            }
        }

        // Найденные страницы: уникальны (product_id, url_hash).
        [$lu, $ls] = [0, 0];
        foreach (DB::table('mp_listings')->whereRaw($where)->orderBy('id')->lazyById(1000, 'id') as $r) {
            $h = UrlCanonicalizer::hash($r->url);
            if ($h === $r->url_hash) {
                continue;
            }
            if (DB::table('mp_listings')->where('product_id', $r->product_id)->where('url_hash', $h)->exists()) {
                $ls++;
                continue;
            }
            $lu++;
            if (!$dry) {
                DB::table('mp_listings')->where('id', $r->id)->update(['url_hash' => $h]);
            }
        }
        $this->info("Индекс: пересчитано {$pu}, оставлено (дубль адреса) {$ps}; страницы: пересчитано {$lu}, оставлено {$ls}" . ($dry ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
