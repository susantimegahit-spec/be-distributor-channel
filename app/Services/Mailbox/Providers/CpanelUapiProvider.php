<?php

namespace App\Services\Mailbox\Providers;

use App\Services\Mailbox\Contracts\MailboxDataProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class CpanelUapiProvider implements MailboxDataProviderInterface
{
    protected string $host;
    protected int $port;
    protected string $username;
    protected ?string $apiToken;
    protected bool $sslVerify;

    public function __construct(array $config = [])
    {
        $this->host      = $config['host'] ?? \App\Models\MailboxSetting::get('cpanel_host') ?: env('CPANEL_HOST', '127.0.0.1');
        $this->port      = (int) ($config['port'] ?? \App\Models\MailboxSetting::get('cpanel_port') ?: env('CPANEL_PORT', 2083));
        $this->username  = $config['username'] ?? \App\Models\MailboxSetting::get('cpanel_user') ?: env('CPANEL_USERNAME', 'susantimegah');
        $this->apiToken  = $config['api_token'] ?? \App\Models\MailboxSetting::get('cpanel_api_token') ?: env('CPANEL_API_TOKEN', null);
        $this->sslVerify = filter_var($config['ssl_verify'] ?? env('CPANEL_SSL_VERIFY', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function getName(): string
    {
        return 'cpanel_uapi';
    }

    /**
     * Fetch mailboxes directly from cPanel UAPI.
     */
    public function getMailboxList(array $options = []): array
    {
        // 1. Try HTTP UAPI with API Token if configured
        if (!empty($this->apiToken) && !empty($this->username)) {
            return $this->fetchViaHttpApi($options);
        }

        // 2. Try Local cPanel CLI command (`uapi Email list_pops_with_disk`) on server
        if ($this->canExecuteLocalUapi()) {
            return $this->fetchViaLocalCli($options);
        }

        throw new \Exception(
            "cPanel API Token belum diatur. Untuk sinkronisasi otomatis via web, masukkan cPanel API Token di menu 'Konfigurasi & cPanel' atau tambahkan CPANEL_API_TOKEN di file .env."
        );
    }

    /**
     * Query cPanel UAPI over HTTPS.
     */
    protected function fetchViaHttpApi(array $options = []): array
    {
        $url = "https://{$this->host}:{$this->port}/execute/Email/list_pops_with_disk";
        $domain = $options['domain'] ?? env('CPANEL_DOMAIN', 'susantimegah.com');

        try {
            $response = Http::withHeaders([
                'Authorization' => "cpanel {$this->username}:{$this->apiToken}",
            ])
            ->withoutVerifying()
            ->timeout(25)
            ->get($url, [
                'domain' => $domain,
            ]);

            if (!$response->successful()) {
                throw new \Exception("cPanel UAPI returned HTTP status {$response->status()}: " . substr($response->body(), 0, 300));
            }

            $json = $response->json();
            return $this->normalizeUapiData($json['data'] ?? []);
        } catch (\Throwable $e) {
            Log::error("Failed to connect to cPanel UAPI: " . $e->getMessage());
            throw new \Exception("Gagal menghubungi cPanel UAPI: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Query local cPanel UAPI CLI.
     */
    protected function fetchViaLocalCli(array $options = []): array
    {
        try {
            $process = Process::run('uapi --output=jsonpretty Email list_pops_with_disk');

            if ($process->failed()) {
                throw new \Exception($process->errorOutput() ?: 'UAPI CLI execution failed');
            }

            $json = json_decode($process->output(), true);
            if (!is_array($json) || empty($json['data'])) {
                throw new \Exception("Invalid JSON output from cPanel uapi CLI.");
            }

            return $this->normalizeUapiData($json['data']);
        } catch (\Throwable $e) {
            Log::error("Failed executing local cPanel CLI: " . $e->getMessage());
            throw new \Exception("Gagal menjalankan cPanel CLI lokal: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Check if local uapi binary is executable without triggering disabled_function errors.
     */
    protected function canExecuteLocalUapi(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false; // Windows local dev
        }

        // Check if shell_exec is disabled in php.ini
        if (!function_exists('shell_exec') || !is_callable('shell_exec')) {
            return false;
        }

        $disabled = explode(',', (string) ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);
        if (in_array('shell_exec', $disabled, true)) {
            return false;
        }

        try {
            $check = @shell_exec('which uapi 2>/dev/null');
            return !empty(trim((string) $check));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Normalize cPanel UAPI list_pops_with_disk output.
     */
    protected function normalizeUapiData(array $rawItems): array
    {
        $normalized = [];

        foreach ($rawItems as $item) {
            $email = trim((string) ($item['email'] ?? $item['login'] ?? ''));
            if (empty($email) || !str_contains($email, '@')) {
                continue;
            }

            // Quota in bytes (0 / unlimited in cPanel could be 'unlimited', 0, or null)
            $rawQuota = $item['_diskquota'] ?? $item['diskquota'] ?? 0;
            $quotaBytes = is_numeric($rawQuota) ? (int) $rawQuota : 0;
            if (strtolower((string) $rawQuota) === 'unlimited') {
                $quotaBytes = 0;
            }

            // Usage in bytes
            $rawUsage = $item['_diskused'] ?? $item['diskused'] ?? 0;
            $usageBytes = is_numeric($rawUsage) ? (int) $rawUsage : 0;

            // Guess display name from email prefix or user field
            $userPrefix = $item['user'] ?? explode('@', $email)[0];
            $displayName = ucwords(str_replace(['.', '_', '-'], ' ', (string) $userPrefix));

            $domain = $item['domain'] ?? explode('@', $email)[1] ?? 'susantimegah.com';

            $normalized[] = [
                'email'               => strtolower($email),
                'user_name'           => $displayName,
                'quota_bytes'         => $quotaBytes,
                'current_usage_bytes' => $usageBytes,
                'domain'              => strtolower($domain),
                'suspended_incoming'  => !empty($item['suspended_incoming']),
                'suspended_login'     => !empty($item['suspended_login']),
            ];
        }

        return $normalized;
    }
}
