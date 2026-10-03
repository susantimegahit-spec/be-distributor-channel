<?php

use App\Modules\DocumentApproval\Controllers\DocumentApprovalController;
use App\Modules\DocumentApproval\Controllers\DocumentTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/document-approval')->group(function () {
    // -------------------------------------------------------------
    // Master Document Types CRUD & Upload Endpoints
    // -------------------------------------------------------------
    Route::get('/document-types', [DocumentTypeController::class, 'index']);
    Route::post('/document-types', [DocumentTypeController::class, 'store']);
    Route::get('/document-types/{idOrCode}', [DocumentTypeController::class, 'show']);
    Route::put('/document-types/{id}', [DocumentTypeController::class, 'update']);
    Route::post('/document-types/{id}', [DocumentTypeController::class, 'update']); // for multipart/form-data support
    Route::delete('/document-types/{id}', [DocumentTypeController::class, 'destroy']);
    Route::patch('/document-types/{id}/toggle-status', [DocumentTypeController::class, 'toggleStatus']);
    Route::post('/document-types/{id}/attachment', [DocumentTypeController::class, 'uploadAttachment']);

    // -------------------------------------------------------------
    // Approvals Management
    // -------------------------------------------------------------
    Route::get('/approvals', [DocumentApprovalController::class, 'index']);
    Route::get('/approvals/{id}', [DocumentApprovalController::class, 'show']);
    Route::post('/approvals/{id}/approve', [DocumentApprovalController::class, 'approve']);
    Route::post('/approvals/{id}/reject', [DocumentApprovalController::class, 'reject']);
    Route::post('/approvals/{id}/revise', [DocumentApprovalController::class, 'revise']);

    // Live Preview un-submitted SAP document
    Route::get('/preview/{typeCode}/{docEntry}', [DocumentApprovalController::class, 'preview']);
});
