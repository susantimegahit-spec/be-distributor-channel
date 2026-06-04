<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', function () {
    return redirect('/docs/index.html');
});

Route::get('/docs/openapi.yaml', function () {
    $path = public_path('docs/openapi.yaml');
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, [
        'Content-Type' => 'text/yaml'
    ]);
});

Route::get('/openapi.yaml', function () {
    $path = public_path('docs/openapi.yaml');
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, [
        'Content-Type' => 'text/yaml'
    ]);
});


use Illuminate\Support\Facades\Artisan;
Route::get('/run-seeder-darurat', function () {
    Artisan::call('db:seed');
    return "Database seeder successfully executed!";
});