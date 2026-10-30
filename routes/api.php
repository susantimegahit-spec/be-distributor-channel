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
Route::match(['get', 'post'], '/GetListByOwnerId', [\App\Modules\MasterApproval\Controllers\MasterApprovalController::class, 'getOwnerDocuments'])->middleware('auth:sanctum');
Route::match(['get', 'post'], '/GetDetailByObjectCode', [\App\Modules\MasterApproval\Controllers\MasterApprovalController::class, 'getDocumentDetail'])->middleware('auth:sanctum');
Route::match(['get', 'post'], '/GetOriginator', [\App\Modules\MasterApproval\Controllers\MasterApprovalController::class, 'getOriginators'])->middleware('auth:sanctum');
Route::match(['get', 'post'], '/GetDepartment', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'getDepartments']);
Route::match(['get', 'post'], '/GetDraftPR', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'getDraftPR']);
Route::match(['get', 'post'], '/GetListPRGenerated', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'getListPRGenerated']);
Route::match(['get', 'post'], '/GetDetailPR', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'getDetailPR']);
Route::match(['get', 'post'], '/ClosePR', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'closePR']);
Route::match(['get', 'post'], '/CancelPR', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'cancelPR']);

Route::prefix('dashboard-layouts')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [\App\Modules\Dashboard\Controllers\DashboardLayoutController::class, 'getMyLayout']);
    Route::get('/roles/{roleId}', [\App\Modules\Dashboard\Controllers\DashboardLayoutController::class, 'getByRole']);
    Route::put('/roles/{roleId}', [\App\Modules\Dashboard\Controllers\DashboardLayoutController::class, 'saveByRole']);
    Route::delete('/roles/{roleId}', [\App\Modules\Dashboard\Controllers\DashboardLayoutController::class, 'resetByRole']);
});
