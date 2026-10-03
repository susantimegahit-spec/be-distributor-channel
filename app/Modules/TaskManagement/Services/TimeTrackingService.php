<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\TmTask;
use App\Models\TmTaskTimeTracking;
use App\Modules\TaskManagement\Repositories\TaskRepositoryInterface;
use Carbon\Carbon;
use Exception;

class TimeTrackingService
{
    protected TaskRepositoryInterface $taskRepo;

    public function __construct(TaskRepositoryInterface $taskRepo)
    {
        $this->taskRepo = $taskRepo;
    }

    public function startTimer(int $taskId, int $employeeId, ?string $note = null): TmTaskTimeTracking
    {
        // Stop any active timer for this employee first
        $activeTimer = TmTaskTimeTracking::where('employee_id', $employeeId)
            ->whereNull('end_time')
            ->first();

        if ($activeTimer) {
            $this->stopTimer($activeTimer->id);
        }

        return TmTaskTimeTracking::create([
            'task_id'     => $taskId,
            'employee_id' => $employeeId,
            'start_time'  => now(),
            'note'        => $note,
        ]);
    }

    public function stopTimer(int $trackingId): TmTaskTimeTracking
    {
        $tracking = TmTaskTimeTracking::findOrFail($trackingId);
        if ($tracking->end_time) {
            return $tracking;
        }

        $now = now();
        $startTime = Carbon::parse($tracking->start_time);
        $durationMinutes = max(1, $startTime->diffInMinutes($now));

        $tracking->update([
            'end_time'         => $now,
            'duration_minutes' => $durationMinutes,
        ]);

        $this->recalculateActualHours($tracking->task_id);

        return $tracking;
    }

    public function logManualTime(int $taskId, int $employeeId, int $durationMinutes, ?string $note = null, ?string $startTime = null): TmTaskTimeTracking
    {
        $start = $startTime ? Carbon::parse($startTime) : now()->subMinutes($durationMinutes);
        $end = (clone $start)->addMinutes($durationMinutes);

        $tracking = TmTaskTimeTracking::create([
            'task_id'          => $taskId,
            'employee_id'      => $employeeId,
            'start_time'       => $start,
            'end_time'         => $end,
            'duration_minutes' => $durationMinutes,
            'note'             => $note,
        ]);

        $this->recalculateActualHours($taskId);

        return $tracking;
    }

    public function getActiveTimer(int $employeeId): ?TmTaskTimeTracking
    {
        return TmTaskTimeTracking::with('task:id,task_code,title')
            ->where('employee_id', $employeeId)
            ->whereNull('end_time')
            ->first();
    }

    protected function recalculateActualHours(int $taskId): void
    {
        $totalMinutes = TmTaskTimeTracking::where('task_id', $taskId)->sum('duration_minutes');
        $actualHours = round($totalMinutes / 60, 2);

        $task = TmTask::find($taskId);
        if ($task) {
            $task->update(['actual_hours' => $actualHours]);
        }
    }
}
