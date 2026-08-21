<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Hub - PT Susanti Megah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 48px 20px 60px;
            overflow-y: auto;
            position: relative;
            color: #0f172a;
        }

        /* Subtle background glow */
        .glow {
            position: absolute;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, rgba(245, 158, 11, 0.04) 40%, transparent 70%);
            top: 20%; 
            left: 50%;
            transform: translate(-50%, -20%);
            pointer-events: none;
            z-index: 1;
        }

        .hub-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 760px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 28px;
            margin: auto 0;
        }

        .hub-header {
            text-align: center;
        }

        .logo-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #f59e0b, #ea580c);
            border-radius: 18px;
            font-weight: 800;
            font-size: 22px;
            color: #ffffff;
            margin-bottom: 16px;
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.28);
        }

        .hub-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .hub-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 6px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            width: 100%;
        }

        @media (max-width: 640px) {
            body { padding: 24px 16px 40px; }
            .cards-grid { grid-template-columns: 1fr; }
            .hub-header h1 { font-size: 22px; }
            .nav-card { padding: 22px 20px; }
        }

        .nav-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 26px 24px;
            border-radius: 20px;
            text-decoration: none;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s, border-color 0.2s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .nav-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.08), 0 4px 8px rgba(0, 0, 0, 0.04);
        }

        /* Specific card highlights */
        .card-reporting {
            border-color: #e9d5ff;
        }
        .card-reporting:hover {
            border-color: #c084fc;
            box-shadow: 0 16px 32px rgba(168, 85, 247, 0.12);
        }

        .card-pulse {
            border-color: #e0e7ff;
        }
        .card-pulse:hover {
            border-color: #818cf8;
            box-shadow: 0 16px 32px rgba(99, 102, 241, 0.12);
        }

        .card-apikey {
            border-color: #fef3c7;
        }
        .card-apikey:hover {
            border-color: #fbbf24;
            box-shadow: 0 16px 32px rgba(245, 158, 11, 0.12);
        }

        .card-docs {
            border-color: #dcfce7;
        }
        .card-docs:hover {
            border-color: #4ade80;
            box-shadow: 0 16px 32px rgba(34, 197, 94, 0.12);
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 16px;
        }

        .card-reporting .card-icon { background: #f3e8ff; }
        .card-pulse .card-icon { background: #eef2ff; }
        .card-apikey .card-icon { background: #fef3c7; }
        .card-docs .card-icon { background: #f0fdf4; }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-desc {
            font-size: 13px;
            color: #64748b;
            line-height: 1.55;
        }

        .card-arrow {
            margin-top: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: gap 0.2s;
        }

        .nav-card:hover .card-arrow {
            gap: 8px;
        }

        .card-reporting .card-arrow { color: #9333ea; }
        .card-pulse .card-arrow { color: #4f46e5; }
        .card-apikey .card-arrow { color: #d97706; }
        .card-docs .card-arrow { color: #16a34a; }

        .footer-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 10px;
        }

        .logout-link {
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 10px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            transition: color 0.2s, background 0.2s, border-color 0.2s;
        }

        .logout-link:hover { 
            color: #e11d48;
            background: #ffe4e6;
            border-color: #fecdd3;
        }
    </style>
</head>
<body>
    <div class="glow"></div>

    <div class="hub-container">
        <!-- Header -->
        <div class="hub-header">
            <div class="logo-badge">SM</div>
            <h1>Admin Control Hub</h1>
            <p>PT Susanti Megah — Portal Pusat Manajemen & Monitoring Sistem</p>
        </div>

        <!-- Cards Menu Grid -->
        <div class="cards-grid">
            
            <!-- ClickUp Task Reporting Dashboard (Featured / Top Full Width) -->
            <a href="{{ url('/reporting/tasks') }}" class="nav-card card-reporting" style="grid-column: 1 / -1;">
                <div class="card-icon">📊</div>
                <div class="card-title">
                    ClickUp Task Reporting Dashboard
                    <span style="font-size: 11px; font-weight: 600; background: #f3e8ff; color: #9333ea; border: 1px solid #e9d5ff; padding: 2px 8px; border-radius: 9999px;">Baru</span>
                </div>
                <div class="card-desc">Monitoring progress pekerjaan tim, status task, assignee/PIC, timeline sprint, deadline overdue, serta grafik visualisasi interaktif dari database ClickUp.</div>
                <div class="card-arrow">Buka Task Reporting Dashboard →</div>
            </a>

            <!-- Pulse System Monitoring -->
            <a href="{{ url('/monitoringsm') }}" class="nav-card card-pulse">
                <div class="card-icon">📡</div>
                <div class="card-title">System Pulse Monitoring</div>
                <div class="card-desc">Pantau utilisasi server, performa aplikasi, query lambat, request traffic, exception, dan job queue secara real-time.</div>
                <div class="card-arrow">Buka Pulse Dashboard →</div>
            </a>

            <!-- B2B API Key Management -->
            <a href="{{ url('/monitoringsm/api-keys') }}" class="nav-card card-apikey">
                <div class="card-icon">🔑</div>
                <div class="card-title">B2B API Key Monitoring</div>
                <div class="card-desc">Kelola, generate token baru, pantau penggunaan, aktifkan, atau cabut (revoke) API Key distributor eksternal.</div>
                <div class="card-arrow">Kelola API Keys →</div>
            </a>

            <!-- API Documentation (Swagger) -->
            <a href="{{ url('/docs') }}" target="_blank" class="nav-card card-docs" style="grid-column: 1 / -1;">
                <div class="card-icon">📖</div>
                <div class="card-title">Dokumentasi API (Swagger)</div>
                <div class="card-desc">Spesifikasi OpenAPI / Swagger lengkap untuk seluruh endpoint API backend Distributor Channel PT Susanti Megah.</div>
                <div class="card-arrow">Buka Swagger Docs ↗</div>
            </a>
        </div>

        <!-- Footer Actions -->
        <div class="footer-nav">
            <a href="{{ url('/monitoringsm/logout') }}" class="logout-link">
                Keluar / Logout Admin →
            </a>
        </div>
    </div>
</body>
</html>
