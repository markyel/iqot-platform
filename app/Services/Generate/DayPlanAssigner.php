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
 * Этап 1 — раскладка РАУНДАМИ (равномерное покрытие, а не «первая срочная позиция
 * съела всё»):
 *   - в каждом раунде каждая активная позиция (в порядке срочности) получает ПО ОДНОМУ
 *     новому поставщику из своего пула (релевантные вперёд);
 *   - внутри поставщика: сначала подсадка в уже ОТКРЫТЫЙ конверт с совместимыми
 *     позициями и свободным местом (письмо плотнее, ёмкость не расходуется);
 *     иначе — НОВЫЙ конверт, пока лимит получателя и суммарная ёмкость ящиков позволяют;
 *   - несовместимые позиции могут уйти одному поставщику в один день разными письмами;
 *   - позиция НЕ ограничивается искусственно: выходит из раундов только при исчерпании
 *     СВОЕГО пула. Не хватило ёмкости на поставщика → он просто уйдёт завтра.
 *
 * Этап 2 — назначение ящиков ПОСЛЕ раскладки, ГРУППАМИ одинаковых наборов позиций:
 * конверты одного набора получают ОДИН «липкий» ящик (пока его лимит позволяет) →
 * рендер собирает их в один батч (1 ящик, 1 тело AI, N поставщиков) — на порядок
 * меньше AI-вызовов, чем ящик-на-конверт round-robin. Ограничения: дневной лимит
 * ящика; НЕ ящик, писавший этому получателю за окно ротации (senderRecent); разные
 * ящики конвертам одного получателя (иначе персистер молча срежет). Внутри группы
 * получатели уникальны by design (позиция не идёт дважды одному поставщику).
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
     * @param int|null $droppedNoSender              out: конверты, не получившие ящик (дропнуты)
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
        ?int &$droppedNoSender = null,
    ): array {
        $maxPerEmail = max(1, $maxPerEmail);
        $droppedNoSender = 0;

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

        // Суммарная ёмкость ящиков = бюджет конвертов дня (конкретный ящик — на этапе 2).
        $capacityLeft = 0;
        foreach ($senderCaps as $c) {
            $capacityLeft += max(0, (int) $c);
        }

        // Конверты: idx => {supplier_id, email, item_ids, relevant, urgency}.
        $envelopes = [];
        $bySupplier = [];   // supplierId => [envelope idx,...]

        // ── Этап 1: раунды. Позиция за раунд получает одного поставщика. ──────────
        $active = array_keys($positions);
        usort($active, fn ($a, $b) => ($positions[$b]['urgency'] ?? 0) <=> ($positions[$a]['urgency'] ?? 0));

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

                    // 2) Новый конверт: лимит получателя + суммарная ёмкость ящиков.
                    if (($recipientCaps[$email] ?? 0) <= 0) {
                        continue; // получатель на сегодня полон — этой позиции он достанется завтра
                    }
                    if ($capacityLeft <= 0) {
                        continue; // ёмкость дня исчерпана — подсадки ещё возможны, новые конверты нет
                    }
                    $capacityLeft--;
                    $recipientCaps[$email]--;
                    $envelopes[] = [
                        'supplier_id' => $sid,
                        'email' => $email,
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

        // ── Этап 2: ящики группами одинаковых наборов («липкий» ящик на группу). ──
        $groups = [];
        foreach ($envelopes as $i => $env) {
            $ids = $env['item_ids'];
            sort($ids);
            $groups[implode(',', $ids)][] = $i;
        }

        $senderIds = array_keys(array_filter($senderCaps, static fn ($c) => $c > 0));
        $n = count($senderIds);
        $sPtr = 0;
        $sendersOfSupplier = []; // supplierId => set(senderId) — разные ящики конвертам получателя
        $senderOf = [];          // envelope idx => senderId

        $okFor = function (int $s, array $env) use (&$senderCaps, $senderRecent, &$sendersOfSupplier): bool {
            if (($senderCaps[$s] ?? 0) <= 0) {
                return false;
            }
            if (isset($senderRecent[$s . '|' . $env['email']])) {
                return false;
            }
            return !isset($sendersOfSupplier[$env['supplier_id']][$s]);
        };

        foreach ($groups as $idxs) {
            $current = null; // липкий ящик группы
            foreach ($idxs as $i) {
                $env = $envelopes[$i];
                if ($current !== null && ($senderCaps[$current] ?? 0) <= 0) {
                    $current = null; // лимит липкого исчерпан — группе нужен следующий
                }
                $use = ($current !== null && $okFor($current, $env)) ? $current : null;
                if ($use === null) {
                    for ($k = 0; $k < $n; $k++) {
                        $s = (int) $senderIds[($sPtr + $k) % $n];
                        if ($okFor($s, $env)) {
                            $use = $s;
                            $sPtr = ($sPtr + $k + 1) % max(1, $n);
                            break;
                        }
                    }
                }
                if ($use === null) {
                    $droppedNoSender++; // ни одного допустимого ящика (ротация/лимиты) — дроп
                    continue;
                }
                if ($current === null) {
                    $current = $use; // новый липкий (конверто-специфичный обход current не меняет)
                }
                $senderOf[$i] = $use;
                $senderCaps[$use]--;
                $sendersOfSupplier[$env['supplier_id']][$use] = true;
            }
        }

        // ── Порядок дня: релевантные вперёд, затем по срочности. ──────────────────
        $keep = [];
        foreach ($envelopes as $i => $env) {
            if (isset($senderOf[$i])) {
                $env['sender_id'] = $senderOf[$i];
                $keep[] = $env;
            }
        }
        usort($keep, static function ($a, $b) {
            if ($a['relevant'] !== $b['relevant']) {
                return $b['relevant'] <=> $a['relevant'];
            }
            return $b['urgency'] <=> $a['urgency'];
        });

        $plan = [];
        foreach ($keep as $i => $env) {
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
