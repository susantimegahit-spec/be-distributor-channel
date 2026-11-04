<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Warehouse\Services\WarehouseService;
use Throwable;

class SyncSapWarehouses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sap:sync-warehouses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Master Warehouses from SAP B1 (/api/ListWhs)';

    /**
     * Execute the console command.
     *
     * @param  WarehouseService  $warehouseService
     * @return int
     */
    public function handle(WarehouseService $warehouseService): int
    {
        $this->info('Starting Master Warehouses synchronization from SAP B1...');

        try {
            $synced = $warehouseService->syncFromSap();
            $count = count($synced);

            $this->info("Successfully synchronized {$count} warehouses from SAP B1.");
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to sync warehouses: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
