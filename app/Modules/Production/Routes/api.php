<?php

use App\Modules\Production\Controllers\ProductionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/production')->middleware('auth:sanctum')->group(function () {
    Route::get('/resources', [ProductionController::class, 'indexResources']);
    Route::post('/resources/sync', [ProductionController::class, 'syncResources']);
    Route::get('/items', [ProductionController::class, 'indexItems']);
    Route::post('/items/sync', [ProductionController::class, 'syncItems']);

    // Bill of Material CRUD
    Route::get('/boms', [ProductionController::class, 'indexBoms']);
    Route::get('/boms/{id}', [ProductionController::class, 'showBom']);
    Route::post('/boms', [ProductionController::class, 'storeBom']);
    Route::put('/boms/{id}', [ProductionController::class, 'updateBom']);
    Route::delete('/boms/{id}', [ProductionController::class, 'destroyBom']);

    // Production Order CRUD
    Route::get('/orders', [ProductionController::class, 'indexOrders']);
    Route::get('/orders/{id}', [ProductionController::class, 'showOrder']);
    Route::post('/orders', [ProductionController::class, 'storeOrder']);
    Route::put('/orders/{id}', [ProductionController::class, 'updateOrder']);
    Route::delete('/orders/{id}', [ProductionController::class, 'destroyOrder']);
});
