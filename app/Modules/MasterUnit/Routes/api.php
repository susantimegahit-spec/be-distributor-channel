<?php

use App\Modules\MasterUnit\Controllers\MasterUnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/master-units')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [MasterUnitController::class, 'index']);
    Route::post('/', [MasterUnitController::class, 'store']);
    Route::get('/{id}', [MasterUnitController::class, 'show']);
    Route::put('/{id}', [MasterUnitController::class, 'update']);
    Route::delete('/{id}', [MasterUnitController::class, 'destroy']);
});
