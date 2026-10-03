<?php

namespace App\Modules\CronJob\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CronJob;
use App\Models\CronJobLog;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

class CronJobController extends Controller
{
    use ApiResponseFormatter;

    /**
     * Display a listing of the cron jobs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CronJob::query();

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('command', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $cronJobs = $query->orderBy('name', 'asc')->get();
        return $this->successResponse($cronJobs, 'Daftar cron job berhasil diambil.');
    }

    /**
     * Clean command string by stripping 'php artisan' or 'artisan' prefix if present.
     */
    private function cleanCommand(string $command): string
    {
        $cmd = trim($command);
        if (str_starts_with($cmd, 'php artisan ')) {
            $cmd = substr($cmd, 12);
        } elseif (str_starts_with($cmd, 'artisan ')) {
            $cmd = substr($cmd, 8);
        } elseif (str_starts_with($cmd, 'php ')) {
            $cmd = substr($cmd, 4);
        }
        return trim($cmd);
    }

    /**
     * Store a newly created cron job in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'command' => 'required|string|max:255',
            'expression' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['command'] = $this->cleanCommand($validated['command']);

        $parts = explode(' ', $validated['expression']);
        if (count($parts) !== 5 && !str_starts_with($validated['expression'], '@')) {
            return $this->errorResponse('Format ekspresi cron tidak valid. Harus memiliki 5 bagian (misal: */15 * * * *).', null, 422);
        }

        $validated['is_active'] = $validated['is_active'] ?? true;
        $cronJob = CronJob::create($validated);

        return $this->successResponse($cronJob, 'Cron job berhasil dibuat.', 201);
    }

    /**
     * Display the specified cron job detail.
     */
    public function show(int $id): JsonResponse
    {
        $cronJob = CronJob::with(['logs' => function ($q) {
            $q->orderBy('run_at', 'desc')->limit(10);
        }])->find($id);

        if (!$cronJob) {
            return $this->errorResponse('Cron job tidak ditemukan.', null, 404);
        }

        return $this->successResponse($cronJob, 'Detail cron job berhasil diambil.');
    }

    /**
     * Update the specified cron job in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $cronJob = CronJob::find($id);

        if (!$cronJob) {
            return $this->errorResponse('Cron job tidak ditemukan.', null, 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'command' => 'sometimes|required|string|max:255',
            'expression' => 'sometimes|required|string|max:100',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string',
        ]);

        if (isset($validated['command'])) {
            $validated['command'] = $this->cleanCommand($validated['command']);
        }

        if (isset($validated['expression'])) {
            $parts = explode(' ', $validated['expression']);
            if (count($parts) !== 5 && !str_starts_with($validated['expression'], '@')) {
                return $this->errorResponse('Format ekspresi cron tidak valid. Harus memiliki 5 bagian (misal: */15 * * * *).', null, 422);
            }
        }

        $cronJob->update($validated);

        return $this->successResponse($cronJob, 'Cron job berhasil diupdate.');
    }

    /**
     * Remove the specified cron job from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $cronJob = CronJob::find($id);

        if (!$cronJob) {
            return $this->errorResponse('Cron job tidak ditemukan.', null, 404);
        }

        $cronJob->logs()->delete();
        $cronJob->delete();

        return $this->successResponse(null, 'Cron job berhasil dihapus.');
    }

    /**
     * Run the specified cron job manually.
     */
    public function run(int $id): JsonResponse
    {
        $cronJob = CronJob::find($id);

        if (!$cronJob) {
            return $this->errorResponse('Cron job tidak ditemukan.', null, 404);
        }

        $startTime = now();
        $log = CronJobLog::create([
            'cron_job_id' => $cronJob->id,
            'run_at' => $startTime,
            'status' => 'running',
        ]);

        try {
            $outputBuffer = new BufferedOutput();
            $command = $this->cleanCommand($cronJob->command);
            Artisan::call($command, [], $outputBuffer);
            $output = $outputBuffer->fetch();

            $cronJob->update([
                'last_run_at' => now(),
                'last_run_status' => 'success'
            ]);

            $log->update([
                'finished_at' => now(),
                'status' => 'success',
                'duration_seconds' => (int) abs(now()->timestamp - $startTime->timestamp),
                'message' => $output ?: 'Executed successfully via manual trigger.'
            ]);

            return $this->successResponse($cronJob, 'Cron Job berhasil dijalankan.');
        } catch (\Throwable $e) {
            $cronJob->update([
                'last_run_at' => now(),
                'last_run_status' => 'failed'
            ]);

            $log->update([
                'finished_at' => now(),
                'status' => 'failed',
                'duration_seconds' => (int) abs(now()->timestamp - $startTime->timestamp),
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse('Cron Job gagal dijalankan: ' . $e->getMessage());
        }
    }

    /**
     * Display the execution logs of a specific cron job.
     */
    public function logs(int $id): JsonResponse
    {
        $cronJob = CronJob::find($id);

        if (!$cronJob) {
            return $this->errorResponse('Cron job tidak ditemukan.', null, 404);
        }

        $logs = CronJobLog::where('cron_job_id', $id)
            ->orderBy('run_at', 'desc')
            ->limit(100)
            ->get();

        return $this->successResponse($logs, 'Riwayat log cron job berhasil diambil.');
    }

    /**
     * Display real-time execution logs across all cron jobs with filters and pagination.
     */
    public function allLogs(Request $request): JsonResponse
    {
        $query = CronJobLog::with('cronJob');

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('cron_job_id') && !empty($request->input('cron_job_id'))) {
            $query->where('cron_job_id', $request->input('cron_job_id'));
        }

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', $search)
                  ->orWhereHas('cronJob', function ($cq) use ($search) {
                      $cq->where('name', 'like', $search)
                         ->orWhere('command', 'like', $search);
                  });
            });
        }

        $perPage = (int)$request->query('per_page', 20);
        $logs = $query->orderBy('run_at', 'desc')->paginate($perPage);

        return $this->successResponse($logs, 'Daftar monitoring log eksekusi cron job berhasil diambil.');
    }
}

