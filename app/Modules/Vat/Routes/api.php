<?php

use App\Modules\Vat\Controllers\VatController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/vats')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [VatController::class, 'index']);
    Route::post('/sync', [VatController::class, 'sync']);
});
