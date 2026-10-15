<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MpAsk;
use App\Models\Monitoring\ProgramItem;
use App\Services\Monitoring\ProductIdentityResolver;
use App\Support\Monitoring\BrandNormalizer;
use App\Support\Monitoring\ItemScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Разовый перевод на ItemScope (инцидент 15.10.2026, M02502) и диагностика «узких» позиций:
 *  1. program_items.identity_codes — коды каждой позиции (артикул + oem_articles / коды из названия);
 *  2. листинги товаров с узкими позициями — на перепроверку сейчас: проверка вычислит matched_codes
 *     (какие коды есть на странице) и пересчитает «подозрительно» по ценам того же варианта;
 *  3. запланированные письма Гибрида о страницах, не видимых позиции, — отменить;
 *  4. узким позициям — prices_changed_at, чтобы клиент перечитал их в инкрементальной выгрузке.
 */
class ItemScopeCommand extends Command
{
    protected $signature = 'monitoring:item-scope {--dry-run : Только показать}';

    protected $description = 'Мониторинг: коды позиций клиента и фильтр страниц чужих вариантов (ItemScope)';

    public function handle(ProductIdentityResolver $resolver): int
    {
        $dry = (bool) $this->option('dry-run');

        // 1. Коды позиций.
        $filled = 0;
        ProgramItem::query()->orderBy('id')->chunkById(500, function ($items) use ($resolver, $dry, &$filled) {
            foreach ($items as $item) {
                $codes = $resolver->itemCodes($item, BrandNormalizer::normalize($item->raw_brand));
                if (($item->identity_codes ?? null) !== $codes) {
                    $filled++;
                    if (!$dry) {
                        $item->forceFill(['identity_codes' => $codes])->saveQuietly();
                    }
                }
            }
        });
        $this->info("Коды позиций: обновлено {$filled}");

        // 2. Узкие позиции и их товары.
        $items = DB::table('program_items')->where('active', true)->whereNotNull('product_id')
            ->get(['id', 'program_id', 'client_ref', 'product_id', 'identity_codes']);
        if ($dry) {
            // В dry-run коды не записаны — считаем на лету.
            $byId = ProgramItem::query()->whereIn('id', $items->pluck('id'))->get()->keyBy('id');
            $items = $items->map(function ($i) use ($byId, $resolver) {
                $m = $byId[$i->id];
                $i->identity_codes = json_encode($resolver->itemCodes($m, BrandNormalizer::normalize($m->raw_brand)));

                return $i;
            });
        }
        $productCodes = ItemScope::productCodes($items->pluck('product_id')->all());
        $narrow = $items->filter(fn ($i) => ItemScope::isNarrow(ItemScope::itemCodes($i), $productCodes[(int) $i->product_id] ?? []));
        $products = $narrow->pluck('product_id')->unique()->values();
        $this->info("Узких позиций: {$narrow->count()} (товаров {$products->count()}), по программам: "
            . json_encode($narrow->countBy('program_id')->all()));

        $listings = DB::table('mp_listings')->whereIn('product_id', $products)->where('bench', false)
            ->whereIn('status', ['active', 'suspicious'])->get(['id', 'product_id', 'status', 'price', 'matched_codes', 'match_method', 'match_evidence']);
        $hidden = 0;
        foreach ($narrow as $i) {
            foreach ($listings->where('product_id', $i->product_id) as $l) {
                if (!ItemScope::allows(ItemScope::itemCodes($i), $productCodes[(int) $i->product_id] ?? [],
                    ItemScope::listingCodes($l->matched_codes, $l->match_method, $l->match_evidence))) {
                    $hidden++;
                }
            }
        }
        $this->info("Страниц этих товаров (active/suspicious): {$listings->count()}; пар «узкая позиция × скрытая страница»: {$hidden}");
        if (!$dry && $listings->isNotEmpty()) {
            DB::table('mp_listings')->whereIn('id', $listings->pluck('id'))
                ->update(['next_check_at' => now(), 'content_hash' => null, 'updated_at' => now()]);
            $this->info("На перепроверку: {$listings->count()} страниц");
        }

        // 3. Запланированные письма о невидимых позиции страницах.
        $asks = MpAsk::query()->where('status', MpAsk::STATUS_PLANNED)->whereNotNull('listing_id')->with('listing')->get();
        $itemsByKey = $items->groupBy(fn ($i) => $i->program_id . '|' . $i->product_id);
        $cancel = $asks->filter(function (MpAsk $a) use ($itemsByKey, $productCodes) {
            $l = $a->listing;
            $cands = $itemsByKey[$a->program_id . '|' . $a->product_id] ?? collect();
            if (!$l || $cands->isEmpty()) {
                return false;
            }

            return $cands->every(fn ($i) => !ItemScope::allows(ItemScope::itemCodes($i), $productCodes[(int) $a->product_id] ?? [],
                ItemScope::listingCodes($l->matched_codes, $l->match_method, $l->match_evidence)));
        });
        $this->info("Запланированных писем о чужих вариантах: {$cancel->count()}");
        if (!$dry && $cancel->isNotEmpty()) {
            MpAsk::query()->whereIn('id', $cancel->pluck('id'))->update(['status' => MpAsk::STATUS_CANCELLED, 'updated_at' => now()]);
        }

        // 4. Клиент перечитает узкие позиции.
        if (!$dry && $narrow->isNotEmpty()) {
            DB::table('program_items')->whereIn('id', $narrow->pluck('id'))->update(['prices_changed_at' => now()]);
        }

        foreach ($narrow->take(10) as $i) {
            $this->line("  {$i->client_ref} (программа {$i->program_id}): свои " . implode('/', ItemScope::itemCodes($i))
                . ' | у товара ещё ' . implode('/', array_slice(array_diff($productCodes[(int) $i->product_id] ?? [], ItemScope::itemCodes($i)), 0, 6)));
        }

        return self::SUCCESS;
    }
}
