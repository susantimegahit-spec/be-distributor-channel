<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Documentation API PT Susanti Megah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(2, 132, 199, 0.18) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(30, 58, 138, 0.25) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(14, 165, 233, 0.08) 0px, transparent 50%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        /* Subtle Animated Grid Background */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background-image: linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            border-radius: 20px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5), 0 0 30px rgba(2, 132, 199, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .login-header {
            background: linear-gradient(-45deg, #0284c7, #0369a1, #1e3a8a, #0c4a6e);
            background-size: 300% 300%;
            animation: gradientBg 12s ease infinite;
            color: #ffffff;
            padding: 36px 28px 28px 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Ambient Glow behind Header Icon */
        .header-icon-wrapper {
            width: 54px;
            height: 54px;
            margin: 0 auto 14px auto;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            animation: floatGlow 4s ease-in-out infinite;
        }

        .header-icon-wrapper svg {
            width: 26px;
            height: 26px;
            stroke: #ffffff;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        /* Animated Title Effect */
        .login-header h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            background: linear-gradient(90deg, #ffffff 0%, #bae6fd 50%, #ffffff 100%);
            background-size: 200% auto;
            color: transparent;
            -webkit-background-clip: text;
            background-clip: text;
            animation: textShimmer 4s linear infinite;
            text-transform: uppercase;
            text-shadow: 0 2px 10px rgba(2, 132, 199, 0.3);
        }

        /* Subtitle with Animated Pill Badge */
        .subtitle-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: #e0f2fe;
            font-weight: 600;
            letter-spacing: 0.02em;
            backdrop-filter: blur(4px);
        }

        .status-dot {
            width: 7px;
            height: 7px;
            background-color: #38bdf8;
            border-radius: 50%;
            box-shadow: 0 0 8px #38bdf8;
            animation: badgePulse 2s infinite ease-in-out;
        }

        .login-body {
            padding: 32px 28px;
            background-color: #ffffff;
            color: #0f172a;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.4;
            animation: fadeInUp 0.4s ease forwards;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background-color: #fffbebfb;
            color: #92400e;
            border: 1px solid #fef3c7;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            outline: none;
            transition: all 0.25s ease;
            background-color: #f8fafc;
            color: #0f172a;
        }

        .form-control:focus {
            border-color: #0284c7;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.15);
            transform: translateY(-1px);
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.45);
            transform: translateY(-2px);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);
        }

        .login-footer {
            text-align: center;
            padding: 16px 28px;
            background-color: #f8fafc;
            border-top: 1px solid #f1f5f9;
            font-size: 12px;
            color: #64748b;
        }

        /* Keyframe Animations */
        @keyframes gradientBg {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes textShimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        @keyframes floatGlow {
            0%, 100% { transform: translateY(0px); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15); }
            50% { transform: translateY(-5px); box-shadow: 0 14px 24px rgba(2, 132, 199, 0.4); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(24px) scale(0.97);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes badgePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.3); }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="header-icon-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <h1>PT SUSANTI MEGAH</h1>
            <div class="subtitle-badge">
                <span class="status-dot"></span>
                IT Department - SM Connect API Documentation
            </div>
        </div>

        <div class="login-body">
            @if(session('error'))
                <div class="alert alert-error">
                    <span>⚠️</span>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            @if(request()->has('expired'))
                <div class="alert alert-warning">
                    <span>⏱️</span>
                    <div>Sesi Anda telah berakhir (10 menit tidak aktif). Silakan login kembali.</div>
                </div>
            @endif

            <form action="{{ url('/docs/login') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required autofocus autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn-submit">
                    <span>Masuk ke Documentation</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
            </form>
        </div>

        <div class="login-footer">
            Sistem Keamanan Dokumentasi API &copy; {{ date('Y') }} PT Susanti Megah
        </div>
    </div>
</body>
</html>
