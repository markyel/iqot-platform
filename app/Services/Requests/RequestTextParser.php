<?php

namespace App\Services\Requests;

use App\Jobs\ParseRequestTextJob;
use App\Models\ApplicationDomain;
use App\Models\Category;
use App\Models\ParseTask;
use App\Models\ProductType;
use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Разбор текста заявки в позиции (админка и кабинет). Заменяет n8n-воркфлоу «parse-request» (n8n выключен 10.2026).
 * Асинхронно, как и раньше: parseRequestAsync() заводит parse_tasks и ставит ParseRequestTextJob, UI опрашивает getTaskStatus().
 *
 * Два шага: (1) ИИ выделяет позиции (название, бренд, артикул, кол-во, ед., категория из справочника);
 * (2) тип товара и область применения — пачкой по CLASSIFY_CHUNK позиций за вызов, из кандидатов,
 * найденных по словам позиции в product_types (id вне списка отбрасываются).
 */
class RequestTextParser
{
    private const CLASSIFY_CHUNK = 20;
    private const TYPES_PER_ITEM = 25;
    private const MAX_ITEMS = 300;

    public function __construct(private readonly OpenAIClassifierClient $ai)
    {
    }

    public function parseRequestAsync(string $text, ?int $userId = null): array
    {
        if (!$this->ai->isConfigured()) {
            return ['success' => false, 'message' => 'ИИ не настроен (OPENAI_CLASSIFIER_*). Обратитесь к администратору.'];
        }
        $taskId = 'parse_' . Str::uuid();
        ParseTask::create(['task_id' => $taskId, 'user_id' => $userId, 'text' => $text, 'status' => ParseTask::STATUS_PENDING]);
        ParseRequestTextJob::dispatch($taskId);

        return ['success' => true, 'task_id' => $taskId, 'message' => 'Задача парсинга запущена'];
    }

    public function getTaskStatus(string $taskId): array
    {
        $task = ParseTask::where('task_id', $taskId)->first();

        return $task ? $task->toApiResponse() : ['success' => false, 'error' => 'Task not found', 'message' => 'Задача не найдена'];
    }

    /** @return array{items: array<int,array<string,mixed>>, items_count: int, has_new_classifications: bool} */
    public function parse(string $text): array
    {
        $items = $this->extract($text);
        foreach (array_chunk(array_keys($items), self::CLASSIFY_CHUNK) as $keys) {
            $this->classifyChunk($items, $keys);
        }

        return ['items' => array_values($items), 'items_count' => count($items), 'has_new_classifications' => false];
    }

    /** @return array<int,array<string,mixed>> */
    private function extract(string $text): array
    {
        $categories = array_values(Category::getActiveForSelect());
        $system = "Ты разбираешь текст закупочной заявки (B2B: запчасти, оборудование, материалы) на позиции.\n"
            . "Верни строго JSON: {\"items\":[{\"name\":string,\"brand\":string|null,\"article\":string|null,\"quantity\":number,\"unit\":string,\"category\":string}]}.\n"
            . "Правила:\n"
            . "- Одна позиция — один товар. Шапки, итоги, реквизиты, сроки и прочий текст — не позиции.\n"
            . "- name — полное название как в тексте, вместе с характеристиками и артикулом; без номера строки и без количества.\n"
            . "- article — артикул/каталожный номер/модель, если он явно есть; иначе null. brand — производитель, если указан; иначе null.\n"
            . "- quantity — число из текста (по умолчанию 1); unit — единица (шт, м, кг, компл, упак…; по умолчанию «шт»).\n"
            . "- category — ОДНО значение из списка: " . implode(' | ', $categories) . ". Если не подходит ничего — «Другое».\n"
            . "- Ничего не выдумывай: чего нет в тексте — null.";
        $res = $this->ai->jsonCompletion($this->ai->modelFull(), $system, mb_substr($text, 0, 20000), 8000);
        $out = [];
        foreach (array_slice((array) ($res['items'] ?? []), 0, self::MAX_ITEMS) as $it) {
            $name = trim((string) ($it['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $cat = (string) ($it['category'] ?? '');
            $out[] = [
                'index' => count($out) + 1,
                'name' => mb_substr($name, 0, 500),
                'brand' => self::nullable($it['brand'] ?? null, 100),
                'article' => self::nullable($it['article'] ?? null, 100),
                'quantity' => max(1, (int) ceil((float) str_replace(',', '.', (string) ($it['quantity'] ?? 1)))),
                'unit' => self::nullable($it['unit'] ?? null, 50) ?? 'шт',
                'category' => in_array($cat, $categories, true) ? $cat : (in_array('Другое', $categories, true) ? 'Другое' : ($categories[0] ?? 'Другое')),
                'product_type_id' => null,
                'domain_id' => null,
                'type_confidence' => null,
                'domain_confidence' => null,
                'needs_review' => true,
            ];
        }

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param int[] $keys
     */
    private function classifyChunk(array &$items, array $keys): void
    {
        $types = collect();
        foreach ($keys as $k) {
            $types = $types->merge($this->candidateTypes($items[$k]));
        }
        $types = $types->unique('id')->values();
        if ($types->isEmpty()) {
            $types = ProductType::query()->where('is_active', 1)->where('status', 'active')->where('is_leaf', 1)
                ->orderBy('name')->limit(250)->get(['id', 'name']);
        }
        $domains = ApplicationDomain::query()->where('is_active', 1)->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        $system = "Ты классификатор B2B-товаров. Для каждой позиции выбери product_type_id (тип товара) и domain_id (область применения: лифты, эскалаторы, HVAC, станки…) ТОЛЬКО из списков.\n"
            . "Бренды и артикулы подсказывают отрасль (OTIS/Thyssen/Sodimas → лифты). domain_id = null только для универсальных товаров (крепёж, смазки, общий инструмент).\n"
            . "Если подходящего типа нет — product_type_id = null.\n"
            . "Ответ строго JSON: {\"items\":[{\"i\":int,\"product_type_id\":int|null,\"domain_id\":int|null,\"confidence\":number}]}.";
        $user = "Позиции:\n";
        foreach ($keys as $k) {
            $it = $items[$k];
            $user .= "  i={$k}: {$it['name']}" . ($it['brand'] ? " | бренд {$it['brand']}" : '') . ($it['article'] ? " | арт. {$it['article']}" : '') . "\n";
        }
        $user .= "\nТипы товаров:\n" . $types->map(fn ($t) => "  id={$t->id} {$t->name}")->implode("\n")
            . "\n\nОбласти применения:\n" . $domains->map(fn ($d) => "  id={$d->id} {$d->name}")->implode("\n");

        try {
            $res = $this->ai->jsonCompletion($this->ai->modelFull(), $system, $user, 2000);
        } catch (\Throwable $e) {
            Log::warning('RequestTextParser: classification failed', ['error' => $e->getMessage()]);

            return;
        }
        $typeIds = $types->pluck('id')->map(fn ($v) => (int) $v)->all();
        $domainIds = $domains->pluck('id')->map(fn ($v) => (int) $v)->all();
        foreach ((array) ($res['items'] ?? []) as $r) {
            $k = (int) ($r['i'] ?? -1);
            if (!in_array($k, $keys, true)) {
                continue;
            }
            $pt = isset($r['product_type_id']) ? (int) $r['product_type_id'] : null;
            $dm = isset($r['domain_id']) ? (int) $r['domain_id'] : null;
            $conf = round(max(0, min(1, (float) ($r['confidence'] ?? 0))), 2);
            $pt = $pt !== null && in_array($pt, $typeIds, true) ? $pt : null;
            $dm = $dm !== null && in_array($dm, $domainIds, true) ? $dm : null;
            $items[$k]['product_type_id'] = $pt;
            $items[$k]['domain_id'] = $dm;
            $items[$k]['type_confidence'] = $pt ? $conf : null;
            $items[$k]['domain_confidence'] = $dm ? $conf : null;
            $items[$k]['needs_review'] = $pt === null || $conf < 0.9;
        }
    }

    /** Кандидаты типов по словам позиции (как prefilter классификатора API). */
    private function candidateTypes(array $item): Collection
    {
        $raw = trim($item['name'] . ' ' . ($item['brand'] ?? '') . ' ' . ($item['article'] ?? ''));
        $terms = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $raw) ?: [] as $t) {
            if (mb_strlen($t) >= 3 && !preg_match('/^\d+$/', $t)) {
                $terms[mb_strtolower($t)] = true;
            }
        }
        if ($terms === []) {
            return collect();
        }

        return ProductType::query()->where('is_active', 1)->where('status', 'active')->where('is_leaf', 1)
            ->where(function ($q) use ($terms) {
                foreach (array_keys($terms) as $t) {
                    $q->orWhere('name', 'LIKE', '%' . $t . '%')
                        ->orWhereRaw("JSON_SEARCH(LOWER(CAST(keywords AS CHAR)), 'one', ?) IS NOT NULL", ['%' . $t . '%']);
                }
            })->limit(self::TYPES_PER_ITEM)->get(['id', 'name']);
    }

    private static function nullable(mixed $v, int $max): ?string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' || in_array(mb_strtolower($v), ['null', 'нет', '-', '—'], true) ? null : mb_substr($v, 0, $max);
    }
}
