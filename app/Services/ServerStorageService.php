<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class ServerStorageService
{
    /**
     * Get storage breakdown for partitions
     *
     * @param array $paths
     * @return array
     */
    public static function getStorageBreakdown(array $paths = ['/', '/home', '/tmp']): array
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $results = [];

        foreach ($paths as $path) {
            $totalBytes = 0;
            $freeBytes = 0;
            $usedBytes = 0;

            // Try df on Linux first
            if (!$isWindows) {
                try {
                    $process = Process::fromShellCommandline('df -P ' . escapeshellarg($path));
                    $process->run();
                    if ($process->isSuccessful()) {
                        $lines = explode("\n", trim($process->getOutput()));
                        if (count($lines) >= 2) {
                            $parts = preg_split('/\s+/', trim($lines[1]));
                            // Format: Filesystem 1024-blocks Used Available Capacity Mounted on
                            if (isset($parts[1], $parts[2], $parts[3])) {
                                $totalBytes = ((float) $parts[1]) * 1024;
                                $usedBytes = ((float) $parts[2]) * 1024;
                                $freeBytes = ((float) $parts[3]) * 1024;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Fallback to PHP native functions
                }
            }

            // Fallback to PHP native functions if df returned empty or running on Windows
            if ($totalBytes <= 0) {
                $target = ($isWindows) ? 'C:' : (@is_dir($path) ? $path : '/');
                $totalBytes = (float) (@disk_total_space($target) ?: 0);
                $freeBytes = (float) (@disk_free_space($target) ?: 0);
                $usedBytes = max(0, $totalBytes - $freeBytes);
            }

            if ($totalBytes > 0) {
                $usedPercent = round(($usedBytes / $totalBytes) * 100, 1);
                $freePercent = max(0, round(100 - $usedPercent, 1));
            } else {
                $usedPercent = 0;
                $freePercent = 100;
            }

            // Determine status based on thresholds
            $status = 'Normal';
            $statusColor = 'emerald';
            if ($usedPercent >= 90) {
                $status = 'Critical';
                $statusColor = 'rose';
            } elseif ($usedPercent >= 80) {
                $status = 'Warning';
                $statusColor = 'amber';
            }

            $results[] = [
                'path' => $path,
                'name' => match ($path) {
                    '/' => 'Root Storage (/)',
                    '/home' => 'User Data (/home)',
                    '/tmp' => 'Temporary Files (/tmp)',
                    default => $path,
                },
                'total_bytes' => $totalBytes,
                'used_bytes' => $usedBytes,
                'free_bytes' => $freeBytes,
                'total_formatted' => self::formatBytes($totalBytes),
                'used_formatted' => self::formatBytes($usedBytes),
                'free_formatted' => self::formatBytes($freeBytes),
                'used_percent' => $usedPercent,
                'free_percent' => $freePercent,
                'status' => $status,
                'status_color' => $statusColor,
            ];
        }

        return $results;
    }

    /**
     * Format bytes into human readable format
     */
    public static function formatBytes(float $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 GB';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min((int) $power, count($units) - 1);
        return round($bytes / pow(1024, $power), $precision) . ' ' . $units[$power];
    }
}
