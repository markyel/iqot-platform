<?php

namespace App\Services\AliasExp;

use App\Services\Generate\Batch;
use App\Services\Generate\CampaignBodyGenerator;
use App\Services\Generate\CampaignEmailBuilder;
use App\Services\Generate\CampaignPersister;
use App\Services\Generate\CampaignSenderAssigner;
use App\Services\Generate\CampaignTokenGenerator;
use App\Services\Generate\HomogeneousKey;
use App\Services\Api\OpenAIClassifierClient;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ветка «ящик на батч» в дневном плане (docs/alias-mailbox-design.md, Ф3).
 *
 * Доля позиций (services.alias_exp.share, 20%) выбирается стабильно по id позиции; в ветку попадает только
 * «чистая» позиция — не именной заявки и ещё никому не отправленная, чтобы ветки можно было честно сравнить.
 * Позиция, однажды ушедшая в ветку, основной веткой больше не берётся (claimedItemIds). Не влезшие в дневной
 * бюджет ящиков позиции доли уходят сегодня в основную ветку (иначе копился бы хвост).
 *
 * Батч — 1–2 позиции одного типа и области (однотипные — первыми); на батч — новый ящик (AliasMailboxFactory);
 * письма — всем поставщикам остаточного пула (релевантные по Яндексу — первыми), без меток, по слотам рабочих
 * часов с шагом паузы ящика (AliasWindow::slots).
 */
class AliasBranch
{
    private const CONN = 'reports';

    public function __construct(private readonly AliasMailboxFactory $factory)
    {
    }

    public static function enabled(): bool
    {
        return (bool) config('services.alias_exp.enabled', false);
    }

    /** Позиция в доле ветки (стабильно, независимо от дня). */
    public static function inShare(int $itemId): bool
    {
        $pct = (int) round(100 * max(0.0, min(1.0, (float) config('services.alias_exp.share', 0.2))));

        return $pct > 0 && (crc32('alias:' . $itemId) % 100) < $pct;
    }

    /** @return array<int,true> позиции, уже отданные ветке (их основная ветка не берёт) */
    public static function claimedItemIds(): array
    {
        $ids = [];
        try {
            foreach (DB::table('alias_mailboxes')->whereNotNull('request_item_ids')->pluck('request_item_ids') as $json) {
                foreach ((array) json_decode((string) $json, true) as $id) {
                    $ids[(int) $id] = true;
                }
            }
        } catch (\Throwable) {
            // таблицы ещё нет — ветка не работала
        }

        return $ids;
    }

    /** Сколько ящиков ещё можно создать сегодня (МСК-сутки). */
    public static function budgetToday(): int
    {
        $cap = (int) config('services.alias_exp.mailboxes_per_day', 10);
        $dayStart = Carbon::now('Europe/Moscow')->startOfDay()->utc();
        $made = (int) DB::table('alias_mailboxes')->where('created_at', '>=', $dayStart)->count();

        return max(0, $cap - $made);
    }

    /**
     * Позиции доли на сегодня.
     *
     * @param array<int,array<string,mixed>> $positions itemId => мета плана (is_customer, urgency, …)
     * @param array<int,int[]> $poolMap остаточный пул
     * @param array<int,int> $emailedCount itemId => скольким поставщикам уже писали
     * @return int[]
     */
    public static function candidates(array $positions, array $poolMap, array $emailedCount): array
    {
        $claimed = self::claimedItemIds();
        $out = [];
        foreach ($positions as $id => $m) {
            if (!empty($m['is_customer']) || isset($claimed[$id]) || ($emailedCount[$id] ?? 0) > 0
                || ($poolMap[$id] ?? []) === [] || !self::inShare((int) $id)) {
                continue;
            }
            $out[] = (int) $id;
        }
        usort($out, fn ($a, $b) => ($positions[$b]['urgency'] ?? 0) <=> ($positions[$a]['urgency'] ?? 0));

        return $out;
    }

    /**
     * Пары: позиции одного типа и области; однотипные (HomogeneousKey) — первыми. Пулы таких позиций совпадают
     * (подбор поставщиков идёт по типу/области), получатели батча — объединение пулов.
     *
     * @param int[] $itemIds в порядке срочности
     * @param array<int,array<string,mixed>> $itemRows
     * @return array<int,int[]>
     */
    public static function group(array $itemIds, array $itemRows, int $perBatch): array
    {
        $perBatch = max(1, min(2, $perBatch));
        $left = $itemIds;
        $groups = [];
        while ($left !== []) {
            $a = array_shift($left);
            $g = [$a];
            if ($perBatch > 1) {
                $ra = $itemRows[$a] ?? [];
                $typeKey = (int) ($ra['product_type_id'] ?? 0) . '|' . (int) ($ra['domain_id'] ?? 0);
                $homKey = HomogeneousKey::of((int) ($ra['product_type_id'] ?? 0) ?: null, $ra['brand'] ?? null, $ra['name'] ?? null);
                $pick = null;
                foreach ($left as $i => $b) {
                    $rb = $itemRows[$b] ?? [];
                    if ((int) ($ra['product_type_id'] ?? 0) <= 0
                        || (int) ($rb['product_type_id'] ?? 0) . '|' . (int) ($rb['domain_id'] ?? 0) !== $typeKey) {
                        continue;
                    }
                    $same = $homKey !== null && HomogeneousKey::of((int) ($rb['product_type_id'] ?? 0) ?: null, $rb['brand'] ?? null, $rb['name'] ?? null) === $homKey;
                    if ($same) {
                        $pick = $i;
                        break;
                    }
                    $pick ??= $i;
                }
                if ($pick !== null) {
                    $g[] = $left[$pick];
                    unset($left[$pick]);
                    $left = array_values($left);
                }
            }
            $groups[] = $g;
        }

        return $groups;
    }

    /**
     * Ящик + письма на каждый батч.
     *
     * @param array<int,int[]> $groups
     * @param array<int,array<string,mixed>> $itemRows
     * @param array<int,int[]> $poolMap
     * @param array<int,int[]> $relevantMap
     * @param Closure(array<int,array<string,mixed>>): Batch $makeBatch
     * @return array{batches:int, queued:int, mailboxes:array<int,string>, errors:int, item_ids:int[]}
     */
    public function render(array $groups, array $itemRows, array $poolMap, array $relevantMap, Closure $makeBatch, OpenAIClassifierClient $client, bool $hold = false): array
    {
        $cfg = config('services.email_generate');
        $assigner = new CampaignSenderAssigner();
        $tokenGenerator = new CampaignTokenGenerator($client, (string) ($cfg['token_model'] ?? 'gpt-4o-mini'), (bool) ($cfg['token_use_ai'] ?? true));
        $bodyGenerator = new CampaignBodyGenerator($client, (string) ($cfg['body_model'] ?? 'gpt-4o'), (float) ($cfg['body_temperature'] ?? 0.7), (int) ($cfg['max_tokens'] ?? 1500));
        $emailBuilder = new CampaignEmailBuilder();
        $persister = new CampaignPersister();

        $res = ['batches' => 0, 'queued' => 0, 'mailboxes' => [], 'errors' => 0, 'item_ids' => []];
        foreach ($groups as $itemIds) {
            $items = array_values(array_filter(array_map(fn ($id) => $itemRows[$id] ?? null, $itemIds)));
            $supIds = $this->recipients($itemIds, $poolMap, $relevantMap);
            if ($items === [] || $supIds === []) {
                continue;
            }
            $mailbox = null;
            try {
                $mailbox = $this->factory->create(AliasMailboxFactory::expiresAt($this->deadline($items)));
                $batch = $makeBatch($items);
                $batch->purpose = 'alias';
                $batch->sender = $assigner->fullSender($mailbox['sender_id']);
                $suppliers = $this->suppliers($supIds);
                $batch->suppliers = $suppliers;
                $batch->supplierIds = array_map(static fn ($s) => (int) $s['id'], $suppliers);

                $tokenGenerator->generate($batch); // внутренний реф батча (в письмо не попадает)
                $bodyGenerator->generate($batch);
                $slots = AliasWindow::slots(count($suppliers));
                $emails = [];
                foreach ($suppliers as $i => $sup) {
                    $e = $emailBuilder->build($batch, $sup);
                    $e['wave'] = 1;
                    $e['scheduled_at'] = $hold ? '2037-01-01 00:00:00' : $slots[$i]->toDateTimeString();
                    $emails[] = $e;
                }
                $p = $persister->persist($batch, $emails);

                DB::table('alias_mailboxes')->where('id', $mailbox['mailbox_id'])->update([
                    'batch_ids' => json_encode([(int) $p['batch_id']]),
                    'request_item_ids' => json_encode(array_values(array_map('intval', $itemIds))),
                    'updated_at' => now(),
                ]);
                $res['batches']++;
                $res['queued'] += count($p['queue_ids']);
                $res['mailboxes'][(int) $p['batch_id']] = $mailbox['email'];
                $res['item_ids'] = array_merge($res['item_ids'], $itemIds);
            } catch (\Throwable $e) {
                $res['errors']++;
                Log::error('AliasBranch: батч упал', ['item_ids' => $itemIds, 'mailbox' => $mailbox['email'] ?? null, 'error' => $e->getMessage()]);
                // Ящик без писем не нужен: позиция вернётся в план завтра.
                if ($mailbox) {
                    try {
                        $this->factory->drop(DB::table('alias_mailboxes')->find($mailbox['mailbox_id']));
                    } catch (\Throwable) {
                    }
                }
            }
        }

        return $res;
    }

    /** Получатели батча: объединение пулов, релевантные (Яндекс) — первыми, остальные — вперемешку. @return int[] */
    private function recipients(array $itemIds, array $poolMap, array $relevantMap): array
    {
        $rel = [];
        $all = [];
        foreach ($itemIds as $id) {
            foreach ($relevantMap[$id] ?? [] as $s) {
                $rel[(int) $s] = true;
            }
            foreach ($poolMap[$id] ?? [] as $s) {
                $all[(int) $s] = true;
            }
        }
        $rest = array_keys(array_diff_key($all, $rel));
        shuffle($rest);

        return array_merge(array_keys(array_intersect_key($rel, $all)), $rest);
    }

    /** @return array<int,array<string,mixed>> в порядке $ids */
    private function suppliers(array $ids): array
    {
        $rows = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::connection(self::CONN)->table('suppliers')->whereIn('id', $chunk)
                ->get(['id', 'name', 'email', 'contact_person', 'categories']) as $r) {
                if (trim((string) $r->email) !== '') {
                    $rows[(int) $r->id] = (array) $r;
                }
            }
        }

        return array_values(array_filter(array_map(fn ($id) => $rows[$id] ?? null, $ids)));
    }

    /** Ближайший срок сбора КП среди заявок позиций. */
    private function deadline(array $items): ?Carbon
    {
        $reqIds = array_values(array_unique(array_map(fn ($it) => (int) ($it['request_id'] ?? 0), $items)));
        $d = DB::connection(self::CONN)->table('requests')->whereIn('id', $reqIds)->whereNotNull('collection_deadline')->min('collection_deadline');

        return $d ? Carbon::parse($d) : null;
    }
}
