<?php

use Illuminate\Support\Facades\Route;
use App\Modules\TaskManagement\Controllers\TaskController;
use App\Modules\TaskManagement\Controllers\HierarchyController;
use App\Modules\TaskManagement\Controllers\ChecklistController;
use App\Modules\TaskManagement\Controllers\TimeTrackingController;
use App\Modules\TaskManagement\Controllers\CommentController;
use App\Modules\TaskManagement\Controllers\MasterDataController;

Route::prefix('task-management')->middleware('auth:sanctum')->group(function () {
    // 1. Task Core Endpoints
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/metrics', [TaskController::class, 'metrics']);
    Route::get('/tasks/{id}', [TaskController::class, 'show']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    Route::post('/tasks/{id}/status', [TaskController::class, 'updateStatus']);

    // 2. Hierarchy Endpoints (Workspaces, Spaces, Folders, Lists)
    Route::get('/workspaces', [HierarchyController::class, 'getWorkspaces']);
    Route::get('/workspaces/{id}', [HierarchyController::class, 'getWorkspace']);
    Route::post('/workspaces', [HierarchyController::class, 'createWorkspace']);

    Route::get('/spaces', [HierarchyController::class, 'getSpaces']);
    Route::get('/spaces/{id}', [HierarchyController::class, 'getSpace']);
    Route::post('/spaces', [HierarchyController::class, 'createSpace']);

    Route::get('/folders', [HierarchyController::class, 'getFolders']);
    Route::post('/folders', [HierarchyController::class, 'createFolder']);

    Route::get('/lists', [HierarchyController::class, 'getLists']);
    Route::post('/lists', [HierarchyController::class, 'createList']);

    // 3. Checklist Endpoints
    Route::post('/checklists', [ChecklistController::class, 'storeChecklist']);
    Route::delete('/checklists/{id}', [ChecklistController::class, 'deleteChecklist']);
    Route::post('/checklists/{id}/items', [ChecklistController::class, 'storeItem']);
    Route::post('/checklist-items/{id}/toggle', [ChecklistController::class, 'toggleItem']);
    Route::delete('/checklist-items/{id}', [ChecklistController::class, 'destroyItem']);

    // 4. Time Tracking Endpoints
    Route::get('/time-tracking/active', [TimeTrackingController::class, 'getActive']);
    Route::post('/time-tracking/start', [TimeTrackingController::class, 'startTimer']);
    Route::post('/time-tracking/{id}/stop', [TimeTrackingController::class, 'stopTimer']);
    Route::post('/time-tracking/manual', [TimeTrackingController::class, 'logManual']);

    // 5. Comments Endpoints
    Route::get('/tasks/{taskId}/comments', [CommentController::class, 'index']);
    Route::post('/tasks/{taskId}/comments', [CommentController::class, 'store']);

    // 6. Master Data Endpoints
    Route::get('/master/statuses', [MasterDataController::class, 'getStatuses']);
    Route::get('/master/priorities', [MasterDataController::class, 'getPriorities']);
    Route::get('/master/task-types', [MasterDataController::class, 'getTaskTypes']);
    Route::get('/master/tags', [MasterDataController::class, 'getTags']);
    Route::get('/master/departments', [MasterDataController::class, 'getDepartments']);
    Route::get('/master/employees', [MasterDataController::class, 'getEmployees']);
});
