@extends('mailboxes.layout')

@section('title', 'Laporan Eksekutif Penggunaan Mailbox')

@section('breadcrumb')
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Laporan Eksekutif</span>
@endsection

@section('header_actions')
    <button type="button" class="btn btn-emerald no-print" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        <span>Cetak / Download PDF Laporan</span>
    </button>
@endsection

@section('styles')
<style>
    @media print {
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-size: 11pt;
            color: #000;
            background: #fff !important;
        }
        .report-sheet {
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
            width: 100% !important;
        }
        .page-break {
            page-break-before: always;
        }
    }

    .report-sheet {
        background: #ffffff;
        border: 1px solid var(--slate-200);
        border-radius: var(--radius-xl);
        padding: 40px;
        box-shadow: var(--shadow-card);
        max-width: 1000px;
        margin: 0 auto;
    }

    .report-header {
        border-bottom: 2px solid var(--navy);
        padding-bottom: 20px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }

    .report-title {
        font-size: 20px;
        font-weight: 800;
        color: var(--navy);
        letter-spacing: -0.3px;
        text-transform: uppercase;
    }

    .report-subtitle {
        font-size: 13px;
        color: var(--slate-600);
        margin-top: 4px;
    }

    .report-meta-box {
        text-align: right;
        font-size: 12px;
        color: var(--slate-500);
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        margin-bottom: 24px;
    }

    .report-table th {
        background-color: #f1f5f9;
        border: 1px solid #cbd5e1;
        padding: 8px 10px;
        font-weight: 700;
        text-align: left;
        color: var(--navy);
    }

    .report-table td {
        border: 1px solid #e2e8f0;
        padding: 8px 10px;
        color: #334155;
    }

    .report-table tr:nth-child(even) td {
        background-color: #f8fafc;
    }

    .section-heading {
        font-size: 14px;
        font-weight: 800;
        color: var(--navy);
        text-transform: uppercase;
        border-left: 4px solid var(--primary);
        padding-left: 10px;
        margin-bottom: 12px;
        margin-top: 24px;
    }
</style>
@endsection

@section('content')
<div class="report-sheet">
    <!-- Header -->
    <div class="report-header">
        <div>
            <div style="font-size: 13px; font-weight: 800; color: #059669; letter-spacing: 0.5px; text-transform: uppercase;">
                PT Susanti Megah &bull; IT Infrastructure Division
            </div>
            <div class="report-title">MAILBOX STORAGE &amp; CAPACITY USAGE REPORT</div>
            <div class="report-subtitle">Laporan Audit Utilisasi Storage Email, Analisis Risiko, dan Rekomendasi Alokasi Kuota</div>
        </div>
        <div class="report-meta-box">
            <div><strong>Tanggal Dokumen:</strong> {{ $generatedAt }}</div>
            <div><strong>Status Server:</strong> Production cPanel Exim</div>
            <div><strong>Klasifikasi:</strong> Internal Management Only</div>
        </div>
    </div>

    <!-- 1. Executive Summary Table -->
    <div class="section-heading">1. Ringkasan Eksekutif (Executive Summary)</div>
    <table class="report-table">
        <thead>
            <tr>
                <th>Total Mailbox</th>
                <th>Storage Digunakan</th>
                <th>Total Kuota Server</th>
                <th>Rata-rata Utilisasi</th>
                <th>Akun Kritis (&gt;95%)</th>
                <th>Akun Peringatan (&gt;85%)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>{{ $stats['total_mailbox'] }} Akun</strong></td>
                <td>{{ $stats['formatted_total_storage'] }}</td>
                <td>{{ $stats['formatted_total_quota'] }}</td>
                <td>
                    <strong style="color: {{ $stats['average_usage_percentage'] > 85 ? '#dc2626' : '#059669' }};">
                        {{ $stats['average_usage_percentage'] }}%
                    </strong>
                </td>
                <td style="color: #dc2626; font-weight: 700;">{{ $stats['count_critical'] }} Akun</td>
                <td style="color: #d97706; font-weight: 700;">{{ $stats['count_warning'] }} Akun</td>
            </tr>
        </tbody>
    </table>

    <!-- 2. Breakdown per Departemen -->
    <div class="section-heading">2. Distribusi Utilisasi per Unit Kerja / Departemen</div>
    <table class="report-table">
        <thead>
            <tr>
                <th>Departemen</th>
                <th style="text-align: center;">Jumlah Akun</th>
                <th style="text-align: right;">Storage Terpakai</th>
                <th style="text-align: right;">Kapasitas Kuota</th>
                <th style="text-align: center;">Rasio Penggunaan</th>
                <th style="text-align: center;">Status Departemen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($departments as $dept)
                <tr>
                    <td><strong>{{ $dept['name'] }}</strong></td>
                    <td style="text-align: center;">{{ $dept['count'] }}</td>
                    <td style="text-align: right;">{{ $dept['used_gb'] }} GB</td>
                    <td style="text-align: right;">{{ $dept['quota_gb'] }} GB</td>
                    <td style="text-align: center; font-weight: 700; color: {{ $dept['usage_percent'] > 85 ? '#dc2626' : ($dept['usage_percent'] > 70 ? '#d97706' : '#059669') }};">
                        {{ $dept['usage_percent'] }}%
                    </td>
                    <td style="text-align: center;">
                        @if($dept['usage_percent'] >= 85)
                            <span style="color: #dc2626; font-weight: 700;">PERHATIAN TINGGI</span>
                        @elseif($dept['usage_percent'] >= 70)
                            <span style="color: #d97706; font-weight: 600;">MONITORING</span>
                        @else
                            <span style="color: #059669; font-weight: 600;">NORMAL</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- 3. High Risk Mailboxes -->
    <div class="section-heading">3. Akun Mailbox Berisiko Tinggi (Mendekati / Melebihi Batas)</div>
    @if($highRiskMailboxes->count() > 0)
        <table class="report-table">
            <thead>
                <tr>
                    <th>Alamat Email</th>
                    <th>PIC / Pemilik</th>
                    <th>Departemen</th>
                    <th style="text-align: right;">Terpakai / Kuota</th>
                    <th style="text-align: center;">% Kuota</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Estimasi Penuh</th>
                </tr>
            </thead>
            <tbody>
                @foreach($highRiskMailboxes as $box)
                    <tr>
                        <td><strong>{{ $box->email }}</strong></td>
                        <td>{{ $box->user_name ?? '-' }}</td>
                        <td>{{ $box->department ? $box->department->name : 'Unassigned' }}</td>
                        <td style="text-align: right;">{{ $box->formatted_usage }} / {{ $box->formatted_quota }}</td>
                        <td style="text-align: center; font-weight: 800; color: {{ $box->status === 'CRITICAL' ? '#dc2626' : '#d97706' }};">
                            {{ $box->usage_percentage }}%
                        </td>
                        <td style="text-align: center;">
                            <span style="font-weight: 700; color: {{ $box->status === 'CRITICAL' ? '#dc2626' : '#d97706' }};">
                                {{ $box->status }}
                            </span>
                        </td>
                        <td style="text-align: center;">
                            {{ $box->days_until_full !== null ? $box->days_until_full . ' Hari' : 'Stabil' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="font-size: 12px; color: #64748b; font-style: italic; margin-bottom: 20px;">
            Tidak ditemukan akun dalam status WARNING maupun CRITICAL pada periode laporan ini.
        </p>
    @endif

    <!-- 4. Recommendations & Action Plan -->
    <div class="section-heading">4. Rekomendasi Tindak Lanjut &amp; Alokasi Tambahan Kuota</div>
    @if($recommendations->count() > 0)
        <table class="report-table">
            <thead>
                <tr>
                    <th>Akun Target</th>
                    <th>Kuota Sekarang</th>
                    <th>Usulan Kuota Baru</th>
                    <th>Penambahan (GB)</th>
                    <th>Alasan Teknis IT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recommendations as $rec)
                    @php
                        $curr = round($rec->current_quota_bytes / (1024*1024*1024), 2);
                        $target = round($rec->recommended_quota_bytes / (1024*1024*1024), 2);
                    @endphp
                    <tr>
                        <td><strong>{{ $rec->mailbox ? $rec->mailbox->email : '-' }}</strong></td>
                        <td>{{ $curr }} GB</td>
                        <td><strong style="color: #2563eb;">{{ $target }} GB</strong></td>
                        <td><strong style="color: #059669;">+{{ $target - $curr }} GB</strong></td>
                        <td style="font-size: 11px;">{{ $rec->reason }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="font-size: 12px; color: #64748b; font-style: italic; margin-bottom: 20px;">
            Tidak ada usulan penambahan kuota pending saat ini.
        </p>
    @endif

    <!-- 5. Signatures Block -->
    <div style="margin-top: 40px; display: flex; justify-content: space-between; page-break-inside: avoid;">
        <div style="width: 250px; text-align: center;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 60px;">Disiapkan Oleh (IT Specialist):</div>
            <div style="font-weight: 700; color: var(--navy); border-bottom: 1px solid #94a3b8; padding-bottom: 4px;">
                IT Infrastructure &amp; Mail Admin
            </div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">PT Susanti Megah</div>
        </div>

        <div style="width: 250px; text-align: center;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 60px;">Mengetahui / Menyetujui (Management):</div>
            <div style="font-weight: 700; color: var(--navy); border-bottom: 1px solid #94a3b8; padding-bottom: 4px;">
                IT Manager / Direksi
            </div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">PT Susanti Megah</div>
        </div>
    </div>
</div>
@endsection
