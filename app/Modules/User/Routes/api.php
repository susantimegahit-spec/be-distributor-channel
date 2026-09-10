<?php

use App\Modules\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/users')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);

    // User-Level Custom Permissions Endpoints
    Route::get('/{id}/custom-permissions', [UserController::class, 'getCustomPermissions']);
    Route::put('/{id}/custom-permissions', [UserController::class, 'updateCustomPermissions']);
    Route::post('/{id}/custom-permissions', [UserController::class, 'updateCustomPermissions']);
    Route::delete('/{id}/custom-permissions', [UserController::class, 'resetCustomPermissions']);
});
