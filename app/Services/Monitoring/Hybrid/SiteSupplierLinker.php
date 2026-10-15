<?php

namespace App\Services\Monitoring\Hybrid;

use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Support\Facades\DB;

/**
 * Сайт продавца ↔ поставщик IQOT (reports.suppliers) — кому слать проверочный КП (Ф3).
 * Совпадение по домену сайта поставщика (website) или домену его e-mail. Из нескольких
 * кандидатов (у revator их 6) — рабочий для рассылки: активен, notify_email, не на паузе
 * отписки, e-mail на домене сайта; при равенстве — кто чаще отвечал и давал цены.
 * Проба 15.10.2026: 97,6% листингов с ценой лежат на сайтах, сопоставимых с поставщиками.
 */
class SiteSupplierLinker
{
    /**
     * Проставить mp_sites.supplier_id там, где его нет (или --relink).
     *
     * @return array{linked: int, none: int}
     */
    public function linkAll(bool $relink = false): array
    {
        $index = $this->supplierIndex();
        $linked = 0;
        $none = 0;
        $sites = DB::table('mp_sites')->when(!$relink, fn ($q) => $q->whereNull('supplier_id'))->get(['id', 'domain']);
        foreach ($sites as $site) {
            $best = $this->best($index[$site->domain] ?? [], $site->domain);
            if ($best === null) {
                $none++;
                continue;
            }
            DB::table('mp_sites')->where('id', $site->id)->update(['supplier_id' => $best->id, 'updated_at' => now()]);
            $linked++;
        }

        return ['linked' => $linked, 'none' => $none];
    }

    /** @return array<string, array<int, object>> домен → поставщики */
    private function supplierIndex(): array
    {
        $out = [];
        $rows = DB::connection('reports')->table('suppliers')
            ->get(['id', 'email', 'website', 'is_active', 'notify_email', 'unsubscribe_until', 'total_responses_received', 'total_prices_offered']);
        foreach ($rows as $s) {
            $domains = [];
            if ($s->website) {
                $domains[] = UrlCanonicalizer::registrableDomain(str_contains($s->website, '//') ? $s->website : 'http://' . $s->website);
            }
            if ($s->email && str_contains($s->email, '@')) {
                $domains[] = UrlCanonicalizer::registrableDomain(substr(strrchr($s->email, '@'), 1));
            }
            foreach (array_unique(array_filter($domains)) as $d) {
                $out[$d][] = $s;
            }
        }

        return $out;
    }

    /** @param array<int, object> $candidates */
    private function best(array $candidates, string $domain): ?object
    {
        $usable = array_filter($candidates, fn ($s) => (int) $s->is_active === 1 && (int) $s->notify_email === 1
            && trim((string) $s->email) !== ''
            && ($s->unsubscribe_until === null || strtotime((string) $s->unsubscribe_until) < time()));
        if ($usable === []) {
            return null;
        }
        usort($usable, function ($a, $b) use ($domain) {
            $ad = str_ends_with(mb_strtolower((string) $a->email), '@' . $domain) ? 1 : 0;
            $bd = str_ends_with(mb_strtolower((string) $b->email), '@' . $domain) ? 1 : 0;

            return [$bd, (int) $b->total_prices_offered, (int) $b->total_responses_received]
                <=> [$ad, (int) $a->total_prices_offered, (int) $a->total_responses_received];
        });

        return $usable[0];
    }
}
