<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\DistributorApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateDistributorApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'distributor:generate-api-key 
                            {code_customer : Kode Customer / Distributor} 
                            {name : Nama sistem atau label integrasi (misal: ERP Surabaya)} 
                            {--allowed-ips= : IP Whitelist dipisahkan dengan koma (opsional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API Key baru untuk integrasi B2B Distributor';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $codeCustomer = $this->argument('code_customer');
        $name = $this->argument('name');
        $allowedIpsInput = $this->option('allowed-ips');

        $distributor = Distributor::where('code_customer', $codeCustomer)->first();

        if (!$distributor) {
            $this->error("Distributor dengan code_customer '{$codeCustomer}' tidak ditemukan di database.");
            return 1;
        }

        // Generate a cryptographically secure random API key
        $rawKey = 'susanti_sec_' . Str::random(40);
        $hashedKey = DistributorApiKey::hashKey($rawKey);
        $keyPrefix = substr($rawKey, 0, 15);

        $allowedIps = null;
        if ($allowedIpsInput) {
            $allowedIps = array_filter(array_map('trim', explode(',', $allowedIpsInput)));
        }

        $apiKeyRecord = DistributorApiKey::create([
            'distributor_id' => $distributor->id,
            'name' => $name,
            'key_prefix' => $keyPrefix,
            'api_key_hash' => $hashedKey,
            'allowed_ips' => $allowedIps,
            'is_active' => true,
        ]);

        $this->info("=================================================");
        $this->info("  BERHASIL GENERATE DISTRIBUTOR B2B API KEY");
        $this->info("=================================================");
        $this->table(
            ['Property', 'Value'],
            [
                ['Distributor ID', $distributor->id],
                ['Distributor Name', $distributor->name],
                ['Code Customer', $distributor->code_customer],
                ['Key Name / Label', $apiKeyRecord->name],
                ['Allowed IPs', $allowedIps ? implode(', ', $allowedIps) : 'ANY (Tidak dibatasi)'],
                ['API Key ID', $apiKeyRecord->id],
            ]
        );

        $this->newLine();
        $this->warn("Simpan API Key di bawah ini dengan aman. Key ini HANYA DITAMPILKAN SEKALIKAN INI:");
        $this->line("<fg=black;bg=green;options=bold> {$rawKey} </fg=black;bg=green;options=bold>");
        $this->newLine();

        return 0;
    }
}
