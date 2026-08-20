<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Hub - PT Susanti Megah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }

        body {
            background: linear-gradient(135deg, #020617 0%, #0f172a 50%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        .glow {
            position: absolute;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(99,102,241,0.12) 0%, transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }

        .hub-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 700px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 32px;
        }

        .hub-header {
            text-align: center;
        }

        .logo-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #f59e0b, #ea580c);
            border-radius: 18px;
            font-weight: 800;
            font-size: 24px;
            color: #0f172a;
            margin-bottom: 16px;
            box-shadow: 0 8px 32px rgba(245,158,11,0.3);
        }

        .hub-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #f8fafc;
            letter-spacing: -0.5px;
        }

        .hub-header p {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 6px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            width: 100%;
        }

        @media (max-width: 560px) {
            .cards-grid { grid-template-columns: 1fr; }
            .hub-header h1 { font-size: 22px; }
        }

        .nav-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 28px 24px;
            border-radius: 20px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.06);
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            cursor: pointer;
        }

        .nav-card:hover {
            transform: translateY(-4px);
        }

        .nav-card.card-pulse {
            background: linear-gradient(145deg, #1e1b4b, #0f172a);
            border-color: rgba(99,102,241,0.3);
        }

        .nav-card.card-pulse:hover {
            box-shadow: 0 20px 40px rgba(99,102,241,0.2);
            border-color: rgba(99,102,241,0.6);
        }

        .nav-card.card-apikey {
            background: linear-gradient(145deg, #1c1108, #0f172a);
            border-color: rgba(245,158,11,0.3);
        }

        .nav-card.card-apikey:hover {
            box-shadow: 0 20px 40px rgba(245,158,11,0.15);
            border-color: rgba(245,158,11,0.6);
        }

        .nav-card.card-docs {
            background: linear-gradient(145deg, #0c1a12, #0f172a);
            border-color: rgba(34,197,94,0.3);
        }

        .nav-card.card-docs:hover {
            box-shadow: 0 20px 40px rgba(34,197,94,0.15);
            border-color: rgba(34,197,94,0.6);
        }

        .nav-card.card-reporting {
            background: linear-gradient(145deg, #180d2b, #0f172a);
            border-color: rgba(168,85,247,0.3);
        }

        .nav-card.card-reporting:hover {
            box-shadow: 0 20px 40px rgba(168,85,247,0.2);
            border-color: rgba(168,85,247,0.6);
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

        .card-pulse .card-icon { background: rgba(99,102,241,0.15); }
        .card-apikey .card-icon { background: rgba(245,158,11,0.15); }
        .card-docs .card-icon { background: rgba(34,197,94,0.15); }
        .card-reporting .card-icon { background: rgba(168,85,247,0.15); }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 6px;
        }

        .card-desc {
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
        }

        .card-arrow {
            margin-top: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .card-pulse .card-arrow { color: #818cf8; }
        .card-apikey .card-arrow { color: #fbbf24; }
        .card-docs .card-arrow { color: #4ade80; }
        .card-reporting .card-arrow { color: #c084fc; }

        .logout-link {
            font-size: 13px;
            color: #475569;
            text-decoration: none;
            transition: color 0.2s;
        }

        .logout-link:hover { color: #f87171; }
    </style>
</head>
<body>
    <div class="glow"></div>

    <div class="hub-container">
        <div class="hub-header">
            <div class="logo-badge">SM</div>
            <h1>Admin Control Hub</h1>
            <p>PT Susanti Megah — Pilih halaman yang ingin Anda akses</p>
        </div>

        <div class="cards-grid">
            <!-- ClickUp Task Reporting Dashboard -->
            <a href="{{ url('/reporting/tasks') }}" class="nav-card card-reporting" style="grid-column: 1 / -1;">
                <div class="card-icon">📊</div>
                <div class="card-title">ClickUp Task Reporting Dashboard</div>
                <div class="card-desc">Monitoring progress pekerjaan tim, status task, assignee, timeline sprint, deadline overdue, dan visualisasi interaktif dari database ClickUp.</div>
                <div class="card-arrow">Buka Task Reporting Dashboard →</div>
            </a>

            <!-- Pulse System Monitoring -->
            <a href="{{ url('/monitoringsm') }}" class="nav-card card-pulse">
                <div class="card-icon">📡</div>
                <div class="card-title">System Pulse Monitoring</div>
                <div class="card-desc">Monitor performa server, query lambat, request, exception, dan job queue secara real-time via Laravel Pulse.</div>
                <div class="card-arrow">Buka Dashboard →</div>
            </a>

            <!-- B2B API Key Management -->
            <a href="{{ url('/monitoringsm/api-keys') }}" class="nav-card card-apikey">
                <div class="card-icon">🔑</div>
                <div class="card-title">B2B API Key Monitoring</div>
                <div class="card-desc">Generate, monitoring, aktifkan, dan revoke API Key untuk integrasi B2B sistem distributor external.</div>
                <div class="card-arrow">Kelola API Keys →</div>
            </a>

            <!-- API Documentation -->
            <a href="{{ url('/docs') }}" target="_blank" class="nav-card card-docs" style="grid-column: 1 / -1;">
                <div class="card-icon">📖</div>
                <div class="card-title">Dokumentasi API (Swagger)</div>
                <div class="card-desc">Dokumentasi OpenAPI/Swagger untuk semua endpoint sistem Distributor Channel PT Susanti Megah.</div>
                <div class="card-arrow">Buka Docs →</div>
            </a>
        </div>

        <a href="{{ url('/monitoringsm/logout') }}" class="logout-link">
            Keluar / Logout Admin →
        </a>
    </div>
</body>
</html>
