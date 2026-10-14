<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MonitoringProgram;
use App\Services\Monitoring\NomenclatureFileReader;
use App\Services\Monitoring\ProgramItemImporter;
use Illuminate\Console\Command;

/**
 * Загрузить номенклатуру в программу из CSV/XLSX (колонки: client_ref, name, brand,
 * article, unit — по заголовкам). Тот же конвейер, что у API-импорта.
 */
class ImportProgramFile extends Command
{
    protected $signature = 'monitoring:import-file {program : id программы} {path : путь к CSV/XLSX} {--sync : обработать сразу, без очереди} {--dry-run : только прочитать и показать}';

    protected $description = 'Мониторинг цен: импорт номенклатуры программы из файла';

    public function handle(NomenclatureFileReader $reader, ProgramItemImporter $importer): int
    {
        $program = MonitoringProgram::find((int) $this->argument('program'));
        if (!$program) {
            $this->error('Program not found.');

            return self::FAILURE;
        }
        $path = (string) $this->argument('path');
        if (!is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = $reader->read($path);
        $this->info('Rows read: ' . count($rows));
        if ($this->option('dry-run')) {
            foreach (array_slice($rows, 0, 5) as $r) {
                $this->line(json_encode($r, JSON_UNESCAPED_UNICODE));
            }

            return self::SUCCESS;
        }

        $import = $importer->queueRows($program, $rows, 'file', null, !$this->option('sync'));
        if ($this->option('sync')) {
            $importer->process($import->refresh());
            $import->refresh();
            $this->info("Import imp_{$import->external_id}: {$import->status}, ok {$import->rows_ok}, failed {$import->rows_failed}.");
            foreach (array_slice($import->errors ?? [], 0, 10) as $e) {
                $this->warn(json_encode($e, JSON_UNESCAPED_UNICODE));
            }
            $program->refresh();
            $this->info("Program: total {$program->items_total}, matched {$program->items_matched}, ambiguous {$program->items_ambiguous}.");
        } else {
            $this->info("Queued import imp_{$import->external_id} (queue monitor-import).");
        }

        return self::SUCCESS;
    }
}
