<?php

namespace App\Jobs;

use App\Models\ParseTask;
use App\Services\Requests\RequestTextParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/** Разбор текста заявки в позиции (RequestTextParser) с записью результата в parse_tasks — его опрашивает UI. */
class ParseRequestTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 190; // воркер generate: --timeout=200 (очередь простаивает — генерация v1 выключена, рассылку строит plan-day)

    public function __construct(public string $taskId)
    {
        $this->onQueue('generate');
    }

    public function handle(RequestTextParser $parser): void
    {
        $task = ParseTask::where('task_id', $this->taskId)->first();
        if (!$task || $task->isCompleted()) {
            return;
        }
        $task->markAsProcessing();
        try {
            $result = $parser->parse((string) $task->text);
            if ($result['items_count'] === 0) {
                $task->markAsFailed('Позиции в тексте не найдены');

                return;
            }
            $task->markAsCompleted($result);
        } catch (\Throwable $e) {
            Log::error('ParseRequestTextJob failed', ['task_id' => $this->taskId, 'error' => $e->getMessage()]);
            $task->markAsFailed('Не удалось распознать позиции: ' . $e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        ParseTask::where('task_id', $this->taskId)->whereNotIn('status', [ParseTask::STATUS_COMPLETED, ParseTask::STATUS_FAILED])
            ->update(['status' => ParseTask::STATUS_FAILED, 'error_message' => 'Не удалось распознать позиции (превышено время)', 'completed_at' => now()]);
    }
}
