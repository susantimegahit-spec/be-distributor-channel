<?php

use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Dashboard\Controllers\DashboardLayoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/dashboard')->middleware('auth:sanctum')->group(function () {
    Route::get('/admin/summary', [DashboardController::class, 'adminSummary']);
    Route::get('/admin/charts', [DashboardController::class, 'adminCharts']);
    Route::get('/distributor/summary', [DashboardController::class, 'distributorSummary']);
    Route::get('/distributor/charts', [DashboardController::class, 'distributorCharts']);
});

// Dashboard Layout routes (with v1 prefix: /api/distributor-channel/v1/dashboard-layouts/...)
Route::prefix('v1/dashboard-layouts')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [DashboardLayoutController::class, 'getMyLayout']);
    Route::get('/roles/{roleId}', [DashboardLayoutController::class, 'getByRole']);
    Route::put('/roles/{roleId}', [DashboardLayoutController::class, 'saveByRole']);
    Route::delete('/roles/{roleId}', [DashboardLayoutController::class, 'resetByRole']);
});

// Dashboard Layout routes alias (without v1 prefix: /api/distributor-channel/dashboard-layouts/...)
Route::prefix('dashboard-layouts')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [DashboardLayoutController::class, 'getMyLayout']);
    Route::get('/roles/{roleId}', [DashboardLayoutController::class, 'getByRole']);
    Route::put('/roles/{roleId}', [DashboardLayoutController::class, 'saveByRole']);
    Route::delete('/roles/{roleId}', [DashboardLayoutController::class, 'resetByRole']);
});
