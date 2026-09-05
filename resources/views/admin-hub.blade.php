<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Hub - PT Susanti Megah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            --surface: #ffffff;
            
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

        /* ---------------------------------------------------- */
        /* SIDEBAR STYLING                                      */
        /* ---------------------------------------------------- */
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
            padding: 0 24px;
            border-bottom: 1px solid var(--slate-100);
            gap: 12px;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 10px;
            color: #ffffff;
            font-weight: 800;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.15);
            flex-shrink: 0;
        }

        .brand-info {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            font-size: 15px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.3px;
            line-height: 1.1;
        }

        .brand-sub {
            font-size: 11px;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        .sidebar-content {
            flex: 1;
            padding: 24px 16px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .nav-group-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--slate-400);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 0 12px 8px 12px;
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
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-item a svg {
            color: var(--slate-400);
            transition: color 0.2s ease;
            flex-shrink: 0;
        }

        .nav-item a:hover {
            color: var(--navy);
            background-color: var(--slate-100);
        }

        .nav-item a:hover svg {
            color: var(--navy);
        }

        .nav-item.active a {
            background-color: var(--primary-light);
            color: var(--primary);
            font-weight: 700;
        }

        .nav-item.active a svg {
            color: var(--primary);
        }

        .nav-badge {
            margin-left: auto;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 9999px;
        }

        .badge-purple { background: #f3e8ff; color: #9333ea; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-amber { background: #fef3c7; color: #b45309; }
        .badge-green { background: #dcfce7; color: #15803d; }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--slate-100);
            background-color: var(--slate-50);
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-md);
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-meta {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 11px;
            color: var(--slate-500);
        }

        .btn-logout-icon {
            color: var(--slate-400);
            padding: 6px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-logout-icon:hover {
            color: var(--danger);
            background-color: #fee2e2;
        }

        /* ---------------------------------------------------- */
        /* MAIN LAYOUT & HEADER                                 */
        /* ---------------------------------------------------- */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--slate-50);
        }

        .top-header {
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--slate-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .header-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .breadcrumb-root {
            color: var(--slate-400);
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
            gap: 16px;
        }

        .system-status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            color: #15803d;
        }

        .status-dot-pulse {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
            animation: pulseDot 2s infinite;
        }

        .clock-badge {
            font-size: 12px;
            font-weight: 600;
            color: var(--slate-600);
            background-color: var(--slate-100);
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--slate-200);
        }

        /* ---------------------------------------------------- */
        /* PAGE CONTENT STYLING                                 */
        /* ---------------------------------------------------- */
        .content-body {
            flex: 1;
            padding: 32px;
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* Banner Welcome */
        .welcome-banner {
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-xl);
            padding: 30px 32px;
            box-shadow: var(--shadow-card);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #2563eb 0%, #10b981 100%);
        }

        .welcome-text h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .welcome-text p {
            font-size: 14px;
            color: var(--slate-600);
            max-width: 680px;
            line-height: 1.6;
        }

        .quick-meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--slate-50);
            border: 1px solid var(--slate-200);
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 600;
            color: var(--slate-700);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon-wrapper {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon-blue { background: #eff6ff; color: #2563eb; }
        .stat-icon-emerald { background: #ecfdf5; color: #059669; }
        .stat-icon-purple { background: #faf5ff; color: #9333ea; }
        .stat-icon-amber { background: #fffbeb; color: #d97706; }

        .stat-content {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--slate-400);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 800;
            color: var(--navy);
            margin-top: 2px;
        }

        /* Main Cards Grid */
        .section-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .module-card {
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-xl);
            padding: 28px;
            box-shadow: var(--shadow-card);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .module-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-card-hover);
            border-color: var(--slate-300);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .module-icon-box {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: transform 0.25s ease;
        }

        .module-card:hover .module-icon-box {
            transform: scale(1.05);
        }

        .icon-box-purple { background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); color: #7e22ce; }
        .icon-box-blue { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); color: #1d4ed8; }
        .icon-box-amber { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #b45309; }
        .icon-box-emerald { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); color: #047857; }

        .card-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.3px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-desc {
            font-size: 13px;
            color: var(--slate-600);
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .card-bottom-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 1px solid var(--slate-100);
        }

        .action-link-text {
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: gap 0.2s ease;
        }

        .module-card:hover .action-link-text {
            gap: 10px;
        }

        .card-purple-action { color: #7e22ce; }
        .card-blue-action { color: #1d4ed8; }
        .card-amber-action { color: #b45309; }
        .card-emerald-action { color: #047857; }

        .system-spec-bar {
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--slate-600);
        }

        .spec-items {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .spec-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .spec-item strong {
            color: var(--navy);
            font-weight: 700;
        }

        /* ---------------------------------------------------- */
        /* FOOTER                                               */
        /* ---------------------------------------------------- */
        .site-footer {
            background: #ffffff;
            border-top: 1px solid var(--slate-200);
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--slate-500);
            margin-top: auto;
        }

        .footer-links {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .footer-links a {
            color: var(--slate-600);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        /* Mobile Responsive */
        .btn-sidebar-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--navy);
            padding: 6px;
            border-radius: 6px;
        }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .cards-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-wrapper { margin-left: 0; }
            .btn-sidebar-toggle { display: block; }
            .top-header { padding: 0 16px; }
            .content-body { padding: 20px 16px; }
            .stats-grid { grid-template-columns: 1fr; }
            .welcome-banner { flex-direction: column; align-items: flex-start; gap: 16px; }
            .system-spec-bar { flex-direction: column; align-items: flex-start; gap: 12px; }
            .site-footer { flex-direction: column; gap: 12px; text-align: center; }
        }

        @keyframes pulseDot {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
    </style>
</head>
<body>

    <!-- -------------------------------------------------- -->
    <!-- SIDEBAR COMPONENT                                  -->
    <!-- -------------------------------------------------- -->
    <aside class="sidebar" id="sidebar">
        <!-- Sidebar Brand -->
        <div class="sidebar-header">
            <div class="brand-logo">SM</div>
            <div class="brand-info">
                <span class="brand-name">SUSANTI MEGAH</span>
                <span class="brand-sub">SMESTA Hub</span>
            </div>
        </div>

        <!-- Sidebar Navigation -->
        <div class="sidebar-content">
            <div>
                <div class="nav-group-label">Pusat Navigasi</div>
                <ul class="nav-list">
                    <li class="nav-item active">
                        <a href="{{ url('/monitoringsm/hub') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            <span>Dashboard Hub</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ url('/reporting/tasks') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                            <span>ClickUp Tasks</span>
                            <span class="nav-badge badge-purple">Baru</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ url('/monitoringsm') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                            </svg>
                            <span>Pulse Server</span>
                            <span class="nav-badge badge-blue">Live</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ url('/monitoringsm/api-keys') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 2l-2 2m-1.5 1.5L16 7m-1.5 1.5L13 10m-1.5 1.5L10 13l-4 4-2-2-2 2 4 4 2-2 4-4 1.5-1.5M19 5l2 2-7 7-2-2 7-7z"></path>
                            </svg>
                            <span>B2B API Keys</span>
                            <span class="nav-badge badge-amber">Sec</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <div class="nav-group-label">Dokumentasi & Standar</div>
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="{{ url('/docs') }}" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                            <span>Swagger API Docs</span>
                            <span class="nav-badge badge-green">v1.0</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Sidebar User Footer -->
        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar">AD</div>
                <div class="user-meta">
                    <div class="user-name">adminsm</div>
                    <div class="user-role">System Administrator</div>
                </div>
                <a href="{{ url('/monitoringsm/logout') }}" class="btn-logout-icon" title="Keluar dari sesi admin">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- -------------------------------------------------- -->
    <!-- MAIN CONTENT WRAPPER                               -->
    <!-- -------------------------------------------------- -->
    <div class="main-wrapper">
        
        <!-- Top Header -->
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 14px;">
                <button class="btn-sidebar-toggle" onclick="toggleSidebar()" title="Toggle Menu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div class="header-breadcrumb">
                    <span class="breadcrumb-root">Portal SMESTA</span>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-current">Admin Control Hub</span>
                </div>
            </div>

            <div class="header-actions">
                <div class="system-status-indicator">
                    <span class="status-dot-pulse"></span>
                    <span>Semua Layanan Normal</span>
                </div>
                <div class="clock-badge" id="live-clock">
                    {{ date('d M Y') }}
                </div>
            </div>
        </header>

        <!-- Body Content -->
        <main class="content-body">
            
            <!-- Welcome Hero Banner -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h1>Selamat Datang di Portal Pengawasan SMESTA</h1>
                    <p>Pusat integrasi telemetri performa server, monitoring progres task ClickUp, otorisasi API Key B2B, serta dokumentasi API SMESTA Architecture PT Susanti Megah.</p>
                </div>
                <div class="quick-meta-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>Node: Production Ready</span>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon-wrapper stat-icon-blue">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                            <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                            <line x1="6" y1="6" x2="6.01" y2="6"></line>
                            <line x1="6" y1="18" x2="6.01" y2="18"></line>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Infrastruktur Server</span>
                        <span class="stat-value">Online (100%)</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper stat-icon-emerald">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">SAP B1 Middleware</span>
                        <span class="stat-value">Terhubung (3100)</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper stat-icon-purple">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Task Reporting Sync</span>
                        <span class="stat-value">Otomatis / n8n</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper stat-icon-amber">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Keamanan B2B API</span>
                        <span class="stat-value">HMAC SHA-256</span>
                    </div>
                </div>
            </div>

            <!-- Main Module Navigation Cards -->
            <div>
                <div class="section-title" style="margin-bottom: 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                    <span>Modul Layanan & Monitoring</span>
                </div>

                <div class="cards-grid">
                    
                    <!-- Card 1: ClickUp Task Reporting Dashboard -->
                    <a href="{{ url('/reporting/tasks') }}" class="module-card">
                        <div>
                            <div class="card-top">
                                <div class="module-icon-box icon-box-purple">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 20h9"></path>
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                    </svg>
                                </div>
                                <span class="nav-badge badge-purple">Reporting Task</span>
                            </div>
                            <h2 class="card-title">ClickUp Task Reporting Dashboard</h2>
                            <p class="card-desc">Monitoring progress pekerjaan tim, status task, assignee/PIC, timeline sprint, deadline overdue, serta grafik visualisasi interaktif dari database sinkronisasi ClickUp.</p>
                        </div>
                        <div class="card-bottom-action">
                            <span class="action-link-text card-purple-action">
                                <span>Buka Dashboard Task Reporting</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                            </span>
                        </div>
                    </a>

                    <!-- Card 2: Laravel Pulse System Monitoring -->
                    <a href="{{ url('/monitoringsm') }}" class="module-card">
                        <div>
                            <div class="card-top">
                                <div class="module-icon-box icon-box-blue">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                    </svg>
                                </div>
                                <span class="nav-badge badge-blue">Live Telemetry</span>
                            </div>
                            <h2 class="card-title">Laravel Pulse System Monitoring</h2>
                            <p class="card-desc">Pantau utilisasi server, performa memori, CPU usage, query lambat, request traffic, exception logs, dan status antrean job Laravel secara real-time.</p>
                        </div>
                        <div class="card-bottom-action">
                            <span class="action-link-text card-blue-action">
                                <span>Buka Pulse Monitoring</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                            </span>
                        </div>
                    </a>

                    <!-- Card 3: B2B API Key Management -->
                    <a href="{{ url('/monitoringsm/api-keys') }}" class="module-card">
                        <div>
                            <div class="card-top">
                                <div class="module-icon-box icon-box-amber">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 2l-2 2m-1.5 1.5L16 7m-1.5 1.5L13 10m-1.5 1.5L10 13l-4 4-2-2-2 2 4 4 2-2 4-4 1.5-1.5M19 5l2 2-7 7-2-2 7-7z"></path>
                                    </svg>
                                </div>
                                <span class="nav-badge badge-amber">Security & Keys</span>
                            </div>
                            <h2 class="card-title">B2B API Key Monitoring & Management</h2>
                            <p class="card-desc">Kelola otorisasi akses eksternal, generate API Key baru untuk distributor/rekanan, atur masa berlaku (expiry), aktifkan, atau cabut (revoke) token akses.</p>
                        </div>
                        <div class="card-bottom-action">
                            <span class="action-link-text card-amber-action">
                                <span>Kelola API Keys</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                            </span>
                        </div>
                    </a>

                    <!-- Card 4: Swagger API Docs -->
                    <a href="{{ url('/docs') }}" target="_blank" class="module-card">
                        <div>
                            <div class="card-top">
                                <div class="module-icon-box icon-box-emerald">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                    </svg>
                                </div>
                                <span class="nav-badge badge-green">OpenAPI 3.0</span>
                            </div>
                            <h2 class="card-title">Dokumentasi API (Swagger UI)</h2>
                            <p class="card-desc">Spesifikasi OpenAPI interaktif lengkap seluruh endpoint backend SMESTA, skema request/response JSON, dan parameter otentikasi.</p>
                        </div>
                        <div class="card-bottom-action">
                            <span class="action-link-text card-emerald-action">
                                <span>Buka Swagger Docs</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
                            </span>
                        </div>
                    </a>

                </div>
            </div>

            <!-- Server Specs Info Bar -->
            <div class="system-spec-bar">
                <div class="spec-items">
                    <div class="spec-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                        <span>Backend: <strong>Laravel 12 / PHP 8.3</strong></span>
                    </div>
                    <div class="spec-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                        <span>Cache: <strong>Redis / In-Memory Store</strong></span>
                    </div>
                    <div class="spec-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <span>Autentikasi: <strong>Sanctum + Session Guard</strong></span>
                    </div>
                </div>
                <div>
                    <span>Versi Sistem: <strong>v1.2.0-smesta</strong></span>
                </div>
            </div>

        </main>

        <!-- Page Footer -->
        <footer class="site-footer">
            <div>
                &copy; {{ date('Y') }} <strong>PT Susanti Megah</strong>. Seluruh Hak Cipta Dilindungi Undang-Undang.
            </div>
            <div class="footer-links">
                <a href="{{ url('/monitoringsm/hub') }}">Home</a>
                <a href="{{ url('/reporting/tasks') }}">Reporting</a>
                <a href="{{ url('/monitoringsm') }}">Pulse</a>
                <a href="{{ url('/docs') }}" target="_blank">API Docs</a>
                <a href="{{ url('/monitoringsm/logout') }}" style="color: var(--danger);">Logout</a>
            </div>
        </footer>

    </div>

    <!-- Interactive Scripts -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }

        // Live Clock
        function updateClock() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const clockEl = document.getElementById('live-clock');
            if (clockEl) {
                clockEl.textContent = now.toLocaleDateString('id-ID', options) + ' WIB';
            }
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>
