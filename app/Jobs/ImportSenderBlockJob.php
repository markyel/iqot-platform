<?php

namespace App\Jobs;

use App\Services\Senders\BulkSenderImporter;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Импорт одного сгенерированного отправителя в фоне.
 *
 * Раньше генератор импортировал всю пачку синхронно в веб-запросе: на каждый
 * адрес идёт вызов AI (~несколько секунд), и на десятке адресов прокси рвал
 * соединение по таймауту (504). Теперь каждый блок — отдельная задача в батче,
 * результат складывается в кэш, а страница статуса опрашивает прогресс.
 */
class ImportSenderBlockJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;
    public int $backoff = 30;

    /**
     * @param array<string,mixed> $block Поля одного отправителя (email/password/...).
     */
    public function __construct(
        private readonly string $runId,
        private readonly int $index,
        private readonly array $block,
    ) {
    }

    public function handle(BulkSenderImporter $importer): void
    {
        $result = $importer->importBlocks([$this->block]);
        $row = $result['rows'][0] ?? [
            'status' => 'failed',
            'email' => $this->block['email'] ?? '',
            'message' => 'Пустой результат импорта',
        ];

        Cache::put($this->rowKey(), $row, now()->addDay());
    }

    /**
     * Записать строку-ошибку, если задача упала окончательно (после всех попыток).
     */
    public function failed(\Throwable $e): void
    {
        Cache::put($this->rowKey(), [
            'status' => 'failed',
            'email' => $this->block['email'] ?? '',
            'message' => 'Ошибка фоновой задачи: ' . $e->getMessage(),
        ], now()->addDay());
    }

    private function rowKey(): string
    {
        return "senders_gen:{$this->runId}:row:{$this->index}";
    }
}
