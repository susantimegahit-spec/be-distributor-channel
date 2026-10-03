<!DOCTYPE html>
<html lang="en" class="{{ $theme == 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('health::notifications.health_results') }} - PT Susanti Megah</title>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('smesta_health_theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
            } else if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <style>
        :root {
            --bg-body: #f1f5f9;
            --bg-card: #ffffff;
            --bg-subcard: #f8fafc;
            --border-color: #e2e8f0;
            --text-title: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --track-color: #e2e8f0;
            --shadow-card: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            --color-emerald: #10b981;
            --color-emerald-bg: #ecfdf5;
            --color-emerald-text: #047857;
            --color-amber: #f59e0b;
            --color-amber-bg: #fffbeb;
            --color-amber-text: #b45309;
            --color-rose: #f43f5e;
            --color-rose-bg: #fff1f2;
            --color-rose-text: #be123c;
            --color-indigo: #6366f1;
            --color-indigo-hover: #4f46e5;
        }

        html.dark {
            --bg-body: #0b1120;
            --bg-card: #151f32;
            --bg-subcard: #0d1527;
            --border-color: #243048;
            --text-title: #f8fafc;
            --text-body: #cbd5e1;
            --text-muted: #8493a8;
            --track-color: #243048;
            --shadow-card: 0 10px 30px -5px rgba(0, 0, 0, 0.5);
            --color-emerald-bg: rgba(16, 185, 129, 0.15);
            --color-emerald-text: #34d399;
            --color-amber-bg: rgba(245, 158, 11, 0.15);
            --color-amber-text: #fbbf24;
            --color-rose-bg: rgba(244, 63, 94, 0.15);
            --color-rose-text: #fb7185;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-body);
            min-height: 100vh;
            padding: 24px 16px 48px;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        .container {
            max-width: 1240px;
            margin: 0 auto;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 12px 20px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-card);
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-title);
            transition: all 0.2s ease;
        }

        .btn:hover {
            border-color: var(--color-indigo);
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--color-indigo);
            border-color: var(--color-indigo);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            background: var(--color-indigo-hover);
            border-color: var(--color-indigo-hover);
        }

        /* Header */
        .page-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-title);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .pulse-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 10px 0;
        }

        .pulse-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
        }

        .pulse-status.danger {
            color: var(--color-rose);
            font-weight: 700;
        }

        .dot-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--color-emerald);
        }

        .pulse-status.danger .dot-pulse {
            background-color: var(--color-rose);
            animation: ping 1.5s infinite;
        }

        @keyframes ping {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.6); opacity: 0.4; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Card Container */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 28px;
            box-shadow: var(--shadow-card);
            margin-bottom: 36px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-title-group h2 {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-title);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .badge-count {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            background: rgba(99, 102, 241, 0.12);
            color: var(--color-indigo);
            border: 1px solid rgba(99, 102, 241, 0.25);
        }

        /* Partition Grid */
        .partition-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        @media (max-width: 980px) {
            .partition-grid {
                grid-template-columns: 1fr;
            }
        }

        .partition-card {
            background: var(--bg-subcard);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .partition-card:hover {
            border-color: var(--color-indigo);
            box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.1);
        }

        .p-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
        }

        .p-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-title);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .p-mount {
            font-size: 12px;
            color: var(--text-muted);
            font-family: monospace;
            display: block;
            margin-top: 3px;
        }

        .p-status {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .status-normal {
            background: var(--color-emerald-bg);
            color: var(--color-emerald-text);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-warning {
            background: var(--color-amber-bg);
            color: var(--color-amber-text);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-critical {
            background: var(--color-rose-bg);
            color: var(--color-rose-text);
            border: 1px solid rgba(244, 63, 94, 0.3);
        }

        /* Donut Chart Container */
        .chart-box {
            position: relative;
            width: 170px;
            height: 170px;
            margin: 10px auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chart-inner-text {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            text-align: center;
        }

        .chart-percent {
            font-size: 26px;
            font-weight: 900;
            color: var(--text-title);
            line-height: 1.1;
        }

        .chart-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Horizontal Allocation Bar */
        .bar-chart-section {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--border-color);
        }

        .bar-header {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .progress-bar-container {
            width: 100%;
            height: 10px;
            background: var(--track-color);
            border-radius: 999px;
            overflow: hidden;
            display: flex;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.6s ease;
        }

        /* Metric KPI Boxes */
        .kpi-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 16px;
        }

        .kpi-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 10px 12px;
        }

        .kpi-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .kpi-value {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-title);
            margin-top: 2px;
        }

        .kpi-sub {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Table */
        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            margin-top: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: var(--bg-subcard);
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-body);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: var(--bg-subcard);
        }

        .tb-storage-name {
            font-weight: 700;
            color: var(--text-title);
        }

        .tb-storage-sub {
            font-size: 11px;
            font-family: monospace;
            color: var(--text-muted);
        }

        .tb-mono {
            font-family: monospace;
            font-weight: 700;
            font-size: 13px;
            color: var(--text-title);
        }

        /* Health Check Cards Grid */
        .section-title-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 0 4px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
        }

        .health-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        @media (max-width: 980px) {
            .health-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .health-grid {
                grid-template-columns: 1fr;
            }
        }

        .health-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 18px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: var(--shadow-card);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .health-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -4px rgba(0, 0, 0, 0.15);
        }

        .health-card-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-title);
            margin-bottom: 4px;
        }

        .health-card-desc {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.4;
            word-break: break-word;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            max-width: 460px;
            margin: 40px auto;
        }

        .empty-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--color-amber-bg);
            color: var(--color-amber);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--text-title);
            margin-bottom: 8px;
        }

        .empty-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 20px;
        }
    </style>
    {{ $assets }}
</head>

<body>
    <div class="container">
        {{-- Top Navigation Bar --}}
        <div class="top-bar">
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="{{ url('/monitoringsm/hub') }}" class="btn" title="Kembali ke Admin Hub">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Admin Hub
                </a>
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
                {{-- Theme Switcher Button --}}
                <button id="themeToggleBtn" type="button" class="btn" title="Ganti Mode Tampilan (Light / Dark)">
                    <span id="themeIconSun" style="display: inline-flex; align-items: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #f59e0b;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    </span>
                    <span id="themeLabelText">Mode</span>
                </button>

                {{-- Refresh Button --}}
                <a href="{{ url('/monitoringsm/health?fresh') }}" class="btn btn-primary" title="Jalankan Uji Kesehatan Segar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    Refresh Checks
                </a>
            </div>
        </div>

        {{-- Main Page Header --}}
        <div class="page-header">
            <h1 class="page-title">{{ __('health::notifications.laravel_health') }}</h1>
            <div class="pulse-container">
                <x-health-logo/>
            </div>
            @if ($lastRanAt)
                <div class="pulse-status {{ $lastRanAt->diffInMinutes() > 5 ? 'danger' : '' }}">
                    <span class="dot-pulse"></span>
                    {{ __('health::notifications.check_results_from') }} {{ $lastRanAt->diffForHumans() }}
                </div>
            @endif
        </div>

        @php
            $partitions = \App\Services\ServerStorageService::getStorageBreakdown(['/', '/home', '/tmp']);
        @endphp

        {{-- Section 1: Server Storage & Partition Analytics --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title-group">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-indigo);"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                        Server Storage & Partition Analytics
                    </h2>
                    <p class="card-subtitle">Pemantauan visual ruang penyimpanan sistem operasi, direktori user cPanel, dan berkas temporary</p>
                </div>
                <span class="badge-count">3 Partisi Aktif</span>
            </div>

            {{-- Partition Cards Grid --}}
            <div class="partition-grid">
                @foreach ($partitions as $idx => $p)
                    <div class="partition-card">
                        {{-- Top Header --}}
                        <div class="p-head">
                            <div>
                                <div class="p-title">
                                    <span style="display:inline-block; width:9px; height:9px; border-radius:50%; background-color: {{ $p['used_percent'] >= 90 ? 'var(--color-rose)' : ($p['used_percent'] >= 80 ? 'var(--color-amber)' : 'var(--color-emerald)') }};"></span>
                                    {{ $p['name'] }}
                                </div>
                                <span class="p-mount">Mount: {{ $p['path'] }}</span>
                            </div>
                            <span class="p-status {{ $p['used_percent'] >= 90 ? 'status-critical' : ($p['used_percent'] >= 80 ? 'status-warning' : 'status-normal') }}">
                                {{ $p['status'] }}
                            </span>
                        </div>

                        {{-- Primary Circular Pie/Donut Chart --}}
                        <div class="chart-box">
                            <canvas id="donut-partition-{{ $idx }}"></canvas>
                            <div class="chart-inner-text">
                                <div class="chart-percent">{{ $p['used_percent'] }}%</div>
                                <div class="chart-label">TERPAKAI</div>
                            </div>
                        </div>

                        {{-- Secondary Horizontal Linear Allocation Bar Chart --}}
                        <div class="bar-chart-section">
                            <div class="bar-header">
                                <span>Distribusi Alokasi</span>
                                <span style="font-family: monospace;">Total: {{ $p['total_formatted'] }}</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: {{ $p['used_percent'] }}%; background-color: {{ $p['used_percent'] >= 90 ? 'var(--color-rose)' : ($p['used_percent'] >= 80 ? 'var(--color-amber)' : 'var(--color-emerald)') }};"></div>
                            </div>
                        </div>

                        {{-- Metric KPI Boxes --}}
                        <div class="kpi-grid">
                            <div class="kpi-item">
                                <div class="kpi-label">Terpakai</div>
                                <div class="kpi-value">{{ $p['used_formatted'] }}</div>
                                <div class="kpi-sub">{{ $p['used_percent'] }}% dari total</div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-label">Sisa Ruang</div>
                                <div class="kpi-value" style="color: var(--color-emerald);">{{ $p['free_formatted'] }}</div>
                                <div class="kpi-sub">{{ $p['free_percent'] }}% tersisa</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Storage Breakdown Data Table --}}
            <div style="margin-top: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <h3 style="font-size: 15px; font-weight: 800; color: var(--text-title);">Rincian Lengkap Kapasitas Partisi</h3>
                    <span style="font-size: 12px; color: var(--text-muted);">Batas Aman: &lt; 80% | Warning: 80% | Kritis: 90%</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Storage</th>
                                <th>Total</th>
                                <th>Terpakai</th>
                                <th>Sisa</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($partitions as $p)
                                <tr>
                                    <td>
                                        <div class="tb-storage-name">{{ $p['name'] }}</div>
                                        <div class="tb-storage-sub">{{ $p['path'] }}</div>
                                    </td>
                                    <td>
                                        <span class="tb-mono">{{ $p['total_formatted'] }}</span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="tb-mono">{{ $p['used_formatted'] }}</span>
                                            <span style="font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 6px; background: {{ $p['used_percent'] >= 90 ? 'var(--color-rose-bg)' : ($p['used_percent'] >= 80 ? 'var(--color-amber-bg)' : 'var(--color-emerald-bg)') }}; color: {{ $p['used_percent'] >= 90 ? 'var(--color-rose-text)' : ($p['used_percent'] >= 80 ? 'var(--color-amber-text)' : 'var(--color-emerald-text)') }};">
                                                {{ $p['used_percent'] }}%
                                            </span>
                                        </div>
                                        <div style="width: 140px; height: 6px; background: var(--track-color); border-radius: 999px; margin-top: 6px; overflow: hidden;">
                                            <div style="width: {{ $p['used_percent'] }}%; height: 100%; border-radius: 999px; background: {{ $p['used_percent'] >= 90 ? 'var(--color-rose)' : ($p['used_percent'] >= 80 ? 'var(--color-amber)' : 'var(--color-emerald)') }};"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="tb-mono" style="color: var(--color-emerald);">{{ $p['free_formatted'] }}</div>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">{{ $p['free_percent'] }}% ruang bebas</div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="p-status {{ $p['used_percent'] >= 90 ? 'status-critical' : ($p['used_percent'] >= 80 ? 'status-warning' : 'status-normal') }}">
                                            {{ $p['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Section 2: Spatie Health Check Cards --}}
        <div>
            <div class="section-title-wrap">
                <span class="section-title">System & Service Health Status</span>
                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">
                    Total: {{ count($checkResults?->storedCheckResults ?? []) }} Checks
                </span>
            </div>

            @if (count($checkResults?->storedCheckResults ?? []))
                <div class="health-grid">
                    @foreach ($checkResults->storedCheckResults as $result)
                        <div class="health-card">
                            <div style="flex-shrink: 0; padding-top: 2px;">
                                <x-health-status-indicator :result="$result" />
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div class="health-card-title">{{ $result->label }}</div>
                                <div class="health-card-desc">
                                    @if (!empty($result->notificationMessage))
                                        {{ $result->notificationMessage }}
                                    @else
                                        {{ $result->shortSummary }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    </div>
                    <div class="empty-title">Belum Ada Riwayat Pemeriksaan</div>
                    <div class="empty-desc">Pemeriksaan kesehatan sistem belum dijalankan pada server ini. Silakan klik tombol di bawah untuk menjalankan pemeriksaan.</div>
                    <a href="{{ url('/monitoringsm/health?fresh') }}" class="btn btn-primary" style="display: inline-flex;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        Jalankan Pemeriksaan Sekarang
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Chart.js Rendering & Theme Switcher Script --}}
    <script>
        const partitionsData = @json($partitions);
        const chartInstances = [];

        function getTrackColor() {
            const isDark = document.documentElement.classList.contains('dark');
            return isDark ? '#243048' : '#e2e8f0';
        }

        function renderAllCharts() {
            chartInstances.forEach(c => c.destroy());
            chartInstances.length = 0;

            const trackColor = getTrackColor();

            partitionsData.forEach((p, idx) => {
                let usedColor = '#10b981'; // normal
                if (p.used_percent >= 90) {
                    usedColor = '#f43f5e'; // critical
                } else if (p.used_percent >= 80) {
                    usedColor = '#f59e0b'; // warning
                }

                // If 0% used, provide a tiny slice for visual aesthetics
                const chartUsedBytes = p.used_bytes > 0 ? p.used_bytes : (p.total_bytes * 0.001);

                const canvas = document.getElementById(`donut-partition-${idx}`);
                if (canvas) {
                    const donutChart = new Chart(canvas, {
                        type: 'doughnut',
                        data: {
                            labels: ['Terpakai (' + p.used_formatted + ')', 'Sisa (' + p.free_formatted + ')'],
                            datasets: [{
                                data: [chartUsedBytes, p.free_bytes],
                                backgroundColor: [usedColor, trackColor],
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            cutout: '76%',
                            animation: { duration: 500 },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            const label = context.label || '';
                                            const percent = context.dataIndex === 0 ? p.used_percent : p.free_percent;
                                            return ` ${label} : ${percent}%`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                    chartInstances.push(donutChart);
                }
            });
        }

        // Theme Toggle Controller
        document.addEventListener('DOMContentLoaded', function () {
            renderAllCharts();

            const themeToggleBtn = document.getElementById('themeToggleBtn');
            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function () {
                    const isDark = document.documentElement.classList.contains('dark');
                    if (isDark) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('smesta_health_theme', 'light');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('smesta_health_theme', 'dark');
                    }
                    renderAllCharts();
                });
            }
        });
    </script>
</body>
</html>
