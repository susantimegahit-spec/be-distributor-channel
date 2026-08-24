<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\SalesOrder\Services\SalesOrderService;
use Throwable;

class SyncSapSalesOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sap:sync-sales-orders {--card_code=* : Specific distributor card codes to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Sales Orders and Discounts from SAP B1 (GetDataSO & getDiscountSO)';

    /**
     * Execute the console command.
     *
     * @param  SalesOrderService  $salesOrderService
     * @return int
     */
    public function handle(SalesOrderService $salesOrderService): int
    {
        $this->info('Starting Sales Orders synchronization from SAP...');

        try {
            $rawCardCodes = (array) $this->option('card_code');
            $cardCodes = [];
            foreach ($rawCardCodes as $raw) {
                $parts = explode(',', (string) $raw);
                foreach ($parts as $part) {
                    $trimmed = trim($part);
                    if ($trimmed !== '') {
                        $cardCodes[] = $trimmed;
                    }
                }
            }
            $cardCodes = array_values(array_unique($cardCodes));

            $result = $salesOrderService->syncSalesOrdersFromSap($cardCodes);

            $this->info($result['message']);
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to sync sales orders from SAP: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
