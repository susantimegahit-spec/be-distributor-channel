<?php

use App\Modules\Production\Controllers\ProductionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/production')->middleware('auth:sanctum')->group(function () {
    Route::get('/resources', [ProductionController::class, 'indexResources']);
    Route::post('/resources/sync', [ProductionController::class, 'syncResources']);
    Route::get('/items', [ProductionController::class, 'indexItems']);
    Route::post('/items/sync', [ProductionController::class, 'syncItems']);

    // Bill of Material CRUD & Import
    Route::get('/boms', [ProductionController::class, 'indexBoms']);
    Route::post('/boms/import', [ProductionController::class, 'importBoms']);
    Route::post('/boms/upload-excel', [ProductionController::class, 'importBoms']);
    Route::post('/boms/import-excel', [ProductionController::class, 'importBoms']);
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
    Route::get('/orders/sap/{id}', [ProductionController::class, 'getPdoByIdSap']);
    Route::post('/orders/sap/detail', [ProductionController::class, 'getPdoByIdSap']);
    Route::get('/get-pdo-by-id', [ProductionController::class, 'getPdoByIdSap']);
    Route::post('/get-pdo-by-id', [ProductionController::class, 'getPdoByIdSap']);
    Route::get('/orders/{id}', [ProductionController::class, 'showOrder']);
    Route::post('/orders', [ProductionController::class, 'storeOrder']);
    Route::post('/orders/sap', [ProductionController::class, 'addPdoSap']);
    Route::post('/add-pdo-sap', [ProductionController::class, 'addPdoSap']);
    Route::post('/orders/sap/cancel', [ProductionController::class, 'cancelPdoSap']);
    Route::post('/cancel-pdo-sap', [ProductionController::class, 'cancelPdoSap']);
    Route::post('/cancelpdo', [ProductionController::class, 'cancelPdoSap']);
    Route::post('/close-pdo-sap', [ProductionController::class, 'closePdoSap']);
    Route::post('/inventory-transfer/sap/cancel', [ProductionController::class, 'cancelItSap']);
    Route::post('/cancel-it-sap', [ProductionController::class, 'cancelItSap']);
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
    Route::post('/add-receipt-prod-sap', [ProductionController::class, 'addReceiptProdSap']);

    // Issue for Production from SAP
    Route::get('/issues/sap-list', [ProductionController::class, 'getListIssueProdSap']);
    Route::post('/issues/sap-list', [ProductionController::class, 'getListIssueProdSap']);
    Route::get('/get-list-issue-prod', [ProductionController::class, 'getListIssueProdSap']);
    Route::post('/get-list-issue-prod', [ProductionController::class, 'getListIssueProdSap']);
    Route::get('/issues/sap/{id}', [ProductionController::class, 'getIssueProdByIdSap']);
    Route::post('/issues/sap/detail', [ProductionController::class, 'getIssueProdByIdSap']);
    Route::get('/get-issue-prod-by-id', [ProductionController::class, 'getIssueProdByIdSap']);
    Route::post('/get-issue-prod-by-id', [ProductionController::class, 'getIssueProdByIdSap']);
    Route::post('/add-issue-prod-sap', [ProductionController::class, 'addIssueProdSap']);

    // Master Units from SAP
    Route::get('/get-unit', [ProductionController::class, 'getUnitsSap']);

    // Warehouse Stock by Item from SAP
    Route::match(['get', 'post'], '/stock-by-item', [ProductionController::class, 'getStockByItem']);
    Route::post('/get-stok-by-item', [ProductionController::class, 'getStockByItem']);
    Route::post('/get-stock-by-item', [ProductionController::class, 'getStockByItem']);

    // Change Product CRUD & SAP Integration (/api/AddCP)
    Route::get('/change-products', [ProductionController::class, 'indexChangeProducts']);
    Route::get('/change-products/{id}', [ProductionController::class, 'showChangeProduct']);
    Route::post('/change-products', [ProductionController::class, 'storeChangeProduct']);
    Route::put('/change-products/{id}', [ProductionController::class, 'updateChangeProduct']);
    Route::delete('/change-products/{id}', [ProductionController::class, 'destroyChangeProduct']);
    Route::post('/change-products/{id}/post', [ProductionController::class, 'postChangeProductSap']);
    Route::post('/change-products/sap', [ProductionController::class, 'postChangeProductSap']);
    Route::post('/add-cp', [ProductionController::class, 'postChangeProductSap']);
});

