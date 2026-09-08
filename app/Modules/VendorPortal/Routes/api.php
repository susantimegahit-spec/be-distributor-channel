<?php

use Illuminate\Support\Facades\Route;
use App\Modules\VendorPortal\Controllers\VendorRegistrationController;
use App\Modules\VendorPortal\Controllers\VendorAuthController;
use App\Modules\VendorPortal\Controllers\VendorLegalApprovalController;

/*
|--------------------------------------------------------------------------
| Vendor Portal & Legal Approval Routes
| Prefix: /api/distributor-channel
|--------------------------------------------------------------------------
*/

Route::prefix('vendor-portal')->group(function () {
    // Public Vendor Onboarding & Auth Endpoints
    Route::post('/register', [VendorRegistrationController::class, 'register']);
    Route::get('/check-email', [VendorRegistrationController::class, 'checkEmail']);
    Route::post('/login', [VendorAuthController::class, 'login']);

    // Authenticated Vendor Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [VendorAuthController::class, 'me']);
        Route::post('/logout', [VendorAuthController::class, 'logout']);
    });
});

Route::prefix('vendor-management')->group(function () {
    // Backoffice Legal Review & Approval Endpoints
    Route::get('/registrations', [VendorLegalApprovalController::class, 'index']);
    Route::get('/registrations/{id}', [VendorLegalApprovalController::class, 'show']);
    Route::post('/registrations/{id}/approve', [VendorLegalApprovalController::class, 'approve']);
    Route::post('/registrations/{id}/reject', [VendorLegalApprovalController::class, 'reject']);
    Route::post('/registrations/{id}/request-revision', [VendorLegalApprovalController::class, 'requestRevision']);
});
