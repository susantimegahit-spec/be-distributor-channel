<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SeedIndonesianRegions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ekspedisi:seed-regions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Indonesian administrative regions (Provinces, Regencies, Districts, Villages) from emsifa CSV sources.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Indonesian regions seeding...');

        $connection = 'pgsql_ekspedisi';

        // 1. Seed Provinces
        $this->info('Downloading provinces data...');
        $provincesUrl = 'https://raw.githubusercontent.com/emsifa/api-wilayah-indonesia/master/data/provinces.csv';
        $provincesCsv = Http::withoutVerifying()->get($provincesUrl)->body();
        $provincesRows = explode("\n", $provincesCsv);
        
        $this->info('Inserting provinces into database...');
        DB::connection($connection)->table('provinces')->delete();
        $provincesChunk = [];
        $processedProvinces = [];
        foreach ($provincesRows as $rowStr) {
            if (empty(trim($rowStr))) continue;
            $row = str_getcsv($rowStr);
            if (count($row) < 2) continue;
            $id = (int)$row[0];
            if (isset($processedProvinces[$id])) continue;
            $processedProvinces[$id] = true;
            
            $provincesChunk[] = [
                'id' => $id,
                'name' => trim($row[1]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($provincesChunk) >= 500) {
                DB::connection($connection)->table('provinces')->insert($provincesChunk);
                $provincesChunk = [];
            }
        }
        if (!empty($provincesChunk)) {
            DB::connection($connection)->table('provinces')->insert($provincesChunk);
        }
        $this->info('Provinces seeded successfully.');

        // 2. Seed Regencies
        $this->info('Downloading regencies data...');
        $regenciesUrl = 'https://raw.githubusercontent.com/emsifa/api-wilayah-indonesia/master/data/regencies.csv';
        $regenciesCsv = Http::withoutVerifying()->get($regenciesUrl)->body();
        $regenciesRows = explode("\n", $regenciesCsv);

        $this->info('Inserting regencies into database...');
        DB::connection($connection)->table('regencies')->delete();
        $regenciesChunk = [];
        $processedRegencies = [];
        foreach ($regenciesRows as $rowStr) {
            if (empty(trim($rowStr))) continue;
            $row = str_getcsv($rowStr);
            if (count($row) < 3) continue;
            $id = (int)$row[0];
            if (isset($processedRegencies[$id])) continue;
            $processedRegencies[$id] = true;

            $regenciesChunk[] = [
                'id' => $id,
                'province_id' => (int)$row[1],
                'name' => trim($row[2]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($regenciesChunk) >= 500) {
                DB::connection($connection)->table('regencies')->insert($regenciesChunk);
                $regenciesChunk = [];
            }
        }
        if (!empty($regenciesChunk)) {
            DB::connection($connection)->table('regencies')->insert($regenciesChunk);
        }
        $this->info('Regencies seeded successfully.');

        // 3. Seed Districts
        $this->info('Downloading districts data...');
        $districtsUrl = 'https://raw.githubusercontent.com/emsifa/api-wilayah-indonesia/master/data/districts.csv';
        $districtsCsv = Http::withoutVerifying()->get($districtsUrl)->body();
        $districtsRows = explode("\n", $districtsCsv);

        $this->info('Inserting districts into database (this may take a few moments)...');
        DB::connection($connection)->table('districts')->delete();
        $districtsChunk = [];
        $processedDistricts = [];
        foreach ($districtsRows as $rowStr) {
            if (empty(trim($rowStr))) continue;
            $row = str_getcsv($rowStr);
            if (count($row) < 3) continue;
            $id = (int)$row[0];
            if (isset($processedDistricts[$id])) continue;
            $processedDistricts[$id] = true;

            $districtsChunk[] = [
                'id' => $id,
                'regency_id' => (int)$row[1],
                'name' => trim($row[2]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($districtsChunk) >= 500) {
                DB::connection($connection)->table('districts')->insert($districtsChunk);
                $districtsChunk = [];
            }
        }
        if (!empty($districtsChunk)) {
            DB::connection($connection)->table('districts')->insert($districtsChunk);
        }
        $this->info('Districts seeded successfully.');

        // 4. Seed Villages
        $this->info('Downloading villages data...');
        $villagesUrl = 'https://raw.githubusercontent.com/emsifa/api-wilayah-indonesia/master/data/villages.csv';
        $villagesCsv = Http::withoutVerifying()->get($villagesUrl)->body();
        $villagesRows = explode("\n", $villagesCsv);

        $this->info('Inserting villages into database (this will take a while, please wait)...');
        DB::connection($connection)->table('villages')->delete();
        $villagesChunk = [];
        $processedVillages = [];
        $totalInserted = 0;
        foreach ($villagesRows as $rowStr) {
            if (empty(trim($rowStr))) continue;
            $row = str_getcsv($rowStr);
            if (count($row) < 3) continue;
            $id = (int)$row[0];
            if (isset($processedVillages[$id])) continue;
            $processedVillages[$id] = true;

            $villagesChunk[] = [
                'id' => $id,
                'district_id' => (int)$row[1],
                'name' => trim($row[2]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($villagesChunk) >= 1000) {
                DB::connection($connection)->table('villages')->insert($villagesChunk);
                $totalInserted += count($villagesChunk);
                $this->output->write('.');
                $villagesChunk = [];
            }
        }
        if (!empty($villagesChunk)) {
            DB::connection($connection)->table('villages')->insert($villagesChunk);
            $totalInserted += count($villagesChunk);
        }
        $this->newLine();
        $this->info("Villages seeded successfully. Total inserted villages: {$totalInserted}");

        $this->info('Indonesian regions seeding completed successfully!');
    }
}
