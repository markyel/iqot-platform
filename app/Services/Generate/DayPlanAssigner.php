<?php

namespace App\Services\Generate;

/**
 * Фаза 2 (v2): назначатель дневного плана рассылки. ЧИСТАЯ логика (без БД/сети) —
 * тестируется синтетикой.
 *
 * На входе — по каждой активной позиции: пул поставщиков (остаток) + релевантные
 * (Яндекс) + матрица аффинности (что с чем можно в одном письме) + ёмкости
 * (ящики, дневные потолки получателей). На выходе — упорядоченный план: список писем
 * {поставщик → набор позиций → ящик → очерёдность}.
 *
 * Единица письма = ПОСТАВЩИК + его совместимые позиции (1 письмо/поставщик/день).
 * Фаза 1 — релевантные (горячие) вперёд; фаза 2 — добор из общего пула до target.
 * Позиция выпадает при достижении target (кроме max_reach). Рендер потом группирует
 * письма с ОДИНАКОВЫМ набором позиций → 1 тело AI на группу.
 */
class DayPlanAssigner
{
    /**
     * @param array<int,array{request_id:int,urgency:float,target:int,max_reach:bool}> $positions  itemId => мета
     * @param array<int,array<int,int>> $pool        itemId => [supplierId,...] (профильный остаток)
     * @param array<int,array<int,int>> $relevant    itemId => [supplierId,...] (Яндекс-релевантные ⊆ пула)
     * @param array<int,array<int,int>> $compatible  itemId => [compatible itemId,...] (аффинность, симметрично)
     * @param array<int,string> $supplierEmail       supplierId => email
     * @param array<int,int> $senderCaps             senderId => остаток писем (декрементим)
     * @param array<string,int> $recipientCaps       email => остаток дневного cap (декрементим)
     * @param int $maxPerEmail                       максимум позиций в одном письме
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
        int $maxPerEmail = 4,
    ): array {
        $plan = [];
        $order = 0;
        $covered = [];          // itemId => сколько поставщиков уже в плане
        $supplierUsed = [];     // supplierId => true (1 письмо/поставщик/день)

        // Порядок позиций по срочности (убыв.).
        $byUrgency = array_keys($positions);
        usort($byUrgency, fn ($a, $b) => ($positions[$b]['urgency'] ?? 0) <=> ($positions[$a]['urgency'] ?? 0));

        // Round-robin ящиков с остатком ёмкости.
        $senderIds = array_keys(array_filter($senderCaps, static fn ($c) => $c > 0));
        $sPtr = 0;
        $pickSender = function () use (&$senderCaps, &$senderIds, &$sPtr): ?int {
            $n = count($senderIds);
            for ($i = 0; $i < $n; $i++) {
                $sid = $senderIds[($sPtr + $i) % $n] ?? null;
                if ($sid !== null && ($senderCaps[$sid] ?? 0) > 0) {
                    $sPtr = ($sPtr + $i + 1) % max(1, $n);
                    return (int) $sid;
                }
            }
            return null;
        };

        $need = function (int $item) use (&$covered, $positions): bool {
            $m = $positions[$item] ?? null;
            if ($m === null) {
                return false;
            }
            return ($m['max_reach'] ?? false) || ($covered[$item] ?? 0) < (int) ($m['target'] ?? 4);
        };

        // Инверсия: supplierId => [позиции, где он присутствует] — для релевантных и пула.
        $invRelevant = $this->invert($relevant);
        $invPool = $this->invert($pool);

        // Обход двух фаз одним кодом: сначала релевантные, потом добор из пула.
        foreach (['relevant' => $invRelevant, 'fill' => $invPool] as $phase => $inv) {
            foreach ($byUrgency as $anchorItem) {
                if (!$need($anchorItem)) {
                    continue;
                }
                // Поставщики этой позиции в текущей фазе, по срочности их «якорной» позиции
                // (тут просто в порядке пула — рейтинг можно добавить позже).
                $suppliers = ($phase === 'relevant' ? ($relevant[$anchorItem] ?? []) : ($pool[$anchorItem] ?? []));
                foreach ($suppliers as $sid) {
                    $sid = (int) $sid;
                    if (isset($supplierUsed[$sid])) {
                        continue;
                    }
                    $email = mb_strtolower(trim((string) ($supplierEmail[$sid] ?? '')));
                    if ($email === '' || ($recipientCaps[$email] ?? 0) <= 0) {
                        continue;
                    }

                    // Все позиции, что этот поставщик может обслужить в этой фазе И которым нужен добор.
                    $serve = array_values(array_filter($inv[$sid] ?? [], fn ($it) => $need((int) $it)));
                    if ($serve === []) {
                        continue;
                    }
                    $bundle = $this->bundle($anchorItem, $serve, $compatible, $positions, $maxPerEmail);
                    if ($bundle === []) {
                        continue;
                    }

                    $sender = $pickSender();
                    if ($sender === null) {
                        return $plan; // ёмкость ящиков исчерпана — план готов
                    }

                    $plan[] = [
                        'supplier_id' => $sid,
                        'sender_id' => $sender,
                        'item_ids' => $bundle,
                        'order' => $order++,
                        'phase' => $phase,
                    ];
                    $supplierUsed[$sid] = true;
                    $recipientCaps[$email]--;
                    $senderCaps[$sender]--;
                    foreach ($bundle as $it) {
                        $covered[$it] = ($covered[$it] ?? 0) + 1;
                    }
                }
            }
        }

        return $plan;
    }

    /**
     * Бандл для поставщика: с «якоря» добираем совместимые (со ВСЕМИ уже взятыми) позиции
     * по срочности, до maxPerEmail.
     *
     * @param array<int,int> $serve позиции, что поставщик может обслужить (и им нужен добор)
     * @param array<int,array<int,int>> $compatible
     * @param array<int,array<string,mixed>> $positions
     * @return array<int,int>
     */
    private function bundle(int $anchor, array $serve, array $compatible, array $positions, int $maxPerEmail): array
    {
        $serve = array_values(array_unique(array_map('intval', $serve)));
        if (!in_array($anchor, $serve, true)) {
            return [];
        }
        // Остальные по срочности.
        usort($serve, fn ($a, $b) => ($positions[$b]['urgency'] ?? 0) <=> ($positions[$a]['urgency'] ?? 0));

        $picked = [$anchor];
        foreach ($serve as $it) {
            if ($it === $anchor || count($picked) >= max(1, $maxPerEmail)) {
                continue;
            }
            $ok = true;
            foreach ($picked as $p) {
                if (!$this->canBundle($p, $it, $compatible)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $picked[] = $it;
            }
        }
        return $picked;
    }

    /** Совместимы ли две позиции (аффинность симметрична; сама с собой — да). */
    private function canBundle(int $a, int $b, array $compatible): bool
    {
        if ($a === $b) {
            return true;
        }
        return in_array($b, $compatible[$a] ?? [], true) || in_array($a, $compatible[$b] ?? [], true);
    }

    /**
     * itemId=>[supplierId] → supplierId=>[itemId].
     *
     * @param array<int,array<int,int>> $map
     * @return array<int,array<int,int>>
     */
    private function invert(array $map): array
    {
        $out = [];
        foreach ($map as $item => $sids) {
            foreach ($sids as $sid) {
                $out[(int) $sid][] = (int) $item;
            }
        }
        return $out;
    }
}
