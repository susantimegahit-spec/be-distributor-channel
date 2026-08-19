<?php

use App\Modules\ReportingTask\Controllers\ReportingTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/reporting')->group(function () {
    // Webhook from n8n (Can be public or authenticated)
    Route::post('/tasks/sync', [ReportingTaskController::class, 'sync']);
    Route::post('/clickup-tasks/sync', [ReportingTaskController::class, 'sync']);

    // Reporting & Data Studio Endpoints
    Route::get('/tasks', [ReportingTaskController::class, 'index']);
    Route::get('/tasks/summary', [ReportingTaskController::class, 'summary']);
    Route::get('/tasks/{id}', [ReportingTaskController::class, 'show']);
    Route::delete('/tasks/{id}', [ReportingTaskController::class, 'destroy']);
});
