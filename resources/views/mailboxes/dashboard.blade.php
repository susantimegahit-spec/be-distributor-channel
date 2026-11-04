@extends('mailboxes.layout')

@section('title', 'Dashboard Overview')

@section('breadcrumb')
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Dashboard</span>
@endsection

@section('header_actions')
    <button type="button" class="btn btn-outline" onclick="openImportModal()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Import Data</span>
    </button>
    <form action="{{ route('mailboxes.sync') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="btn btn-emerald" onclick="this.innerHTML='Sedang Sync...'; this.disabled=true; this.form.submit();">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
            <span>Sync cPanel Live</span>
        </button>
    </form>
    <a href="{{ route('mailboxes.export.csv') }}" class="btn btn-outline">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Download CSV</span>
    </a>
@endsection

@section('content')
    <!-- Top Summary Banner -->
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 24px 28px; border-radius: var(--radius-xl); box-shadow: var(--shadow-card); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <h1 style="font-size: 20px; font-weight: 800; letter-spacing: -0.3px;">Monitoring Mailbox Server PT Susanti Megah</h1>
                <span style="font-size: 11px; background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 3px 8px; border-radius: 6px; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.4);">
                    cPanel Exim Engine
                </span>
            </div>
            <p style="font-size: 13.5px; color: #94a3b8; max-width: 650px; line-height: 1.5;">
                Pemantauan real-time kapasitas disk, tingkat pertumbuhan storage per departemen, dan peringatan preventif terhadap akun email yang mendekati batas kuota.
            </p>
        </div>
        <div style="text-align: right; font-size: 12px; color: #94a3b8;">
            <div>Terakhir Sinkronisasi Server:</div>
            <div style="font-size: 14px; font-weight: 700; color: #f8fafc; margin-top: 3px;">
                {{ $lastSync ? \Carbon\Carbon::parse($lastSync)->isoFormat('D MMMM Y, HH:mm') : 'Belum pernah sync' }}
            </div>
        </div>
    </div>

    <!-- KPI Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px;">
        <!-- Card 1: Total Mailbox -->
        <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <span style="font-size: 12.5px; font-weight: 700; color: var(--slate-500); text-transform: uppercase;">Total Mailbox</span>
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                </div>
            </div>
            <div style="font-size: 28px; font-weight: 800; color: var(--navy); line-height: 1;">{{ $stats['total_mailbox'] }}</div>
            <div style="font-size: 12px; color: var(--slate-500); margin-top: 6px;">Akun email terdaftar</div>
        </div>

        <!-- Card 2: Used Storage -->
        <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <span style="font-size: 12.5px; font-weight: 700; color: var(--slate-500); text-transform: uppercase;">Storage Digunakan</span>
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                </div>
            </div>
            <div style="font-size: 28px; font-weight: 800; color: var(--navy); line-height: 1;">{{ $stats['formatted_total_storage'] }}</div>
            <div style="font-size: 12px; color: var(--slate-500); margin-top: 6px;">Dari {{ $stats['formatted_total_quota'] }} total kuota</div>
        </div>

        <!-- Card 3: Global % -->
        <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <span style="font-size: 12.5px; font-weight: 700; color: var(--slate-500); text-transform: uppercase;">Utilisasi Kuota</span>
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #f5f3ff; color: #7c3aed; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                </div>
            </div>
            <div style="font-size: 28px; font-weight: 800; color: var(--navy); line-height: 1;">{{ $stats['average_usage_percentage'] }}%</div>
            <div class="progress-bar-container" style="margin-top: 8px;">
                <div class="progress-bar-fill {{ $stats['average_usage_percentage'] > 85 ? 'fill-warning' : 'fill-safe' }}" style="width: {{ min(100, $stats['average_usage_percentage']) }}%;"></div>
            </div>
        </div>

        <!-- Card 4: Critical & Warning Alerts -->
        <div class="card" style="padding: 20px; border-left: 4px solid var(--danger);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <span style="font-size: 12.5px; font-weight: 700; color: #991b1b; text-transform: uppercase;">Perhatian Segera</span>
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #fef2f2; color: #dc2626; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
            </div>
            <div style="display: flex; gap: 14px; align-items: baseline;">
                <div>
                    <span style="font-size: 26px; font-weight: 800; color: var(--danger);">{{ $stats['count_critical'] }}</span>
                    <span style="font-size: 11px; font-weight: 700; color: var(--slate-500); margin-left: 3px;">Kritis (>95%)</span>
                </div>
                <div style="border-left: 1px solid var(--slate-200); padding-left: 14px;">
                    <span style="font-size: 26px; font-weight: 800; color: var(--warning);">{{ $stats['count_warning'] }}</span>
                    <span style="font-size: 11px; font-weight: 700; color: var(--slate-500); margin-left: 3px;">Peringatan</span>
                </div>
            </div>
            <div style="margin-top: 8px;">
                <a href="{{ route('mailboxes.recommendations') }}" style="font-size: 12px; font-weight: 700; color: var(--primary); text-decoration: none;">
                    Lihat {{ $pendingRecommendationsCount }} Rekomendasi Quota &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Status Distribution Quick Pills -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;">
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-lg); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 11px; font-weight: 700; color: #065f46; text-transform: uppercase;">Aman (&lt;70%)</div>
                <div style="font-size: 20px; font-weight: 800; color: #047857; margin-top: 2px;">{{ $stats['count_safe'] }} Mailbox</div>
            </div>
            <span style="width: 12px; height: 12px; border-radius: 50%; background: #10b981;"></span>
        </div>

        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-lg); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase;">Monitoring (70-85%)</div>
                <div style="font-size: 20px; font-weight: 800; color: #1d4ed8; margin-top: 2px;">{{ $stats['count_monitoring'] }} Mailbox</div>
            </div>
            <span style="width: 12px; height: 12px; border-radius: 50%; background: #3b82f6;"></span>
        </div>

        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-lg); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 11px; font-weight: 700; color: #92400e; text-transform: uppercase;">Peringatan (85-95%)</div>
                <div style="font-size: 20px; font-weight: 800; color: #b45309; margin-top: 2px;">{{ $stats['count_warning'] }} Mailbox</div>
            </div>
            <span style="width: 12px; height: 12px; border-radius: 50%; background: #f59e0b;"></span>
        </div>

        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-lg); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase;">Kritis (&gt;95%)</div>
                <div style="font-size: 20px; font-weight: 800; color: #b91c1c; margin-top: 2px;">{{ $stats['count_critical'] }} Mailbox</div>
            </div>
            <span style="width: 12px; height: 12px; border-radius: 50%; background: #ef4444;"></span>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
        <!-- Chart 1: Donut Status Distribution -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a10 10 0 0 1 10 10h-10z"></path></svg>
                    <span>Distribusi Status Quota</span>
                </h3>
            </div>
            <div style="position: relative; height: 260px; display: flex; align-items: center; justify-content: center;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: Top 10 by Storage Size -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    <span>Top 10 Mailbox Terbesar (GB Digunakan)</span>
                </h3>
                <a href="{{ route('mailboxes.list') }}" style="font-size: 12.5px; font-weight: 600; color: var(--primary); text-decoration: none;">Lihat Semua &rarr;</a>
            </div>
            <div style="position: relative; height: 260px;">
                <canvas id="topStorageChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Attention Needed Table -->
    @if($alertMailboxes->count() > 0)
        <div class="card" style="border-top: 4px solid var(--danger);">
            <div class="card-header">
                <div>
                    <h3 class="card-title" style="color: #991b1b;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span>Daftar Mailbox Membutuhkan Penanganan Kuota</span>
                    </h3>
                    <p style="font-size: 12.5px; color: var(--slate-500); margin-top: 4px;">
                        Mailbox berikut telah melampaui batas peringatan 85% atau kritis 95% dan berisiko mengalami gagal terima email (bounce).
                    </p>
                </div>
                <a href="{{ route('mailboxes.recommendations') }}" class="btn btn-outline" style="font-size: 12px;">
                    Review Rekomendasi
                </a>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Akun Email</th>
                            <th>PIC / Pengguna</th>
                            <th>Departemen</th>
                            <th>Penggunaan / Kuota</th>
                            <th>Persentase</th>
                            <th>Status</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($alertMailboxes as $box)
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--navy);">{{ $box->email }}</div>
                                    <div style="font-size: 11px; color: var(--slate-400);">Domain: {{ $box->domain }}</div>
                                </td>
                                <td>{{ $box->user_name ?? '-' }}</td>
                                <td>
                                    @if($box->department)
                                        <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 600; font-size: 12px;">
                                            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: {{ $box->department->color }};"></span>
                                            {{ $box->department->name }}
                                        </span>
                                    @else
                                        <span style="color: var(--slate-400); font-size: 12px;">Unassigned</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="font-weight: 600;">{{ $box->formatted_usage }} / {{ $box->formatted_quota }}</div>
                                    <div class="progress-bar-container" style="margin-top: 4px; width: 140px;">
                                        <div class="progress-bar-fill fill-{{ strtolower($box->status) }}" style="width: {{ min(100, (float)$box->usage_percentage) }}%;"></div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 800; font-size: 14px; color: {{ $box->status === 'CRITICAL' ? 'var(--danger)' : 'var(--warning)' }};">
                                        {{ $box->usage_percentage }}%
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-badge-{{ strtolower($box->status) }}">
                                        {{ $box->status }}
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="{{ route('mailboxes.show', $box->id) }}" class="btn btn-outline" style="padding: 4px 10px; font-size: 11.5px;">
                                        Detail & Analisis
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Department Distribution Grid -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>Penggunaan Storage per Departemen</span>
            </h3>
            <a href="{{ route('mailboxes.departments') }}" style="font-size: 12.5px; font-weight: 600; color: var(--primary); text-decoration: none;">Kelola Departemen &rarr;</a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
            @foreach($departments as $dept)
                <div style="background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: var(--radius-lg); padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="width: 10px; height: 10px; border-radius: 50%; background: {{ $dept['color'] }};"></span>
                            <span style="font-weight: 700; font-size: 13.5px; color: var(--navy);">{{ $dept['name'] }}</span>
                        </div>
                        <span style="font-size: 11.5px; font-weight: 700; color: var(--slate-500); background: #fff; padding: 2px 8px; border-radius: 6px; border: 1px solid var(--slate-200);">
                            {{ $dept['count'] }} email
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--slate-600); margin-bottom: 6px;">
                        <span>{{ $dept['total_used_gb'] }} GB digunakan</span>
                        <span style="font-weight: 700;">{{ $dept['usage_percent'] }}% dari {{ $dept['total_quota_gb'] }} GB</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: {{ min(100, $dept['usage_percent']) }}%; background-color: {{ $dept['color'] }};"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@section('scripts')
<script>
    // Status Donut Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Aman (<70%)', 'Monitoring (70-85%)', 'Peringatan (85-95%)', 'Kritis (>95%)'],
            datasets: [{
                data: [
                    {{ $stats['count_safe'] }},
                    {{ $stats['count_monitoring'] }},
                    {{ $stats['count_warning'] }},
                    {{ $stats['count_critical'] }}
                ],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 12,
                        font: { family: 'Plus Jakarta Sans', size: 11 }
                    }
                }
            },
            cutout: '68%'
        }
    });

    // Top 10 Storage Bar Chart
    const storageCtx = document.getElementById('topStorageChart').getContext('2d');
    const storageLabels = {!! json_encode($topStorage->pluck('email')->map(fn($e) => explode('@', $e)[0])) !!};
    const storageData = {!! json_encode($topStorage->map(fn($m) => round($m->current_usage_bytes / (1024*1024*1024), 2))) !!};

    new Chart(storageCtx, {
        type: 'bar',
        data: {
            labels: storageLabels,
            datasets: [{
                label: 'Digunakan (GB)',
                data: storageData,
                backgroundColor: '#3b82f6',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Gigabytes (GB)' },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>
@endsection
