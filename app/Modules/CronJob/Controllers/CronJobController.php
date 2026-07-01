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
    public function index(): JsonResponse
    {
        $cronJobs = CronJob::orderBy('name', 'asc')->get();
        return $this->successResponse($cronJobs, 'Daftar cron job berhasil diambil.');
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
            'expression' => 'required|string',
            'is_active' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        // Simple validation for cron expression format (must have 5 fields or be a predefined string like @daily)
        $parts = explode(' ', $validated['expression']);
        if (count($parts) !== 5 && !str_starts_with($validated['expression'], '@')) {
            return $this->errorResponse('Format ekspresi cron tidak valid. Harus memiliki 5 bagian (misal: */15 * * * *).');
        }

        $cronJob->update($validated);

        return $this->successResponse($cronJob, 'Cron job berhasil diupdate.');
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
            Artisan::call($cronJob->command, [], $outputBuffer);
            $output = $outputBuffer->fetch();

            $cronJob->update([
                'last_run_at' => now(),
                'last_run_status' => 'success'
            ]);

            $log->update([
                'finished_at' => now(),
                'status' => 'success',
                'duration_seconds' => now()->diffInSeconds($startTime),
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
                'duration_seconds' => now()->diffInSeconds($startTime),
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse('Cron Job gagal dijalankan: ' . $e->getMessage());
        }
    }

    /**
     * Display the execution logs of a cron job.
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
}
