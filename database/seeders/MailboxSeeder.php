<?php

namespace Database\Seeders;

use App\Models\Mailbox;
use App\Models\MailboxDepartment;
use App\Models\MailboxQuotaRecommendation;
use App\Models\MailboxSetting;
use App\Models\MailboxUsageSnapshot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MailboxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Initial Settings (key, value, description)
        $defaultSettings = [
            'safe_threshold' => ['70', 'Batas persentase aman (< 70%)'],
            'monitoring_threshold' => ['85', 'Batas persentase monitoring (70% - 85%)'],
            'warning_threshold' => ['95', 'Batas persentase peringatan (85% - 95%)'],
            'warning_addition_gb' => ['5', 'Penambahan kuota rekomendasi untuk status Warning (GB)'],
            'critical_addition_gb' => ['10', 'Penambahan kuota rekomendasi untuk status Critical (GB)'],
            'cpanel_host' => ['163.61.58.29', 'Host server cPanel Mail Exim'],
            'cpanel_port' => ['2083', 'Port SSL cPanel'],
            'cpanel_user' => ['susantimegah', 'Username akun cPanel'],
            'cpanel_api_token' => ['', 'Token UAPI cPanel'],
            'auto_sync_enabled' => ['1', 'Sinkronisasi otomatis aktif'],
            'sync_frequency' => ['daily', 'Frekuensi sinkronisasi'],
        ];

        foreach ($defaultSettings as $key => [$val, $desc]) {
            MailboxSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $val, 'description' => $desc]
            );
        }

        // 2. Departments
        $deptData = [
            ['name' => 'IT & Systems', 'code' => 'IT', 'color' => '#3B82F6', 'description' => 'Departemen Teknologi Informasi & Infrastruktur'],
            ['name' => 'Finance & Accounting', 'code' => 'FINANCE', 'color' => '#10B981', 'description' => 'Keuangan, Pajak, dan Akuntansi'],
            ['name' => 'Sales & Marketing', 'code' => 'SALES', 'color' => '#F59E0B', 'description' => 'Penjualan, Marketing, dan Distribusi'],
            ['name' => 'Operations & Logistics', 'code' => 'LOGISTIC', 'color' => '#8B5CF6', 'description' => 'Operasional Gudang, Armada, dan Logistik'],
            ['name' => 'Human Resources & General Affairs', 'code' => 'HR', 'color' => '#EC4899', 'description' => 'HRD, Rekrutmen, dan GA'],
            ['name' => 'Procurement & Purchasing', 'code' => 'PROC', 'color' => '#6366F1', 'description' => 'Pengadaan Barang & Vendor'],
            ['name' => 'Management & Board', 'code' => 'MANAGEMENT', 'color' => '#14B8A6', 'description' => 'Direksi dan Manajemen Eksekutif'],
        ];

        $deptMap = [];
        foreach ($deptData as $d) {
            $dept = MailboxDepartment::updateOrCreate(['name' => $d['name']], $d);
            $deptMap[$d['code']] = $dept->id;
        }

        // 3. Mailboxes sample (25 mailboxes across all usage brackets)
        $mailboxes = [
            // CRITICAL (>95%)
            [
                'email' => 'sales.surabaya@susantimegah.com',
                'dept' => 'SALES',
                'user_name' => 'Budi Santoso',
                'quota_bytes' => 10 * 1024 * 1024 * 1024, // 10 GB
                'current_usage_bytes' => (int) (9.82 * 1024 * 1024 * 1024), // 98.2%
                'growth_rate' => 0.04, // GB per day
            ],
            [
                'email' => 'finance.ap@susantimegah.com',
                'dept' => 'FINANCE',
                'user_name' => 'Dewi Lestari',
                'quota_bytes' => 15 * 1024 * 1024 * 1024, // 15 GB
                'current_usage_bytes' => (int) (14.55 * 1024 * 1024 * 1024), // 97.0%
                'growth_rate' => 0.05,
            ],
            [
                'email' => 'purchasing.import@susantimegah.com',
                'dept' => 'PROC',
                'user_name' => 'Hendra Setiawan',
                'quota_bytes' => 8 * 1024 * 1024 * 1024, // 8 GB
                'current_usage_bytes' => (int) (7.68 * 1024 * 1024 * 1024), // 96.0%
                'growth_rate' => 0.03,
            ],

            // WARNING (85 - 95%)
            [
                'email' => 'logistik.spv@susantimegah.com',
                'dept' => 'LOGISTIC',
                'user_name' => 'Rahmat Hidayat',
                'quota_bytes' => 10 * 1024 * 1024 * 1024, // 10 GB
                'current_usage_bytes' => (int) (9.25 * 1024 * 1024 * 1024), // 92.5%
                'growth_rate' => 0.035,
            ],
            [
                'email' => 'it.support@susantimegah.com',
                'dept' => 'IT',
                'user_name' => 'Agus Pratama',
                'quota_bytes' => 20 * 1024 * 1024 * 1024, // 20 GB
                'current_usage_bytes' => (int) (17.8 * 1024 * 1024 * 1024), // 89.0%
                'growth_rate' => 0.06,
            ],
            [
                'email' => 'hrd.recruitment@susantimegah.com',
                'dept' => 'HR',
                'user_name' => 'Siti Nurhaliza',
                'quota_bytes' => 5 * 1024 * 1024 * 1024, // 5 GB
                'current_usage_bytes' => (int) (4.45 * 1024 * 1024 * 1024), // 89.0%
                'growth_rate' => 0.02,
            ],
            [
                'email' => 'tax.supervisor@susantimegah.com',
                'dept' => 'FINANCE',
                'user_name' => 'Sri Wahyuni',
                'quota_bytes' => 10 * 1024 * 1024 * 1024, // 10 GB
                'current_usage_bytes' => (int) (8.65 * 1024 * 1024 * 1024), // 86.5%
                'growth_rate' => 0.025,
            ],

            // MONITORING (70 - 85%)
            [
                'email' => 'direksi.sekretaris@susantimegah.com',
                'dept' => 'MANAGEMENT',
                'user_name' => 'Clara Wijaya',
                'quota_bytes' => 15 * 1024 * 1024 * 1024, // 15 GB
                'current_usage_bytes' => (int) (12.3 * 1024 * 1024 * 1024), // 82.0%
                'growth_rate' => 0.03,
            ],
            [
                'email' => 'gudang.gresik@susantimegah.com',
                'dept' => 'LOGISTIC',
                'user_name' => 'Joko Widodo',
                'quota_bytes' => 8 * 1024 * 1024 * 1024, // 8 GB
                'current_usage_bytes' => (int) (6.24 * 1024 * 1024 * 1024), // 78.0%
                'growth_rate' => 0.02,
            ],
            [
                'email' => 'marketing.digital@susantimegah.com',
                'dept' => 'SALES',
                'user_name' => 'Kevin Sanjaya',
                'quota_bytes' => 10 * 1024 * 1024 * 1024, // 10 GB
                'current_usage_bytes' => (int) (7.5 * 1024 * 1024 * 1024), // 75.0%
                'growth_rate' => 0.04,
            ],
            [
                'email' => 'purchasing.lokal@susantimegah.com',
                'dept' => 'PROC',
                'user_name' => 'Luki Hakim',
                'quota_bytes' => 6 * 1024 * 1024 * 1024, // 6 GB
                'current_usage_bytes' => (int) (4.38 * 1024 * 1024 * 1024), // 73.0%
                'growth_rate' => 0.015,
            ],
            [
                'email' => 'finance.ar@susantimegah.com',
                'dept' => 'FINANCE',
                'user_name' => 'Fitriani',
                'quota_bytes' => 8 * 1024 * 1024 * 1024, // 8 GB
                'current_usage_bytes' => (int) (5.72 * 1024 * 1024 * 1024), // 71.5%
                'growth_rate' => 0.02,
            ],

            // SAFE (< 70%)
            [
                'email' => 'hrd.payroll@susantimegah.com',
                'dept' => 'HR',
                'user_name' => 'Eka Yulianti',
                'quota_bytes' => 8 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (5.12 * 1024 * 1024 * 1024), // 64.0%
                'growth_rate' => 0.015,
            ],
            [
                'email' => 'sales.jakarta@susantimegah.com',
                'dept' => 'SALES',
                'user_name' => 'Denny Chandra',
                'quota_bytes' => 10 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (5.8 * 1024 * 1024 * 1024), // 58.0%
                'growth_rate' => 0.025,
            ],
            [
                'email' => 'it.dev@susantimegah.com',
                'dept' => 'IT',
                'user_name' => 'Fajar Nugraha',
                'quota_bytes' => 15 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (7.8 * 1024 * 1024 * 1024), // 52.0%
                'growth_rate' => 0.02,
            ],
            [
                'email' => 'logistik.armada@susantimegah.com',
                'dept' => 'LOGISTIC',
                'user_name' => 'Bambang Irawan',
                'quota_bytes' => 5 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (2.4 * 1024 * 1024 * 1024), // 48.0%
                'growth_rate' => 0.01,
            ],
            [
                'email' => 'general.affair@susantimegah.com',
                'dept' => 'HR',
                'user_name' => 'Wahyu Hidayat',
                'quota_bytes' => 5 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (2.1 * 1024 * 1024 * 1024), // 42.0%
                'growth_rate' => 0.01,
            ],
            [
                'email' => 'audit.internal@susantimegah.com',
                'dept' => 'FINANCE',
                'user_name' => 'Maria Kristina',
                'quota_bytes' => 10 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (3.6 * 1024 * 1024 * 1024), // 36.0%
                'growth_rate' => 0.01,
            ],
            [
                'email' => 'sales.semarang@susantimegah.com',
                'dept' => 'SALES',
                'user_name' => 'Teguh Prasetyo',
                'quota_bytes' => 6 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (1.8 * 1024 * 1024 * 1024), // 30.0%
                'growth_rate' => 0.008,
            ],
            [
                'email' => 'direksi.finance@susantimegah.com',
                'dept' => 'MANAGEMENT',
                'user_name' => 'Direktur Keuangan',
                'quota_bytes' => 25 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (6.5 * 1024 * 1024 * 1024), // 26.0%
                'growth_rate' => 0.012,
            ],
            [
                'email' => 'direksi.operasional@susantimegah.com',
                'dept' => 'MANAGEMENT',
                'user_name' => 'Direktur Operasional',
                'quota_bytes' => 25 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (5.5 * 1024 * 1024 * 1024), // 22.0%
                'growth_rate' => 0.01,
            ],
            [
                'email' => 'customer.care@susantimegah.com',
                'dept' => 'SALES',
                'user_name' => 'Putri Ayu',
                'quota_bytes' => 8 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (1.6 * 1024 * 1024 * 1024), // 20.0%
                'growth_rate' => 0.005,
            ],
            [
                'email' => 'legal.corporate@susantimegah.com',
                'dept' => 'HR',
                'user_name' => 'Rina Gunawan',
                'quota_bytes' => 6 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (1.08 * 1024 * 1024 * 1024), // 18.0%
                'growth_rate' => 0.005,
            ],
            [
                'email' => 'info@susantimegah.com',
                'dept' => 'IT',
                'user_name' => 'General Mailbox',
                'quota_bytes' => 5 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (0.6 * 1024 * 1024 * 1024), // 12.0%
                'growth_rate' => 0.002,
            ],
            [
                'email' => 'noreply@susantimegah.com',
                'dept' => 'IT',
                'user_name' => 'Automated System',
                'quota_bytes' => 2 * 1024 * 1024 * 1024,
                'current_usage_bytes' => (int) (0.1 * 1024 * 1024 * 1024), // 5.0%
                'growth_rate' => 0.001,
            ],
        ];

        $now = Carbon::now();

        foreach ($mailboxes as $m) {
            $quota = $m['quota_bytes'];
            $used = $m['current_usage_bytes'];
            $pct = $quota > 0 ? round(($used / $quota) * 100, 2) : 0.00;

            $status = Mailbox::STATUS_SAFE;
            if ($pct >= 95) {
                $status = Mailbox::STATUS_CRITICAL;
            } elseif ($pct >= 85) {
                $status = Mailbox::STATUS_WARNING;
            } elseif ($pct >= 70) {
                $status = Mailbox::STATUS_MONITORING;
            }

            $mailbox = Mailbox::updateOrCreate(
                ['email' => $m['email']],
                [
                    'department_id'       => $deptMap[$m['dept']] ?? null,
                    'user_name'           => $m['user_name'],
                    'quota_bytes'         => $quota,
                    'current_usage_bytes' => $used,
                    'usage_percentage'    => $pct,
                    'status'              => $status,
                    'domain'              => 'susantimegah.com',
                    'suspended_incoming'  => false,
                    'suspended_login'     => false,
                    'last_synced_at'      => $now,
                ]
            );

            // Generate 14-day snapshots
            $dailyGrowthBytes = (int) ($m['growth_rate'] * 1024 * 1024 * 1024);
            for ($i = 13; $i >= 0; $i--) {
                $snapDate = $now->copy()->subDays($i);
                $historicalUsed = max(100 * 1024 * 1024, $used - ($i * $dailyGrowthBytes) + rand(-10 * 1024 * 1024, 10 * 1024 * 1024));
                $histPct = $quota > 0 ? round(($historicalUsed / $quota) * 100, 2) : 0.00;

                MailboxUsageSnapshot::updateOrCreate(
                    [
                        'mailbox_id'  => $mailbox->id,
                        'recorded_at' => $snapDate->format('Y-m-d H:i:s'),
                    ],
                    [
                        'usage_bytes'      => $historicalUsed,
                        'quota_bytes'      => $quota,
                        'usage_percentage' => $histPct,
                    ]
                );
            }

            // Generate Quota Recommendations for WARNING / CRITICAL
            if (in_array($status, [Mailbox::STATUS_WARNING, Mailbox::STATUS_CRITICAL])) {
                $incrementGb = $status === Mailbox::STATUS_CRITICAL ? 10 : 5;
                $additionalBytes = $incrementGb * 1024 * 1024 * 1024;
                $recommendedQuotaBytes = $quota + $additionalBytes;

                $reason = $status === Mailbox::STATUS_CRITICAL
                    ? "Penggunaan telah mencapai {$pct}% (>95%). Kuota mailbox hampir habis dan berisiko email masuk tertolak (bounce). Disarankan penambahan kuota sebesar +{$incrementGb} GB sesegera mungkin."
                    : "Penggunaan telah mencapai {$pct}% (>85%). Pola pertumbuhan email berpotensi mencapai kapasitas maksimal dalam tempo singkat. Disarankan penambahan kuota +{$incrementGb} GB.";

                $daysToFull = $m['growth_rate'] > 0 ? (int) floor(($quota - $used) / ($m['growth_rate'] * 1024 * 1024 * 1024)) : 7;

                MailboxQuotaRecommendation::updateOrCreate(
                    [
                        'mailbox_id' => $mailbox->id,
                    ],
                    [
                        'current_quota_bytes'       => $quota,
                        'recommended_quota_bytes'   => $recommendedQuotaBytes,
                        'additional_quota_bytes'    => $additionalBytes,
                        'reason'                    => $reason,
                        'status'                    => 'PENDING',
                        'growth_rate_bytes_per_day' => $dailyGrowthBytes,
                        'estimated_days_to_full'    => max(1, $daysToFull),
                        'risk_level'                => $status === Mailbox::STATUS_CRITICAL ? 'HIGH' : 'MEDIUM',
                    ]
                );
            }
        }
    }
}
