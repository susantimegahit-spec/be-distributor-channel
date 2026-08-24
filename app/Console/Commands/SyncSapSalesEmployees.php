<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\SalesEmployee\Services\SalesEmployeeService;
use Throwable;

class SyncSapSalesEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sap:sync-sales-employees';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Master Sales Employees from SAP B1';

    /**
     * Execute the console command.
     *
     * @param  SalesEmployeeService  $salesEmployeeService
     * @return int
     */
    public function handle(SalesEmployeeService $salesEmployeeService): int
    {
        $this->info('Starting Master Sales Employees synchronization from SAP...');

        try {
            $synced = $salesEmployeeService->syncFromSap();
            $count = count($synced);
            
            $this->info("Successfully synchronized {$count} sales employees from SAP.");
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to sync sales employees: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
