<?php

namespace App\Services\Mailbox;

use App\Models\Mailbox;
use App\Models\MailboxDepartment;
use App\Models\MailboxQuotaRecommendation;
use App\Models\MailboxSetting;
use App\Models\MailboxUsageSnapshot;
use App\Services\Mailbox\Contracts\MailboxDataProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MailboxService
{
    public const GB_BYTES = 1073741824; // 1024 * 1024 * 1024

    /**
     * Synchronize mailboxes from a given data provider.
     *
     * @param MailboxDataProviderInterface $provider
     * @param array $options
     * @return array
     */
    public function syncFromProvider(MailboxDataProviderInterface $provider, array $options = []): array
    {
        $items = $provider->getMailboxList($options);

        $created = 0;
        $updated = 0;
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $email = strtolower(trim($item['email']));
                $quota = (int) ($item['quota_bytes'] ?? 0);
                $usage = (int) ($item['current_usage_bytes'] ?? 0);

                $percentage = $quota > 0 ? round(($usage / $quota) * 100, 2) : 0.00;
                $status = $this->determineStatus($percentage);

                $mailbox = Mailbox::where('email', $email)->first();

                if (!$mailbox) {
                    // Try auto-resolve department by email prefix
                    $departmentId = $this->resolveDepartmentIdByEmail($email);

                    $mailbox = Mailbox::create([
                        'email'               => $email,
                        'user_name'           => $item['user_name'] ?? null,
                        'department_id'       => $departmentId,
                        'quota_bytes'         => $quota,
                        'current_usage_bytes' => $usage,
                        'usage_percentage'    => $percentage,
                        'status'              => $status,
                        'domain'              => $item['domain'] ?? 'susantimegah.com',
                        'suspended_incoming'  => $item['suspended_incoming'] ?? false,
                        'suspended_login'     => $item['suspended_login'] ?? false,
                        'last_synced_at'      => $now,
                    ]);
                    $created++;
                } else {
                    $mailbox->update([
                        'quota_bytes'         => $quota,
                        'current_usage_bytes' => $usage,
                        'usage_percentage'    => $percentage,
                        'status'              => $status,
                        'domain'              => $item['domain'] ?? $mailbox->domain,
                        'suspended_incoming'  => $item['suspended_incoming'] ?? $mailbox->suspended_incoming,
                        'suspended_login'     => $item['suspended_login'] ?? $mailbox->suspended_login,
                        'last_synced_at'      => $now,
                    ]);
                    $updated++;
                }

                // Record daily historical snapshot (avoid duplicate for the same date)
                $todayDate = $now->toDateString();
                $snapshotExists = MailboxUsageSnapshot::where('mailbox_id', $mailbox->id)
                    ->whereDate('recorded_at', $todayDate)
                    ->exists();

                if (!$snapshotExists) {
                    MailboxUsageSnapshot::create([
                        'mailbox_id'       => $mailbox->id,
                        'usage_bytes'      => $usage,
                        'quota_bytes'      => $quota,
                        'usage_percentage' => $percentage,
                        'recorded_at'      => $now,
                    ]);
                }

                // Compute Quota Recommendation & Forecasting
                $this->calculateRecommendation($mailbox);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Mailbox sync failed: " . $e->getMessage());
            throw $e;
        }

        return [
            'total_fetched' => count($items),
            'created'       => $created,
            'updated'       => $updated,
            'synced_at'     => $now->toIso8601String(),
            'provider'      => $provider->getName(),
        ];
    }

    /**
     * Determine mailbox status according to configured threshold settings.
     */
    public function determineStatus(float $percentage): string
    {
        $safeThreshold       = (float) MailboxSetting::get('safe_threshold', 70);
        $monitoringThreshold = (float) MailboxSetting::get('monitoring_threshold', 85);
        $warningThreshold    = (float) MailboxSetting::get('warning_threshold', 95);

        if ($percentage < $safeThreshold) {
            return Mailbox::STATUS_SAFE;
        }
        if ($percentage < $monitoringThreshold) {
            return Mailbox::STATUS_MONITORING;
        }
        if ($percentage < $warningThreshold) {
            return Mailbox::STATUS_WARNING;
        }
        return Mailbox::STATUS_CRITICAL;
    }

    /**
     * Calculate quota recommendation and forecast metrics for a mailbox.
     */
    public function calculateRecommendation(Mailbox $mailbox): MailboxQuotaRecommendation
    {
        $currentQuota = $mailbox->quota_bytes;
        $currentUsage = $mailbox->current_usage_bytes;
        $percentage   = (float) $mailbox->usage_percentage;

        // Configurable additions
        $warningAdditionGb  = (float) MailboxSetting::get('warning_addition_gb', 5);
        $criticalAdditionGb = (float) MailboxSetting::get('critical_addition_gb', 10);

        $warningAdditionBytes  = (int) ($warningAdditionGb * self::GB_BYTES);
        $criticalAdditionBytes = (int) ($criticalAdditionGb * self::GB_BYTES);

        // Historical snapshots for growth & forecast
        $forecast = $this->calculateForecast($mailbox);
        $growthPerDay = $forecast['growth_rate_bytes_per_day'];
        $daysToFull   = $forecast['estimated_days_to_full'];
        $riskLevel    = $forecast['risk_level'];

        $recommendedQuota = $currentQuota;
        $additionalQuota  = 0;
        $reason           = '';

        if ($percentage >= 95.0) {
            $additionalQuota  = $criticalAdditionBytes;
            $recommendedQuota = $currentQuota + $additionalQuota;
            $reason = "Penggunaan mencapai {$percentage}% (Kritis > 95%). Sangat disarankan penambahan kuota +{$warningAdditionGb}GB - +{$criticalAdditionGb}GB agar pengiriman/penerimaan email tidak terblokir.";
            $riskLevel = 'HIGH';
        } elseif ($percentage >= 85.0) {
            $additionalQuota  = $warningAdditionBytes;
            $recommendedQuota = $currentQuota + $additionalQuota;
            $reason = "Penggunaan berada di ambang Peringatan ({$percentage}%). Direkomendasikan penambahan kuota +{$warningAdditionGb}GB.";
            $riskLevel = 'MEDIUM';
        } elseif ($percentage >= 70.0) {
            // Trend monitoring: if growth rate is fast (> 50 MB/day) and full in < 30 days
            if ($growthPerDay > (50 * 1024 * 1024) && $daysToFull !== null && $daysToFull <= 30) {
                $additionalQuota  = (int) (2 * self::GB_BYTES);
                $recommendedQuota = $currentQuota + $additionalQuota;
                $reason = "Status Monitoring ({$percentage}%) dengan laju pertumbuhan tinggi (" . Mailbox::formatBytes($growthPerDay) . "/hari). Diperkirakan penuh dalam {$daysToFull} hari. Disarankan penambahan antisipatif +2GB.";
                $riskLevel = 'MEDIUM';
            } else {
                $recommendedQuota = $currentQuota;
                $additionalQuota  = 0;
                $reason = "Status Monitoring ({$percentage}%). Kapasitas kuota saat ini masih mencukupi, disarankan pemantauan berkala.";
                $riskLevel = 'LOW';
            }
        } else {
            $recommendedQuota = $currentQuota;
            $additionalQuota  = 0;
            $reason = "Penggunaan sehat ({$percentage}% < 70%). Kuota mencukupi dan tidak memerlukan penambahan.";
            $riskLevel = 'LOW';
        }

        return MailboxQuotaRecommendation::updateOrCreate(
            ['mailbox_id' => $mailbox->id],
            [
                'current_quota_bytes'       => $currentQuota,
                'recommended_quota_bytes'   => $recommendedQuota,
                'additional_quota_bytes'    => $additionalQuota,
                'reason'                    => $reason,
                'growth_rate_bytes_per_day' => $growthPerDay,
                'estimated_days_to_full'    => $daysToFull,
                'risk_level'                => $riskLevel,
            ]
        );
    }

    /**
     * Calculate historical growth and estimated full date.
     */
    public function calculateForecast(Mailbox $mailbox): array
    {
        $snapshots = MailboxUsageSnapshot::where('mailbox_id', $mailbox->id)
            ->orderBy('recorded_at', 'asc')
            ->get();

        // Require at least 2 distinct days of historical data for growth calculation
        if ($snapshots->count() < 2) {
            return [
                'has_enough_data'           => false,
                'growth_rate_bytes_per_day' => 0,
                'growth_per_week'           => 0,
                'growth_per_month'          => 0,
                'estimated_days_to_full'    => null,
                'risk_level'                => 'LOW',
                'message'                   => 'Belum cukup data untuk melakukan forecasting.',
            ];
        }

        $first = $snapshots->first();
        $last  = $snapshots->last();

        $daysDiff = max(1, Carbon::parse($first->recorded_at)->diffInDays(Carbon::parse($last->recorded_at)));
        $usageDiff = $last->usage_bytes - $first->usage_bytes;

        // Daily average growth in bytes
        $growthPerDay = max(0, (int) round($usageDiff / $daysDiff));
        $growthPerWeek = $growthPerDay * 7;
        $growthPerMonth = $growthPerDay * 30;

        $remainingBytes = $mailbox->remaining_bytes;
        $daysToFull = null;
        $riskLevel = 'LOW';

        if ($growthPerDay > 0 && $remainingBytes > 0) {
            $daysToFull = (int) floor($remainingBytes / $growthPerDay);

            if ($daysToFull <= 14) {
                $riskLevel = 'HIGH';
            } elseif ($daysToFull <= 30) {
                $riskLevel = 'MEDIUM';
            } else {
                $riskLevel = 'LOW';
            }
        } elseif ($remainingBytes <= 0) {
            $daysToFull = 0;
            $riskLevel = 'HIGH';
        }

        return [
            'has_enough_data'           => true,
            'growth_rate_bytes_per_day' => $growthPerDay,
            'growth_per_week'           => $growthPerWeek,
            'growth_per_month'          => $growthPerMonth,
            'estimated_days_to_full'    => $daysToFull,
            'risk_level'                => $riskLevel,
            'message'                   => null,
        ];
    }

    /**
     * Get aggregate statistics for Dashboard Overview.
     */
    public function getDashboardOverview(): array
    {
        $totalMailbox = Mailbox::count();
        $totalStorage = (int) Mailbox::sum('current_usage_bytes');
        $totalQuota   = (int) Mailbox::sum('quota_bytes');

        $avgUsage = $totalMailbox > 0 ? round((float) Mailbox::avg('usage_percentage'), 1) : 0;

        $countSafe       = Mailbox::where('status', Mailbox::STATUS_SAFE)->count();
        $countMonitoring = Mailbox::where('status', Mailbox::STATUS_MONITORING)->count();
        $countWarning    = Mailbox::where('status', Mailbox::STATUS_WARNING)->count();
        $countCritical   = Mailbox::where('status', Mailbox::STATUS_CRITICAL)->count();

        $countUpgradeRecommended = MailboxQuotaRecommendation::where('additional_quota_bytes', '>', 0)->count();

        $topUsageMailboxes = Mailbox::with('department')
            ->orderBy('current_usage_bytes', 'desc')
            ->limit(10)
            ->get();

        $departmentStats = MailboxDepartment::withCount('mailboxes')
            ->withSum('mailboxes as total_usage', 'current_usage_bytes')
            ->withSum('mailboxes as total_quota', 'quota_bytes')
            ->get();

        return [
            'total_mailbox'              => $totalMailbox,
            'total_storage_bytes'        => $totalStorage,
            'total_quota_bytes'          => $totalQuota,
            'formatted_total_storage'    => Mailbox::formatBytes($totalStorage),
            'formatted_total_quota'      => Mailbox::formatBytes($totalQuota),
            'average_usage_percentage'   => $avgUsage,
            'count_safe'                 => $countSafe,
            'count_monitoring'           => $countMonitoring,
            'count_warning'              => $countWarning,
            'count_critical'             => $countCritical,
            'count_upgrade_recommended'  => $countUpgradeRecommended,
            'top_usage_mailboxes'        => $topUsageMailboxes,
            'department_stats'           => $departmentStats,
        ];
    }

    /**
     * Auto-detect department ID by email keyword or pattern.
     */
    protected function resolveDepartmentIdByEmail(string $email): ?int
    {
        $prefix = strtolower(explode('@', $email)[0]);

        $keywords = [
            'IT'         => ['it', 'sysadmin', 'tech', 'developer', 'infra', 'devops', 'edp'],
            'FINANCE'    => ['finance', 'accounting', 'tax', 'kasir', 'faktur', 'pajak', 'keuangan'],
            'HR'         => ['hr', 'hrd', 'personalia', 'recruitment', 'training', 'ga'],
            'SALES'      => ['sales', 'order', 'commercial', 'penjualan', 'distributor', 'retail'],
            'MARKETING'  => ['marketing', 'promo', 'digital', 'branding', 'sosmed'],
            'MANAGEMENT' => ['direksi', 'bod', 'ceo', 'cfo', 'director', 'gm', 'manager'],
            'LOGISTIC'   => ['logistic', 'logistik', 'ekspedisi', 'gudang', 'warehouse', 'driver'],
        ];

        foreach ($keywords as $code => $words) {
            foreach ($words as $word) {
                if (str_contains($prefix, $word)) {
                    $dept = MailboxDepartment::where('code', $code)->first();
                    if ($dept) return $dept->id;
                }
            }
        }

        return null;
    }
}
