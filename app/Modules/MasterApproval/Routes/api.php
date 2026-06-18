<?php

use App\Modules\MasterApproval\Controllers\MasterApprovalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/master-approvals')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [MasterApprovalController::class, 'index']);
});
