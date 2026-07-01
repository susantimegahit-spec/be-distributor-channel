<?php

use App\Modules\CronJob\Controllers\CronJobController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/cron-jobs')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CronJobController::class, 'index']);
    Route::put('/{id}', [CronJobController::class, 'update']);
    Route::post('/{id}/run', [CronJobController::class, 'run']);
    Route::get('/{id}/logs', [CronJobController::class, 'logs']);
});
