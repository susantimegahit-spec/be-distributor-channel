<?php

use App\Modules\DocumentApproval\Controllers\DocumentApprovalController;
use App\Modules\DocumentApproval\Controllers\DocumentTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/document-approval')->group(function () {
    // Master Document Types & Schemas
    Route::get('/document-types', [DocumentTypeController::class, 'index']);
    Route::get('/document-types/{code}', [DocumentTypeController::class, 'show']);

    // Approvals Management
    Route::get('/approvals', [DocumentApprovalController::class, 'index']);
    Route::get('/approvals/{id}', [DocumentApprovalController::class, 'show']);
    Route::post('/approvals/{id}/approve', [DocumentApprovalController::class, 'approve']);
    Route::post('/approvals/{id}/reject', [DocumentApprovalController::class, 'reject']);
    Route::post('/approvals/{id}/revise', [DocumentApprovalController::class, 'revise']);

    // Live Preview un-submitted SAP document
    Route::get('/preview/{typeCode}/{docEntry}', [DocumentApprovalController::class, 'preview']);
});
