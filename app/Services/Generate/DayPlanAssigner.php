<?php

namespace App\Services\Generate;

/**
 * Фаза 2 (v2): назначатель дневного плана рассылки. ЧИСТАЯ логика (без БД/сети) —
 * тестируется синтетикой.
 *
 * Задача РАСПРЕДЕЛЕНИЯ между двумя наборами: позиции (остаточный пул, релевантные
 * из Яндекса, совместимость, срочность) × поставщики (дневной лимит писем-«конвертов»
 * на получателя, по умолчанию 10). Раскладываем позиции по конвертам поставщиков
 * максимально эффективно, не заспамив.
 *
 * Алгоритм — РАУНДАМИ (равномерное покрытие, а не «первая срочная позиция съела всё»):
 *   - в каждом раунде каждая активная позиция (в порядке срочности) получает ПО ОДНОМУ
 *     новому поставщику из своего пула (релевантные вперёд);
 *   - внутри поставщика: сначала подсадка в уже ОТКРЫТЫЙ конверт с совместимыми
 *     позициями и свободным местом (письмо плотнее, ёмкость не расходуется);
 *     иначе — НОВЫЙ конверт, пока лимит получателя и ёмкость ящиков позволяют;
 *   - несовместимые позиции могут уйти одному поставщику в один день разными письмами;
 *   - позиция НЕ ограничивается искусственно: выходит из раундов только при исчерпании
 *     СВОЕГО пула. Не хватило ёмкости на поставщика → он просто уйдёт завтра;
 *   - конверт при открытии получает ящик-отправитель: round-robin по остаткам лимитов,
 *     ИСКЛЮЧАЯ ящики, писавшие этому получателю за окно ротации (senderRecent), и уже
 *     назначенные другим конвертам этого же получателя (иначе персистер молча срежет).
 *
 * Итог — упорядоченный план: конверты с релевантными позициями в начало дня.
 */
class DayPlanAssigner
{
    /**
     * @param array<int,array{request_id:int,urgency:float}> $positions itemId => мета
     * @param array<int,array<int,int>> $pool        itemId => [supplierId,...] (остаток: ещё не слали эту позицию)
     * @param array<int,array<int,int>> $relevant    itemId => [supplierId,...] (Яндекс-релевантные ⊆ пула)
     * @param array<int,array<int,int>> $compatible  itemId => [compatible itemId,...] (аффинность, симметрично)
     * @param array<int,string> $supplierEmail       supplierId => email
     * @param array<int,int> $senderCaps             senderId => остаток писем (декрементим)
     * @param array<string,int> $recipientCaps       email => остаток конвертов сегодня (декрементим)
     * @param array<string,bool> $senderRecent       "senderId|email" => true (ящик писал получателю за окно ротации)
     * @param int $maxPerEmail                       максимум позиций в одном конверте
     * @return array<int,array{supplier_id:int,sender_id:int,item_ids:array<int,int>,order:int,phase:string}>
     */
    public function plan(
        array $positions,
        array $pool,
        array $relevant,
        array $compatible,
        array $supplierEmail,
        array $senderCaps,
        array $recipientCaps,
        array $senderRecent = [],
        int $maxPerEmail = 4,
    ): array {
        $maxPerEmail = max(1, $maxPerEmail);

        // Кандидаты позиции: релевантные вперёд, затем остальной пул (порядок пула).
        $cand = [];      // itemId => [supplierId,...]
        $ptr = [];       // itemId => позиция курсора в cand (пройденное = «завтра»/назначено)
        $relFlip = [];   // itemId => set(supplierId) релевантных
        foreach ($positions as $id => $m) {
            $rel = array_values(array_unique(array_map('intval', $relevant[$id] ?? [])));
            $all = array_values(array_unique(array_map('intval', $pool[$id] ?? [])));
            $relSet = array_flip($rel);
            $rest = array_values(array_filter($all, static fn ($s) => !isset($relSet[$s])));
            $cand[$id] = array_merge(array_values(array_intersect($all, $rel)), $rest);
            $ptr[$id] = 0;
            $relFlip[$id] = $relSet;
        }

        // Конверты: idx => {supplier_id, sender_id, item_ids, relevant, urgency}.
        $envelopes = [];
        $bySupplier = [];          // supplierId => [envelope idx,...]
        $sendersOfSupplier = [];   // supplierId => set(senderId) — конвертам одного получателя разные ящики

        // Round-robin ящиков с остатком ёмкости.
        $senderIds = array_keys(array_filter($senderCaps, static fn ($c) => $c > 0));
        $sPtr = 0;
        $pickSender = function (int $supplierId, string $email) use (&$senderCaps, $senderIds, &$sPtr, &$sendersOfSupplier, $senderRecent): ?int {
            $n = count($senderIds);
            for ($i = 0; $i < $n; $i++) {
                $sid = (int) $senderIds[($sPtr + $i) % $n];
                if (($senderCaps[$sid] ?? 0) <= 0) {
                    continue;
                }
                if (isset($senderRecent[$sid . '|' . $email])) {
                    continue;
                }
                if (isset($sendersOfSupplier[$supplierId][$sid])) {
                    continue;
                }
                $sPtr = ($sPtr + $i + 1) % max(1, $n);
                return $sid;
            }
            return null;
        };

        // Порядок позиций в раунде — по срочности (убыв.).
        $active = array_keys($positions);
        usort($active, fn ($a, $b) => ($positions[$b]['urgency'] ?? 0) <=> ($positions[$a]['urgency'] ?? 0));

        // Раунды: позиция за раунд получает одного поставщика; выпадает при исчерпании пула.
        while ($active !== []) {
            $next = [];
            foreach ($active as $item) {
                $assigned = false;
                while ($ptr[$item] < count($cand[$item])) {
                    $sid = $cand[$item][$ptr[$item]];
                    $ptr[$item]++; // пройден навсегда: назначен ЛИБО «не хватило места — завтра»
                    $email = mb_strtolower(trim((string) ($supplierEmail[$sid] ?? '')));
                    if ($email === '') {
                        continue;
                    }
                    $isRel = isset($relFlip[$item][$sid]);
                    $urg = (float) ($positions[$item]['urgency'] ?? 0);

                    // 1) Подсадка в открытый конверт (бесплатно по ёмкости).
                    foreach ($bySupplier[$sid] ?? [] as $ei) {
                        if (count($envelopes[$ei]['item_ids']) >= $maxPerEmail) {
                            continue;
                        }
                        if (!$this->compatibleWithAll($item, $envelopes[$ei]['item_ids'], $compatible)) {
                            continue;
                        }
                        $envelopes[$ei]['item_ids'][] = $item;
                        $envelopes[$ei]['relevant'] = $envelopes[$ei]['relevant'] || $isRel;
                        $envelopes[$ei]['urgency'] = max($envelopes[$ei]['urgency'], $urg);
                        $assigned = true;
                        break;
                    }
                    if ($assigned) {
                        break;
                    }

                    // 2) Новый конверт: лимит получателя + ящик с ёмкостью и без ротационного конфликта.
                    if (($recipientCaps[$email] ?? 0) <= 0) {
                        continue; // получатель на сегодня полон — этой позиции он достанется завтра
                    }
                    $sender = $pickSender($sid, $email);
                    if ($sender === null) {
                        continue; // нет подходящего ящика — поставщик уйдёт завтра
                    }
                    $senderCaps[$sender]--;
                    $recipientCaps[$email]--;
                    $sendersOfSupplier[$sid][$sender] = true;
                    $envelopes[] = [
                        'supplier_id' => $sid,
                        'sender_id' => $sender,
                        'item_ids' => [$item],
                        'relevant' => $isRel,
                        'urgency' => $urg,
                    ];
                    $bySupplier[$sid][] = array_key_last($envelopes);
                    $assigned = true;
                    break;
                }
                if ($assigned) {
                    $next[] = $item; // позиция хочет ВЕСЬ пул — остаётся на следующий раунд
                }
            }
            $active = $next;
        }

        // Порядок дня: конверты с релевантными позициями вперёд, затем по срочности.
        usort($envelopes, static function ($a, $b) {
            if ($a['relevant'] !== $b['relevant']) {
                return $b['relevant'] <=> $a['relevant'];
            }
            return $b['urgency'] <=> $a['urgency'];
        });

        $plan = [];
        foreach ($envelopes as $i => $env) {
            $plan[] = [
                'supplier_id' => (int) $env['supplier_id'],
                'sender_id' => (int) $env['sender_id'],
                'item_ids' => array_values($env['item_ids']),
                'order' => $i,
                'phase' => $env['relevant'] ? 'relevant' : 'fill',
            ];
        }
        return $plan;
    }

    /** Совместима ли позиция со ВСЕМИ позициями конверта (аффинность симметрична). */
    private function compatibleWithAll(int $item, array $envelopeItems, array $compatible): bool
    {
        foreach ($envelopeItems as $other) {
            if ($item === $other) {
                return false; // дубль в конверте недопустим
            }
            if (!in_array($other, $compatible[$item] ?? [], true)
                && !in_array($item, $compatible[$other] ?? [], true)) {
                return false;
            }
        }
        return true;
    }
}
