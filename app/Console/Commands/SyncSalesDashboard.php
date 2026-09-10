<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\SalesDashboard\Services\SalesDashboardService;
use Illuminate\Support\Facades\DB;

class SyncSalesDashboard extends Command
{
    /**
     * 
     * tes aja
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales-dashboard:sync {year? : Tahun data yang akan disinkronkan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data CMO, SO, dan DO dari SAP ke tabel sales_dashboard_data untuk semua customer';

    /**
     * Execute the console command.
     *
     * @param  SalesDashboardService  $service
     * @return int
     */
    public function handle(SalesDashboardService $service)
    {
        $year = $this->argument('year') ? (int)$this->argument('year') : (int)date('Y');

        $this->info("Memulai sinkronisasi data sales dashboard untuk tahun {$year}...");

        // Ambil semua customer_code unik dari tabel sales_dashboard_data untuk tahun tersebut
        $customerCodes = DB::table('sales_dashboard_data')
            ->where('year', $year)
            ->distinct()
            ->pluck('customer_code')
            ->toArray();

        if (empty($customerCodes)) {
            $this->warn("Tidak ditemukan data customer target untuk tahun {$year} di tabel sales_dashboard_data.");
            return Command::SUCCESS;
        }

        $this->info("Ditemukan " . count($customerCodes) . " customer untuk disinkronkan.");

        foreach ($customerCodes as $customerCode) {
            // Dapatkan seluruh brand unik dari target customer tersebut
            $brands = DB::table('sales_dashboard_data')
                ->where('customer_code', $customerCode)
                ->where('year', $year)
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->distinct()
                ->pluck('brand')
                ->toArray();

            if (empty($brands)) {
                $this->warn("Customer {$customerCode} tidak memiliki item dengan brand terdaftar. Melewati...");
                continue;
            }

            $this->info("Menyinkronkan customer {$customerCode} dengan brand: " . implode(', ', $brands));

            try {
                // Jalankan sync tanpa override agar masuk ke kode customer aslinya
                $result = $service->syncDashboardData($year, $customerCode, $brands);

                $this->line("  -> Hasil: CMO {$result['cmo_synced']} record, SO {$result['so_synced']} record, DO {$result['do_synced']} record berhasil diperbarui.");
            } catch (\Exception $e) {
                $this->error("  -> Gagal menyinkronkan customer {$customerCode}: " . $e->getMessage());
            }
        }

        $this->info("Sinkronisasi data sales dashboard selesai!");
        return Command::SUCCESS;
    }
}
