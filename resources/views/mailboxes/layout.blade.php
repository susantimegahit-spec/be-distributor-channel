<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Mailbox Monitoring') - PT Susanti Megah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --navy: #0f172a;
            --slate-900: #0f172a;
            --slate-800: #1e293b;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-400: #94a3b8;
            --slate-300: #cbd5e1;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
            --slate-50: #f8fafc;
            
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #8b5cf6;
            
            --sidebar-width: 260px;
            --header-height: 70px;
            
            --radius-xl: 18px;
            --radius-lg: 14px;
            --radius-md: 10px;
            --radius-sm: 6px;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-card: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.02);
            --shadow-card-hover: 0 20px 30px -10px rgba(15, 23, 42, 0.08), 0 10px 15px -3px rgba(15, 23, 42, 0.03);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: var(--slate-50);
            color: var(--navy);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: #ffffff;
            border-right: 1px solid var(--slate-200);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid var(--slate-100);
            gap: 12px;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            border-radius: 10px;
            color: #ffffff;
            font-weight: 800;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
            flex-shrink: 0;
        }

        .brand-info {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            font-size: 14px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .brand-sub {
            font-size: 11px;
            font-weight: 600;
            color: var(--success);
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        .sidebar-content {
            flex: 1;
            padding: 20px 14px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .nav-group-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--slate-400);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 0 12px 10px 12px;
        }

        .nav-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: var(--radius-md);
            color: var(--slate-600);
            text-decoration: none;
            font-weight: 600;
            font-size: 13.5px;
            transition: all 0.2s ease;
        }

        .nav-item a:hover {
            background-color: var(--slate-100);
            color: var(--navy);
        }

        .nav-item.active a {
            background-color: #ecfdf5;
            color: #065f46;
            font-weight: 700;
        }

        .nav-item.active a svg {
            color: #059669;
        }

        .nav-badge {
            margin-left: auto;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
        }

        .badge-red {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .badge-emerald {
            background: #ecfdf5;
            color: #065f46;
        }

        /* Sidebar Footer */
        .sidebar-user {
            padding: 12px 14px;
            background-color: var(--slate-50);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--slate-200);
            margin-top: 16px;
        }

        .user-details {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #059669;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
        }

        .user-meta-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
            line-height: 1.2;
        }

        .user-meta-role {
            font-size: 11px;
            color: var(--slate-500);
        }

        /* Main Wrapper */
        .main-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Top Header */
        .top-header {
            height: var(--header-height);
            background: #ffffff;
            border-bottom: 1px solid var(--slate-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            color: var(--slate-500);
        }

        .breadcrumbs a {
            color: var(--slate-600);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-sep {
            color: var(--slate-300);
        }

        .breadcrumb-current {
            color: var(--navy);
            font-weight: 700;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Content Body */
        .content-body {
            flex: 1;
            padding: 28px 32px;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            background-color: var(--primary);
            color: #fff;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-emerald {
            background-color: #059669;
            color: #fff;
        }
        .btn-emerald:hover {
            background-color: #047857;
        }

        .btn-outline {
            background: #fff;
            border-color: var(--slate-300);
            color: var(--slate-700);
        }
        .btn-outline:hover {
            background-color: var(--slate-50);
            border-color: var(--slate-400);
        }

        .btn-danger {
            background-color: #ef4444;
            color: #fff;
        }
        .btn-danger:hover {
            background-color: #dc2626;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-badge-safe {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .status-badge-monitoring {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .status-badge-warning {
            background-color: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .status-badge-critical {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Progress Bars */
        .progress-bar-container {
            width: 100%;
            height: 7px;
            background-color: var(--slate-200);
            border-radius: 9999px;
            overflow: hidden;
            display: flex;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.3s ease;
        }

        .fill-safe { background-color: #10b981; }
        .fill-monitoring { background-color: #3b82f6; }
        .fill-warning { background-color: #f59e0b; }
        .fill-critical { background-color: #ef4444; }

        /* Card Styles */
        .card {
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-card);
            padding: 24px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--navy);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Alerts */
        .alert {
            padding: 12px 18px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Table */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }

        .data-table th {
            background-color: var(--slate-50);
            color: var(--slate-600);
            font-weight: 700;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid var(--slate-200);
            white-space: nowrap;
        }

        .data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--slate-100);
            color: var(--slate-700);
            vertical-align: middle;
        }

        .data-table tr:hover td {
            background-color: #f8fafc;
        }

        /* Site Footer */
        .site-footer {
            background: #ffffff;
            border-top: 1px solid var(--slate-200);
            padding: 18px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--slate-500);
            margin-top: auto;
        }

        .site-footer a {
            color: var(--slate-600);
            text-decoration: none;
            font-weight: 600;
        }

        .site-footer a:hover {
            color: var(--primary);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 16px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            border-radius: var(--radius-xl);
            max-width: 520px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            border: 1px solid var(--slate-200);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--slate-100);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--navy);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 16px 24px;
            background-color: var(--slate-50);
            border-top: 1px solid var(--slate-100);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--slate-700);
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 9px 13px;
            border-radius: var(--radius-md);
            border: 1px solid var(--slate-300);
            font-size: 13.5px;
            color: var(--slate-800);
            outline: none;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-wrapper { margin-left: 0; }
            .top-header { padding: 0 16px; }
            .content-body { padding: 16px; }
        }

        @media print {
            .sidebar, .top-header, .site-footer, .no-print {
                display: none !important;
            }
            .main-wrapper {
                margin-left: 0 !important;
            }
            .content-body {
                padding: 0 !important;
                max-width: 100% !important;
            }
            body {
                background: #ffffff !important;
            }
        }
    </style>
    @yield('styles')
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand-logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
            </div>
            <div class="brand-info">
                <span class="brand-name">SUSANTI MEGAH</span>
                <span class="brand-sub">Mailbox Monitor</span>
            </div>
        </div>

        <div class="sidebar-content">
            <div>
                <div class="nav-group-label">Menu Monitoring</div>
                <ul class="nav-list">
                    <li class="nav-item {{ request()->routeIs('mailboxes.dashboard') ? 'active' : '' }}">
                        <a href="{{ route('mailboxes.dashboard') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            <span>Overview Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item {{ request()->routeIs('mailboxes.list') || request()->routeIs('mailboxes.show') ? 'active' : '' }}">
                        <a href="{{ route('mailboxes.list') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="8" y1="6" x2="21" y2="6"></line>
                                <line x1="8" y1="12" x2="21" y2="12"></line>
                                <line x1="8" y1="18" x2="21" y2="18"></line>
                                <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                <line x1="3" y1="18" x2="3.01" y2="18"></line>
                            </svg>
                            <span>Daftar Mailbox</span>
                        </a>
                    </li>
                    <li class="nav-item {{ request()->routeIs('mailboxes.departments') ? 'active' : '' }}">
                        <a href="{{ route('mailboxes.departments') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <span>Mapping Departemen</span>
                        </a>
                    </li>
                    <li class="nav-item {{ request()->routeIs('mailboxes.recommendations') ? 'active' : '' }}">
                        <a href="{{ route('mailboxes.recommendations') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span>Rekomendasi Quota</span>
                            @php
                                $pendingRecs = \App\Models\MailboxQuotaRecommendation::where('status', 'PENDING')->count();
                            @endphp
                            @if($pendingRecs > 0)
                                <span class="nav-badge badge-red">{{ $pendingRecs }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item {{ request()->routeIs('mailboxes.reports') ? 'active' : '' }}">
                        <a href="{{ route('mailboxes.reports') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                            <span>Laporan Eksekutif</span>
                        </a>
                    </li>
                </ul>

                <div class="nav-group-label" style="margin-top: 24px;">Pengaturan & Navigasi</div>
                <ul class="nav-list">
                    <li class="nav-item {{ request()->routeIs('mailboxes.settings') ? 'active' : '' }}">
                        <a href="{{ route('mailboxes.settings') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            <span>Konfigurasi & cPanel</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ url('/monitoringsm/hub') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            <span>Kembali ke Admin Hub</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-user">
                <div class="user-details">
                    <div class="user-avatar">IT</div>
                    <div>
                        <div class="user-meta-name">Admin IT SM</div>
                        <div class="user-meta-role">cPanel Mail Server</div>
                    </div>
                </div>
                <a href="{{ url('/monitoringsm/logout') }}" title="Logout" style="color: var(--slate-400); text-decoration: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- MAIN WRAPPER -->
    <div class="main-wrapper">
        <!-- Top Header -->
        <header class="top-header">
            <div class="breadcrumbs">
                <a href="{{ url('/monitoringsm/hub') }}">SMESTA Hub</a>
                <span class="breadcrumb-sep">/</span>
                <a href="{{ route('mailboxes.dashboard') }}">Mailbox Monitoring</a>
                @yield('breadcrumb')
            </div>

            <div class="header-actions">
                @yield('header_actions')
            </div>
        </header>

        <!-- Main Content -->
        <main class="content-body">
            @if(session('success'))
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('content')
        </main>

        <!-- Site Footer -->
        <footer class="site-footer">
            <div>&copy; {{ date('Y') }} PT Susanti Megah &bull; Mailbox Capacity & Quota Intelligence System</div>
            <div>
                Server cPanel Exim &bull; <a href="{{ route('mailboxes.settings') }}">Pengaturan Sinkronisasi</a>
            </div>
        </footer>
    </div>

    <!-- Modal Import Data -->
    <div class="modal-overlay" id="importModal">
        <div class="modal-box">
            <form action="{{ route('mailboxes.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title">Import Data Mailbox</h3>
                    <button type="button" onclick="closeImportModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--slate-400);">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="font-size: 13px; color: var(--slate-600); margin-bottom: 16px;">
                        Upload file export dari cPanel atau backup internal dalam format <strong>CSV</strong> atau <strong>JSON</strong> (format <code>Email::list_pops_with_disk</code>).
                    </p>
                    <div class="form-group">
                        <label class="form-label">Pilih File (.csv atau .json)</label>
                        <input type="file" name="import_file" class="form-control" accept=".csv,.json" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeImportModal()">Batal</button>
                    <button type="submit" class="btn btn-emerald">Mulai Import</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openImportModal() {
            document.getElementById('importModal').classList.add('active');
        }
        function closeImportModal() {
            document.getElementById('importModal').classList.remove('active');
        }
    </script>
    @yield('scripts')
</body>
</html>
