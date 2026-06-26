<?php

use App\Modules\SalesOrder\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/sales-orders')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [SalesOrderController::class, 'index']);
    Route::get('/max-discount', [SalesOrderController::class, 'getMaxDiscount']);
    Route::get('/series', [SalesOrderController::class, 'getSeries']);
    Route::post('/', [SalesOrderController::class, 'store']);
    Route::get('/{id}', [SalesOrderController::class, 'show']);
    Route::put('/{id}', [SalesOrderController::class, 'update']);
    Route::delete('/{id}', [SalesOrderController::class, 'destroy']);
    Route::post('/post-sap', [SalesOrderController::class, 'postNewToSap']);
    Route::post('/{id}/post-sap', [SalesOrderController::class, 'postToSap']);

    // Workflow Approval routes
    Route::post('/{id}/save-discounts', [SalesOrderController::class, 'saveDiscounts']);
    Route::get('/{id}/pdf', [SalesOrderController::class, 'downloadPdf']);
});

// Public Signed Routes for Email Quick Actions
Route::get('v1/sales-orders/{id}/email-action', [SalesOrderController::class, 'emailAction'])
    ->name('sales-orders.email-action');
Route::post('v1/sales-orders/{id}/email-reject', [SalesOrderController::class, 'emailRejectPost'])
    ->name('sales-orders.email-reject-post');
