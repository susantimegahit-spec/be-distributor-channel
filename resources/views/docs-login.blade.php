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
            background: linear-gradient(180deg, #020617 0%, #0f172a 40%, #075985 80%, #0c4a6e 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        /* Subtle Ambient Glow (Soft Deep Aqua) */
        .ocean-glow {
            position: absolute;
            top: -25%;
            left: 50%;
            transform: translateX(-50%);
            width: 900px;
            height: 600px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.12) 0%, rgba(3, 105, 161, 0.05) 50%, transparent 75%);
            pointer-events: none;
            z-index: 0;
            animation: pulseSubtle 8s ease-in-out infinite alternate;
        }

        /* Soft Subtle Rays */
        .light-ray {
            position: absolute;
            top: -100px;
            width: 70px;
            height: 120vh;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.05) 0%, rgba(14, 165, 233, 0.02) 60%, transparent 100%);
            transform: rotate(-25deg);
            pointer-events: none;
            z-index: 0;
            animation: rayShimmer 12s ease-in-out infinite alternate;
        }
        .ray-1 { left: 22%; animation-delay: 0s; }
        .ray-2 { left: 50%; width: 100px; animation-delay: 4s; }
        .ray-3 { left: 75%; width: 80px; animation-delay: 2s; }

        /* SVG Waves Section (Deep Midnight Navy Tones) */
        .waves-wrapper {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 36vh;
            min-height: 200px;
            max-height: 400px;
            pointer-events: none;
            z-index: 2;
        }

        .waves {
            position: relative;
            width: 100%;
            height: 100%;
            margin-bottom: -7px;
        }

        .parallax > use {
            animation: move-wave 24s cubic-bezier(.55, .5, .45, .5) infinite;
        }
        .parallax > use:nth-child(1) {
            animation-delay: -2s;
            animation-duration: 9s;
            fill: rgba(14, 165, 233, 0.25);
        }
        .parallax > use:nth-child(2) {
            animation-delay: -4s;
            animation-duration: 13s;
            fill: rgba(3, 105, 161, 0.35);
        }
        .parallax > use:nth-child(3) {
            animation-delay: -6s;
            animation-duration: 18s;
            fill: rgba(12, 74, 110, 0.6);
        }
        .parallax > use:nth-child(4) {
            animation-delay: -8s;
            animation-duration: 24s;
            fill: #020617;
        }

        /* Swimming & Jumping Fish Container */
        .fish-container {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 38vh;
            pointer-events: none;
            z-index: 3;
            overflow: hidden;
        }

        .swimming-fish {
            position: absolute;
            opacity: 0.85;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.4));
        }

        /* Fish 1 - Soft Ocean Blue */
        .fish-1 {
            bottom: 130px;
            animation: swimRight 18s linear infinite;
        }

        /* Fish 2 - Deep Sky Blue */
        .fish-2 {
            bottom: 65px;
            animation: swimLeft 24s linear infinite;
        }

        /* Fish 3 - Subtle Amber Warm Fish */
        .fish-3 {
            bottom: 180px;
            animation: swimRightSlow 30s linear infinite;
        }

        /* Fish 4 - Small Light Cyan Fish */
        .fish-4 {
            bottom: 100px;
            animation: swimLeftFast 15s linear infinite;
        }

        /* Jumping Fish */
        .jumping-fish-1 {
            position: absolute;
            bottom: 120px;
            left: 16%;
            animation: fishJumpLeft 7s ease-in-out infinite;
        }

        .jumping-fish-2 {
            position: absolute;
            bottom: 140px;
            right: 20%;
            animation: fishJumpRight 8.5s ease-in-out infinite 4s;
        }

        /* Subtle Bubbles */
        .bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
            overflow: hidden;
        }

        .bubble {
            position: absolute;
            bottom: -40px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            animation: riseBubble linear infinite;
        }

        .bubble:nth-child(1) { left: 10%; width: 14px; height: 14px; animation-duration: 12s; animation-delay: 0s; }
        .bubble:nth-child(2) { left: 26%; width: 10px; height: 10px; animation-duration: 9s; animation-delay: 2s; }
        .bubble:nth-child(3) { left: 44%; width: 18px; height: 18px; animation-duration: 15s; animation-delay: 4s; }
        .bubble:nth-child(4) { left: 60%; width: 12px; height: 12px; animation-duration: 10s; animation-delay: 1s; }
        .bubble:nth-child(5) { left: 76%; width: 16px; height: 16px; animation-duration: 13s; animation-delay: 3s; }
        .bubble:nth-child(6) { left: 88%; width: 8px; height: 8px; animation-duration: 8s; animation-delay: 5s; }
        .bubble:nth-child(7) { left: 52%; width: 14px; height: 14px; animation-duration: 11s; animation-delay: 6s; }

        /* Glassmorphism Card Container (Dark Professional Tone) */
        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 430px;
            border-radius: 20px;
            box-shadow: 0 20px 45px -10px rgba(2, 6, 23, 0.7), 0 0 25px rgba(2, 132, 199, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            position: relative;
            z-index: 10;
            animation: fadeInUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .login-header {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 60%, #075985 100%);
            background-size: 200% 200%;
            animation: gradientHeader 10s ease infinite;
            color: #ffffff;
            padding: 34px 26px 26px 26px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header-icon-wrapper {
            width: 54px;
            height: 54px;
            margin: 0 auto 12px auto;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            animation: floatIcon 4s ease-in-out infinite;
        }

        .header-icon-wrapper svg {
            width: 26px;
            height: 26px;
            stroke: #ffffff;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .login-header h1 {
            font-size: 21px;
            font-weight: 800;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            background: linear-gradient(90deg, #ffffff 0%, #e0f2fe 50%, #ffffff 100%);
            background-size: 200% auto;
            color: transparent;
            -webkit-background-clip: text;
            background-clip: text;
            animation: textShimmer 4s linear infinite;
            text-transform: uppercase;
            text-shadow: 0 2px 10px rgba(2, 132, 199, 0.3);
        }

        .subtitle-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: #f0f9ff;
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
            padding: 30px 26px;
            background-color: #ffffff;
            color: #0f172a;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
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
            margin-bottom: 20px;
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
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.3);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.4);
            transform: translateY(-2px);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.2);
        }

        .login-footer {
            text-align: center;
            padding: 16px 26px;
            background-color: #f8fafc;
            border-top: 1px solid #f1f5f9;
            font-size: 12px;
            color: #64748b;
        }

        /* Keyframes */
        @keyframes move-wave {
            0% { transform: translate3d(-90px, 0, 0); }
            100% { transform: translate3d(85px, 0, 0); }
        }

        @keyframes swimRight {
            0% { transform: translateX(-120px) translateY(0) scaleX(1); }
            50% { transform: translateX(calc(100vw + 120px)) translateY(-15px) scaleX(1); }
            50.01% { transform: translateX(calc(100vw + 120px)) translateY(-15px) scaleX(-1); }
            100% { transform: translateX(-120px) translateY(0) scaleX(-1); }
        }

        @keyframes swimLeft {
            0% { transform: translateX(calc(100vw + 120px)) translateY(0) scaleX(-1); }
            50% { transform: translateX(-120px) translateY(15px) scaleX(-1); }
            50.01% { transform: translateX(-120px) translateY(15px) scaleX(1); }
            100% { transform: translateX(calc(100vw + 120px)) translateY(0) scaleX(1); }
        }

        @keyframes swimRightSlow {
            0% { transform: translateX(-120px) translateY(0) scaleX(1); }
            50% { transform: translateX(calc(100vw + 120px)) translateY(12px) scaleX(1); }
            50.01% { transform: translateX(calc(100vw + 120px)) translateY(12px) scaleX(-1); }
            100% { transform: translateX(-120px) translateY(0) scaleX(-1); }
        }

        @keyframes swimLeftFast {
            0% { transform: translateX(calc(100vw + 120px)) translateY(0) scaleX(-1); }
            50% { transform: translateX(-120px) translateY(-12px) scaleX(-1); }
            50.01% { transform: translateX(-120px) translateY(-12px) scaleX(1); }
            100% { transform: translateX(calc(100vw + 120px)) translateY(0) scaleX(1); }
        }

        @keyframes fishJumpLeft {
            0%, 65%, 100% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 0;
            }
            15% { opacity: 0.9; }
            30% {
                transform: translate(65px, -80px) rotate(30deg);
                opacity: 0.9;
            }
            45% {
                transform: translate(130px, 15px) rotate(-30deg);
                opacity: 0.7;
            }
            52% { opacity: 0; }
        }

        @keyframes fishJumpRight {
            0%, 65%, 100% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 0;
            }
            15% { opacity: 0.9; }
            30% {
                transform: translate(-65px, -85px) rotate(-30deg);
                opacity: 0.9;
            }
            45% {
                transform: translate(-130px, 15px) rotate(30deg);
                opacity: 0.7;
            }
            52% { opacity: 0; }
        }

        @keyframes riseBubble {
            0% {
                transform: translateY(0) scale(0.8);
                opacity: 0;
            }
            20% { opacity: 0.5; }
            80% { opacity: 0.5; }
            100% {
                transform: translateY(-110vh) scale(1.2);
                opacity: 0;
            }
        }

        @keyframes pulseSubtle {
            0% { opacity: 0.5; transform: translateX(-50%) scale(1); }
            100% { opacity: 0.9; transform: translateX(-50%) scale(1.1); }
        }

        @keyframes rayShimmer {
            0%, 100% { opacity: 0.15; transform: rotate(-25deg) translateY(0); }
            50% { opacity: 0.35; transform: rotate(-22deg) translateY(8px); }
        }

        @keyframes gradientHeader {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes textShimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0px); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15); }
            50% { transform: translateY(-5px); box-shadow: 0 14px 22px rgba(2, 132, 199, 0.35); }
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

        @media (max-width: 480px) {
            .waves-wrapper { height: 26vh; }
            .login-card { border-radius: 18px; }
        }
    </style>
</head>
<body>

    <!-- Soft Ocean Ambient Glow & Rays -->
    <div class="ocean-glow"></div>
    <div class="light-ray ray-1"></div>
    <div class="light-ray ray-2"></div>
    <div class="light-ray ray-3"></div>

    <!-- Rising Bubbles -->
    <div class="bubbles">
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
    </div>

    <!-- Swimming & Jumping Fish (Deep Dark Ocean Theme) -->
    <div class="fish-container">
        <!-- Fish 1 - Soft Ocean Blue -->
        <div class="swimming-fish fish-1">
            <svg viewBox="0 0 50 30" width="44" height="26">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#38bdf8"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#0284c7"/> <!-- Tail -->
                <path d="M 22 8 Q 28 3 32 8" fill="#7dd3fc"/> <!-- Fin -->
                <circle cx="38" cy="12" r="2.2" fill="#ffffff"/>
                <circle cx="39" cy="12" r="1.1" fill="#020617"/>
            </svg>
        </div>

        <!-- Fish 2 - Deep Sky Blue -->
        <div class="swimming-fish fish-2">
            <svg viewBox="0 0 50 30" width="36" height="22">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#0284c7"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#0369a1"/>
                <circle cx="38" cy="12" r="2" fill="#ffffff"/>
                <circle cx="39" cy="12" r="1" fill="#0f172a"/>
            </svg>
        </div>

        <!-- Fish 3 - Subtle Amber Warm Fish -->
        <div class="swimming-fish fish-3">
            <svg viewBox="0 0 50 30" width="38" height="23">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#f59e0b"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#d97706"/>
                <circle cx="38" cy="12" r="2" fill="#ffffff"/>
                <circle cx="39" cy="12" r="1" fill="#000000"/>
            </svg>
        </div>

        <!-- Fish 4 - Small Light Cyan Fish -->
        <div class="swimming-fish fish-4">
            <svg viewBox="0 0 50 30" width="30" height="18">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#7dd3fc"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#0284c7"/>
                <circle cx="38" cy="12" r="1.8" fill="#ffffff"/>
                <circle cx="39" cy="12" r="0.9" fill="#000000"/>
            </svg>
        </div>

        <!-- Jumping Fish 1 -->
        <div class="jumping-fish-1">
            <svg viewBox="0 0 50 30" width="44" height="26">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#38bdf8"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#0284c7"/>
                <circle cx="38" cy="12" r="2.2" fill="#ffffff"/>
                <circle cx="39" cy="12" r="1.1" fill="#000000"/>
            </svg>
        </div>

        <!-- Jumping Fish 2 -->
        <div class="jumping-fish-2">
            <svg viewBox="0 0 50 30" width="42" height="25">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#7dd3fc"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#0369a1"/>
                <circle cx="38" cy="12" r="2" fill="#ffffff"/>
                <circle cx="39" cy="12" r="1" fill="#000000"/>
            </svg>
        </div>
    </div>

    <!-- Waves SVG at bottom -->
    <div class="waves-wrapper">
        <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
        viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
            <defs>
                <path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" />
            </defs>
            <g class="parallax">
                <use xlink:href="#gentle-wave" x="48" y="0" />
                <use xlink:href="#gentle-wave" x="48" y="3" />
                <use xlink:href="#gentle-wave" x="48" y="5" />
                <use xlink:href="#gentle-wave" x="48" y="7" />
            </g>
        </svg>
    </div>

    <!-- Login Card Container -->
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
