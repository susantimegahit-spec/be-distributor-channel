<?php

namespace App\Health\Checks;

use App\Services\ServerStorageService;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class PartitionDiskCheck extends Check
{
    protected string $partitionPath = '/';
    protected int $warningThreshold = 80;
    protected int $errorThreshold = 90;

    public function partition(string $path): self
    {
        $this->partitionPath = $path;
        return $this;
    }

    public function warnWhenAbove(int $percentage): self
    {
        $this->warningThreshold = $percentage;
        return $this;
    }

    public function failWhenAbove(int $percentage): self
    {
        $this->errorThreshold = $percentage;
        return $this;
    }

    public function run(): Result
    {
        $breakdown = ServerStorageService::getStorageBreakdown([$this->partitionPath]);
        $data = $breakdown[0] ?? null;

        if (!$data || $data['total_bytes'] <= 0) {
            return Result::make()
                ->warning()
                ->shortSummary('N/A')
                ->notificationMessage("Partisi {$this->partitionPath} tidak dapat diakses atau tidak tersedia.");
        }

        $usedPercent = $data['used_percent'];
        $result = Result::make()
            ->meta($data)
            ->shortSummary("{$usedPercent}% ({$data['free_formatted']} free)");

        if ($usedPercent >= $this->errorThreshold) {
            return $result->failed("Disk {$data['name']} is critically full ({$usedPercent}% used). Sisa: {$data['free_formatted']}");
        }

        if ($usedPercent >= $this->warningThreshold) {
            return $result->warning("Disk {$data['name']} is almost full ({$usedPercent}% used). Sisa: {$data['free_formatted']}");
        }

        return $result->ok()->shortSummary("{$usedPercent}% used ({$data['free_formatted']} free)");
    }
}
