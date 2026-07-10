<?php

namespace App\Services\Generate;

use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Support\Facades\Log;

/**
 * Фаза 2 (v2): аффинность позиций — что с чем можно объединить в одном письме.
 *
 * Матрица СОВМЕСТИМОСТИ (симметричная): itemId => [совместимые itemId]. Назначатель
 * бандлит поставщику только совместимые позиции. Используется вместе с релевантностью
 * (Яндекс) и пулом — не вместо них.
 *
 * Логика: позиции РАЗНЫХ доменов не смешиваем (по умолчанию несовместимы). Внутри
 * одного домена — совместимы, КРОМЕ пар, которые AI пометил «не мешать» (слишком
 * разные). AI-прогон — по группе домена (1 вызов на группу); фолбэк без AI/при ошибке —
 * все внутри домена совместимы.
 */
class PositionAffinity
{
    /** Больше этого размера группы — AI не зовём (всё внутри домена совместимо). */
    private const MAX_AI_GROUP = 25;

    public function __construct(
        private readonly ?OpenAIClassifierClient $ai = null,
        private readonly string $model = 'gpt-4o-mini',
    ) {
    }

    /**
     * @param array<int,array{id:int,request_id:int,name:string,brand:string,article:string,type:string,domain_id:int}> $positions
     * @return array<int,array<int,int>> itemId => [совместимые itemId,...]
     */
    public function compute(array $positions): array
    {
        // Группируем по домену (разные домены не смешиваем).
        $byDomain = [];
        foreach ($positions as $p) {
            $byDomain[(int) ($p['domain_id'] ?? 0)][] = $p;
        }

        $compat = [];
        foreach ($byDomain as $group) {
            if (count($group) < 2) {
                continue; // одиночка — не с чем бандлить
            }
            $avoid = $this->avoidPairs($group); // множество «не мешать» (индекс-пары)

            $ids = array_map(static fn ($p) => (int) $p['id'], $group);
            $n = count($ids);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if (isset($avoid["$i-$j"])) {
                        continue;
                    }
                    $a = $ids[$i];
                    $b = $ids[$j];
                    $compat[$a][] = $b;
                    $compat[$b][] = $a;
                }
            }
        }

        foreach ($compat as $k => $v) {
            $compat[$k] = array_values(array_unique($v));
        }
        return $compat;
    }

    /**
     * Пары позиций (по индексу в группе), которые НЕ стоит объединять. AI-прогон по
     * группе домена; фолбэк — пусто (всё совместимо).
     *
     * @param array<int,array<string,mixed>> $group
     * @return array<string,bool> "i-j" => true
     */
    private function avoidPairs(array $group): array
    {
        if ($this->ai === null || count($group) > self::MAX_AI_GROUP) {
            return [];
        }

        $lines = [];
        foreach ($group as $i => $p) {
            $lines[] = "{$i}: " . trim(implode(' ', array_filter([
                (string) ($p['type'] ?? ''),
                (string) ($p['brand'] ?? ''),
                (string) ($p['name'] ?? ''),
                ($p['article'] ?? '') !== '' ? 'арт.' . $p['article'] : '',
            ])));
        }
        $sys = 'Ты помогаешь группировать позиции (комплектующие для лифтов/эскалаторов) в запросы поставщикам. '
            . 'Вернуть строго JSON.';
        $user = "Позиции (индекс: описание):\n" . implode("\n", $lines)
            . "\n\nКакие ПАРЫ позиций НЕ стоит объединять в одном запросе одному поставщику "
            . "(слишком разные по типу/назначению)? Верни JSON: {\"avoid\": [[i,j], ...]} — индексы. "
            . "Если все нормально сочетаются — {\"avoid\": []}.";

        try {
            $res = $this->ai->jsonCompletion($this->model, $sys, $user, 500, 0.0);
            $out = [];
            foreach ((array) ($res['avoid'] ?? []) as $pair) {
                if (is_array($pair) && count($pair) === 2) {
                    $i = (int) $pair[0];
                    $j = (int) $pair[1];
                    if ($i !== $j) {
                        $out[min($i, $j) . '-' . max($i, $j)] = true;
                    }
                }
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('PositionAffinity: AI failed, fallback all-compatible', ['error' => mb_substr($e->getMessage(), 0, 200)]);
            return [];
        }
    }
}
