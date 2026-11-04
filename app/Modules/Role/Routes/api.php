<?php

use App\Modules\Role\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/roles')->middleware('auth:sanctum')->group(function () {
    Route::get('/my-permissions', [RoleController::class, 'myPermissions']);
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/{id}', [RoleController::class, 'show']);
    Route::post('/', [RoleController::class, 'store']);
    Route::put('/{id}', [RoleController::class, 'update']);
    Route::delete('/{id}', [RoleController::class, 'destroy']);
    Route::get('/{id}/menu', [RoleController::class, 'getMenu']);
    Route::put('/{id}/menu', [RoleController::class, 'updateMenu']);
    Route::get('/{id}/permissions', [RoleController::class, 'getPermissions']);
    Route::post('/{id}/permissions', [RoleController::class, 'updatePermissions']);
    Route::put('/{id}/permissions', [RoleController::class, 'updatePermissions']);
});
