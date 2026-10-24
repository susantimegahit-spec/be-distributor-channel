<?php

use App\Modules\Claim\Controllers\ClaimController;
use App\Modules\Claim\Controllers\ProgramController;
use App\Modules\Claim\Controllers\UploadController;
use App\Modules\Claim\Controllers\ResultController;
use App\Modules\Claim\Controllers\WithdrawController;
use App\Modules\Claim\Controllers\BalanceLedgerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/claims')->middleware('auth:sanctum')->group(function () {
    // Master Programs CRUD
    Route::get('/programs', [ProgramController::class, 'index']);
    Route::post('/programs', [ProgramController::class, 'store']);
    Route::get('/programs/{id}', [ProgramController::class, 'show']);
    Route::put('/programs/{id}', [ProgramController::class, 'update']);
    Route::delete('/programs/{id}', [ProgramController::class, 'destroy']);

    // Lookups & General Utilities
    Route::get('/items', [ClaimController::class, 'getItems']);
    Route::get('/template-excel', [ClaimController::class, 'downloadTemplate']);
    Route::get('/dashboard', [ClaimController::class, 'dashboard']);

    // Upload & Batch Management
    Route::post('/upload', [UploadController::class, 'upload']);
    Route::get('/batches', [UploadController::class, 'getBatches']);
    Route::get('/batches/{id}', [UploadController::class, 'showBatch']);
    Route::delete('/batches/{id}', [UploadController::class, 'destroy']);

    // Calculation Results & Export
    Route::get('/results', [ResultController::class, 'index']);
    Route::get('/results/export', [ResultController::class, 'export']);
    Route::post('/results/verify', [ResultController::class, 'verifyBulk']);
    Route::get('/reward-summary', [ResultController::class, 'getSummary']);

    // Withdraws
    Route::get('/withdraws', [WithdrawController::class, 'index']);
    Route::post('/withdraws', [WithdrawController::class, 'store']);
    Route::post('/withdraws/{id}/status', [WithdrawController::class, 'updateStatus']);

    // Balance Ledger
    Route::get('/balance-ledger/approved-claims', [BalanceLedgerController::class, 'getApprovedClaims']);
    Route::get('/balance-ledger', [BalanceLedgerController::class, 'index']);
    Route::post('/balance-ledger/adjustment', [BalanceLedgerController::class, 'storeAdjustment']);
});

// External B2B API Routes for Distributor Claim Integration (API Key Auth)
Route::prefix('v1/external/claims')
    ->middleware([\App\Http\Middleware\AuthenticateDistributorApiKey::class, 'throttle:60,1'])
    ->group(function () {
        Route::post('/', [\App\Modules\Claim\Controllers\ExternalClaimController::class, 'store']);
        Route::get('/batches', [\App\Modules\Claim\Controllers\ExternalClaimController::class, 'getBatches']);
        Route::get('/batches/{identifier}', [\App\Modules\Claim\Controllers\ExternalClaimController::class, 'showBatch']);
        Route::get('/programs', [\App\Modules\Claim\Controllers\ExternalClaimController::class, 'getPrograms']);
    });

