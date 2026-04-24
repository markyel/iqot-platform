<?php

namespace App\Console\Commands;

use App\Models\Api\ApiSubmission;
use App\Models\UserTariff;
use Illuminate\Console\Command;

/**
 * Backfill: добавляет к UserTariff.items_used сумму позиций из уже созданных
 * ApiSubmission, которые не были учтены (в старой версии SubmissionService
 * useItems() не вызывался для API-flow).
 *
 * Логика: для каждого активного тарифа считаем sum(items_total) по ApiSubmission,
 * созданным после tariff.activated_at, с status != 'cancelled'. Сравниваем
 * с текущим items_used, при разнице догоняем (не декрементируя).
 */
class SyncApiItemsUsedCommand extends Command
{
    protected $signature = 'iqot:api-sync-items-used
                            {--dry-run : показать план без изменений}
                            {--user= : ограничить одним user_id}';

    protected $description = 'Догоняет UserTariff.items_used по API-submissions, не учтённым до фикса';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyUser = $this->option('user') ? (int) $this->option('user') : null;

        $tariffs = UserTariff::query()
            ->where('is_active', true)
            ->when($onlyUser, fn ($q) => $q->where('user_id', $onlyUser))
            ->get();

        if ($tariffs->isEmpty()) {
            $this->warn('Активных тарифов не найдено.');
            return self::SUCCESS;
        }

        $this->info('Найдено активных тарифов: ' . $tariffs->count());

        foreach ($tariffs as $tariff) {
            $apiItems = (int) ApiSubmission::query()
                ->whereHas('client', fn ($q) => $q->where('user_id', $tariff->user_id))
                ->where('status', '!=', 'cancelled')
                ->when(
                    $tariff->activated_at,
                    fn ($q) => $q->where('created_at', '>=', $tariff->activated_at)
                )
                ->sum('items_total');

            // Оценка текущего недоучёта: если items_used меньше суммы api_items,
            // возможно web-заявки ещё не вносились — не будем уменьшать, только догонять.
            $currentUsed = (int) $tariff->items_used;
            $needed = $apiItems;                // минимум, который должен был быть учтён от API
            $delta = max(0, $needed - $currentUsed);

            if ($delta === 0) {
                $this->line(sprintf(
                    '  tariff #%d (user #%d): уже в норме (used=%d, api_items=%d)',
                    $tariff->id, $tariff->user_id, $currentUsed, $apiItems
                ));
                continue;
            }

            $this->line(sprintf(
                '  tariff #%d (user #%d): used=%d, api_items=%d → +%d',
                $tariff->id, $tariff->user_id, $currentUsed, $apiItems, $delta
            ));

            if (!$dryRun) {
                $tariff->increment('items_used', $delta);
            }
        }

        if ($dryRun) {
            $this->warn('Dry-run — изменения не сохранены.');
        } else {
            $this->info('Готово.');
        }
        return self::SUCCESS;
    }
}
