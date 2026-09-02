<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Distributor\Services\DistributorService;
use App\Modules\Item\Services\ItemService;
use App\Modules\SalesEmployee\Services\SalesEmployeeService;
use App\Modules\Vat\Services\VatService;
use App\Modules\Warehouse\Services\WarehouseService;
use App\Modules\Discount\Services\DiscountService;
use App\Modules\Production\Services\ProductionService;
use Throwable;

class SyncSapAllMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sap:sync-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync ALL Master Data from SAP B1 (Distributors, OCR Codes, Items, Sales Employees, VAT, Warehouses, Discounts, Production Resources & Items)';

    /**
     * Execute the console command.
     */
    public function handle(
        DistributorService $distributorService,
        ItemService $itemService,
        SalesEmployeeService $salesEmployeeService,
        VatService $vatService,
        WarehouseService $warehouseService,
        DiscountService $discountService,
        ProductionService $productionService
    ): int {
        $this->info('====================================================');
        $this->info('Starting Full Master Data Synchronization from SAP B1');
        $this->info('====================================================');

        // 1. Distributors
        $this->syncTask('Distributors', fn() => $distributorService->syncFromSap());

        // 2. OCR Codes (Cabang, Unit, Departemen)
        $this->syncTask('OCR Codes (Cabang, Bisnis Unit, Departemen)', fn() => $distributorService->syncOcrCodesFromSap());

        // 3. Items
        $this->syncTask('Items / Products', fn() => $itemService->syncFromSap());

        // 4. Sales Employees
        $this->syncTask('Sales Employees', fn() => $salesEmployeeService->syncFromSap());

        // 5. VATs
        $this->syncTask('Master Pajak (VAT)', fn() => $vatService->syncFromSap());

        // 6. Warehouses
        $this->syncTask('Master Gudang (Warehouses)', fn() => $warehouseService->syncFromSap());

        // 7. Discount Types
        $this->syncTask('Tipe Diskon (Discount Types)', fn() => $discountService->syncDiscountTypesFromSap());

        // 8. Production Resources
        $this->syncTask('Production Resources', fn() => $productionService->syncResourcesFromSap());

        // 9. Production Items
        $this->syncTask('Production Items', fn() => $productionService->syncItemsFromSap());

        $this->info('====================================================');
        $this->info('Full SAP B1 Master Data Synchronization Completed.');
        $this->info('====================================================');

        return Command::SUCCESS;
    }

    protected function syncTask(string $name, callable $task): void
    {
        try {
            $this->output->write("Syncing {$name}... ");
            $data = $task();
            $count = is_countable($data) ? count($data) : '-';
            $this->info("DONE ({$count} records)");
        } catch (Throwable $e) {
            $this->error("FAILED: " . $e->getMessage());
        }
    }
}
