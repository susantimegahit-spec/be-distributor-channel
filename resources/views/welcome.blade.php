<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SM Connect API - PT Susanti Megah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: #020617;
            color: #f8fafc;
            min-height: 100vh;
            line-height: 1.6;
            background-image: 
                radial-gradient(at 10% 10%, rgba(2, 132, 199, 0.15) 0px, transparent 50%),
                radial-gradient(at 90% 90%, rgba(14, 165, 233, 0.1) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 0.5) 0px, transparent 100%);
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Navigation Bar */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.4);
        }

        .brand-icon svg {
            width: 22px;
            height: 22px;
            stroke: #ffffff;
        }

        .brand-text h1 {
            font-size: 16px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.05em;
            line-height: 1.2;
        }

        .brand-text span {
            font-size: 11px;
            color: #38bdf8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .nav-btn {
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .nav-btn-outline {
            color: #94a3b8;
            border: 1px solid #334155;
            background: rgba(30, 41, 59, 0.4);
        }

        .nav-btn-outline:hover {
            color: #ffffff;
            border-color: #0284c7;
            background: rgba(2, 132, 199, 0.1);
        }

        .nav-btn-primary {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);
        }

        .nav-btn-primary:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.45);
        }

        /* Hero Section */
        .hero {
            padding: 80px 0 60px 0;
            text-align: center;
            position: relative;
        }

        .system-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(2, 132, 199, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.3);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            color: #38bdf8;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .status-pulse {
            width: 8px;
            height: 8px;
            background-color: #22c55e;
            border-radius: 50%;
            box-shadow: 0 0 10px #22c55e;
        }

        .hero h2 {
            font-size: 42px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -0.02em;
            margin-bottom: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero h2 span {
            background: linear-gradient(90deg, #38bdf8 0%, #818cf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 17px;
            color: #94a3b8;
            max-width: 760px;
            margin: 0 auto 36px auto;
            line-height: 1.7;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-lg {
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 12px;
        }

        /* Tech Specs Strip */
        .tech-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 40px 0 80px 0;
            padding: 24px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .tech-item {
            text-align: center;
        }

        .tech-item label {
            display: block;
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .tech-item val {
            font-size: 14px;
            color: #e2e8f0;
            font-weight: 600;
            font-family: 'Fira Code', monospace;
        }

        /* Feature Section */
        .section-title {
            text-align: center;
            margin-bottom: 48px;
        }

        .section-title h3 {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 10px;
        }

        .section-title p {
            font-size: 15px;
            color: #64748b;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 80px;
        }

        .feature-card {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 32px 28px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            border-color: rgba(56, 189, 248, 0.4);
            transform: translateY(-4px);
            box-shadow: 0 12px 30px -10px rgba(2, 132, 199, 0.25);
            background: rgba(30, 41, 59, 0.6);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: rgba(2, 132, 199, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.25);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .feature-icon svg {
            width: 24px;
            height: 24px;
            stroke: #38bdf8;
        }

        .feature-card h4 {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 10px;
        }

        .feature-card p {
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .feature-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .tag {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.06);
            color: #cbd5e1;
            font-family: 'Fira Code', monospace;
        }

        /* Endpoints & Architecture Quick Links */
        .endpoints-box {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.9) 100%);
            border: 1px solid rgba(56, 189, 248, 0.2);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 80px;
        }

        .endpoints-header {
            margin-bottom: 24px;
        }

        .endpoints-header h4 {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 6px;
        }

        .endpoints-header p {
            font-size: 14px;
            color: #94a3b8;
        }

        .endpoints-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .endpoint-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(2, 6, 23, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            padding: 14px 20px;
            border-radius: 12px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .endpoint-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .method-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            font-family: 'Fira Code', monospace;
        }

        .method-get { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .method-post { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .method-auth { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        .endpoint-path {
            font-family: 'Fira Code', monospace;
            font-size: 14px;
            color: #38bdf8;
            font-weight: 500;
        }

        .endpoint-desc {
            font-size: 13px;
            color: #94a3b8;
        }

        .endpoint-link {
            color: #38bdf8;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .endpoint-link:hover {
            text-decoration: underline;
        }

        /* Footer */
        .footer {
            padding: 40px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }

        @media (max-width: 768px) {
            .hero h2 { font-size: 30px; }
            .features-grid { grid-template-columns: 1fr; }
            .endpoint-row { flex-direction: column; align-items: flex-start; }
            .navbar { flex-direction: column; gap: 16px; }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Navigation Header -->
        <header class="navbar">
            <a href="{{ url('/') }}" class="brand">
                <div class="brand-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                </div>
                <div class="brand-text">
                    <h1>PT SUSANTI MEGAH</h1>
                    <span>SM CONNECT API</span>
                </div>
            </a>

            <div class="nav-links">
                <a href="{{ url('/docs/login') }}" class="nav-btn nav-btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Login Docs
                </a>
                <a href="{{ url('/docs') }}" class="nav-btn nav-btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                    Buka Dokumentasi API
                </a>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="hero">
            <div class="system-badge">
                <span class="status-pulse"></span>
                SM CONNECT API v1.0 • SYSTEM ACTIVE
            </div>

            <h2>Backend Integrasi <span>Distributor Channel & SAP B1</span></h2>
            
            <p>
                Platform REST API terpusat milik PT Susanti Megah yang menghubungkan Portal Distributor, 
                Mesin Kalkulasi Klaim Reward, Sistem Manajemen Ekspedisi, dan Integrasi Langsung ke ERP SAP Business One.
            </p>

            <div class="hero-actions">
                <a href="{{ url('/docs') }}" class="nav-btn nav-btn-primary btn-lg">
                    📖 Buka Dokumentasi API RapiDoc
                </a>
                <a href="{{ url('/docs/login') }}" class="nav-btn nav-btn-outline btn-lg">
                    🔑 Login Akses Dokumentasi
                </a>
            </div>
        </section>

        <!-- Technical Specifications Strip -->
        <div class="tech-strip">
            <div class="tech-item">
                <label>FRAMEWORK</label>
                <val>Laravel 12 (PHP 8.3)</val>
            </div>
            <div class="tech-item">
                <label>DATABASE</label>
                <val>PostgreSQL (Multi-Schema)</val>
            </div>
            <div class="tech-item">
                <label>AUTHENTICATION</label>
                <val>Laravel Sanctum API</val>
            </div>
            <div class="tech-item">
                <label>ERP INTEGRATION</label>
                <val>SAP Business One Layer</val>
            </div>
        </div>

        <!-- System Core Features Section -->
        <section>
            <div class="section-title">
                <h3>Fitur & Modul Utama API</h3>
                <p>Arsitektur Modular Monolith yang dirancang scalable untuk seluruh ekosistem bisnis distributor PT Susanti Megah.</p>
            </div>

            <div class="features-grid">
                <!-- Card 1 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <h4>Authentication & User Roles</h4>
                    <p>Manajemen otentikasi token berbasis Laravel Sanctum, Refresh Token, Change Password, dan kontrol hak akses multi-role (Distributor, Admin Sales, Finance, Logistic, Admin).</p>
                    <div class="feature-tags">
                        <span class="tag">Sanctum</span>
                        <span class="tag">Audit Log</span>
                        <span class="tag">Multi-Role</span>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                    </div>
                    <h4>Sales Order & Approval Workflow</h4>
                    <p>Sistem pembuatan pesanan distributor, fitur simpan draft order, review diskon oleh Admin Sales, serta workflow persetujuan berjenjang sebelum dikirim ke ERP.</p>
                    <div class="feature-tags">
                        <span class="tag">Draft SO</span>
                        <span class="tag">Approval Sales</span>
                        <span class="tag">Status Tracking</span>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="16 18 22 12 16 6"></polyline>
                            <polyline points="8 6 2 12 8 18"></polyline>
                        </svg>
                    </div>
                    <h4>Integrasi ERP SAP Business One</h4>
                    <p>Integration Layer otomatis yang mentransmisikan Sales Order yang disetujui (APPROVED) langsung ke sistem SAP B1 dengan retry mechanism jika integrasi gagal.</p>
                    <div class="feature-tags">
                        <span class="tag">SAP B1 Sync</span>
                        <span class="tag">Queue Driver</span>
                        <span class="tag">Auto Retry</span>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path>
                            <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"></path>
                            <path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"></path>
                        </svg>
                    </div>
                    <h4>Klaim Reward & Perhitungan Diskon</h4>
                    <p>Otomatisasi perhitungan program diskon per-KG berbasis master strata & tipe customer. Menggantikan proses kalkulasi Excel manual serta mendukung export laporan.</p>
                    <div class="feature-tags">
                        <span class="tag">Claim Reward</span>
                        <span class="tag">Master Strata</span>
                        <span class="tag">Export Excel</span>
                    </div>
                </div>

                <!-- Card 5 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"></rect>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                    </div>
                    <h4>Modul Ekspedisi & Pengiriman</h4>
                    <p>Pengelolaan data logistik dengan multi-schema PostgreSQL (`ekspedisi`). Pemantauan status barang dari penyiapan gudang, dalam perjalanan driver, hingga barang tiba (`ARRIVED`).</p>
                    <div class="feature-tags">
                        <span class="tag">Multi-Schema</span>
                        <span class="tag">Logistics Tracking</span>
                        <span class="tag">Delivery Status</span>
                    </div>
                </div>

                <!-- Card 6 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <h4>Interaktif OpenAPI 3.0 Documentation</h4>
                    <p>Konsol RapiDoc interaktif untuk menjelajahi endpoint, skema request/response, mencoba kirim request API, dan terproteksi keamanan autentikasi.</p>
                    <div class="feature-tags">
                        <span class="tag">OpenAPI 3.0</span>
                        <span class="tag">RapiDoc</span>
                        <span class="tag">Try-out Console</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Access Endpoints Box -->
        <section class="endpoints-box">
            <div class="endpoints-header">
                <h4>Navigasi & Endpoint Utama API</h4>
                <p>Tautan cepat untuk mengakses dokumentasi dan base URL service API SM Connect.</p>
            </div>

            <div class="endpoints-list">
                <div class="endpoint-row">
                    <div class="endpoint-info">
                        <span class="method-badge method-get">GET</span>
                        <span class="endpoint-path">/docs</span>
                        <span class="endpoint-desc">Konsol Dokumentasi API Interaktif RapiDoc</span>
                    </div>
                    <a href="{{ url('/docs') }}" class="endpoint-link">
                        Buka Dokumentasi &rarr;
                    </a>
                </div>

                <div class="endpoint-row">
                    <div class="endpoint-info">
                        <span class="method-badge method-auth">AUTH</span>
                        <span class="endpoint-path">/docs/login</span>
                        <span class="endpoint-desc">Halaman Login Keamanan Dokumentasi API</span>
                    </div>
                    <a href="{{ url('/docs/login') }}" class="endpoint-link">
                        Halaman Login &rarr;
                    </a>
                </div>

                <div class="endpoint-row">
                    <div class="endpoint-info">
                        <span class="method-badge method-post">POST</span>
                        <span class="endpoint-path">/api/v1/auth/login</span>
                        <span class="endpoint-desc">Endpoint Authenticate User & Generate Sanctum Token</span>
                    </div>
                    <span class="endpoint-desc" style="font-family: 'Fira Code', monospace; color: #64748b;">API Endpoint</span>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <p>SM Connect API &copy; {{ date('Y') }} PT Susanti Megah • IT Department</p>
        </footer>
    </div>

</body>
</html>
