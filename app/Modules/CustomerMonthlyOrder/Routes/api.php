<?php

use App\Http\Middleware\AuthenticateDistributorApiKey;
use App\Modules\CustomerMonthlyOrder\Controllers\CustomerMonthlyOrderController;
use App\Modules\CustomerMonthlyOrder\Controllers\ExternalCustomerMonthlyOrderController;
use Illuminate\Support\Facades\Route;

// Internal Customer Portal API Routes (Sanctum User Auth)
Route::prefix('v1/customer-monthly-orders')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CustomerMonthlyOrderController::class, 'index']);
    Route::get('/{id}', [CustomerMonthlyOrderController::class, 'show']);
    Route::post('/', [CustomerMonthlyOrderController::class, 'store']);
    Route::match(['put', 'post'], '/{id}', [CustomerMonthlyOrderController::class, 'update']);
    Route::delete('/{id}', [CustomerMonthlyOrderController::class, 'destroy']);
    Route::post('/{id}/post-to-so', [CustomerMonthlyOrderController::class, 'postToSalesOrder']);
});

// External B2B API Routes for Distributor Integration (API Key Auth)
Route::prefix('v1/external/customer-monthly-orders')
    ->middleware([AuthenticateDistributorApiKey::class, 'throttle:60,1'])
    ->group(function () {
        Route::post('/', [ExternalCustomerMonthlyOrderController::class, 'store']);
        Route::get('/distributors', [ExternalCustomerMonthlyOrderController::class, 'getDistributors']);
        Route::get('/items', [ExternalCustomerMonthlyOrderController::class, 'getItems']);
        Route::get('/{refNo}', [ExternalCustomerMonthlyOrderController::class, 'show']);
    });
