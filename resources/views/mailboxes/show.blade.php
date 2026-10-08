@extends('mailboxes.layout')

@section('title', 'Detail Mailbox - ' . $mailbox->email)

@section('breadcrumb')
    <span class="breadcrumb-sep">/</span>
    <a href="{{ route('mailboxes.list') }}">Daftar Mailbox</a>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">{{ $mailbox->email }}</span>
@endsection

@section('header_actions')
    <a href="{{ route('mailboxes.list') }}" class="btn btn-outline">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        <span>Kembali</span>
    </a>
@endsection

@section('content')
    <!-- Mailbox Header Card -->
    <div class="card" style="border-left: 5px solid {{ $mailbox->status === 'CRITICAL' ? 'var(--danger)' : ($mailbox->status === 'WARNING' ? 'var(--warning)' : 'var(--success)') }};">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                    <h1 style="font-size: 22px; font-weight: 800; color: var(--navy); margin: 0;">{{ $mailbox->email }}</h1>
                    <span class="status-badge status-badge-{{ strtolower($mailbox->status) }}">
                        {{ $mailbox->status }}
                    </span>
                    @if($mailbox->suspended_incoming)
                        <span class="status-badge" style="background: #fef2f2; color: #991b1b;">Suspended Incoming</span>
                    @endif
                </div>
                <div style="font-size: 13.5px; color: var(--slate-600); display: flex; gap: 16px; flex-wrap: wrap;">
                    <span><strong>PIC:</strong> {{ $mailbox->user_name ?? 'Belum ditentukan' }}</span>
                    <span><strong>Departemen:</strong> {{ $mailbox->department ? $mailbox->department->name : 'Unassigned' }}</span>
                    <span><strong>Domain:</strong> {{ $mailbox->domain }}</span>
                </div>
            </div>

            <div style="text-align: right;">
                <div style="font-size: 11.5px; color: var(--slate-500);">Terakhir Sinkronisasi Server</div>
                <div style="font-size: 13.5px; font-weight: 700; color: var(--navy);">
                    {{ $mailbox->last_synced_at ? \Carbon\Carbon::parse($mailbox->last_synced_at)->isoFormat('D MMMM Y, HH:mm') : '-' }}
                </div>
            </div>
        </div>

        <!-- Usage Bar & Stats -->
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--slate-100); display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px;">
            <div>
                <div style="font-size: 12px; color: var(--slate-500); font-weight: 700; text-transform: uppercase;">Storage Digunakan</div>
                <div style="font-size: 24px; font-weight: 800; color: var(--navy); margin-top: 4px;">{{ $mailbox->formatted_usage }}</div>
                <div style="font-size: 12px; color: var(--slate-500);">{{ round($mailbox->current_usage_bytes / (1024*1024), 1) }} MB</div>
            </div>

            <div>
                <div style="font-size: 12px; color: var(--slate-500); font-weight: 700; text-transform: uppercase;">Kapasitas Kuota</div>
                <div style="font-size: 24px; font-weight: 800; color: var(--navy); margin-top: 4px;">{{ $mailbox->formatted_quota }}</div>
                <div style="font-size: 12px; color: var(--slate-500);">Batas maksimal di server</div>
            </div>

            <div>
                <div style="font-size: 12px; color: var(--slate-500); font-weight: 700; text-transform: uppercase;">Sisa Kuota Tersedia</div>
                <div style="font-size: 24px; font-weight: 800; color: {{ $mailbox->remaining_bytes < (1024*1024*1024) ? 'var(--danger)' : 'var(--navy)' }}; margin-top: 4px;">
                    {{ $mailbox->formatted_remaining }}
                </div>
                <div style="font-size: 12px; color: var(--slate-500);">Ruang penyimpanan bebas</div>
            </div>

            <div>
                <div style="font-size: 12px; color: var(--slate-500); font-weight: 700; text-transform: uppercase;">Persentase Terpakai</div>
                <div style="font-size: 24px; font-weight: 800; color: {{ $mailbox->status === 'CRITICAL' ? 'var(--danger)' : ($mailbox->status === 'WARNING' ? 'var(--warning)' : 'var(--success)') }}; margin-top: 4px;">
                    {{ $mailbox->usage_percentage }}%
                </div>
                <div class="progress-bar-container" style="margin-top: 6px;">
                    <div class="progress-bar-fill fill-{{ strtolower($mailbox->status) }}" style="width: {{ min(100, (float)$mailbox->usage_percentage) }}%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Forecasting & Risk Intelligence Card -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="card" style="background: #f8fafc; border: 1px solid var(--slate-200);">
            <div class="card-header" style="margin-bottom: 14px;">
                <h3 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                    <span>Prediksi & Pertumbuhan Storage</span>
                </h3>
                @php
                    $riskColor = match($forecast['risk_level'] ?? 'LOW') {
                        'HIGH' => '#ef4444',
                        'MEDIUM' => '#f59e0b',
                        default => '#10b981',
                    };
                @endphp
                <span style="font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px; background: #fff; color: {{ $riskColor }}; border: 1px solid {{ $riskColor }};">
                    RISK: {{ $forecast['risk_level'] ?? 'LOW' }}
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13.5px;">
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid var(--slate-200);">
                    <span style="color: var(--slate-600);">Laju Pertumbuhan Harian:</span>
                    <strong style="color: var(--navy);">
                        {{ round(($forecast['growth_rate_bytes_per_day'] ?? 0) / (1024*1024), 2) }} MB / hari
                    </strong>
                </div>

                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid var(--slate-200);">
                    <span style="color: var(--slate-600);">Estimasi Kuota Habis:</span>
                    <strong style="color: {{ isset($forecast['estimated_days_to_full']) && $forecast['estimated_days_to_full'] !== null && $forecast['estimated_days_to_full'] <= 30 ? 'var(--danger)' : 'var(--navy)' }};">
                        @if(isset($forecast['estimated_days_to_full']) && $forecast['estimated_days_to_full'] !== null)
                            {{ $forecast['estimated_days_to_full'] }} Hari ke depan
                        @else
                            Stabil / Tidak ada penambahan signifikan
                        @endif
                    </strong>
                </div>

                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid var(--slate-200);">
                    <span style="color: var(--slate-600);">Status Rekomendasi:</span>
                    <strong style="color: {{ in_array($mailbox->status, ['WARNING', 'CRITICAL']) ? 'var(--danger)' : 'var(--success)' }};">
                        {{ in_array($mailbox->status, ['WARNING', 'CRITICAL']) ? 'Perlu Tambah Kuota' : 'Kapasitas Memadai' }}
                    </strong>
                </div>
            </div>

            @if(in_array($mailbox->status, ['WARNING', 'CRITICAL']))
                <div style="margin-top: 16px; padding: 12px 14px; background: #fef2f2; border-left: 3px solid var(--danger); border-radius: 4px; font-size: 12.5px; color: #991b1b;">
                    <strong>Peringatan Sistem:</strong> Akun ini berisiko penuh dalam tempo dekat. Disarankan penambahan kuota melalui menu Rekomendasi Quota.
                </div>
            @endif
        </div>

        <!-- Inline Update Metadata Card -->
        <div class="card">
            <div class="card-header" style="margin-bottom: 14px;">
                <h3 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    <span>Pemetaan PIC & Departemen</span>
                </h3>
            </div>

            <form action="{{ route('mailboxes.update', $mailbox->id) }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">Nama Pemilik / PIC</label>
                    <input type="text" name="user_name" value="{{ $mailbox->user_name }}" class="form-control" placeholder="Contoh: Budi Santoso">
                </div>

                <div class="form-group">
                    <label class="form-label">Departemen Terkait</label>
                    <select name="department_id" class="form-control">
                        <option value="">-- Pilih Departemen --</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ $mailbox->department_id == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="text-align: right; margin-top: 14px;">
                    <button type="submit" class="btn btn-emerald">Simpan Mapping</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Historical Storage Trend Line Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                <span>Riwayat Penggunaan Storage (Snapshot Harian)</span>
            </h3>
            <span style="font-size: 12px; color: var(--slate-500);">Data 14-30 hari terakhir</span>
        </div>

        <div style="position: relative; height: 320px;">
            <canvas id="historyChart"></canvas>
        </div>
    </div>

    <!-- Recommendations History for this mailbox -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                <span>Riwayat Rekomendasi Kuota</span>
            </h3>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal Rekomendasi</th>
                        <th>Kuota Saat Ini</th>
                        <th>Kuota Disarankan</th>
                        <th>Keterangan / Alasan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mailbox->recommendations as $rec)
                        <tr>
                            <td>{{ $rec->created_at ? $rec->created_at->format('d M Y, H:i') : '-' }}</td>
                            <td>{{ round($rec->current_quota_bytes / (1024*1024*1024), 2) }} GB</td>
                            <td><strong style="color: var(--primary);">{{ round($rec->recommended_quota_bytes / (1024*1024*1024), 2) }} GB</strong></td>
                            <td style="max-width: 400px; font-size: 12.5px;">{{ $rec->reason }}</td>
                            <td>
                                <span class="status-badge" style="background: {{ $rec->status === 'PENDING' ? '#fffbeb' : ($rec->status === 'APPROVED' ? '#ecfdf5' : '#f1f5f9') }};">
                                    {{ $rec->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 24px; color: var(--slate-400);">
                                Belum ada catatan rekomendasi untuk akun ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const historyCtx = document.getElementById('historyChart').getContext('2d');
    const snapshotDates = {!! json_encode($snapshots->pluck('recorded_at')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M'))) !!};
    const snapshotUsageGb = {!! json_encode($snapshots->map(fn($s) => round($s->usage_bytes / (1024*1024*1024), 2))) !!};
    const quotaGb = {{ round($mailbox->quota_bytes / (1024*1024*1024), 2) }};
    const quotaDataset = snapshotDates.map(() => quotaGb);

    new Chart(historyCtx, {
        type: 'line',
        data: {
            labels: snapshotDates,
            datasets: [
                {
                    label: 'Penggunaan Storage (GB)',
                    data: snapshotUsageGb,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 4,
                },
                {
                    label: 'Batas Kuota (GB)',
                    data: quotaDataset,
                    borderColor: '#ef4444',
                    borderDash: [5, 5],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    fill: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Gigabytes (GB)' },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { color: '#f8fafc' }
                }
            }
        }
    });
</script>
@endsection
