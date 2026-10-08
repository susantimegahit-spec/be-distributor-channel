<?php

namespace App\Services\Mailbox\Providers;

use App\Services\Mailbox\Contracts\MailboxDataProviderInterface;

class CsvProvider implements MailboxDataProviderInterface
{
    protected ?string $filePath;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath;
    }

    public function getName(): string
    {
        return 'csv';
    }

    public function getMailboxList(array $options = []): array
    {
        $path = $options['file_path'] ?? $this->filePath;
        $content = $options['content'] ?? null;

        if (!$content && $path && file_exists($path)) {
            $content = file_get_contents($path);
        }

        if (empty($content)) {
            throw new \Exception("File CSV kosong atau tidak ditemukan.");
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        // Header parsing
        $headerLine = array_shift($lines);
        $headers = array_map(fn($h) => strtolower(trim(str_replace(['"', "'"], '', $h))), str_getcsv($headerLine));

        $emailCol = $this->findColumn($headers, ['email', 'mailbox', 'login', 'account', 'alamat_email']);
        $usageCol = $this->findColumn($headers, ['current_usage_bytes', 'usage_bytes', 'usage', 'diskused', '_diskused', 'pemakaian']);
        $quotaCol = $this->findColumn($headers, ['quota_bytes', 'quota', 'diskquota', '_diskquota', 'kapasitas']);
        $nameCol  = $this->findColumn($headers, ['user_name', 'name', 'user', 'nama', 'display_name']);

        if ($emailCol === null) {
            throw new \Exception("Kolom 'email' tidak ditemukan dalam file CSV.");
        }

        $normalized = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            $email = trim((string) ($row[$emailCol] ?? ''));
            if (!str_contains($email, '@')) continue;

            $quotaRaw = $quotaCol !== null ? ($row[$quotaCol] ?? 0) : 0;
            $usageRaw = $usageCol !== null ? ($row[$usageCol] ?? 0) : 0;
            $nameRaw  = $nameCol !== null ? ($row[$nameCol] ?? null) : null;

            $normalized[] = [
                'email'               => strtolower($email),
                'user_name'           => $nameRaw ?: ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $email)[0])),
                'quota_bytes'         => $this->parseSizeToBytes($quotaRaw),
                'current_usage_bytes' => $this->parseSizeToBytes($usageRaw),
                'domain'              => strtolower(explode('@', $email)[1] ?? 'susantimegah.com'),
                'suspended_incoming'  => false,
                'suspended_login'     => false,
            ];
        }

        return $normalized;
    }

    protected function findColumn(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $cand) {
            $idx = array_search($cand, $headers, true);
            if ($idx !== false) return $idx;
        }
        return null;
    }

    protected function parseSizeToBytes(mixed $raw): int
    {
        if (is_numeric($raw)) {
            return (int) $raw;
        }

        $str = strtoupper(trim((string) $raw));
        if (empty($str) || $str === 'UNLIMITED' || $str === '0') {
            return 0;
        }

        if (preg_match('/^([\d.]+)\s*(TB|GB|MB|KB|B)$/i', $str, $m)) {
            $val = (float) $m[1];
            return match (strtoupper($m[2])) {
                'TB'    => (int) ($val * 1024 * 1024 * 1024 * 1024),
                'GB'    => (int) ($val * 1024 * 1024 * 1024),
                'MB'    => (int) ($val * 1024 * 1024),
                'KB'    => (int) ($val * 1024),
                default => (int) $val,
            };
        }

        return (int) $raw;
    }
}
