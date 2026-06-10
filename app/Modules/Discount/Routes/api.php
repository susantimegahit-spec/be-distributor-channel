<?php

use App\Modules\Discount\Controllers\DiscountController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/discounts')->middleware('auth:sanctum')->group(function () {
    Route::post('/sap', [DiscountController::class, 'sendToSap']);
});
