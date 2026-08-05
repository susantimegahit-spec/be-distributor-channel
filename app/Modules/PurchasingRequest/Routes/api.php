<?php

use App\Modules\PurchasingRequest\Controllers\PurchaseRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/purchasing-request')->group(function () {
    Route::get('/requests', [PurchaseRequestController::class, 'index']);
    Route::post('/requests', [PurchaseRequestController::class, 'store']);
    Route::get('/requests/{id}', [PurchaseRequestController::class, 'show']);
    Route::put('/requests/{id}', [PurchaseRequestController::class, 'update']);
    Route::post('/requests/{id}/status', [PurchaseRequestController::class, 'updateStatus']);
    Route::delete('/requests/{id}', [PurchaseRequestController::class, 'destroy']);
});
