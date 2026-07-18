<?php

use App\Modules\SalesDashboard\Controllers\SalesDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/sales-dashboard')->middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [SalesDashboardController::class, 'upload']);
    Route::get('/raw-data', [SalesDashboardController::class, 'index']);
    Route::delete('/bulk', [SalesDashboardController::class, 'bulkDelete']);
    Route::post('/sync-so-do', [SalesDashboardController::class, 'syncActuals']);
    Route::get('/comparison', [SalesDashboardController::class, 'comparison']);
    Route::put('/{id}', [SalesDashboardController::class, 'update']);
    Route::delete('/{id}', [SalesDashboardController::class, 'destroy']);
});
