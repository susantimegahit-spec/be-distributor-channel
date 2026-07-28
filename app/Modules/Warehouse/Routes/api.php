<?php

use App\Modules\Warehouse\Controllers\WarehouseController;
use App\Modules\Warehouse\Controllers\InventoryTransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/warehouses')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [WarehouseController::class, 'index']);
    Route::post('/sync', [WarehouseController::class, 'sync']);
    Route::post('/inventory-transfer', [InventoryTransferController::class, 'store']);
    Route::post('/search-bin', [InventoryTransferController::class, 'searchBin']);
});
