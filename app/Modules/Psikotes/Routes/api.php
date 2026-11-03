<?php

use App\Modules\Psikotes\Controllers\DiscAssessmentController;
use App\Modules\Psikotes\Controllers\DiscPublicTestController;
use App\Modules\Psikotes\Controllers\DiscQuestionController;
use Illuminate\Support\Facades\Route;

// Public Candidate Routes
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

