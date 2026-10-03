<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Distributor\Services\DistributorService;
use Throwable;

class SyncSapOcrCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sap:sync-ocr-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Master OCR Codes (Cabang, Bisnis Unit, Departemen) from SAP B1 (/api/ListOcrCode)';

    /**
     * Execute the console command.
     *
     * @param  DistributorService  $distributorService
     * @return int
     */
    public function handle(DistributorService $distributorService): int
    {
        $this->info('Starting Master OCR Codes (Cabang, Unit, Departemen) synchronization from SAP B1...');

        try {
            $synced = $distributorService->syncOcrCodesFromSap();
            $count = count($synced);

            $this->info("Successfully synchronized {$count} OCR Codes from SAP B1.");
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to sync OCR Codes: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
