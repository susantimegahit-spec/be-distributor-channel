<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use App\Models\MailboxDepartment;
use App\Models\MailboxQuotaRecommendation;
use App\Models\MailboxSetting;
use App\Models\MailboxUsageSnapshot;
use App\Services\Mailbox\MailboxService;
use App\Services\Mailbox\Providers\CpanelUapiProvider;
use App\Services\Mailbox\Providers\CsvProvider;
use App\Services\Mailbox\Providers\JsonProvider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class MailboxMonitoringWebController extends Controller
{
    protected MailboxService $mailboxService;

    public function __construct(MailboxService $mailboxService)
    {
        $this->mailboxService = $mailboxService;
    }

    /**
     * Dashboard Overview
     */
    public function index(Request $request)
    {
        $stats = $this->mailboxService->getDashboardOverview();

        // Department usage distribution
        $departments = MailboxDepartment::withCount('mailboxes')
            ->with(['mailboxes' => function ($q) {
                $q->select('id', 'department_id', 'current_usage_bytes', 'quota_bytes');
            }])
            ->get()
            ->map(function ($dept) {
                $totalUsed = $dept->mailboxes->sum('current_usage_bytes');
                $totalQuota = $dept->mailboxes->sum('quota_bytes');
                $pct = $totalQuota > 0 ? round(($totalUsed / $totalQuota) * 100, 1) : 0;
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'code' => $dept->code,
                    'color' => $dept->color ?? '#3B82F6',
                    'count' => $dept->mailboxes_count,
                    'total_used_bytes' => $totalUsed,
                    'total_quota_bytes' => $totalQuota,
                    'total_used_gb' => round($totalUsed / (1024 * 1024 * 1024), 2),
                    'total_quota_gb' => round($totalQuota / (1024 * 1024 * 1024), 2),
                    'usage_percent' => $pct,
                ];
            });

        // Top 10 by Usage Percent
        $topPercent = Mailbox::with('department')
            ->orderBy('usage_percentage', 'desc')
            ->limit(10)
            ->get();

        // Top 10 by Storage Size (GB)
        $topStorage = Mailbox::with('department')
            ->orderBy('current_usage_bytes', 'desc')
            ->limit(10)
            ->get();

        // Warning & Critical Mailboxes needing attention
        $alertMailboxes = Mailbox::with('department')
            ->whereIn('status', [Mailbox::STATUS_WARNING, Mailbox::STATUS_CRITICAL])
            ->orderBy('usage_percentage', 'desc')
            ->get();

        // Active recommendations count
        $pendingRecommendationsCount = MailboxQuotaRecommendation::where('status', 'PENDING')->count();

        // Last sync time
        $lastSync = Mailbox::max('last_synced_at');

        return view('mailboxes.dashboard', compact(
            'stats',
            'departments',
            'topPercent',
            'topStorage',
            'alertMailboxes',
            'pendingRecommendationsCount',
            'lastSync'
        ));
    }

    /**
     * Mailbox List / Management Table
     */
    public function list(Request $request)
    {
        $query = Mailbox::with('department');

        // Filter: Department
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filter: Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter: Search (email, user_name)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'usage_percentage');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['email', 'user_name', 'current_usage_bytes', 'quota_bytes', 'usage_percentage', 'status', 'last_synced_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('usage_percentage', 'desc');
        }

        $perPage = (int) $request->get('per_page', 15);
        $mailboxes = $query->paginate($perPage)->withQueryString();

        $departments = MailboxDepartment::orderBy('name')->get();
        $statuses = [Mailbox::STATUS_SAFE, Mailbox::STATUS_MONITORING, Mailbox::STATUS_WARNING, Mailbox::STATUS_CRITICAL];

        return view('mailboxes.index', compact('mailboxes', 'departments', 'statuses'));
    }

    /**
     * Mailbox Detail View
     */
    public function show($id)
    {
        $mailbox = Mailbox::with(['department', 'recommendations'])->findOrFail($id);

        // Historical snapshots (up to 30 days)
        $snapshots = MailboxUsageSnapshot::where('mailbox_id', $mailbox->id)
            ->orderBy('recorded_at', 'asc')
            ->limit(30)
            ->get();

        // Forecast computation
        $forecast = $this->mailboxService->calculateForecast($mailbox);

        // All departments for quick reassignment
        $departments = MailboxDepartment::orderBy('name')->get();

        return view('mailboxes.show', compact('mailbox', 'snapshots', 'forecast', 'departments'));
    }

    /**
     * Update Mailbox metadata (user_name, department)
     */
    public function update(Request $request, $id)
    {
        $mailbox = Mailbox::findOrFail($id);

        $request->validate([
            'user_name' => 'nullable|string|max:150',
            'department_id' => 'nullable|exists:mailbox_departments,id',
        ]);

        $mailbox->update([
            'user_name' => $request->user_name,
            'department_id' => $request->department_id,
        ]);

        return redirect()->back()->with('success', "Data mailbox {$mailbox->email} berhasil diperbarui.");
    }

    /**
     * Departments List & Mapping
     */
    public function departments()
    {
        $departments = MailboxDepartment::withCount('mailboxes')
            ->with(['mailboxes' => function ($q) {
                $q->select('id', 'department_id', 'current_usage_bytes', 'quota_bytes');
            }])
            ->get()
            ->map(function ($dept) {
                $dept->total_used = $dept->mailboxes->sum('current_usage_bytes');
                $dept->total_quota = $dept->mailboxes->sum('quota_bytes');
                $dept->avg_usage = $dept->total_quota > 0 ? round(($dept->total_used / $dept->total_quota) * 100, 1) : 0;
                return $dept;
            });

        $unassignedCount = Mailbox::whereNull('department_id')->count();

        return view('mailboxes.departments', compact('departments', 'unassignedCount'));
    }

    /**
     * Store Department
     */
    public function storeDepartment(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:mailbox_departments,name',
            'code' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
        ]);

        MailboxDepartment::create($request->only('name', 'code', 'color', 'description'));

        return redirect()->route('mailboxes.departments')->with('success', 'Departemen baru berhasil ditambahkan.');
    }

    /**
     * Update Department
     */
    public function updateDepartment(Request $request, $id)
    {
        $dept = MailboxDepartment::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100|unique:mailbox_departments,name,' . $dept->id,
            'code' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
        ]);

        $dept->update($request->only('name', 'code', 'color', 'description'));

        return redirect()->route('mailboxes.departments')->with('success', 'Data departemen berhasil diupdate.');
    }

    /**
     * Delete Department
     */
    public function destroyDepartment($id)
    {
        $dept = MailboxDepartment::findOrFail($id);
        
        Mailbox::where('department_id', $dept->id)->update(['department_id' => null]);
        $dept->delete();

        return redirect()->route('mailboxes.departments')->with('success', 'Departemen berhasil dihapus. Mailbox terkait kini unassigned.');
    }

    /**
     * Quota Recommendations View
     */
    public function recommendations(Request $request)
    {
        $status = $request->get('status', 'PENDING');

        $query = MailboxQuotaRecommendation::with(['mailbox.department'])
            ->orderBy('created_at', 'desc');

        if ($status !== 'ALL') {
            $query->where('status', $status);
        }

        $recommendations = $query->paginate(15)->withQueryString();

        $counts = [
            'PENDING' => MailboxQuotaRecommendation::where('status', 'PENDING')->count(),
            'APPROVED' => MailboxQuotaRecommendation::where('status', 'APPROVED')->count(),
            'APPLIED' => MailboxQuotaRecommendation::where('status', 'APPLIED')->count(),
            'DISMISSED' => MailboxQuotaRecommendation::where('status', 'DISMISSED')->count(),
        ];

        return view('mailboxes.recommendations', compact('recommendations', 'counts', 'status'));
    }

    /**
     * Update Recommendation Status (Approve / Dismiss / Applied)
     */
    public function updateRecommendation(Request $request, $id)
    {
        $rec = MailboxQuotaRecommendation::findOrFail($id);

        $request->validate([
            'status' => 'required|in:PENDING,APPROVED,DISMISSED,APPLIED',
            'notes' => 'nullable|string|max:255',
        ]);

        $rec->status = $request->status;
        if ($request->filled('notes')) {
            $rec->reason = $rec->reason . " | Catatan: " . $request->notes;
        }
        $rec->save();

        return redirect()->back()->with('success', "Status rekomendasi #{$rec->id} diubah menjadi {$rec->status}.");
    }

    /**
     * Executive Report View
     */
    public function reports()
    {
        $stats = $this->mailboxService->getDashboardOverview();

        $departments = MailboxDepartment::with(['mailboxes' => function ($q) {
            $q->select('id', 'department_id', 'current_usage_bytes', 'quota_bytes');
        }])->get()->map(function ($dept) {
            $totalUsed = $dept->mailboxes->sum('current_usage_bytes');
            $totalQuota = $dept->mailboxes->sum('quota_bytes');
            return [
                'name' => $dept->name,
                'count' => $dept->mailboxes->count(),
                'used_gb' => round($totalUsed / (1024 * 1024 * 1024), 2),
                'quota_gb' => round($totalQuota / (1024 * 1024 * 1024), 2),
                'usage_percent' => $totalQuota > 0 ? round(($totalUsed / $totalQuota) * 100, 1) : 0,
            ];
        });

        $highRiskMailboxes = Mailbox::with('department')
            ->whereIn('status', [Mailbox::STATUS_CRITICAL, Mailbox::STATUS_WARNING])
            ->orderBy('usage_percentage', 'desc')
            ->get()
            ->map(function ($m) {
                $forecast = $this->mailboxService->calculateForecast($m);
                $m->days_until_full = $forecast['estimated_days_to_full'];
                return $m;
            });

        $recommendations = MailboxQuotaRecommendation::with(['mailbox.department'])
            ->where('status', 'PENDING')
            ->get();

        $generatedAt = Carbon::now()->isoFormat('D MMMM Y, HH:mm');

        return view('mailboxes.reports', compact('stats', 'departments', 'highRiskMailboxes', 'recommendations', 'generatedAt'));
    }

    /**
     * Export Mailboxes to CSV
     */
    public function exportCsv()
    {
        $mailboxes = Mailbox::with('department')->orderBy('usage_percentage', 'desc')->get();

        $filename = 'mailbox_monitoring_report_' . Carbon::now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($mailboxes) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, [
                'Email',
                'Pengguna / PIC',
                'Departemen',
                'Kapasitas Digunakan (GB)',
                'Total Kuota (GB)',
                'Penggunaan (%)',
                'Status',
                'Estimasi Penuh (Hari)',
                'Pertumbuhan Harian (MB/Hari)',
                'Terakhir Sync',
            ]);

            foreach ($mailboxes as $m) {
                $forecast = $this->mailboxService->calculateForecast($m);
                $daysUntilFull = $forecast['estimated_days_to_full'] !== null ? $forecast['estimated_days_to_full'] : 'Stabil';
                $dailyMb = round(($forecast['growth_rate_bytes_per_day'] ?? 0) / (1024 * 1024), 2);

                fputcsv($output, [
                    $m->email,
                    $m->user_name ?? '-',
                    $m->department ? $m->department->name : 'Unassigned',
                    round($m->current_usage_bytes / (1024 * 1024 * 1024), 2),
                    round($m->quota_bytes / (1024 * 1024 * 1024), 2),
                    $m->usage_percentage . '%',
                    $m->status,
                    $daysUntilFull,
                    $dailyMb,
                    $m->last_synced_at ? Carbon::parse($m->last_synced_at)->format('Y-m-d H:i') : '-',
                ]);
            }

            fclose($output);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Settings View
     */
    public function settings()
    {
        $settings = MailboxSetting::all()->pluck('value', 'key')->toArray();

        return view('mailboxes.settings', compact('settings'));
    }

    /**
     * Update Settings
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'safe_threshold' => 'required|numeric|min:1|max:100',
            'monitoring_threshold' => 'required|numeric|min:1|max:100',
            'warning_threshold' => 'required|numeric|min:1|max:100',
            'warning_addition_gb' => 'required|numeric|min:1',
            'critical_addition_gb' => 'required|numeric|min:1',
            'cpanel_host' => 'nullable|string|max:150',
            'cpanel_port' => 'nullable|numeric',
            'cpanel_user' => 'nullable|string|max:100',
            'cpanel_api_token' => 'nullable|string|max:255',
            'auto_sync_enabled' => 'nullable|boolean',
            'sync_frequency' => 'nullable|string|in:hourly,daily,weekly',
        ]);

        foreach ($validated as $key => $val) {
            MailboxSetting::set($key, (string) ($val ?? ''));
        }

        return redirect()->route('mailboxes.settings')->with('success', 'Konfigurasi monitoring mailbox berhasil disimpan.');
    }

    /**
     * Trigger Live Sync from cPanel
     */
    public function sync(Request $request)
    {
        try {
            $provider = new CpanelUapiProvider();
            $result = $this->mailboxService->syncFromProvider($provider);

            return redirect()->back()->with('success', "Sinkronisasi berhasil! Total {$result['total_fetched']} akun email diproses (Baru: {$result['created']}, Diperbarui: {$result['updated']}).");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal melakukan sinkronisasi: ' . $e->getMessage());
        }
    }

    /**
     * Import Mailbox Data from CSV or JSON
     */
    public function import(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt,json|max:10240',
        ]);

        $file = $request->file('import_file');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        try {
            if ($ext === 'json') {
                $provider = new JsonProvider($path);
            } else {
                $provider = new CsvProvider($path);
            }

            $result = $this->mailboxService->syncFromProvider($provider);

            return redirect()->route('mailboxes.list')->with('success', "Data berhasil diimpor! Total: {$result['total_fetched']} mailbox.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal memproses file impor: ' . $e->getMessage());
        }
    }
}
