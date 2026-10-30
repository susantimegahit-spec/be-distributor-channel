<?php

use App\Modules\PurchasingRequest\Controllers\DocumentTypeController;
use App\Modules\PurchasingRequest\Controllers\PurchaseQuotationController;
use App\Modules\PurchasingRequest\Controllers\PurchaseRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/purchasing-request')->group(function () {
    Route::get('/series', [PurchaseRequestController::class, 'getSeries']);
    Route::get('/items', [PurchaseRequestController::class, 'getItems']);
    Route::match(['get', 'post'], '/departments', [PurchaseRequestController::class, 'getDepartments']);
    Route::match(['get', 'post'], '/draft-pr', [PurchaseRequestController::class, 'getDraftPR']);
    Route::match(['get', 'post'], '/drafts', [PurchaseRequestController::class, 'getDraftPR']);
    Route::match(['get', 'post'], '/get-draft-pr', [PurchaseRequestController::class, 'getDraftPR']);
    Route::match(['get', 'post'], '/generated', [PurchaseRequestController::class, 'getListPRGenerated']);
    Route::match(['get', 'post'], '/list-pr-generated', [PurchaseRequestController::class, 'getListPRGenerated']);
    Route::match(['get', 'post'], '/get-list-pr-generated', [PurchaseRequestController::class, 'getListPRGenerated']);
    Route::match(['get', 'post'], '/detail', [PurchaseRequestController::class, 'getDetailPR']);
    Route::match(['get', 'post'], '/detail-pr', [PurchaseRequestController::class, 'getDetailPR']);
    Route::match(['get', 'post'], '/get-detail-pr', [PurchaseRequestController::class, 'getDetailPR']);
    Route::get('/detail/{docEntry}', [PurchaseRequestController::class, 'getDetailPR']);
    Route::get('/requests/sap/{docEntry}', [PurchaseRequestController::class, 'getDetailPR']);
    Route::match(['get', 'post'], '/close-pr', [PurchaseRequestController::class, 'closePR']);
    Route::match(['get', 'post'], '/close', [PurchaseRequestController::class, 'closePR']);
    Route::match(['get', 'post'], '/cancel-pr', [PurchaseRequestController::class, 'cancelPR']);
    Route::match(['get', 'post'], '/cancel', [PurchaseRequestController::class, 'cancelPR']);
    Route::get('/requests', [PurchaseRequestController::class, 'index']);
    Route::post('/requests', [PurchaseRequestController::class, 'store']);
    Route::post('/add-pr', [PurchaseRequestController::class, 'store']);
    Route::post('/addpr', [PurchaseRequestController::class, 'store']);
    Route::get('/requests/{id}', [PurchaseRequestController::class, 'show']);
    Route::put('/requests/{id}', [PurchaseRequestController::class, 'update']);
    Route::post('/requests/{id}/status', [PurchaseRequestController::class, 'updateStatus']);
    Route::delete('/requests/{id}', [PurchaseRequestController::class, 'destroy']);
    Route::get('/document-types', [DocumentTypeController::class, 'index']);

    // Request for Quotation (RFQ)
    Route::get('/quotations', [PurchaseQuotationController::class, 'index']);
    Route::post('/quotations', [PurchaseQuotationController::class, 'store']);
    Route::get('/quotations/{id}', [PurchaseQuotationController::class, 'show']);
    Route::put('/quotations/{id}', [PurchaseQuotationController::class, 'update']);
    Route::delete('/quotations/{id}', [PurchaseQuotationController::class, 'destroy']);
    Route::get('/quotations/{quotationId}/offers', [PurchaseQuotationController::class, 'getQuotationOffers']);

    // Vendor Offers
    Route::get('/quotation-offers', [PurchaseQuotationController::class, 'listOffers']);
    Route::get('/quotation-offers/{id}', [PurchaseQuotationController::class, 'showOffer']);
    Route::put('/quotation-offers/{id}', [PurchaseQuotationController::class, 'updateOffer']);
    Route::delete('/quotation-offers/{id}', [PurchaseQuotationController::class, 'destroyOffer']);
});

// Quotations direct v1 & public endpoints
Route::get('v1/public/quotations/{token?}', [PurchaseQuotationController::class, 'showPublic']);
Route::post('v1/public/quotations/{token?}/offers', [PurchaseQuotationController::class, 'submitPublicOffer']);
Route::get('quotation-offer/{token?}', [PurchaseQuotationController::class, 'showPublic']);
Route::post('quotation-offer/{token?}', [PurchaseQuotationController::class, 'submitPublicOffer']);
Route::post('quotation-offer/{token?}/submit', [PurchaseQuotationController::class, 'submitPublicOffer']);

Route::get('v1/document-types', [DocumentTypeController::class, 'index']);
Route::match(['get', 'post'], 'v1/departments', [PurchaseRequestController::class, 'getDepartments']);
Route::match(['get', 'post'], 'v1/draft-pr', [PurchaseRequestController::class, 'getDraftPR']);
Route::match(['get', 'post'], 'v1/get-draft-pr', [PurchaseRequestController::class, 'getDraftPR']);
Route::match(['get', 'post'], 'GetDraftPR', [PurchaseRequestController::class, 'getDraftPR']);
Route::match(['get', 'post'], 'v1/list-pr-generated', [PurchaseRequestController::class, 'getListPRGenerated']);
Route::match(['get', 'post'], 'v1/get-list-pr-generated', [PurchaseRequestController::class, 'getListPRGenerated']);
Route::match(['get', 'post'], 'GetListPRGenerated', [PurchaseRequestController::class, 'getListPRGenerated']);
Route::match(['get', 'post'], 'v1/detail-pr', [PurchaseRequestController::class, 'getDetailPR']);
Route::match(['get', 'post'], 'v1/get-detail-pr', [PurchaseRequestController::class, 'getDetailPR']);
Route::match(['get', 'post'], 'GetDetailPR', [PurchaseRequestController::class, 'getDetailPR']);
Route::match(['get', 'post'], 'v1/close-pr', [PurchaseRequestController::class, 'closePR']);
Route::match(['get', 'post'], 'v1/cancel-pr', [PurchaseRequestController::class, 'cancelPR']);
Route::match(['get', 'post'], 'ClosePR', [PurchaseRequestController::class, 'closePR']);
Route::match(['get', 'post'], 'CancelPR', [PurchaseRequestController::class, 'cancelPR']);

