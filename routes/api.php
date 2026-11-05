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
Route::match(['get', 'post'], '/addpr', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'addPR']);
Route::match(['get', 'post'], '/AddPR', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'addPR']);
Route::match(['get', 'post'], '/api/addpr', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'addPR']);
Route::match(['get', 'post'], '/api/AddPR', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'addPR']);

// Purchasing Quotations & Offers (RFQ)
Route::prefix('v1/purchasing-request')->group(function () {
    Route::match(['get', 'post'], '/addpr', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'addPR']);
    Route::match(['get', 'post'], '/AddPR', [\App\Modules\PurchasingRequest\Controllers\PurchaseRequestController::class, 'addPR']);
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

// Recruitment Candidate Portal & DISC Assessments (Direct API Aliases)
Route::prefix('v1/recruitment')->group(function () {
    Route::post('/auth/register', [\App\Modules\Psikotes\Controllers\CandidateAuthController::class, 'register']);
    Route::post('/auth/login', [\App\Modules\Psikotes\Controllers\CandidateAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [\App\Modules\Psikotes\Controllers\CandidateAuthController::class, 'me']);
        Route::post('/auth/logout', [\App\Modules\Psikotes\Controllers\CandidateAuthController::class, 'logout']);
        Route::put('/candidate/biodata', [\App\Modules\Psikotes\Controllers\CandidateAuthController::class, 'updateBiodata']);

        Route::prefix('psikotes/disc')->group(function () {
            Route::get('/test', [\App\Modules\Psikotes\Controllers\DiscCandidateTestController::class, 'getTest']);
            Route::post('/answers', [\App\Modules\Psikotes\Controllers\DiscCandidateTestController::class, 'saveAnswer']);
            Route::post('/submit', [\App\Modules\Psikotes\Controllers\DiscCandidateTestController::class, 'submit']);
            Route::get('/result', [\App\Modules\Psikotes\Controllers\DiscCandidateTestController::class, 'result']);
        });
    });
});

// DISC Psychological Assessment (Direct API Aliases)
Route::prefix('v1/public/psikotes/disc')->group(function () {
    Route::get('/tests/{token}', [\App\Modules\Psikotes\Controllers\DiscPublicTestController::class, 'getTest']);
    Route::post('/tests/{token}/answers', [\App\Modules\Psikotes\Controllers\DiscPublicTestController::class, 'saveAnswer']);
    Route::post('/tests/{token}/submit', [\App\Modules\Psikotes\Controllers\DiscPublicTestController::class, 'submit']);
});

Route::prefix('v1/hr/psikotes/disc')->middleware('auth:sanctum')->group(function () {
    Route::get('/questions', [\App\Modules\Psikotes\Controllers\DiscQuestionController::class, 'index']);
    Route::put('/questions/bulk', [\App\Modules\Psikotes\Controllers\DiscQuestionController::class, 'bulkUpdate']);
    Route::post('/questions/seed-default', [\App\Modules\Psikotes\Controllers\DiscQuestionController::class, 'seedDefault']);
    Route::get('/questions/{number}', [\App\Modules\Psikotes\Controllers\DiscQuestionController::class, 'show']);
    Route::put('/questions/{number}', [\App\Modules\Psikotes\Controllers\DiscQuestionController::class, 'update']);
    Route::get('/assessments', [\App\Modules\Psikotes\Controllers\DiscAssessmentController::class, 'index']);
    Route::post('/assessments', [\App\Modules\Psikotes\Controllers\DiscAssessmentController::class, 'store']);
    Route::get('/assessments-export', [\App\Modules\Psikotes\Controllers\DiscAssessmentController::class, 'exportCsv']);
    Route::get('/assessments/{id}', [\App\Modules\Psikotes\Controllers\DiscAssessmentController::class, 'show']);
});

Route::prefix('v1/hr/recruitment/candidates')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [\App\Modules\Psikotes\Controllers\CandidateManagementController::class, 'index']);
    Route::get('/{id}', [\App\Modules\Psikotes\Controllers\CandidateManagementController::class, 'show']);
    Route::put('/{id}/status', [\App\Modules\Psikotes\Controllers\CandidateManagementController::class, 'updateStatus']);
    Route::post('/{id}/shortlist', [\App\Modules\Psikotes\Controllers\CandidateManagementController::class, 'shortlist']);
    Route::post('/{id}/reject', [\App\Modules\Psikotes\Controllers\CandidateManagementController::class, 'reject']);
});

