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
    public function __construct(private readonly OrganizationExcelParser $parser)
    {
    }

    /** @return array{rows: int, added: int, updated: int, skipped: int} */
    public function import(string $path, ?string $sourceName = null): array
    {
        $rows = $this->parser->parse($path);
        $stats = ['rows' => count($rows), 'added' => 0, 'updated' => 0, 'skipped' => 0];
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
            ];
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
