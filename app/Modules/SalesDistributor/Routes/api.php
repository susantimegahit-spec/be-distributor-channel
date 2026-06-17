<?php

use App\Modules\SalesDistributor\Controllers\SalesDistributorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/sales-distributors')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [SalesDistributorController::class, 'index']);
    Route::post('/', [SalesDistributorController::class, 'store']);
    Route::get('/{id}', [SalesDistributorController::class, 'show']);
    Route::put('/{id}', [SalesDistributorController::class, 'update']);
    Route::delete('/{id}', [SalesDistributorController::class, 'destroy']);
});
