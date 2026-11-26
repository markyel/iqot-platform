<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Карма поставщиков по ответам (26.11.2026, перенос MySQL-процедур collect_response_signals → process_offer_signals /
 * process_rejection_signal / update_supplier_signal, которые раз в 2 ч звал n8n «Status Manager»; процедуры жили
 * только в базе Beget, с 19.10 карма не считалась). Правила — как были (решение владельца):
 *  - цена в ответе — плюс поставщику по типу товара и области позиции (supplier_product_types / supplier_domains;
 *    связи нет — заводится source=response_positive);
 *  - отказ «не наш профиль» (rejection_reason=not_our_profile) — минус по типам и областям позиций письма (связи нет —
 *    заводится исключённой, source=response_negative: дискавери её не включит);
 *  - есть плюс → связь включена; ≥5 минусов без единого плюса и без ручной отметки → исключена.
 * Заодно счётчики поставщика total_responses_received / total_prices_offered (по ним ранжируется пул).
 * Читает email_messages.ai_classification (формат SupplierReplyAnalyzer), флаг signals_processed: 0 → 1.
 */
class CollectSupplierSignals extends Command
{
    protected $signature = 'suppliers:collect-signals {--limit=1000} {--dry-run}';

    protected $description = 'Карма поставщиков по ответам: плюс за цену, минус за «не наш профиль»';

    private const CONN = 'reports';

    private const NEG_TO_EXCLUDE = 5;

    public function handle(): int
    {
        $db = DB::connection(self::CONN);
        $dry = (bool) $this->option('dry-run');
        $msgs = $db->table('email_messages as m')
            ->join('email_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->leftJoin('email_batches as b', 'b.id', '=', 'c.batch_id')
            ->where('m.direction', 'incoming')->where('m.signals_processed', 0)->where('m.ai_processed', 1)
            ->orderBy('m.id')->limit((int) $this->option('limit'))
            ->get(['m.id', 'm.ai_classification', 'c.supplier_id', 'b.request_items']);

        $stats = ['messages' => 0, 'plus' => 0, 'minus' => 0, 'included' => 0, 'excluded' => 0];
        foreach ($msgs as $m) {
            $stats['messages']++;
            $cls = json_decode((string) $m->ai_classification, true);
            $supplierId = (int) $m->supplier_id;
            if (!is_array($cls) || $supplierId <= 0) {
                $dry || $db->table('email_messages')->where('id', $m->id)->update(['signals_processed' => 1]);
                continue;
            }

            $priced = array_values(array_filter((array) ($cls['offers'] ?? []), fn ($o) => is_array($o) && (int) ($o['item_id'] ?? 0) > 0
                && ((float) ($o['price_per_unit'] ?? 0) > 0 || (float) ($o['total_price'] ?? 0) > 0)));
            $plusItems = array_values(array_unique(array_map(fn ($o) => (int) $o['item_id'], $priced)));
            $minusItems = ($cls['email_type'] ?? null) === 'rejection' && ($cls['rejection_reason'] ?? null) === 'not_our_profile'
                ? array_values(array_filter(array_map('intval', (array) json_decode((string) $m->request_items, true)))) : [];

            $dry || $db->transaction(function () use ($db, $m, $cls, $supplierId, $priced, $plusItems, $minusItems, &$stats) {
                foreach (['plus' => $plusItems, 'minus' => $minusItems] as $sign => $itemIds) {
                    if ($itemIds === []) {
                        continue;
                    }
                    $items = $db->table('request_items')->whereIn('id', $itemIds)->get(['product_type_id', 'domain_id']);
                    $types = $items->pluck('product_type_id')->filter()->unique()->all();
                    $domains = $items->pluck('domain_id')->filter()->unique()->all();
                    foreach ([['supplier_product_types', 'product_type_id', $types], ['supplier_domains', 'domain_id', $domains]] as [$table, $col, $ids]) {
                        foreach ($ids as $id) {
                            $this->signal($table, $col, $supplierId, (int) $id, $sign === 'plus', $stats);
                        }
                    }
                }
                $type = (string) ($cls['email_type'] ?? '');
                $db->table('suppliers')->where('id', $supplierId)->update([
                    'total_responses_received' => DB::raw('total_responses_received + ' . (in_array($type, ['', 'auto_reply', 'empty_reply'], true) ? 0 : 1)),
                    'total_prices_offered' => DB::raw('total_prices_offered + ' . count($priced)),
                ]);
                $db->table('email_messages')->where('id', $m->id)->update(['signals_processed' => 1]);
            });
            if ($dry) {
                $stats['plus'] += count($plusItems);
                $stats['minus'] += count($minusItems);
            }
        }

        $this->info(sprintf('Писем %d; плюсов %d, минусов %d; включено связей %d, исключено %d%s', $stats['messages'], $stats['plus'],
            $stats['minus'], $stats['included'], $stats['excluded'], $dry ? ' (dry-run: позиции, без записи)' : ''));

        return self::SUCCESS;
    }

    /** @param array<string,int> $stats */
    private function signal(string $table, string $col, int $supplierId, int $id, bool $plus, array &$stats): void
    {
        $db = DB::connection(self::CONN);
        $row = $db->table($table)->where('supplier_id', $supplierId)->where($col, $id)->first();
        $extra = $table === 'supplier_product_types' ? ['last_signal_at' => now()] : [];
        if ($row === null) {
            $db->table($table)->insert([
                'supplier_id' => $supplierId, $col => $id,
                'is_included' => $plus ? 1 : 0, 'source' => $plus ? 'response_positive' : 'response_negative', 'confidence' => 1.00,
                'positive_signals' => $plus ? 1 : 0, 'negative_signals' => $plus ? 0 : 1, 'is_manual' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ] + $extra);
            $stats[$plus ? 'plus' : 'minus']++;
            $plus && $stats['included']++;

            return;
        }
        $pos = (int) $row->positive_signals + ($plus ? 1 : 0);
        $neg = (int) $row->negative_signals + ($plus ? 0 : 1);
        $update = ['positive_signals' => $pos, 'negative_signals' => $neg, 'updated_at' => now()] + $extra;
        if ($pos > 0 && !(int) $row->is_included) {
            $update['is_included'] = 1;
            $stats['included']++;
        } elseif ($neg >= self::NEG_TO_EXCLUDE && $pos === 0 && !(int) $row->is_manual && (int) $row->is_included) {
            $update['is_included'] = 0;
            $update['source'] = 'response_negative';
            $stats['excluded']++;
        }
        $db->table($table)->where('supplier_id', $supplierId)->where($col, $id)->update($update);
        $stats[$plus ? 'plus' : 'minus']++;
    }
}
