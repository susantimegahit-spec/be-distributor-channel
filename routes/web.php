<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Middleware\DocsAuthSession;
use Illuminate\Http\Request;

// 1. Halaman Login Dokumentasi API
Route::get('/docs/login', function () {
    if (session('docs_authenticated')) {
        return redirect('/docs');
    }
    return view('docs-login');
});

// 2. Proses Login Dokumentasi API
Route::post('/docs/login', function (Request $request) {
    $username = $request->input('username');
    $password = $request->input('password');

    if ($username === 'adminsm' && $password === 'adminsm!') {
        session([
            'docs_authenticated' => true,
            'docs_last_activity' => time(),
        ]);
        return redirect('/docs');
    }

    return redirect('/docs/login')->with('error', 'Username atau password yang Anda masukkan salah!');
});

// 3. Proses Logout Dokumentasi API
Route::get('/docs/logout', function (Request $request) {
    $expired = $request->query('expired');
    $request->session()->forget(['docs_authenticated', 'docs_last_activity']);
    
    if ($expired) {
        return redirect('/docs/login?expired=1');
    }
    return redirect('/docs/login');
});

// ----------------------------------------------------
// MONITORING SM (PULSE) AUTHENTICATION ROUTES
// ----------------------------------------------------

// 1. Halaman Login Monitoring SM
Route::get('/monitoringsm/login', function () {
    if (session('pulse_authenticated')) {
        return redirect('/monitoringsm');
    }
    return view('pulse-login');
});

// 2. Proses Login Monitoring SM
Route::post('/monitoringsm/login', function (Request $request) {
    $username = $request->input('username');
    $password = $request->input('password');

    if ($username === 'adminsm' && $password === 'adminsm!') {
        session([
            'pulse_authenticated' => true,
            'pulse_last_activity' => time(),
        ]);
        return redirect('/monitoringsm');
    }

    return redirect('/monitoringsm/login')->with('error', 'Username atau password admin yang Anda masukkan salah!');
});

// 3. Proses Logout Monitoring SM
Route::get('/monitoringsm/logout', function (Request $request) {
    $request->session()->forget(['pulse_authenticated', 'pulse_last_activity']);
    return redirect('/monitoringsm/login');
});

// 4. Custom Route Dashboard Monitoring SM & API Keys Dashboard
use App\Http\Controllers\ApiKeyWebController;

Route::middleware(['web', \App\Http\Middleware\PulseAuthSession::class, \Laravel\Pulse\Http\Middleware\Authorize::class])->group(function () {
    Route::get('/monitoringsm', function () {
        return view('monitoring-dashboard');
    });

    Route::get('/monitoringsm/api-keys', [ApiKeyWebController::class, 'index']);
    Route::post('/monitoringsm/api-keys/generate', [ApiKeyWebController::class, 'store']);
    Route::post('/monitoringsm/api-keys/{id}/toggle', [ApiKeyWebController::class, 'toggleStatus']);
    Route::post('/monitoringsm/api-keys/{id}/delete', [ApiKeyWebController::class, 'destroy']);
});

// 4. Route Dokumentasi API yang Dilindungi Session & Timeout 1 Hari (86400 Detik)
Route::middleware([DocsAuthSession::class])->group(function () {
    Route::get('/docs', function () {
        $path = resource_path('docs/index.html');
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path, [
            'Content-Type' => 'text/html',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    });

    Route::get('/docs/index.html', function () {
        $path = resource_path('docs/index.html');
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path, [
            'Content-Type' => 'text/html',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    });

    Route::get('/docs/openapi.yaml', function () {
        $path = resource_path('docs/openapi.yaml');
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
        $path = resource_path('docs/openapi.yaml');
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