<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProductionDump extends Command
{
    protected $signature = 'db:import {file}';
    protected $description = 'Import SQL dump file';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info('Reading SQL file...');
        $sql = file_get_contents($file);

        $this->info('Importing data...');

        // Разбиваем на отдельные запросы
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--') && !str_starts_with($stmt, '/*!')
        );

        $bar = $this->output->createProgressBar(count($statements));
        $bar->start();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                try {
                    DB::unprepared($statement . ';');
                } catch (\Exception $e) {
                    // Игнорируем ошибки с комментариями MySQL
                    if (!str_contains($e->getMessage(), 'syntax error')) {
                        $this->warn("Warning: " . $e->getMessage());
                    }
                }
            }
            $bar->advance();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $bar->finish();
        $this->newLine();
        $this->info('✓ Import completed successfully!');

        // Проверяем результат
        $usersCount = DB::table('users')->count();
        $this->info("Users imported: {$usersCount}");

        return 0;
    }
}
