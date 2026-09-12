<?php

use App\Modules\Ekspedisi\Controllers\ExpeditionController;
use App\Modules\Ekspedisi\Controllers\ExpeditionRateController;
use App\Modules\Ekspedisi\Controllers\WilayahController;
use App\Modules\Ekspedisi\Controllers\WarehouseOriginController;
use App\Modules\Ekspedisi\Controllers\SapEkspedisiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/ekspedisi')->middleware('auth:sanctum')->group(function () {
    // SAP Master Logistics Endpoints (getNamaEkspedisi, getNamaChecker, getKendaraan, getSopir)
    Route::match(['get', 'post'], 'nama-ekspedisi', [SapEkspedisiController::class, 'getNamaEkspedisi']);
    Route::match(['get', 'post'], 'nama-checker', [SapEkspedisiController::class, 'getNamaChecker']);
    Route::match(['get', 'post'], 'kendaraan', [SapEkspedisiController::class, 'getKendaraan']);
    Route::match(['get', 'post'], 'sopir', [SapEkspedisiController::class, 'getSopir']);
    // Master Wilayah
    Route::prefix('wilayah')->group(function () {
        Route::get('/provinces', [WilayahController::class, 'getProvinces']);
        Route::get('/regencies', [WilayahController::class, 'getRegencies']);
        Route::get('/districts', [WilayahController::class, 'getDistricts']);
        Route::get('/villages', [WilayahController::class, 'getVillages']);
    });

    // Upload Master Ekspedisi & Rates & Origins (harus dideklarasikan sebelum apiResource agar tidak bentrok dengan {id})
    Route::post('expeditions/upload', [ExpeditionController::class, 'upload']);
    Route::post('rates/upload', [ExpeditionRateController::class, 'upload']);
    Route::post('origins/upload', [WarehouseOriginController::class, 'upload']);

    // Master Ekspedisi (Expedition Vendors)
    Route::apiResource('expeditions', ExpeditionController::class);

    // Master Tarif Ekspedisi (Expedition Rates) Approval & Ranking
    Route::get('rates/rank', [ExpeditionRateController::class, 'rank']);
    Route::post('rates/bulk-approve', [ExpeditionRateController::class, 'bulkApprove']);
    Route::post('rates/{id}/approve', [ExpeditionRateController::class, 'approve']);
    Route::post('rates/{id}/reject', [ExpeditionRateController::class, 'reject']);
    Route::apiResource('rates', ExpeditionRateController::class);

    // Master Origin/Gudang Asal (Warehouse Origins)
    Route::apiResource('origins', WarehouseOriginController::class);
});
