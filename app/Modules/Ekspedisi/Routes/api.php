<?php

use App\Modules\Ekspedisi\Controllers\WilayahController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/ekspedisi/wilayah')->middleware('auth:sanctum')->group(function () {
    Route::get('/provinces', [WilayahController::class, 'getProvinces']);
    Route::get('/regencies', [WilayahController::class, 'getRegencies']);
    Route::get('/districts', [WilayahController::class, 'getDistricts']);
    Route::get('/villages', [WilayahController::class, 'getVillages']);
});
