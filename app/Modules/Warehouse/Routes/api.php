<?php

use App\Modules\Warehouse\Controllers\WarehouseController;
use App\Modules\Warehouse\Controllers\InventoryTransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/warehouses')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [WarehouseController::class, 'index']);
    Route::post('/', [WarehouseController::class, 'store']);
    Route::post('/sync', [WarehouseController::class, 'sync']);
    Route::get('/inventory-transfer', [InventoryTransferController::class, 'listIT']);
    Route::post('/inventory-transfer/get-by-id', [InventoryTransferController::class, 'getITbyId']);
    Route::post('/inventory-transfer', [InventoryTransferController::class, 'store']);
    Route::post('/inventory-transfer/cancel', [InventoryTransferController::class, 'cancel']);
    Route::post('/search-bin', [InventoryTransferController::class, 'searchBin']);
    Route::post('/search-qty-bin', [InventoryTransferController::class, 'searchQtyBin']);
    Route::get('/{id}', [WarehouseController::class, 'show']);
    Route::put('/{id}', [WarehouseController::class, 'update']);
    Route::delete('/{id}', [WarehouseController::class, 'destroy']);
});
