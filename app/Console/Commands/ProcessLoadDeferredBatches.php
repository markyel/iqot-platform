<?php

namespace App\Console\Commands;

use App\Jobs\GenerateCampaignJob;
use App\Services\Generate\Batch;
use App\Services\Generate\CampaignSupplierSelector;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Накопительная отсрочка по загрузке получателей (Version A). Тонкие анонимные батчи,
 * отложенные генератором в deferred_batches (reason='recipient_load', status='accumulating')
 * с ключом (product_type_id, domain_id), здесь группируются по этому ключу и ВЫПУСКАЮТСЯ,
 * когда выполнено любое из условий:
 *   - накопилось >= target_items однородных позиций (пришли новые заявки того же типа);
 *   - пул поставщиков разгрузился (доля загруженных <= loaded_fraction_pct%);
 *   - истёк max_hold_hours (свежесть — не держим заявку вечно).
 * Выпуск: объединяем позиции группы в один «носитель» (carrier), остальные строки →
 * 'done', диспатчим GenerateCampaignJob(carrier) в load-retry режиме (свежий таргетинг,
 * без повторной отсрочки).
 */
class ProcessLoadDeferredBatches extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:process-load-deferred {--limit=500 : Максимум накопителей за тик}';

    protected $description = 'Выпуск накопленных по загрузке получателей отложенных батчей (recipient_load)';

    public function handle(): int
    {
        if (!(bool) config('services.email_load_defer.enabled', false)) {
            $this->warn('Отсрочка по загрузке выключена (EMAILS_LOAD_DEFER_ENABLED=false).');
            return self::SUCCESS;
        }

        $target = max(1, (int) config('services.email_load_defer.target_items', 3));
        $maxHoldH = max(1, (int) config('services.email_load_defer.max_hold_hours', 48));
        $limit = max(1, (int) $this->option('limit'));

        $rows = DB::connection(self::CONN)->table('deferred_batches')
            ->where('reason', 'recipient_load')
            ->where('status', 'accumulating')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'item_ids', 'request_ids', 'product_type_id', 'domain_id', 'created_at']);

        if ($rows->isEmpty()) {
            $this->info('Нет накопителей recipient_load.');
            return self::SUCCESS;
        }

        // Группируем по ключу маршрутизации (тип+домен).
        $groups = [];
        foreach ($rows as $r) {
            $key = ((int) ($r->product_type_id ?? 0)) . '_' . ((int) ($r->domain_id ?? 0));
            $groups[$key][] = $r;
        }

        $released = 0;
        foreach ($groups as $key => $groupRows) {
            $itemIds = [];
            $requestIds = [];
            $oldest = null;
            foreach ($groupRows as $r) {
                foreach ((array) (json_decode((string) $r->item_ids, true) ?: []) as $id) {
                    $id = (int) $id;
                    if ($id > 0 && !in_array($id, $itemIds, true)) {
                        $itemIds[] = $id;
                    }
                }
                foreach ((array) (json_decode((string) $r->request_ids, true) ?: []) as $id) {
                    $id = (int) $id;
                    if ($id > 0 && !in_array($id, $requestIds, true)) {
                        $requestIds[] = $id;
                    }
                }
                $created = Carbon::parse((string) $r->created_at, 'UTC');
                if ($oldest === null || $created->lt($oldest)) {
                    $oldest = $created;
                }
            }

            if ($itemIds === []) {
                continue;
            }

            $ptid = (int) ($groupRows[0]->product_type_id ?? 0) ?: null;
            $did = (int) ($groupRows[0]->domain_id ?? 0) ?: null;

            $ageHours = $oldest ? $oldest->diffInHours(now()) : 0;
            $reasonRelease = null;
            if (count($itemIds) >= $target) {
                $reasonRelease = 'target(' . count($itemIds) . ')';
            } elseif ($ageHours >= $maxHoldH) {
                $reasonRelease = 'timeout(' . $ageHours . 'ч)';
            } elseif ($this->poolDrained($ptid, $did)) {
                $reasonRelease = 'drained';
            }

            if ($reasonRelease === null) {
                continue; // ещё копим
            }

            // Выпуск: носитель = первая строка, в неё объединяем позиции; остальные → done.
            $carrier = $groupRows[0];
            $claimed = DB::connection(self::CONN)->table('deferred_batches')
                ->where('id', $carrier->id)->where('status', 'accumulating')
                ->update([
                    'item_ids' => json_encode($itemIds, JSON_UNESCAPED_UNICODE),
                    'request_ids' => json_encode($requestIds, JSON_UNESCAPED_UNICODE),
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);
            if ($claimed === 0) {
                continue; // гонка — другой тик уже забрал
            }

            $siblingIds = [];
            foreach ($groupRows as $r) {
                if ((int) $r->id !== (int) $carrier->id) {
                    $siblingIds[] = (int) $r->id;
                }
            }
            if ($siblingIds !== []) {
                DB::connection(self::CONN)->table('deferred_batches')
                    ->whereIn('id', $siblingIds)->where('status', 'accumulating')
                    ->update(['status' => 'done', 'reason' => 'recipient_load:merged_into=' . $carrier->id, 'updated_at' => now()]);
            }

            GenerateCampaignJob::dispatch([], false, (int) $carrier->id);
            $released++;
            $this->info("Выпущен накопитель key={$key}: позиций=" . count($itemIds) . " ({$reasonRelease}), carrier={$carrier->id}, слито строк=" . count($siblingIds));
        }

        $this->info("Групп обработано: " . count($groups) . ", выпущено: {$released}.");
        return self::SUCCESS;
    }

    /**
     * Разгрузился ли пул поставщиков ключа (type, domain): доля адресатов с
     * pending >= loaded_pending упала <= loaded_fraction_pct%. Для OLD-routing (нет
     * product_type/domain) — не проверяем (полагаемся на target/timeout).
     */
    private function poolDrained(?int $ptid, ?int $did): bool
    {
        if ($ptid === null && $did === null) {
            return false;
        }
        try {
            $batch = new Batch();
            $batch->useNewRouting = true;
            $batch->isCustomerRequest = false;
            $batch->productTypeIds = $ptid ? [$ptid] : [];
            $batch->domainIds = $did ? [$did] : [];
            $batch->items = [];
            (new CampaignSupplierSelector())->select($batch); // заполняет $batch->suppliers
        } catch (\Throwable $e) {
            return false;
        }

        $emails = [];
        foreach ($batch->suppliers as $s) {
            $e = mb_strtolower(trim((string) ($s['email'] ?? '')));
            if ($e !== '' && !in_array($e, $emails, true)) {
                $emails[] = $e;
            }
        }
        if ($emails === []) {
            return false;
        }

        $threshold = max(1, (int) config('services.email_load_defer.loaded_pending', 10));
        $loaded = DB::connection(self::CONN)->table('email_queue')
            ->whereIn('to_email', $emails)
            ->where('status', 'pending')
            ->groupBy('to_email')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->get(['to_email'])
            ->count();

        $fraction = 100 * $loaded / count($emails);
        $minFraction = (int) config('services.email_load_defer.loaded_fraction_pct', 10);

        return $fraction <= $minFraction;
    }
}
