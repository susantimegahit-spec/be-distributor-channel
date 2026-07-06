<?php

use App\Modules\Dashboard\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/dashboard')->middleware('auth:sanctum')->group(function () {
    Route::get('/admin/summary', [DashboardController::class, 'adminSummary']);
    Route::get('/admin/charts', [DashboardController::class, 'adminCharts']);
    Route::get('/distributor/summary', [DashboardController::class, 'distributorSummary']);
    Route::get('/distributor/charts', [DashboardController::class, 'distributorCharts']);
});
