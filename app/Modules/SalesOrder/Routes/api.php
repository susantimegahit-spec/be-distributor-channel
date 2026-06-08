<?php

use App\Modules\SalesOrder\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/sales-orders')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [SalesOrderController::class, 'index']);
    Route::post('/', [SalesOrderController::class, 'store']);
    Route::get('/{id}', [SalesOrderController::class, 'show']);
    Route::put('/{id}', [SalesOrderController::class, 'update']);
    Route::delete('/{id}', [SalesOrderController::class, 'destroy']);
});
