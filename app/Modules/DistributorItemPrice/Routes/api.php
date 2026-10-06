<?php

use App\Modules\DistributorItemPrice\Controllers\DistributorItemPriceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/distributor-item-prices')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [DistributorItemPriceController::class, 'index']);
    Route::post('/', [DistributorItemPriceController::class, 'store']);
    Route::get('/{id}', [DistributorItemPriceController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [DistributorItemPriceController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [DistributorItemPriceController::class, 'destroy'])->whereNumber('id');
});
