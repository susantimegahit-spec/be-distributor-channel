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

// Purchasing Quotations & Offers (RFQ)
Route::prefix('v1/purchasing-request')->group(function () {
    Route::get('/quotations', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'index']);
    Route::post('/quotations', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'store']);
    Route::get('/quotations/{id}', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'show']);
    Route::put('/quotations/{id}', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'update']);
    Route::delete('/quotations/{id}', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'destroy']);
    Route::get('/quotations/{quotationId}/offers', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'getQuotationOffers']);

    Route::get('/quotation-offers', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'listOffers']);
    Route::get('/quotation-offers/{id}', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'showOffer']);
    Route::put('/quotation-offers/{id}', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'updateOffer']);
    Route::delete('/quotation-offers/{id}', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'destroyOffer']);
});

// Direct public vendor offer routes
Route::get('/v1/public/quotations/{token?}', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'showPublic']);
Route::post('/v1/public/quotations/{token?}/offers', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'submitPublicOffer']);
Route::get('/quotation-offer/{token?}', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'showPublic']);
Route::post('/quotation-offer/{token?}', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'submitPublicOffer']);
Route::post('/quotation-offer/{token?}/submit', [\App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController::class, 'submitPublicOffer']);

Route::prefix('dashboard-layouts')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [\App\Modules\Dashboard\Controllers\DashboardLayoutController::class, 'getMyLayout']);
    Route::get('/roles/{roleId}', [\App\Modules\Dashboard\Controllers\DashboardLayoutController::class, 'getByRole']);
    Route::put('/roles/{roleId}', [\App\Modules\Dashboard\Controllers\DashboardLayoutController::class, 'saveByRole']);
    Route::delete('/roles/{roleId}', [\App\Modules\Dashboard\Controllers\DashboardLayoutController::class, 'resetByRole']);
});
