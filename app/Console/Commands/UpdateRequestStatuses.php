<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Жизненный цикл заявок (26.11.2026, замена n8n «Status Manager»; с июля статусы никто не двигал). Правила владельца:
 *  - emails_sent — ушло первое письмо по заявке;
 *  - responses_received — пришла первая цена;
 *  - completed — наступил срок сбора (collection_deadline), ИЛИ у всех позиций набрано цен ≥ цели заявки
 *    (offer_target, без неё 3), ИЛИ 14 дней после последнего письма и последнего ответа ничего не происходило.
 * Технические заявки мониторинга (source=monitoring) не трогаем — они бессрочные.
 */
class UpdateRequestStatuses extends Command
{
    protected $signature = 'requests:update-statuses {--dry-run}';

    protected $description = 'Статусы заявок: emails_sent → responses_received → completed';

    private const IDLE_DAYS = 14;

    public function handle(): int
    {
        $db = DB::connection('reports');
        $rows = $db->select("
            SELECT r.id, r.status, r.collection_deadline, COALESCE(NULLIF(r.offer_target, 0), 3) target,
                   (SELECT COUNT(*) FROM request_items ri WHERE ri.request_id = r.id) items,
                   (SELECT MAX(q.sent_at) FROM request_items ri JOIN request_item_responses x ON x.request_item_id = ri.id
                        JOIN email_queue q ON q.id = x.email_queue_id WHERE ri.request_id = r.id) last_sent,
                   (SELECT MAX(x.response_received_at) FROM request_items ri JOIN request_item_responses x ON x.request_item_id = ri.id
                        WHERE ri.request_id = r.id AND x.status = 'received' AND COALESCE(x.price_per_unit, x.total_price, 0) > 0) last_price,
                   (SELECT COUNT(*) FROM request_items ri WHERE ri.request_id = r.id
                        AND (SELECT COUNT(DISTINCT x.supplier_id) FROM request_item_responses x WHERE x.request_item_id = ri.id
                             AND x.status = 'received' AND COALESCE(x.price_per_unit, x.total_price, 0) > 0)
                            >= COALESCE(NULLIF(r.offer_target, 0), 3)) items_done
            FROM requests r
            WHERE r.status IN ('draft', 'new', 'active', 'collecting', 'queued_for_sending', 'emails_sent', 'responses_received')
              AND r.source <> 'monitoring'");

        $now = now();
        $changes = ['emails_sent' => 0, 'responses_received' => 0, 'completed' => 0];
        foreach ($rows as $r) {
            $lastActivity = max((string) $r->last_sent, (string) $r->last_price);
            $reason = null;
            if ($r->collection_deadline !== null && $now->greaterThan($r->collection_deadline)) {
                $reason = 'deadline';
            } elseif ((int) $r->items > 0 && (int) $r->items_done === (int) $r->items) {
                $reason = 'target';
            } elseif ($r->last_sent !== null && $now->copy()->subDays(self::IDLE_DAYS)->greaterThan($lastActivity)) {
                $reason = 'idle';
            }
            $new = match (true) {
                $reason !== null => 'completed',
                $r->last_price !== null => 'responses_received',
                $r->last_sent !== null => 'emails_sent',
                default => null,
            };
            // Только вперёд по циклу: emails_sent ← (new/active/…), responses_received ← (… + emails_sent).
            $order = ['draft' => 0, 'new' => 0, 'active' => 0, 'collecting' => 0, 'queued_for_sending' => 0, 'emails_sent' => 1, 'responses_received' => 2, 'completed' => 3];
            if ($new === null || $order[$new] <= $order[$r->status]) {
                continue;
            }
            $changes[$new]++;
            $this->line("#{$r->id}: {$r->status} → {$new}" . ($reason ? " ({$reason})" : ''));
            if (!$this->option('dry-run')) {
                $db->table('requests')->where('id', $r->id)->where('status', $r->status)
                    ->update(['status' => $new, 'updated_at' => $now] + ($new === 'completed' ? ['completed_at' => $now] : []));
            }
        }
        $this->info(sprintf('emails_sent %d, responses_received %d, completed %d%s', $changes['emails_sent'], $changes['responses_received'],
            $changes['completed'], $this->option('dry-run') ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
