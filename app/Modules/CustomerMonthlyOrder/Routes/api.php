<?php

use App\Modules\CustomerMonthlyOrder\Controllers\CustomerMonthlyOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/customer-monthly-orders')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CustomerMonthlyOrderController::class, 'index']);
    Route::get('/{id}', [CustomerMonthlyOrderController::class, 'show']);
    Route::post('/', [CustomerMonthlyOrderController::class, 'store']);
    Route::match(['put', 'post'], '/{id}', [CustomerMonthlyOrderController::class, 'update']);
    Route::delete('/{id}', [CustomerMonthlyOrderController::class, 'destroy']);
    Route::post('/{id}/post-to-so', [CustomerMonthlyOrderController::class, 'postToSalesOrder']);
});
