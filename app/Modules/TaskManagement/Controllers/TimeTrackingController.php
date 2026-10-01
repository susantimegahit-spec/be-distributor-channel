<?php

namespace App\Modules\TaskManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Services\TimeTrackingService;
use App\Models\HrisEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class TimeTrackingController extends Controller
{
    protected TimeTrackingService $timeService;

    public function __construct(TimeTrackingService $timeService)
    {
        $this->timeService = $timeService;
    }

    protected function getEmployeeId(Request $request): int
    {
        $user = $request->user();
        if ($user) {
            $employee = HrisEmployee::where('user_id', $user->id)->first();
            if ($employee) {
                return $employee->id;
            }
        }
        $first = HrisEmployee::first();
        return $first ? $first->id : 1;
    }

    public function startTimer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|integer',
            'note'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $employeeId = $this->getEmployeeId($request);
        $entry = $this->timeService->startTimer(
            $request->input('task_id'),
            $employeeId,
            $request->input('note')
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Timer started successfully',
            'data'    => $entry,
        ], 201);
    }

    public function stopTimer(int $id): JsonResponse
    {
        $entry = $this->timeService->stopTimer($id);
        return response()->json([
            'status'  => 'success',
            'message' => 'Timer stopped successfully',
            'data'    => $entry,
        ]);
    }

    public function logManual(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id'          => 'required|integer',
            'duration_minutes' => 'required|integer|min:1',
            'note'             => 'nullable|string',
            'start_time'       => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $employeeId = $this->getEmployeeId($request);
        $entry = $this->timeService->logManualTime(
            $request->input('task_id'),
            $employeeId,
            $request->input('duration_minutes'),
            $request->input('note'),
            $request->input('start_time')
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Time logged successfully',
            'data'    => $entry,
        ], 201);
    }

    public function getActive(Request $request): JsonResponse
    {
        $employeeId = $this->getEmployeeId($request);
        $activeTimer = $this->timeService->getActiveTimer($employeeId);

        return response()->json([
            'status'  => 'success',
            'data'    => $activeTimer,
        ]);
    }
}
