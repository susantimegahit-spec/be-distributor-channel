<?php

use App\Modules\Distributor\Controllers\DistributorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/distributors')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [DistributorController::class, 'index']);
    Route::post('/sync', [DistributorController::class, 'sync']);
    Route::get('/addresses', [DistributorController::class, 'getAddresses']);
    Route::get('/ocr-codes', [DistributorController::class, 'getOcrCodes']);
    Route::get('/{id}', [DistributorController::class, 'show'])->whereNumber('id');
});
