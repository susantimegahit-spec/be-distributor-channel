<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::match(['get', 'post'], '/getstages', [\App\Modules\MasterApproval\Controllers\MasterApprovalController::class, 'getStages'])->middleware('auth:sanctum');
Route::match(['get', 'post'], '/getapproval', [\App\Modules\MasterApproval\Controllers\MasterApprovalController::class, 'getApprovals'])->middleware('auth:sanctum');
Route::post('/approvesap', [\App\Modules\MasterApproval\Controllers\MasterApprovalController::class, 'approveSap'])->middleware('auth:sanctum');
Route::post('/ApproveSAP', [\App\Modules\MasterApproval\Controllers\MasterApprovalController::class, 'approveSap'])->middleware('auth:sanctum');

