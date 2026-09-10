<?php

use App\Modules\Item\Controllers\ItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/items')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ItemController::class, 'index']);
    Route::post('/sync', [ItemController::class, 'sync']);
});
