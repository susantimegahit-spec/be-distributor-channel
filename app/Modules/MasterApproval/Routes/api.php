<?php

use App\Modules\MasterApproval\Controllers\MasterApprovalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/master-approvals')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [MasterApprovalController::class, 'index']);
    Route::match(['get', 'post'], '/stages', [MasterApprovalController::class, 'getStages']);
    Route::match(['get', 'post'], '/originators', [MasterApprovalController::class, 'getOriginators']);
    Route::match(['get', 'post'], '/approvals', [MasterApprovalController::class, 'getApprovals']);
    Route::match(['get', 'post'], '/by-owner', [MasterApprovalController::class, 'getOwnerDocuments']);
    Route::match(['get', 'post'], '/detail-by-object', [MasterApprovalController::class, 'getDocumentDetail']);
    Route::post('/approve-sap', [MasterApprovalController::class, 'approveSap']);
});


