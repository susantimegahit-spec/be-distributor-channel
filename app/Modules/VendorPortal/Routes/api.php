<?php

use Illuminate\Support\Facades\Route;
use App\Modules\VendorPortal\Controllers\VendorRegistrationController;
use App\Modules\VendorPortal\Controllers\VendorAuthController;
use App\Modules\VendorPortal\Controllers\VendorLegalApprovalController;

/*
|--------------------------------------------------------------------------
| Vendor Portal & Legal Approval Routes
| Prefix: /api/distributor-channel
| Mendukung format dengan prefix /v1/ (standar FE axios) maupun tanpa /v1/
|--------------------------------------------------------------------------
*/

$registerVendorRoutes = function () {
    Route::prefix('vendor-portal')->group(function () {
        // Health / Connectivity Test Endpoints for FE
        Route::match(['get', 'post'], '/ping', function () {
            return response()->json([
                'success' => true,
                'status' => 'OK',
                'message' => 'Vendor Portal API is active and reachable.',
                'timestamp' => now()->toIso8601String(),
                'server_time' => now()->format('Y-m-d H:i:s'),
                'endpoints' => [
                    'check_email' => url(request()->route()->getPrefix() . '/check-email'),
                    'register' => url(request()->route()->getPrefix() . '/register'),
                    'login' => url(request()->route()->getPrefix() . '/login'),
                    'me' => url(request()->route()->getPrefix() . '/me'),
                ],
            ]);
        });

        Route::match(['get', 'post'], '/test', function () {
            return response()->json([
                'success' => true,
                'status' => 'OK',
                'message' => 'Vendor Portal API test endpoint is reachable.',
                'timestamp' => now()->toIso8601String(),
            ]);
        });

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
        Route::post('/documents/{documentId}/verify', [VendorLegalApprovalController::class, 'verifyDocument']);
        Route::get('/documents/{documentId}/preview', [VendorLegalApprovalController::class, 'previewDocument']);
    });
};

// 1. Dukungan format FE standar dataService (dengan prefix v1/)
Route::prefix('v1')->group($registerVendorRoutes);

// 2. Dukungan format direct endpoint (tanpa v1/)
$registerVendorRoutes();
