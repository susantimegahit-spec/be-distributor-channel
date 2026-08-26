<?php

use App\Modules\MasterApproval\Controllers\MasterApprovalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/master-approvals')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [MasterApprovalController::class, 'index']);
    Route::match(['get', 'post'], '/stages', [MasterApprovalController::class, 'getStages']);
    Route::match(['get', 'post'], '/getstages', [MasterApprovalController::class, 'getStages']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::match(['get', 'post'], 'v1/stages', [MasterApprovalController::class, 'getStages']);
    Route::match(['get', 'post'], 'v1/getstages', [MasterApprovalController::class, 'getStages']);
});

