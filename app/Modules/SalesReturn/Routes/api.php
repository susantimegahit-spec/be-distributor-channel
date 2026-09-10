<?php

use App\Modules\SalesReturn\Controllers\SalesReturnController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/sales-returns')->middleware('auth:sanctum')->group(function () {
    Route::get('/do-by-so', [SalesReturnController::class, 'getDoBySo']);
    Route::get('/', [SalesReturnController::class, 'index']);
    Route::get('/{id}', [SalesReturnController::class, 'show']);
    Route::post('/', [SalesReturnController::class, 'store']);
    Route::post('/{id}/approve', [SalesReturnController::class, 'approve']);
    Route::post('/{id}/reject', [SalesReturnController::class, 'reject']);
});
