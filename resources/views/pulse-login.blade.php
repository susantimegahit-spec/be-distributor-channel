<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk ke Portal Monitoring - PT Susanti Megah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --navy: #0f172a;
            --slate-800: #1e293b;
            --slate-600: #475569;
            --slate-400: #94a3b8;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
            --slate-50: #f8fafc;
            --surface: #ffffff;
            --danger: #ef4444;
            --success: #10b981;
            --radius-lg: 20px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --shadow-card: 0 20px 45px -15px rgba(15, 23, 42, 0.08), 0 0 1px 1px rgba(15, 23, 42, 0.04);
            --shadow-focus: 0 0 0 4px rgba(37, 99, 235, 0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(37, 99, 235, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(16, 185, 129, 0.04) 0%, transparent 40%),
                linear-gradient(to right, rgba(226, 232, 240, 0.3) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(226, 232, 240, 0.3) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 32px 32px, 32px 32px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: var(--navy);
            position: relative;
        }

        .top-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
            text-decoration: none;
            animation: fadeInDown 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .brand-logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 16px -4px rgba(15, 23, 42, 0.2);
            color: #ffffff;
            font-weight: 800;
            font-size: 18px;
            letter-spacing: -0.5px;
        }

        .brand-text {
            text-align: left;
        }

        .brand-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .brand-subtitle {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: 0.2px;
        }

        .login-card {
            background: var(--surface);
            width: 100%;
            max-width: 440px;
            border-radius: var(--radius-lg);
            border: 1px solid #e2e8f0;
            box-shadow: var(--shadow-card);
            overflow: hidden;
            position: relative;
            animation: cardAppear 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .card-header-stripe {
            height: 4px;
            background: linear-gradient(90deg, #2563eb 0%, #3b82f6 50%, #10b981 100%);
            width: 100%;
        }

        .card-inner {
            padding: 36px 32px 32px 32px;
        }

        .card-heading {
            margin-bottom: 24px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #15803d;
            margin-bottom: 12px;
        }

        .pulse-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25);
            animation: pulseDot 2s infinite;
        }

        .card-heading h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.5px;
            line-height: 1.3;
        }

        .card-heading p {
            font-size: 13px;
            color: var(--slate-600);
            margin-top: 4px;
            line-height: 1.5;
        }

        .alert-error {
            padding: 12px 14px;
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: var(--radius-sm);
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.4;
        }

        .alert-error svg {
            flex-shrink: 0;
            margin-top: 2px;
            stroke: #dc2626;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            font-weight: 600;
            color: var(--slate-800);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--slate-400);
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .form-input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            font-size: 14px;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-md);
            outline: none;
            background-color: #ffffff;
            color: var(--navy);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .form-input::placeholder {
            color: var(--slate-400);
            font-size: 13px;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: var(--shadow-focus);
            background-color: #ffffff;
        }

        .form-input:focus + .input-icon,
        .input-wrapper:focus-within .input-icon {
            color: var(--primary);
        }

        .btn-toggle-pwd {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--slate-400);
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: color 0.2s ease;
        }

        .btn-toggle-pwd:hover {
            color: var(--slate-800);
        }

        .btn-submit {
            width: 100%;
            padding: 13px 20px;
            background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border: 1px solid #1e40af;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 26px;
        }

        .btn-submit:hover {
            background: linear-gradient(180deg, #1d4ed8 0%, #1e40af 100%);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
            transform: translateY(-1px);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
        }

        .card-footer-info {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--slate-600);
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--slate-600);
            font-size: 11px;
            font-weight: 500;
        }

        .page-footer {
            margin-top: 28px;
            font-size: 12px;
            color: var(--slate-600);
            text-align: center;
            line-height: 1.6;
            animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes cardAppear {
            from { opacity: 0; transform: translateY(16px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulseDot {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
    </style>
</head>
<body>

    <!-- Brand Header -->
    <div class="top-brand">
        <div class="brand-logo-icon">SM</div>
        <div class="brand-text">
            <div class="brand-title">PT SUSANTI MEGAH</div>
            <div class="brand-subtitle">SMESTA Architecture</div>
        </div>
    </div>

    <!-- Login Card -->
    <div class="login-card">
        <div class="card-header-stripe"></div>
        <div class="card-inner">
            
            <div class="card-heading">
                <div class="status-pill">
                    <span class="pulse-dot"></span>
                    Portal Sistem Terproteksi
                </div>
                <h1>Masuk ke Monitoring Hub</h1>
                <p>Silakan masukkan kredensial administrator Anda untuk mengakses dashboard pengawasan sistem.</p>
            </div>

            @if(session('error'))
                <div class="alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            <form action="{{ url('/monitoringsm/login') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="username">Username Admin</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input type="text" id="username" name="username" class="form-input" placeholder="Masukkan username admin" required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password Admin</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Masukkan password" required autocomplete="current-password">
                        <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility()" title="Lihat password">
                            <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Masuk ke Dashboard</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>

            <div class="card-footer-info">
                <div class="security-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span>256-Bit SSL Enkripsi</span>
                </div>
                <span>SMESTA Internal Node</span>
            </div>

        </div>
    </div>

    <!-- Page Footer -->
    <footer class="page-footer">
        &copy; {{ date('Y') }} PT Susanti Megah. Hak Cipta Dilindungi Undang-Undang.<br>
        Portal Monitoring SM &bull; Administrator Access Gateway
    </footer>

    <script>
        function togglePasswordVisibility() {
            const pwdInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                `;
            } else {
                pwdInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                `;
            }
        }
    </script>

</body>
</html>
