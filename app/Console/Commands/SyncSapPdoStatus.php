<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Production\Services\ProductionService;

class SyncSapPdoStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sap:sync-pdo-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync status for PLANNED Production Orders with SAP B1';

    /**
     * Execute the console command.
     *
     * @param  ProductionService  $productionService
     * @return int
     */
    public function handle(ProductionService $productionService)
    {
        $this->info('Memulai sinkronisasi status PDO dengan SAP B1...');

        $result = $productionService->syncPendingPdoStatus();

        if ($result['success']) {
            $this->info($result['message'] . " Total order diperbarui: {$result['updated_count']}");
            return Command::SUCCESS;
        } else {
            $this->error($result['message'] ?? 'Gagal melakukan sinkronisasi PDO.');
            return Command::FAILURE;
        }
    }
}
