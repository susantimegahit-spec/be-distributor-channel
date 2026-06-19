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
use App\Models\SalesOrder;
use App\Mail\AsmApprovalNotificationMail;

Route::get('/test-send-email', function () {
    try {
        // Coba ambil satu Sales Order yang sudah ada di database beserta detail itemnya
        $salesOrder = SalesOrder::with('details')->first();

        // Jika database masih kosong, kita buat objek dummy
        if (!$salesOrder) {
            $salesOrder = new SalesOrder();
            $salesOrder->id = 999;
            $salesOrder->order_no = 'SO-DUMMY-20260619';
            $salesOrder->customer_name = 'PT CIPTA INDAH DUMMY';
            $salesOrder->card_code = 'C110009999';
            $salesOrder->doc_date = now();
            $salesOrder->doc_total = 15000000.00;
            
            // Hubungkan dengan detail item dummy
            $detail = new \App\Models\SalesOrderDetail();
            $detail->item_code = 'G-SALT-10KG';
            $detail->quantity = 150.00;
            $detail->unit_msr = 'BAL';
            $detail->unit_price = 100000.00;
            $detail->line_total = 15000000.00;
            
            $salesOrder->setRelation('details', collect([$detail]));
        }

        // Kirim email menggunakan Mailable class asli
        Mail::to('sanjayfirmanzyah@gmail.com')
            ->send(new AsmApprovalNotificationMail($salesOrder, 1)); // 1 adalah ID user ASM dummy

        return "Email dengan template HTML asli (AsmApprovalNotificationMail) berhasil dikirim!";
    } catch (\Exception $e) {
        return "Gagal mengirim email: " . $e->getMessage() . "<br><pre>" . $e->getTraceAsString() . "</pre>";
    }
});