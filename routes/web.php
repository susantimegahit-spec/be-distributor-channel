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

// Route untuk menjalankan migrasi baru (membuat tabel yang kurang)
Route::get('/run-migration-darurat', function () {
    Artisan::call('migrate');
    return "Database migration successfully executed!";
});

// Route untuk reset database + jalankan seeder sekaligus
Route::get('/run-migrate-fresh-darurat', function () {
    Artisan::call('migrate:fresh', ['--seed' => true]);
    return "Database fresh migration and seed successfully executed!";
});

// Route untuk jalankan seeder saja
Route::get('/run-seeder-darurat', function () {
    Artisan::call('db:seed');
    return "Database seeder successfully executed!";
});