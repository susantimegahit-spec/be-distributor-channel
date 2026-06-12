<?php

use App\Modules\Claim\Controllers\ClaimController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/claims')->middleware('auth:sanctum')->group(function () {
    Route::get('/template-excel', [ClaimController::class, 'downloadTemplate']);
});
