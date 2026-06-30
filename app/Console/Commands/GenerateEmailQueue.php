<?php

namespace App\Console\Commands;

use App\Jobs\GenerateCampaignJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер генерации рассылок — замена крон-триггера n8n «Create Email Queue v4
 * (AI)» (каждые 5 мин).
 *
 * Порт «Get Requests»: заявки в статусе draft/new/active (именные в приоритете),
 * атомарный claim в queued_for_sending (гарантия идемпотентности — второй тик/n8n их
 * уже не подхватят) и dispatch одного GenerateCampaignJob на весь перехваченный
 * набор (кросс-заявочная группировка позиций в батчи). Очередь `generate`.
 *
 * Флаг EMAILS_GENERATE_ENABLED по умолчанию OFF: включать ТОЛЬКО после отключения
 * n8n-воркфлоу (INSERT'ы email_batches/email_queue не идемпотентны — параллельная
 * работа двух систем шлёт дубли писем). --force обходит флаг для ручного прогона.
 *
 * --dry-run НЕ флипает статус заявки (остаётся повторно-прогоняемым для инспекции),
 * но всё равно пишет email_queue(pending)/email_batches/request_item_responses,
 * чтобы можно было проверить вёрстку до боевой отправки.
 */
class GenerateEmailQueue extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:generate-queue
        {--force : Запустить даже при выключенном флаге EMAILS_GENERATE_ENABLED}
        {--limit= : Переопределить лимит заявок за тик}
        {--request= : Точечный прогон одной заявки по requests.id}
        {--dry-run : Сгенерировать без флипа статуса заявки (для инспекции)}';

    protected $description = 'Сгенерировать рассылку поставщикам по заявкам и поставить письма в email_queue';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!$this->option('force') && !config('services.email_generate.enabled')) {
            $this->warn('emails:generate-queue выключен (EMAILS_GENERATE_ENABLED=false). Используйте --force для ручного запуска.');
            return self::SUCCESS;
        }

        // Точечный прогон одной заявки.
        if ($requestId = $this->option('request')) {
            $requestId = (int) $requestId;
            $ids = $dryRun ? [$requestId] : $this->claim([$requestId]);
            if ($ids === []) {
                $this->warn("Заявка {$requestId} не перехвачена (не в статусе draft/new/active).");
                return self::SUCCESS;
            }
            GenerateCampaignJob::dispatch($ids, $dryRun);
            $this->info("Dispatched generate job for request {$requestId}" . ($dryRun ? ' (dry-run).' : '.'));
            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('services.email_generate.request_limit', 20));

        // Фильтр свежести: не загребать ДРЕВНИЕ заявки, случайно осевшие в active/new/draft
        // (защита от бага «слива backlog'а» — старые заявки попадали в массовую генерацию).
        // 0 = выключено. Точечный --request этим НЕ ограничивается.
        $maxAgeDays = (int) config('services.email_generate.max_request_age_days', 30);

        // Порт «Get Requests»: именные в приоритете, старые раньше.
        $eligible = DB::connection(self::CONN)->table('requests')
            ->whereIn('status', ['draft', 'new', 'active'])
            ->when($maxAgeDays > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($maxAgeDays)))
            ->orderBy('is_customer_request', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($eligible === []) {
            $this->info('Нет заявок в статусе draft/new/active.');
            return self::SUCCESS;
        }

        // Claim (кроме dry-run): только реально перехваченные id уходят в джоб.
        $ids = $dryRun ? $eligible : $this->claim($eligible);

        if ($ids === []) {
            $this->info('Все подходящие заявки уже перехвачены другим процессом.');
            return self::SUCCESS;
        }

        GenerateCampaignJob::dispatch($ids, $dryRun);

        $this->info('Dispatched generate job for ' . count($ids) . ' request(s)' . ($dryRun ? ' (dry-run).' : '.'));
        return self::SUCCESS;
    }

    /**
     * Атомарный claim: UPDATE status='queued_for_sending' по одной заявке за раз —
     * возвращает только те id, что реально перехвачены (status был draft/new/active).
     *
     * @param array<int,int> $requestIds
     * @return array<int,int>
     */
    private function claim(array $requestIds): array
    {
        $claimed = [];
        foreach ($requestIds as $id) {
            $affected = DB::connection(self::CONN)->table('requests')
                ->where('id', $id)
                ->whereIn('status', ['draft', 'new', 'active'])
                ->update([
                    'status' => 'queued_for_sending',
                    'updated_at' => now(),
                ]);
            if ($affected > 0) {
                $claimed[] = $id;
            }
        }
        return $claimed;
    }
}
