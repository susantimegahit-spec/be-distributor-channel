<?php

use App\Modules\Distributor\Controllers\DistributorController;
use App\Modules\Distributor\Controllers\SyncAllController;
use App\Modules\Distributor\Controllers\CustomerShiptoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/distributors')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [DistributorController::class, 'index']);
    Route::post('/sync', [DistributorController::class, 'sync']);
    Route::get('/addresses', [DistributorController::class, 'getAddresses']);
    Route::get('/ocr-codes', [DistributorController::class, 'getOcrCodes']);
    Route::post('/ocr-codes/sync', [DistributorController::class, 'syncOcrCodes']);
    Route::get('/shiptos', [CustomerShiptoController::class, 'index']);
    Route::post('/shiptos/sync', [CustomerShiptoController::class, 'sync']);
    Route::get('/{id}', [DistributorController::class, 'show'])->whereNumber('id');
});

Route::prefix('v1/sync')->middleware('auth:sanctum')->group(function () {
    Route::post('/all', [SyncAllController::class, 'syncAll']);
});
