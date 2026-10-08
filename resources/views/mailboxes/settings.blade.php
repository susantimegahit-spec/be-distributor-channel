@extends('mailboxes.layout')

@section('title', 'Konfigurasi Mailbox & cPanel')

@section('breadcrumb')
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Konfigurasi</span>
@endsection

@section('content')
<div style="max-width: 900px; margin: 0 auto;">
    <div style="background: #ffffff; border: 1px solid var(--slate-200); border-radius: var(--radius-xl); padding: 22px 28px; box-shadow: var(--shadow-card); margin-bottom: 24px;">
        <h1 style="font-size: 19px; font-weight: 800; color: var(--navy); margin-bottom: 4px;">Konfigurasi Sistem Monitoring &amp; Server cPanel</h1>
        <p style="font-size: 13.5px; color: var(--slate-600); margin: 0;">
            Atur batas persentase ambang peringatan, algoritma penambahan kuota cadangan, serta kredensial koneksi UAPI cPanel server.
        </p>
    </div>

    <form action="{{ route('mailboxes.settings.update') }}" method="POST">
        @csrf

        <!-- 1. Thresholds Card -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                    <span>1. Ambang Batas Peringatan (Usage Thresholds)</span>
                </h3>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Batas Aman / Safe (&lt; %)</label>
                    <input type="number" name="safe_threshold" class="form-control" value="{{ $settings['safe_threshold'] ?? '70' }}" min="1" max="100" required>
                    <small style="color: var(--slate-500); font-size: 11.5px;">Batas di bawah nilai ini dikategorikan aman.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Batas Monitoring (70% - %)</label>
                    <input type="number" name="monitoring_threshold" class="form-control" value="{{ $settings['monitoring_threshold'] ?? '85' }}" min="1" max="100" required>
                    <small style="color: var(--slate-500); font-size: 11.5px;">Perlu diawasi pertumbuhannya secara berkala.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Batas Peringatan / Warning (&gt; %)</label>
                    <input type="number" name="warning_threshold" class="form-control" value="{{ $settings['warning_threshold'] ?? '95' }}" min="1" max="100" required>
                    <small style="color: var(--slate-500); font-size: 11.5px;">Jika melampaui nilai ini, status menjadi Kritis.</small>
                </div>
            </div>
        </div>

        <!-- 2. Quota Recommendation Defaults -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>2. Parameter Rekomendasi Penambahan Kuota</span>
                </h3>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Tambahan Kuota untuk Status WARNING (GB)</label>
                    <input type="number" name="warning_addition_gb" class="form-control" value="{{ $settings['warning_addition_gb'] ?? '5' }}" min="1" required>
                    <small style="color: var(--slate-500); font-size: 11.5px;">Default 5 GB saat mencapai peringatan.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Tambahan Kuota untuk Status CRITICAL (GB)</label>
                    <input type="number" name="critical_addition_gb" class="form-control" value="{{ $settings['critical_addition_gb'] ?? '10' }}" min="1" required>
                    <small style="color: var(--slate-500); font-size: 11.5px;">Default 10 GB untuk akun kritis mendesak.</small>
                </div>
            </div>
        </div>

        <!-- 3. cPanel Connection Settings -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                    <span>3. Koneksi API Server cPanel (UAPI Exim)</span>
                </h3>
            </div>

            <div style="background: #f8fafc; border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 14px 16px; margin-bottom: 16px; font-size: 12.5px; color: var(--slate-700); line-height: 1.5;">
                <strong>Catatan Integrasi:</strong> Jika aplikasi dijalankan langsung pada server hosting cPanel yang sama, sistem dapat mengeksekusi CLI lokal (<code>uapi Email list_pops_with_disk</code>) tanpa API token eksternal. Jika diakses via HTTP remote, masukkan API Token cPanel di bawah ini.
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Host Server cPanel / IP</label>
                    <input type="text" name="cpanel_host" class="form-control" value="{{ $settings['cpanel_host'] ?? '163.61.58.29' }}" placeholder="Contoh: 163.61.58.29 atau mail.susantimegah.com">
                </div>

                <div class="form-group">
                    <label class="form-label">Port cPanel (SSL)</label>
                    <input type="number" name="cpanel_port" class="form-control" value="{{ $settings['cpanel_port'] ?? '2083' }}">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Username cPanel</label>
                    <input type="text" name="cpanel_user" class="form-control" value="{{ $settings['cpanel_user'] ?? 'susantimegah' }}" placeholder="susantimegah">
                </div>

                <div class="form-group">
                    <label class="form-label">cPanel API Token</label>
                    <input type="password" name="cpanel_api_token" class="form-control" value="{{ $settings['cpanel_api_token'] ?? '' }}" placeholder="Paste API token cPanel...">
                    <small style="color: var(--slate-500); font-size: 11.5px;">Dibuat dari: cPanel &gt; Security &gt; Manage API Tokens.</small>
                </div>
            </div>
        </div>

        <div style="text-align: right; margin-bottom: 40px;">
            <button type="submit" class="btn btn-emerald" style="padding: 10px 24px; font-size: 14px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                <span>Simpan Seluruh Konfigurasi</span>
            </button>
        </div>
    </form>
</div>
@endsection
