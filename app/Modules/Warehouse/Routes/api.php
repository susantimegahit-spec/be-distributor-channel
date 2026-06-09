<?php

use App\Modules\Warehouse\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/warehouses')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [WarehouseController::class, 'index']);
    Route::post('/sync', [WarehouseController::class, 'sync']);
});
