<?php

namespace App\Jobs\Monitoring;

use App\Models\Monitoring\MpImport;
use App\Services\Monitoring\ProgramItemImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Обработка импорта номенклатуры программы мониторинга (очередь monitor-import).
 */
class ImportProgramItemsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(private readonly int $importId)
    {
        $this->onQueue('monitor-import');
    }

    public function handle(ProgramItemImporter $importer): void
    {
        $import = MpImport::find($this->importId);
        if (!$import || $import->status !== 'queued') {
            return;
        }
        $importer->process($import);
    }
}
