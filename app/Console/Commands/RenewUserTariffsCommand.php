<?php

namespace App\Console\Commands;

use App\Services\TariffService;
use Illuminate\Console\Command;

class RenewUserTariffsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tariffs:renew';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew expired user tariffs and charge monthly fees';

    protected $tariffService;

    public function __construct(TariffService $tariffService)
    {
        parent::__construct();
        $this->tariffService = $tariffService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting tariff renewal process...');

        try {
            $renewedCount = $this->tariffService->renewExpiredTariffs();

            $this->info("Successfully renewed {$renewedCount} tariffs.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error during tariff renewal: ' . $e->getMessage());
            \Log::error('Tariff renewal failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
