<?php

use App\Modules\Budgeting\Controllers\MasterBudgetController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/budgeting')->group(function () {
    Route::get('/master-budgets', [MasterBudgetController::class, 'index']);
    Route::post('/master-budgets', [MasterBudgetController::class, 'store']);
    Route::get('/master-budgets/{id}', [MasterBudgetController::class, 'show']);
    Route::put('/master-budgets/{id}', [MasterBudgetController::class, 'update']);
    Route::delete('/master-budgets/{id}', [MasterBudgetController::class, 'destroy']);
});
