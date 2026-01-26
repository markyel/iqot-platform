<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportProductionData extends Command
{
    protected $signature = 'db:export-production {--connection=mysql_production}';
    protected $description = 'Export data from production database';

    public function handle()
    {
        $connection = $this->option('connection');

        $this->info('Connecting to production database...');

        try {
            // Тестируем подключение
            DB::connection($connection)->getPdo();

            $database = DB::connection($connection)->getDatabaseName();
            $config = config("database.connections.{$connection}");

            $filename = storage_path('app/production_backup_' . date('Y-m-d_His') . '.sql');

            $command = sprintf(
                'mysqldump -h %s -u %s -p%s %s > %s',
                $config['host'],
                $config['username'],
                $config['password'],
                $database,
                $filename
            );

            $this->info('Exporting database...');
            exec($command, $output, $return);

            if ($return === 0) {
                $this->info("✓ Database exported to: {$filename}");
                $this->info("File size: " . number_format(filesize($filename) / 1024 / 1024, 2) . " MB");
            } else {
                $this->error('Export failed!');
            }

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
