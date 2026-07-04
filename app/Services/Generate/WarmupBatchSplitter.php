<?php

namespace App\Services\Generate;

use Illuminate\Support\Facades\Log;

/**
 * Разбивка батча по остаткам дневных лимитов отправителей (Phase 3b, прогрев).
 *
 * Пул волны 1 может быть больше, чем остаток daily_limit одного ящика (start=30 при
 * прогреве). Тогда батч режется на ПОД-БАТЧИ: каждый — со СВОИМ отправителем, а значит
 * своим шаблоном/тоном/стилем токена и телом (анти-фингерпринт: под-батчи проходят
 * генерацию токена/тела/HTML независимо, единого генератора нет). Заодно диверсификация
 * доставки: большой объём не идёт одной пачкой с одного ящика.
 *
 * Учитывается и глобальный потолок платформы (global_daily_cap): суммарная нарезка
 * не превышает его остаток. Поставщики, на которых капасити не хватило, возвращаются
 * как leftover — вызывающий (GenerateCampaignJob) откладывает их в deferred_batches
 * (reason='sender_capacity') до освобождения лимитов.
 *
 * Волна 2 (пул расширения) сегодня не отправляется (held) и лимит не расходует —
 * раскладывается по под-батчам round-robin, чтобы её досыл тоже шёл с разных ящиков.
 */
class WarmupBatchSplitter
{
    public function __construct(
        private readonly CampaignSenderAssigner $assigner,
        private readonly SenderDailyCapacity $capacity,
    ) {
    }

    /**
     * null — прогрев выключен, идти старым путём (один батч = один отправитель).
     * Иначе [под-батчи, leftover-поставщики волны 1 без капасити].
     *
     * @return array{0:array<int,Batch>,1:array<int,array<string,mixed>>}|null
     */
    public function split(Batch $batch): ?array
    {
        if (!$this->capacity->enabled()) {
            return null;
        }

        $wave1 = $batch->suppliers;
        if ($wave1 === []) {
            return null;
        }

        $senders = $this->orderedCandidates($batch);
        if ($senders === []) {
            return [[], $wave1];
        }

        $remaining = $this->capacity->remainingMap(array_map(static fn ($s) => (int) $s['id'], $senders));
        $globalLeft = $this->capacity->globalRemaining();
        $maxSubs = max(1, (int) config('services.email_warmup.max_sub_batches', 10));

        $subs = [];
        $offset = 0;
        $total = count($wave1);

        foreach ($senders as $s) {
            if ($offset >= $total || count($subs) >= $maxSubs || $globalLeft <= 0) {
                break;
            }
            $sid = (int) $s['id'];
            $take = min((int) ($remaining[$sid] ?? 0), $globalLeft, $total - $offset);
            if ($take <= 0) {
                continue;
            }
            $subs[] = $this->makeSub($batch, $sid, array_slice($wave1, $offset, $take));
            $offset += $take;
            $globalLeft -= $take;
        }

        $leftover = array_slice($wave1, $offset);
        $this->distributeExpansion($batch, $subs);
        $this->distributeCold($batch, $subs);

        // Discovery-кандидаты Яндекс-таргетинга — только первому под-батчу
        // (иначе одни и те же домены уйдут в discovery по разу на под-батч).
        if ($subs !== []) {
            $subs[0]->discoveryCandidates = $batch->discoveryCandidates;
        }

        Log::info('WarmupBatchSplitter: батч нарезан по лимитам', [
            'request_ids' => $batch->requestIds,
            'wave1' => $total,
            'sub_batches' => count($subs),
            'senders' => array_map(static fn (Batch $b) => (int) ($b->sender['id'] ?? 0), $subs),
            'leftover' => count($leftover),
            'global_left' => $globalLeft,
        ]);

        return [$subs, $leftover];
    }

    /**
     * Кандидаты-отправители в порядке приоритета: уже назначенный ящик первым
     * (сохраняем выбор round-robin ассайнера), затем — для именной заявки персональный
     * ящик её организации, затем общий пул в порядке наименее недавнего использования.
     *
     * @return array<int,array<string,mixed>>
     */
    private function orderedCandidates(Batch $batch): array
    {
        $pool = $this->assigner->senderPool();
        if ($pool === []) {
            return [];
        }

        $assignedId = (int) ($batch->sender['id'] ?? 0);
        $orgId = $batch->isCustomerRequest ? (int) ($batch->clientOrganizationId ?? 0) : 0;

        $head = [];
        $personal = [];
        $shared = [];
        foreach ($pool as $s) {
            $sid = (int) $s['id'];
            $isPersonal = (int) ($s['is_personal'] ?? 0) === 1;
            if ($sid === $assignedId) {
                $head[] = $s;
            } elseif ($isPersonal && $orgId > 0 && (int) ($s['client_organization_id'] ?? 0) === $orgId) {
                $personal[] = $s;
            } elseif (!$isPersonal) {
                $shared[] = $s; // чужие персональные ящики в чужие батчи не попадают
            }
        }

        return array_merge($head, $personal, $shared);
    }

    /**
     * Под-батч: те же позиции/маршрутизация, но свой отправитель и свой срез
     * поставщиков. Токен/тело/шаблон НЕ копируются — сгенерируются под-батчу заново
     * по стилю его отправителя.
     */
    private function makeSub(Batch $batch, int $senderId, array $suppliers): Batch
    {
        $sub = clone $batch;
        $sub->sender = $senderId === (int) ($batch->sender['id'] ?? 0)
            ? $batch->sender
            : $this->assigner->fullSender($senderId);
        $sub->suppliers = array_values($suppliers);
        $sub->expansionSuppliers = [];
        $sub->coldSuppliers = [];
        $sub->discoveryCandidates = [];
        $sub->supplierIds = [];
        foreach ($sub->suppliers as $s) {
            $id = (int) ($s['id'] ?? 0);
            if ($id > 0 && !in_array($id, $sub->supplierIds, true)) {
                $sub->supplierIds[] = $id;
            }
        }
        $sub->trackingToken = null;
        $sub->aiBody = null;
        $sub->aiModel = null;
        $sub->emailTemplate = null;
        $sub->emailTone = null;
        $sub->batchId = null;

        return $sub;
    }

    /**
     * Волна 2 (held, лимит сегодня не расходует) — round-robin по под-батчам:
     * досыл расширения тоже пойдёт с разных ящиков.
     *
     * @param array<int,Batch> $subs
     */
    private function distributeExpansion(Batch $batch, array $subs): void
    {
        if ($subs === [] || $batch->expansionSuppliers === []) {
            return;
        }
        $n = count($subs);
        foreach (array_values($batch->expansionSuppliers) as $i => $supplier) {
            $subs[$i % $n]->expansionSuppliers[] = $supplier;
        }
    }

    /**
     * Холодная волна 3 (waves-v2, held, лимит сегодня не расходует) — round-robin по
     * под-батчам, зеркало distributeExpansion.
     *
     * @param array<int,Batch> $subs
     */
    private function distributeCold(Batch $batch, array $subs): void
    {
        if ($subs === [] || $batch->coldSuppliers === []) {
            return;
        }
        $n = count($subs);
        foreach (array_values($batch->coldSuppliers) as $i => $supplier) {
            $subs[$i % $n]->coldSuppliers[] = $supplier;
        }
    }
}
