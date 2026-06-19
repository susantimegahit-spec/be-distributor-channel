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
        'Content-Type' => 'text/yaml',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
});

Route::get('/openapi.yaml', function () {
    $path = public_path('docs/openapi.yaml');
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, [
        'Content-Type' => 'text/yaml',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
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

use Illuminate\Support\Facades\Mail;
Route::get('/test-send-email', function () {
    try {
        Mail::raw('Ini adalah email uji coba dari URL browser.', function ($message) {
            $message->to('sanjayfirmanzyah@gmail.com')
                    ->subject('Uji Coba SMTP via URL');
        });
        return "Email berhasil dikirim!";
    } catch (\Exception $e) {
        return "Gagal mengirim email: " . $e->getMessage();
    }
});