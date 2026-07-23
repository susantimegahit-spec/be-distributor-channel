<?php

use App\Modules\Ekspedisi\Controllers\ExpeditionController;
use App\Modules\Ekspedisi\Controllers\ExpeditionRateController;
use App\Modules\Ekspedisi\Controllers\WilayahController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/ekspedisi')->middleware('auth:sanctum')->group(function () {
    // Master Wilayah
    Route::prefix('wilayah')->group(function () {
        Route::get('/provinces', [WilayahController::class, 'getProvinces']);
        Route::get('/regencies', [WilayahController::class, 'getRegencies']);
        Route::get('/districts', [WilayahController::class, 'getDistricts']);
        Route::get('/villages', [WilayahController::class, 'getVillages']);
    });

    // Master Ekspedisi (Expedition Vendors)
    Route::apiResource('expeditions', ExpeditionController::class);

    // Master Tarif Ekspedisi (Expedition Rates)
    Route::apiResource('rates', ExpeditionRateController::class);
});
