<?php

use App\Modules\CronJob\Controllers\CronJobController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/cron-jobs')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CronJobController::class, 'index']);
    Route::post('/', [CronJobController::class, 'store']);
    Route::get('/logs/all', [CronJobController::class, 'allLogs']);
    Route::get('/{id}', [CronJobController::class, 'show']);
    Route::put('/{id}', [CronJobController::class, 'update']);
    Route::delete('/{id}', [CronJobController::class, 'destroy']);
    Route::post('/{id}/run', [CronJobController::class, 'run']);
    Route::get('/{id}/logs', [CronJobController::class, 'logs']);
});

