<?php

namespace App\Services\AliasExp;

use App\Services\Senders\OrganizationExcelParser;
use Illuminate\Support\Facades\DB;

/**
 * Резерв организаций алгоритма «ящик на батч» (docs/alias-mailbox-design.md, Ф1).
 * Загрузка — Excel ExportBase (тот же парсер, что у генератора отправителей); upsert по ИНН.
 * Выбор для нового ящика — самая давно использованная: сначала без живых ящиков, затем ни разу не
 * использованные, затем по last_used_at (повторное использование разрешено — решение владельца 16.10.2026).
 */
class OrgReserve
{
    /**
     * Госсектор и некоммерческие учреждения — не легенда (решение владельца 16.10.2026: фонды, ГУП, МУП, учреждения).
     * Ищется в кратком и полном наименовании.
     */
    private const PUBLIC_SECTOR_RE = '/(^|[^\p{L}])(фонд(?:а|у|ом|е|ы|ов)?|ф?гу?п|муп|[фгм]б?к?а?у|гбоу|мбоу|гкоу|гбуз|фгбу|фгбоу|фкп|фку'
        . '|учрежден\p{L}*|государственн\p{L}*|муниципальн\p{L}*|казенн\p{L}*|казённ\p{L}*|бюджетн\p{L}*|унитарн\p{L}*'
        . '|администраци\p{L}*|министерств\p{L}*|департамент\p{L}*|комитет\p{L}*|правительств\p{L}*|префектур\p{L}*'
        . '|некоммерческ\p{L}*|региональный оператор)($|[^\p{L}])/iu';

    public static function isPublicSector(?string ...$names): bool
    {
        foreach ($names as $n) {
            if ($n !== null && preg_match(self::PUBLIC_SECTOR_RE, $n)) {
                return true;
            }
        }

        return false;
    }

    public function __construct(private readonly OrganizationExcelParser $parser)
    {
    }

    /** @return array{rows: int, added: int, updated: int, skipped: int, excluded: int} */
    public function import(string $path, ?string $sourceName = null): array
    {
        $rows = $this->parser->parse($path);
        $stats = ['rows' => count($rows), 'added' => 0, 'updated' => 0, 'skipped' => 0, 'excluded' => 0];
        $existing = DB::table('alias_org_reserve')->pluck('id', 'inn');
        $seen = [];
        $now = now();
        foreach ($rows as $r) {
            $inn = preg_replace('/\D/', '', (string) ($r['inn'] ?? ''));
            $name = trim((string) ($r['name'] ?? $r['full_name'] ?? ''));
            // ИНН юрлица — 10 цифр, ИП — 12; без ИНН или названия — не организация.
            if (!in_array(strlen($inn), [10, 12], true) || $name === '' || isset($seen[$inn])) {
                $stats['skipped']++;
                continue;
            }
            $seen[$inn] = true;
            // Госсектор хранится выключенным (видно, сколько отсеяно; повторная загрузка не вернёт).
            $public = self::isPublicSector($name, $r['full_name'] ?? null);
            $stats['excluded'] += (int) $public;
            $data = [
                'kpp' => $this->digits($r['kpp'] ?? null, [9]),
                'ogrn' => $this->digits($r['ogrn'] ?? null, [13, 15]),
                'name' => mb_substr($name, 0, 500),
                'full_name' => $this->cut($r['full_name'] ?? null, 1000),
                'okved' => $this->cut($r['okved'] ?? null, 255),
                'legal_address' => $this->cut($r['legal_address'] ?? null, 2000),
                'city' => $this->cut($r['city'] ?? null, 255),
                'region' => $this->cut($r['region'] ?? null, 255),
                'phone' => $this->cut($r['phone'] ?? ($r['mobile'] ?? null), 50),
                'email' => $this->cut($r['email'] ?? null, 255),
                'director_name' => $this->cut($r['director_name'] ?? null, 255),
                'director_position' => $this->cut($r['director_position'] ?? null, 255),
                'source_file' => $sourceName ? mb_substr($sourceName, 0, 255) : null,
                'updated_at' => $now,
            ] + ($public ? ['active' => false] : []);
            if (isset($existing[$inn])) {
                // Повторная загрузка обновляет реквизиты, счётчики использования не трогает.
                DB::table('alias_org_reserve')->where('id', $existing[$inn])->update($data);
                $stats['updated']++;
            } else {
                DB::table('alias_org_reserve')->insert($data + ['inn' => $inn, 'created_at' => $now]);
                $stats['added']++;
            }
        }

        return $stats;
    }

    /**
     * Название для подписи: ExportBase дописывает вид деятельности через запятую («К-электро, Компания»,
     * «Аскон, IT-компания», «Дон, группа строительных компаний») — в подписи живого закупщика так не пишут.
     */
    public static function displayName(string $name): string
    {
        $name = trim($name);
        if (preg_match('/^(.+?),\s*(.+)$/u', $name, $m)
            && preg_match('/(компани|холдинг|групп|дистрибьютор|центр|производств|фирм|завод|магазин|студи|сервис|предприяти|организаци)/iu', $m[2])) {
            return trim($m[1]);
        }

        return $name;
    }

    /** Выключить госсектор среди уже загруженных. @return int выключено */
    public function excludePublicSector(): int
    {
        $ids = DB::table('alias_org_reserve')->where('active', true)->get(['id', 'name', 'full_name'])
            ->filter(fn ($r) => self::isPublicSector($r->name, $r->full_name))->pluck('id')->all();
        if ($ids !== []) {
            DB::table('alias_org_reserve')->whereIn('id', $ids)->update(['active' => false, 'updated_at' => now()]);
        }

        return count($ids);
    }

    /** Самая давно использованная активная организация (для нового ящика). */
    public function pickLeastRecentlyUsed(): ?object
    {
        return DB::table('alias_org_reserve')->where('active', true)
            ->orderBy('live_mailboxes')
            ->orderByRaw('last_used_at IS NOT NULL')
            ->orderBy('last_used_at')
            ->orderBy('id')
            ->first();
    }

    /** Отметить использование организации новым ящиком. */
    public function markUsed(int $id): void
    {
        DB::table('alias_org_reserve')->where('id', $id)->update([
            'uses' => DB::raw('uses + 1'), 'live_mailboxes' => DB::raw('live_mailboxes + 1'),
            'last_used_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Ящик на организации удалён. */
    public function release(int $id): void
    {
        DB::table('alias_org_reserve')->where('id', $id)->where('live_mailboxes', '>', 0)
            ->update(['live_mailboxes' => DB::raw('live_mailboxes - 1'), 'updated_at' => now()]);
    }

    /** @return array{total: int, active: int, never_used: int, live: int, last_import: ?string} */
    public function stats(): array
    {
        $x = DB::selectOne('SELECT COUNT(*) total, SUM(active) active, SUM(active AND uses = 0) never_used,
            SUM(live_mailboxes > 0) live, MAX(created_at) last_import FROM alias_org_reserve');

        return ['total' => (int) $x->total, 'active' => (int) $x->active, 'never_used' => (int) $x->never_used,
            'live' => (int) $x->live, 'last_import' => $x->last_import];
    }

    private function cut(mixed $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    /** Реквизит — только цифры строгой длины, иначе null (как в ClientOrganization::sanitizeImportAttributes). */
    private function digits(mixed $v, array $lengths): ?string
    {
        $d = preg_replace('/\D/', '', (string) $v);

        return in_array(strlen($d), $lengths, true) ? $d : null;
    }
}
