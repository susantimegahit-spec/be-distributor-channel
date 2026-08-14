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
    Route::get('/orders/sap-list', [ProductionController::class, 'getListPdoSap']);
    Route::post('/orders/sap-list', [ProductionController::class, 'getListPdoSap']);
    Route::get('/get-list-pdo-sap', [ProductionController::class, 'getListPdoSap']);
    Route::post('/get-list-pdo-sap', [ProductionController::class, 'getListPdoSap']);
    Route::get('/orders/{id}', [ProductionController::class, 'showOrder']);
    Route::post('/orders', [ProductionController::class, 'storeOrder']);
    Route::post('/orders/sap', [ProductionController::class, 'addPdoSap']);
    Route::post('/add-pdo-sap', [ProductionController::class, 'addPdoSap']);
    Route::put('/orders/{id}', [ProductionController::class, 'updateOrder']);
    Route::delete('/orders/{id}', [ProductionController::class, 'destroyOrder']);

    // Production Receipt from SAP
    Route::get('/receipts/sap-list', [ProductionController::class, 'getListReceiptProdSap']);
    Route::post('/receipts/sap-list', [ProductionController::class, 'getListReceiptProdSap']);
    Route::get('/get-list-receipt-prod', [ProductionController::class, 'getListReceiptProdSap']);
    Route::post('/get-list-receipt-prod', [ProductionController::class, 'getListReceiptProdSap']);
    Route::get('/receipts/sap/{id}', [ProductionController::class, 'getReceiptProdByIdSap']);
    Route::post('/receipts/sap/detail', [ProductionController::class, 'getReceiptProdByIdSap']);
    Route::get('/get-receipt-prod-by-id', [ProductionController::class, 'getReceiptProdByIdSap']);
    Route::post('/get-receipt-prod-by-id', [ProductionController::class, 'getReceiptProdByIdSap']);
});
