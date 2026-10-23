<?php

namespace App\Console\Commands;

use App\Services\Generate\CampaignEmailBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Количество в строке позиции у ожидающих писем, собранных до 22.10.2026 (4e35a52): шаблоны с item_template «{name}»
 * (30, 32 и др.) давали «1. Магнитный датчик … 6557018010» без «— 2 шт.». Письмо хранится готовым HTML, поэтому
 * список перерисовываем: старый вариант (без количества) ищем в HTML дословно и меняем на текущий. Не нашли — не трогаем.
 */
class RequantityPendingEmails extends Command
{
    protected $signature = 'emails:requantity {--dry-run : Только посчитать} {--limit=2000}';

    protected $description = 'Добавить количество в список позиций ожидающих писем, собранных до исправления шаблонов';

    private const CONN = 'reports';

    public function handle(CampaignEmailBuilder $builder): int
    {
        $dry = (bool) $this->option('dry-run');
        $rows = DB::connection(self::CONN)->table('email_queue as q')->join('email_batches as b', 'b.id', '=', 'q.batch_id')
            ->where('q.status', 'pending')->limit((int) $this->option('limit'))
            ->get(['q.id', 'q.batch_id', 'q.body_html', 'b.request_items', 'b.sender_id']);

        $fixed = $notFound = $same = 0;
        foreach ($rows->groupBy('batch_id') as $batchId => $letters) {
            $first = $letters->first();
            $ids = array_map('intval', (array) json_decode((string) $first->request_items, true));
            $template = $this->template((int) $first->sender_id);
            if ($ids === [] || $template === null) {
                continue;
            }
            $byId = DB::connection(self::CONN)->table('request_items')->whereIn('id', $ids)
                ->get(['id', 'name', 'brand', 'article', 'quantity', 'unit'])->keyBy('id');
            $items = array_values(array_filter(array_map(fn ($id) => isset($byId[$id]) ? (array) $byId[$id] : null, $ids)));

            CampaignEmailBuilder::$legacyItemTemplate = true;
            $old = $builder->itemsHtml($items, $template);
            CampaignEmailBuilder::$legacyItemTemplate = false;
            $new = $builder->itemsHtml($items, $template);
            if ($old === $new) {
                $same += $letters->count();
                continue;
            }
            foreach ($letters as $l) {
                if (!str_contains((string) $l->body_html, $old)) {
                    $notFound++;
                    continue;
                }
                $fixed++;
                if (!$dry) {
                    DB::connection(self::CONN)->table('email_queue')->where('id', $l->id)->where('status', 'pending')
                        ->update(['body_html' => str_replace($old, $new, (string) $l->body_html), 'updated_at' => now()]);
                }
            }
        }
        $this->info(($dry ? '[dry-run] ' : '') . "Количество добавлено: {$fixed}; список уже с количеством: {$same}; старый список не найден: {$notFound}");

        return self::SUCCESS;
    }

    /** @return array<string,mixed>|null шаблон ящика письма */
    private function template(int $senderId): ?array
    {
        static $cache = [];
        if (!array_key_exists($senderId, $cache)) {
            $tid = DB::connection(self::CONN)->table('senders')->where('id', $senderId)->value('preferred_template_id');
            $t = $tid ? DB::connection(self::CONN)->table('email_templates')->find($tid) : null;
            $cache[$senderId] = $t ? (array) $t : null;
        }

        return $cache[$senderId];
    }
}
