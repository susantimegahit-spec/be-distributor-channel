<?php

namespace App\Console\Commands;

use App\Services\Mailbox\MailboxService;
use App\Services\Mailbox\Providers\CpanelUapiProvider;
use Illuminate\Console\Command;

class SyncMailboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailbox:sync {--token= : Optional cPanel API token override} {--domain=susantimegah.com : Domain email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi kapasitas storage dan kuota mailbox dari cPanel Exim';

    /**
     * Execute the console command.
     */
    public function handle(MailboxService $service): int
    {
        $this->info("Memulai sinkronisasi mailbox dari cPanel...");

        $config = [];
        if ($this->option('token')) {
            $config['api_token'] = $this->option('token');
        }

        try {
            $provider = new CpanelUapiProvider($config);
            $options = ['domain' => $this->option('domain')];

            $result = $service->syncFromProvider($provider, $options);

            $this->info("Sinkronisasi berhasil!");
            $this->table(
                ['Parameter', 'Nilai'],
                [
                    ['Total Diambil', $result['total_fetched']],
                    ['Baru Dibuat', $result['created']],
                    ['Diperbarui', $result['updated']],
                    ['Waktu Sinkronisasi', $result['synced_at']],
                    ['Provider', $result['provider']],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Gagal sinkronisasi: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
