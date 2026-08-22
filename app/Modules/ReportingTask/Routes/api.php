<?php

use App\Modules\ReportingTask\Controllers\ReportingTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/reporting')->middleware('auth.reporting')->group(function () {
    // Webhook from n8n (Can be called with or without auth depending on env config)
    Route::post('/tasks/sync', [ReportingTaskController::class, 'sync']);
    Route::post('/clickup-tasks/sync', [ReportingTaskController::class, 'sync']);

    // Frontend Dashboard & Analytics Endpoints
    Route::get('/dashboard', [ReportingTaskController::class, 'dashboard']);
    Route::get('/tasks/dashboard', [ReportingTaskController::class, 'dashboard']);

    // Filter Endpoints for Dropdowns (All-in-one & Individual)
    Route::get('/filters', [ReportingTaskController::class, 'filterOptions']);
    Route::get('/filter-options', [ReportingTaskController::class, 'filterOptions']);
    Route::get('/tasks/filters', [ReportingTaskController::class, 'filterOptions']);
    Route::get('/filters/spaces', [ReportingTaskController::class, 'getSpaces']);
    Route::get('/filters/folders', [ReportingTaskController::class, 'getFolders']);
    Route::get('/filters/lists', [ReportingTaskController::class, 'getLists']);
    Route::get('/filters/assignees', [ReportingTaskController::class, 'getAssignees']);
    Route::get('/filters/statuses', [ReportingTaskController::class, 'getStatuses']);
    Route::get('/filters/priorities', [ReportingTaskController::class, 'getPriorities']);
    Route::get('/filters/task-types', [ReportingTaskController::class, 'getTaskTypes']);
    Route::get('/filters/timelines', [ReportingTaskController::class, 'getTimelines']);

    // Reporting, Apigee & Google Looker Studio Endpoints
    Route::get('/tasks', [ReportingTaskController::class, 'index']);
    Route::get('/tasks/all', [ReportingTaskController::class, 'getAll']);
    Route::get('/tasks/summary', [ReportingTaskController::class, 'summary']);
    Route::get('/tasks/{id}', [ReportingTaskController::class, 'show']);
    Route::delete('/tasks/{id}', [ReportingTaskController::class, 'destroy']);
});
