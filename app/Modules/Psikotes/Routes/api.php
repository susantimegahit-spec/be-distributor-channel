<?php

use App\Modules\Psikotes\Controllers\CandidateAuthController;
use App\Modules\Psikotes\Controllers\CandidateManagementController;
use App\Modules\Psikotes\Controllers\DiscAssessmentController;
use App\Modules\Psikotes\Controllers\DiscCandidateTestController;
use App\Modules\Psikotes\Controllers\DiscPublicTestController;
use App\Modules\Psikotes\Controllers\DiscQuestionController;
use Illuminate\Support\Facades\Route;

// Candidate Portal & Recruitment Auth
Route::prefix('v1/recruitment')->group(function () {
    // Auth Candidate (Public)
    Route::post('/auth/register', [CandidateAuthController::class, 'register']);
    Route::post('/auth/login', [CandidateAuthController::class, 'login']);

    // Authenticated Candidate Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [CandidateAuthController::class, 'me']);
        Route::post('/auth/logout', [CandidateAuthController::class, 'logout']);
        Route::put('/candidate/biodata', [CandidateAuthController::class, 'updateBiodata']);

        // DISC Assessment Candidate (No Token Required in URL)
        Route::prefix('psikotes/disc')->group(function () {
            Route::get('/test', [DiscCandidateTestController::class, 'getTest']);
            Route::post('/answers', [DiscCandidateTestController::class, 'saveAnswer']);
            Route::post('/submit', [DiscCandidateTestController::class, 'submit']);
            Route::get('/result', [DiscCandidateTestController::class, 'result']);
        });
    });
});

// Public Candidate Routes (Token-based)
Route::prefix('v1/public/psikotes/disc')->group(function () {
    Route::get('/tests/{token}', [DiscPublicTestController::class, 'getTest']);
    Route::post('/tests/{token}/answers', [DiscPublicTestController::class, 'saveAnswer']);
    Route::post('/tests/{token}/submit', [DiscPublicTestController::class, 'submit']);
});

// Admin HR Protected Routes
Route::prefix('v1/hr/psikotes/disc')->middleware('auth:sanctum')->group(function () {
    // Bank Soal DISC
    Route::get('/questions', [DiscQuestionController::class, 'index']);
    Route::put('/questions/bulk', [DiscQuestionController::class, 'bulkUpdate']);
    Route::post('/questions/seed-default', [DiscQuestionController::class, 'seedDefault']);
    Route::get('/questions/{number}', [DiscQuestionController::class, 'show']);
    Route::put('/questions/{number}', [DiscQuestionController::class, 'update']);

    // Assessments & Results
    Route::get('/assessments', [DiscAssessmentController::class, 'index']);
    Route::post('/assessments', [DiscAssessmentController::class, 'store']);
    Route::get('/assessments-export', [DiscAssessmentController::class, 'exportCsv']);
    Route::get('/assessments/{id}', [DiscAssessmentController::class, 'show']);
});

// Admin HR Candidate Management & Recruitment Pipeline
Route::prefix('v1/hr/recruitment/candidates')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CandidateManagementController::class, 'index']);
    Route::get('/{id}', [CandidateManagementController::class, 'show']);
    Route::put('/{id}/status', [CandidateManagementController::class, 'updateStatus']);
    Route::post('/{id}/shortlist', [CandidateManagementController::class, 'shortlist']);
    Route::post('/{id}/reject', [CandidateManagementController::class, 'reject']);
});
