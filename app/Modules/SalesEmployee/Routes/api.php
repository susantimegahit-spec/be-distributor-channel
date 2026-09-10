<?php

use App\Modules\SalesEmployee\Controllers\SalesEmployeeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/sales-employees')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [SalesEmployeeController::class, 'index']);
    Route::post('/sync', [SalesEmployeeController::class, 'sync']);
});
