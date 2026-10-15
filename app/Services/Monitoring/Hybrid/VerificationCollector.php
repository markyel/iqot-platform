<?php

namespace App\Services\Monitoring\Hybrid;

use App\Models\Monitoring\MpAsk;
use App\Models\Monitoring\MpKpOffer;
use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpPriceObservation;
use App\Models\Monitoring\MpSite;
use App\Models\Monitoring\MpVerification;
use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\BrandNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Итоги Ф3 «Гибрида»:
 *  - collectAsks(): статусы отправки проверочных писем; КП на позицию → сверка с ценой сайта на момент
 *    запроса (PriceComparator) → доверие сайта (TrustPolicy); отказ «нет в наличии» → сверка
 *    availability; без ответа 14 дней → expired (не штраф);
 *  - linkRequestItems(): позиции обычных заявок ↔ канонические товары (OEM/MPN, бренд);
 *  - collectRfq(): «бесплатные» сверки — КП обычной заявки от поставщика, у чьего сайта есть цена на
 *    тот же товар (решение владельца 2026-10-15).
 */
class VerificationCollector
{
    private const CONN = 'reports';

    private const SENT_STATUSES = ['sent', 'opened', 'replied', 'reply_processed', 'in_conversation', 'completed'];

    /** @return array<string,int> */
    public function collectAsks(): array
    {
        $stats = ['sent' => 0, 'verified' => 0, 'kp' => 0, 'availability' => 0, 'declined' => 0, 'expired' => 0, 'requeued' => 0];

        // 1. Отправка.
        $queued = MpAsk::query()->where('status', MpAsk::STATUS_QUEUED)->whereNotNull('email_queue_id')->get();
        if ($queued->isNotEmpty()) {
            $eq = DB::connection(self::CONN)->table('email_queue')->whereIn('id', $queued->pluck('email_queue_id')->unique())
                ->get(['id', 'status', 'sent_at', 'retry_count', 'max_retries'])->keyBy('id');
            foreach ($queued as $ask) {
                $e = $eq[$ask->email_queue_id] ?? null;
                if ($e && in_array($e->status, self::SENT_STATUSES, true)) {
                    $ask->forceFill(['status' => MpAsk::STATUS_SENT, 'sent_at' => $e->sent_at ?? now()])->save();
                    $stats['sent']++;
                } elseif ($e === null || $e->status === 'cancelled'
                    || ($e->status === 'error' && (int) $e->retry_count >= (int) $e->max_retries)) {
                    $ask->forceFill(['status' => MpAsk::STATUS_PLANNED, 'request_item_id' => null, 'batch_id' => null,
                        'email_queue_id' => null, 'queued_at' => null])->save();
                    $stats['requeued']++;
                }
            }
        }

        // 2. Ответы: КП по позиции тех. заявки.
        $open = MpAsk::query()->whereIn('status', [MpAsk::STATUS_QUEUED, MpAsk::STATUS_SENT])->whereNotNull('request_item_id')->get();
        if ($open->isNotEmpty()) {
            $rir = DB::connection(self::CONN)->table('request_item_responses')
                ->whereIn('request_item_id', $open->pluck('request_item_id'))
                ->whereIn('status', ['received', 'processed'])
                ->where(fn ($q) => $q->where('price_per_unit', '>', 0)->orWhere('total_price', '>', 0))
                ->get(['request_item_id', 'supplier_id', 'price_per_unit', 'total_price', 'currency', 'price_includes_vat', 'notes', 'batch_id'])
                ->keyBy(fn ($r) => $r->request_item_id . '|' . $r->supplier_id);
            $hints = $this->offerHints($open);
            foreach ($open as $ask) {
                $r = $rir[$ask->request_item_id . '|' . $ask->supplier_id] ?? null;
                if ($r === null) {
                    continue;
                }
                $kp = (float) ($r->price_per_unit ?: $r->total_price);
                $h = ($hints[$ask->request_item_id] ?? []) + ['notes' => $r->notes];
                $vat = $r->price_includes_vat === null ? null : (bool) $r->price_includes_vat;
                if (!PriceComparator::isAnalog($h)) {
                    $this->recordKp($ask, $kp, $vat, $r->currency, $r->notes);
                }
                if ($ask->kind === 'verify') {
                    $this->verify(MpSite::find($ask->site_id), 'ask', (int) $ask->request_item_id, (int) $ask->supplier_id, (int) $ask->product_id,
                        (int) $ask->listing_id, $ask->site_price, $ask->site_vat_included, $ask->site_currency,
                        $kp, $vat, $r->currency, $h, $ask->id);
                    $stats['verified']++;
                } else {
                    $stats['kp']++;
                }
                $ask->forceFill(['status' => MpAsk::STATUS_ANSWERED, 'answered_at' => now()])->save();
            }

            // 3. Отказ «нет в наличии» по письму — сверка наличия (уровень не трогает).
            foreach ($this->notAvailable($open) as $askId) {
                $ask = $open->firstWhere('id', $askId);
                if (!$ask || $ask->status === MpAsk::STATUS_ANSWERED) {
                    continue;
                }
                if ($ask->kind !== 'verify') {
                    // Запрос КП: «нет в наличии» — просто закрываем (сайта/листинга для сверки нет или цены нет).
                    $ask->forceFill(['status' => MpAsk::STATUS_ANSWERED, 'answered_at' => now()])->save();
                    $stats['declined']++;
                    continue;
                }
                $this->verify(MpSite::find($ask->site_id), 'ask', (int) $ask->request_item_id, (int) $ask->supplier_id, (int) $ask->product_id,
                    (int) $ask->listing_id, $ask->site_price, $ask->site_vat_included, $ask->site_currency, null, null, null,
                    [], $ask->id, 'availability', 'поставщик: нет в наличии');
                $ask->forceFill(['status' => MpAsk::STATUS_ANSWERED, 'answered_at' => now()])->save();
                $stats['availability']++;
            }
        }

        // 4. Запрос КП так и не ушёл (не хватило ящиков/капов за 14 дней) — отменяем, чтобы планировщик
        //    мог запросить заново (expired блокирует повтор на email_refresh_days).
        $stats['unsent_cancelled'] = MpAsk::query()->where('kind', 'rfq')->where('status', MpAsk::STATUS_PLANNED)
            ->where('expires_at', '<', now())->update(['status' => MpAsk::STATUS_CANCELLED, 'updated_at' => now()]);

        // 5. Без ответа — expired (не штраф сайту).
        $stats['expired'] =MpAsk::query()->whereIn('status', [MpAsk::STATUS_SENT, MpAsk::STATUS_PLANNED])
            ->where('expires_at', '<', now())->update(['status' => MpAsk::STATUS_EXPIRED, 'updated_at' => now()]);

        return $stats;
    }

    /** Позиции обычных заявок ↔ канонические товары. @return array<string,int> */
    public function linkRequestItems(int $days = 30): array
    {
        $items = DB::connection(self::CONN)->table('request_items as ri')->join('requests as r', 'r.id', '=', 'ri.request_id')
            ->where('r.source', '!=', 'monitoring')->where('ri.created_at', '>=', now()->subDays($days))
            ->whereNotNull('ri.article')->where('ri.article', '!=', '')
            ->get(['ri.id', 'ri.article', 'ri.brand']);
        $known = DB::table('mp_request_item_links')->whereIn('request_item_id', $items->pluck('id'))->pluck('request_item_id')->flip();
        $stats = ['linked' => 0, 'ambiguous' => 0, 'none' => 0];
        $rows = [];
        foreach ($items as $it) {
            if (isset($known[$it->id])) {
                continue;
            }
            $norm = ArticleNormalizer::normalize($it->article);
            $cands = $norm === '' ? collect() : DB::table('mp_identifiers')->where('value_norm', $norm)->whereIn('kind', ['oem', 'mpn'])
                ->get(['product_id', 'brand_norm'])->unique('product_id');
            if ($cands->count() > 1 && $it->brand) {
                $bn = BrandNormalizer::normalize($it->brand);
                $byBrand = $cands->filter(fn ($c) => $c->brand_norm === $bn);
                $cands = $byBrand->isNotEmpty() ? $byBrand : $cands;
            }
            $status = $cands->count() === 1 ? 'linked' : ($cands->count() > 1 ? 'ambiguous' : 'none');
            $stats[$status]++;
            $rows[] = ['request_item_id' => $it->id, 'product_id' => $status === 'linked' ? $cands->first()->product_id : null,
                'status' => $status, 'linked_at' => now()];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('mp_request_item_links')->insertOrIgnore($chunk);
        }

        return $stats;
    }

    /** «Бесплатные» сверки из КП обычных заявок. @return int сверок */
    public function collectRfq(int $days = 30): int
    {
        $links = DB::table('mp_request_item_links')->where('status', 'linked')->where('linked_at', '>=', now()->subDays($days + 30))
            ->pluck('product_id', 'request_item_id');
        if ($links->isEmpty()) {
            return 0;
        }
        $made = 0;
        foreach ($links->keys()->chunk(500) as $chunk) {
            $responses = DB::connection(self::CONN)->table('request_item_responses')->whereIn('request_item_id', $chunk->all())
                ->whereIn('status', ['received', 'processed'])->where('response_received_at', '>=', now()->subDays($days))
                ->where(fn ($q) => $q->where('price_per_unit', '>', 0)->orWhere('total_price', '>', 0))
                ->get(['request_item_id', 'supplier_id', 'price_per_unit', 'total_price', 'currency', 'price_includes_vat', 'notes', 'response_received_at']);
            foreach ($responses as $r) {
                if (MpVerification::query()->where('source', 'rfq')->where('request_item_id', $r->request_item_id)->where('supplier_id', $r->supplier_id)->exists()) {
                    continue;
                }
                $productId = (int) $links[$r->request_item_id];
                $at = \Illuminate\Support\Carbon::parse($r->response_received_at);
                $listing = MpListing::query()->join('mp_sites as s', 's.id', '=', 'mp_listings.site_id')
                    ->where('s.supplier_id', $r->supplier_id)->where('mp_listings.product_id', $productId)
                    ->where('mp_listings.bench', false)->where('mp_listings.status', MpListing::STATUS_ACTIVE)->whereNotNull('mp_listings.price')
                    ->whereBetween('mp_listings.observed_at', [$at->copy()->subDays(10), $at->copy()->addDays(10)])
                    ->select('mp_listings.*')->first();
                if (!$listing) {
                    continue;
                }
                $this->verify(MpSite::find($listing->site_id), 'rfq', (int) $r->request_item_id, (int) $r->supplier_id, $productId,
                    (int) $listing->id, (float) $listing->price, $listing->vat_included, $listing->currency,
                    (float) ($r->price_per_unit ?: $r->total_price), $r->price_includes_vat === null ? null : (bool) $r->price_includes_vat,
                    $r->currency, ['notes' => $r->notes]);
                $made++;
            }
        }

        return $made;
    }

    /**
     * КП поставщика по товару мониторинга — источник цены «Есть КП / счёт» (в т.ч. без цены на сайте).
     * Повторный ответ по той же позиции тех. заявки перезаписывает КП.
     */
    private function recordKp(MpAsk $ask, float $price, ?bool $vat, ?string $currency, ?string $notes): void
    {
        $source = $ask->kind === 'verify' ? 'verify' : ($ask->reason === 'pool' ? 'pool' : 'on_request');
        MpKpOffer::query()->updateOrCreate(
            ['request_item_id' => (int) $ask->request_item_id, 'supplier_id' => (int) $ask->supplier_id],
            [
                'product_id' => (int) $ask->product_id, 'ask_id' => $ask->id, 'listing_id' => $ask->listing_id, 'site_id' => $ask->site_id,
                'source' => $source, 'price' => round($price, 2),
                'currency' => $currency ? strtoupper(mb_substr($currency, 0, 3)) : 'RUB', 'vat_included' => $vat,
                'notes' => $notes ? mb_substr($notes, 0, 255) : null, 'received_at' => now(),
            ]
        );
        // КП по листингу «по запросу» — в историю цен листинга (цена сайта не подменяется).
        if ($ask->kind !== 'verify' && $ask->listing_id) {
            MpPriceObservation::create([
                'listing_id' => $ask->listing_id, 'product_id' => (int) $ask->product_id, 'price' => round($price, 2),
                'currency' => $currency ? strtoupper(mb_substr($currency, 0, 3)) : 'RUB', 'vat_included' => $vat,
                'method' => 'kp', 'status' => MpPriceObservation::STATUS_OK, 'note' => 'КП (по запросу)', 'observed_at' => now(),
            ]);
        }
        DB::table('program_items')->where('product_id', $ask->product_id)->update(['prices_changed_at' => now()]);
    }

    private function verify(?MpSite $site, string $source, int $requestItemId, int $supplierId, int $productId, int $listingId,
        ?float $sitePrice, ?bool $siteVat, ?string $siteCurrency, ?float $kpPrice, ?bool $kpVat, ?string $kpCurrency,
        array $hints, ?int $askId = null, ?string $forceOutcome = null, ?string $forceNote = null): void
    {
        if (!$site) {
            return;
        }
        if (MpVerification::query()->where('source', $source)->where('request_item_id', $requestItemId)->where('supplier_id', $supplierId)->exists()) {
            return;
        }
        $cmp = $forceOutcome !== null
            ? ['outcome' => $forceOutcome, 'diff_pct' => null, 'note' => $forceNote]
            : PriceComparator::compare($sitePrice, $siteVat, $siteCurrency, $kpPrice, $kpVat, $kpCurrency, $hints);

        DB::transaction(function () use ($site, $source, $requestItemId, $supplierId, $productId, $listingId, $sitePrice, $siteVat, $kpPrice, $kpVat, $kpCurrency, $askId, $cmp) {
            $levels = TrustPolicy::apply($site, $cmp['outcome']);
            $site->save();
            MpVerification::create([
                'site_id' => $site->id, 'listing_id' => $listingId ?: null, 'product_id' => $productId, 'supplier_id' => $supplierId,
                'ask_id' => $askId, 'source' => $source, 'request_item_id' => $requestItemId,
                'site_price' => $sitePrice, 'site_vat_included' => $siteVat, 'kp_price' => $kpPrice,
                'kp_currency' => $kpCurrency ? strtoupper(mb_substr($kpCurrency, 0, 3)) : null, 'kp_vat_included' => $kpVat,
                'diff_pct' => $cmp['diff_pct'], 'outcome' => $cmp['outcome'], 'note' => $cmp['note'] ? mb_substr($cmp['note'], 0, 255) : null,
                'level_before' => $levels['level_before'], 'level_after' => $levels['level_after'], 'verified_at' => now(),
            ]);
            // Подтверждение/КП — изменение для клиента: позиция попадёт в инкрементальную выгрузку API.
            DB::table('program_items')->where('product_id', $productId)->update(['prices_changed_at' => now()]);
            $listing = $listingId ? MpListing::find($listingId) : null;
            if ($listing && $cmp['outcome'] === 'match') {
                $listing->forceFill(['confirmed_price' => $sitePrice, 'confirmed_at' => now()])->save();
            }
            if ($listing && $kpPrice !== null) {
                // КП — в историю цен листинга (признак «Есть КП / счёт»), цена сайта не подменяется.
                MpPriceObservation::create([
                    'listing_id' => $listing->id, 'product_id' => $productId, 'price' => $kpPrice,
                    'currency' => $kpCurrency ? strtoupper(mb_substr($kpCurrency, 0, 3)) : 'RUB', 'vat_included' => $kpVat,
                    'method' => 'kp', 'status' => MpPriceObservation::STATUS_OK,
                    'note' => "КП ({$source}), сверка: {$cmp['outcome']}", 'observed_at' => now(),
                ]);
            }
        });
    }

    /**
     * Подсказки AI-разбора по позициям (offer_type/notes) из email_messages.ai_classification бесед
     * батчей проверочных писем.
     *
     * @return array<int, array{offer_type?: ?string, notes?: ?string}>
     */
    private function offerHints($asks): array
    {
        $out = [];
        foreach ($this->classifications($asks) as $cls) {
            foreach ((array) ($cls['offers'] ?? []) as $o) {
                $iid = (int) ($o['item_id'] ?? 0);
                if ($iid > 0) {
                    $out[$iid] = ['offer_type' => $o['offer_type'] ?? null,
                        'notes' => trim(($o['notes'] ?? '') . ' ' . (!empty($o['is_alternative_to_position']) ? 'аналог' : ''))];
                }
            }
        }

        return $out;
    }

    /** @return array<int,int> id запросов с отказом «нет в наличии» в ответе на их письмо */
    private function notAvailable($asks): array
    {
        $ids = [];
        foreach ($this->classifications($asks, true) as $key => $cls) {
            if (($cls['email_type'] ?? null) === 'rejection' && ($cls['rejection_reason'] ?? null) === 'not_available') {
                [$batchId, $supplierId] = explode('|', $key);
                foreach ($asks as $a) {
                    if ((int) $a->batch_id === (int) $batchId && (int) $a->supplier_id === (int) $supplierId) {
                        $ids[] = $a->id;
                    }
                }
            }
        }

        return $ids;
    }

    /** @return array<string|int, array<string,mixed>> разобранные AI ответы по беседам «батч|поставщик» */
    private function classifications($asks, bool $keyed = false): array
    {
        $pairs = $asks->filter(fn ($a) => $a->batch_id)->map(fn ($a) => [(int) $a->batch_id, (int) $a->supplier_id])->unique(fn ($p) => $p[0] . '|' . $p[1]);
        $out = [];
        foreach ($pairs as [$batchId, $supplierId]) {
            $conv = DB::connection(self::CONN)->table('email_conversations')->where('batch_id', $batchId)->where('supplier_id', $supplierId)->value('id');
            if (!$conv) {
                continue;
            }
            $msgs = DB::connection(self::CONN)->table('email_messages')->where('conversation_id', $conv)->where('direction', 'incoming')
                ->where('ai_processed', 1)->whereNotNull('ai_classification')->pluck('ai_classification');
            foreach ($msgs as $i => $json) {
                $cls = json_decode((string) $json, true);
                if (is_array($cls)) {
                    $keyed ? $out["{$batchId}|{$supplierId}"] = $cls : $out[] = $cls;
                }
            }
        }

        return $out;
    }
}
