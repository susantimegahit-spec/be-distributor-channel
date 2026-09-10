<?php

use App\Modules\PiSetting\Controllers\PiSettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/pi-settings')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PiSettingController::class, 'show']);
    Route::post('/', [PiSettingController::class, 'update']);
});
