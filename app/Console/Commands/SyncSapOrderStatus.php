<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\SalesOrder\Services\SalesOrderService;

class SyncSapOrderStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sap:sync-order-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Sales Order, Delivery, and Invoice status from SAP B1';

    /**
     * Execute the console command.
     *
     * @param  SalesOrderService  $salesOrderService
     * @return int
     */
    public function handle(SalesOrderService $salesOrderService)
    {
        $this->info('Starting SAP status synchronization...');
        
        $result = $salesOrderService->syncAllPendingOrders();

        if ($result['success']) {
            $this->info($result['message'] . " Total updated: {$result['updated_count']}");
            return Command::SUCCESS;
        } else {
            $this->error($result['message']);
            return Command::FAILURE;
        }
    }
}
