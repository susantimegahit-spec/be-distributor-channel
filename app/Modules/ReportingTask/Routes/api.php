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
    Route::get('/tasks/filters', [ReportingTaskController::class, 'filterOptions']);
    Route::get('/filter-options', [ReportingTaskController::class, 'filterOptions']);

    // Reporting, Apigee & Google Looker Studio Endpoints
    Route::get('/tasks', [ReportingTaskController::class, 'index']);
    Route::get('/tasks/all', [ReportingTaskController::class, 'getAll']);
    Route::get('/tasks/summary', [ReportingTaskController::class, 'summary']);
    Route::get('/tasks/{id}', [ReportingTaskController::class, 'show']);
    Route::delete('/tasks/{id}', [ReportingTaskController::class, 'destroy']);
});
