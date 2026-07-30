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
            background: linear-gradient(180deg, #041c32 0%, #064663 35%, #0891b2 75%, #06b6d4 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        /* Aqua Sunbeams / Water Caustics Glow */
        .aqua-caustics {
            position: absolute;
            top: -30%;
            left: 50%;
            transform: translateX(-50%);
            width: 1000px;
            height: 700px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.35) 0%, rgba(6, 182, 212, 0.15) 45%, transparent 75%);
            pointer-events: none;
            z-index: 0;
            animation: pulseAqua 6s ease-in-out infinite alternate;
        }

        /* Light Rays Effect */
        .light-ray {
            position: absolute;
            top: -100px;
            width: 80px;
            height: 120vh;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.15) 0%, rgba(34, 211, 238, 0.05) 60%, transparent 100%);
            transform: rotate(-25deg);
            pointer-events: none;
            z-index: 0;
            animation: rayShimmer 10s ease-in-out infinite alternate;
        }
        .ray-1 { left: 20%; animation-delay: 0s; }
        .ray-2 { left: 45%; width: 120px; animation-delay: 3s; }
        .ray-3 { left: 70%; width: 90px; animation-delay: 1.5s; }

        /* SVG Aqua Waves Section */
        .aqua-waves-wrapper {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 38vh;
            min-height: 220px;
            max-height: 420px;
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
            animation: move-wave 22s cubic-bezier(.55, .5, .45, .5) infinite;
        }
        .parallax > use:nth-child(1) {
            animation-delay: -2s;
            animation-duration: 8s;
            fill: rgba(34, 211, 238, 0.5);
        }
        .parallax > use:nth-child(2) {
            animation-delay: -4s;
            animation-duration: 12s;
            fill: rgba(6, 182, 212, 0.45);
        }
        .parallax > use:nth-child(3) {
            animation-delay: -6s;
            animation-duration: 17s;
            fill: rgba(8, 145, 178, 0.6);
        }
        .parallax > use:nth-child(4) {
            animation-delay: -8s;
            animation-duration: 22s;
            fill: #041c32;
        }

        /* Swimming & Jumping Fish Container */
        .fish-container {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40vh;
            pointer-events: none;
            z-index: 3;
            overflow: hidden;
        }

        .swimming-fish {
            position: absolute;
            filter: drop-shadow(0 6px 12px rgba(6, 182, 212, 0.4));
        }

        /* Fish 1 - Vibrant Aqua Turquoise */
        .fish-1 {
            bottom: 140px;
            animation: swimRight 16s linear infinite;
        }

        /* Fish 2 - Deep Neon Blue */
        .fish-2 {
            bottom: 70px;
            animation: swimLeft 22s linear infinite;
        }

        /* Fish 3 - Golden Tropical Aqua Fish Swimming Right Slow */
        .fish-3 {
            bottom: 190px;
            animation: swimRightSlow 28s linear infinite;
        }

        /* Fish 4 - Small Cyan School */
        .fish-4 {
            bottom: 110px;
            animation: swimLeftFast 14s linear infinite;
        }

        /* Jumping Fish Arc */
        .jumping-fish-1 {
            position: absolute;
            bottom: 130px;
            left: 18%;
            animation: fishJumpLeft 6.5s ease-in-out infinite;
        }

        .jumping-fish-2 {
            position: absolute;
            bottom: 150px;
            right: 22%;
            animation: fishJumpRight 8s ease-in-out infinite 3.5s;
        }

        /* Aqua Crystal Bubbles */
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
            bottom: -50px;
            background: rgba(255, 255, 255, 0.25);
            border: 1px solid rgba(165, 243, 252, 0.5);
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(34, 211, 238, 0.5);
            animation: riseBubble linear infinite;
        }

        .bubble:nth-child(1) { left: 12%; width: 18px; height: 18px; animation-duration: 10s; animation-delay: 0s; }
        .bubble:nth-child(2) { left: 28%; width: 12px; height: 12px; animation-duration: 7s; animation-delay: 1.5s; }
        .bubble:nth-child(3) { left: 45%; width: 22px; height: 22px; animation-duration: 13s; animation-delay: 3s; }
        .bubble:nth-child(4) { left: 62%; width: 14px; height: 14px; animation-duration: 8s; animation-delay: 0.5s; }
        .bubble:nth-child(5) { left: 78%; width: 20px; height: 20px; animation-duration: 11s; animation-delay: 2.5s; }
        .bubble:nth-child(6) { left: 90%; width: 10px; height: 10px; animation-duration: 6s; animation-delay: 4s; }
        .bubble:nth-child(7) { left: 50%; width: 16px; height: 16px; animation-duration: 9s; animation-delay: 5s; }

        /* Glassmorphism Card Container (Aqua Theme) */
        .login-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            width: 100%;
            max-width: 440px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(4, 28, 50, 0.7), 0 0 40px rgba(34, 211, 238, 0.35);
            border: 1.5px solid rgba(165, 243, 252, 0.6);
            overflow: hidden;
            position: relative;
            z-index: 10;
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .login-header {
            background: linear-gradient(135deg, #0891b2 0%, #06b6d4 50%, #0284c7 100%);
            background-size: 200% 200%;
            animation: gradientAqua 8s ease infinite;
            color: #ffffff;
            padding: 36px 28px 28px 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header-icon-wrapper {
            width: 58px;
            height: 58px;
            margin: 0 auto 14px auto;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1.5px solid rgba(255, 255, 255, 0.45);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2), 0 0 15px rgba(165, 243, 252, 0.5);
            animation: floatIcon 4s ease-in-out infinite;
        }

        .header-icon-wrapper svg {
            width: 28px;
            height: 28px;
            stroke: #ffffff;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.25));
        }

        .login-header h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            background: linear-gradient(90deg, #ffffff 0%, #cffaff 50%, #ffffff 100%);
            background-size: 200% auto;
            color: transparent;
            -webkit-background-clip: text;
            background-clip: text;
            animation: textShimmer 4s linear infinite;
            text-transform: uppercase;
            text-shadow: 0 2px 14px rgba(6, 182, 212, 0.5);
        }

        .subtitle-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            color: #ecfeff;
            font-weight: 600;
            letter-spacing: 0.02em;
            backdrop-filter: blur(6px);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: #22d3ee;
            border-radius: 50%;
            box-shadow: 0 0 12px #22d3ee;
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
            border-radius: 12px;
            outline: none;
            transition: all 0.25s ease;
            background-color: #f8fafc;
            color: #0f172a;
        }

        .form-control:focus {
            border-color: #06b6d4;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.18);
            transform: translateY(-1px);
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(6, 182, 212, 0.4);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);
            box-shadow: 0 8px 24px rgba(6, 182, 212, 0.55);
            transform: translateY(-2px);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
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
        @keyframes move-wave {
            0% { transform: translate3d(-90px, 0, 0); }
            100% { transform: translate3d(85px, 0, 0); }
        }

        @keyframes swimRight {
            0% { transform: translateX(-120px) translateY(0) scaleX(1); }
            50% { transform: translateX(calc(100vw + 120px)) translateY(-20px) scaleX(1); }
            50.01% { transform: translateX(calc(100vw + 120px)) translateY(-20px) scaleX(-1); }
            100% { transform: translateX(-120px) translateY(0) scaleX(-1); }
        }

        @keyframes swimLeft {
            0% { transform: translateX(calc(100vw + 120px)) translateY(0) scaleX(-1); }
            50% { transform: translateX(-120px) translateY(18px) scaleX(-1); }
            50.01% { transform: translateX(-120px) translateY(18px) scaleX(1); }
            100% { transform: translateX(calc(100vw + 120px)) translateY(0) scaleX(1); }
        }

        @keyframes swimRightSlow {
            0% { transform: translateX(-120px) translateY(0) scaleX(1); }
            50% { transform: translateX(calc(100vw + 120px)) translateY(15px) scaleX(1); }
            50.01% { transform: translateX(calc(100vw + 120px)) translateY(15px) scaleX(-1); }
            100% { transform: translateX(-120px) translateY(0) scaleX(-1); }
        }

        @keyframes swimLeftFast {
            0% { transform: translateX(calc(100vw + 120px)) translateY(0) scaleX(-1); }
            50% { transform: translateX(-120px) translateY(-15px) scaleX(-1); }
            50.01% { transform: translateX(-120px) translateY(-15px) scaleX(1); }
            100% { transform: translateX(calc(100vw + 120px)) translateY(0) scaleX(1); }
        }

        @keyframes fishJumpLeft {
            0%, 65%, 100% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 0;
            }
            15% { opacity: 1; }
            30% {
                transform: translate(75px, -90px) rotate(35deg);
                opacity: 1;
            }
            45% {
                transform: translate(150px, 15px) rotate(-35deg);
                opacity: 0.8;
            }
            52% { opacity: 0; }
        }

        @keyframes fishJumpRight {
            0%, 65%, 100% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 0;
            }
            15% { opacity: 1; }
            30% {
                transform: translate(-75px, -95px) rotate(-35deg);
                opacity: 1;
            }
            45% {
                transform: translate(-150px, 15px) rotate(35deg);
                opacity: 0.8;
            }
            52% { opacity: 0; }
        }

        @keyframes riseBubble {
            0% {
                transform: translateY(0) scale(0.8);
                opacity: 0;
            }
            20% { opacity: 0.8; }
            80% { opacity: 0.8; }
            100% {
                transform: translateY(-110vh) scale(1.3);
                opacity: 0;
            }
        }

        @keyframes pulseAqua {
            0% { opacity: 0.5; transform: translateX(-50%) scale(1); }
            100% { opacity: 1; transform: translateX(-50%) scale(1.15); }
        }

        @keyframes rayShimmer {
            0%, 100% { opacity: 0.2; transform: rotate(-25deg) translateY(0); }
            50% { opacity: 0.6; transform: rotate(-22deg) translateY(10px); }
        }

        @keyframes gradientAqua {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes textShimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2), 0 0 15px rgba(165, 243, 252, 0.5); }
            50% { transform: translateY(-6px); box-shadow: 0 16px 28px rgba(6, 182, 212, 0.6); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(28px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes badgePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(1.4); }
        }

        @media (max-width: 480px) {
            .aqua-waves-wrapper { height: 28vh; }
            .login-card { border-radius: 20px; }
        }
    </style>
</head>
<body>

    <!-- Aqua Caustics Glow & Rays -->
    <div class="aqua-caustics"></div>
    <div class="light-ray ray-1"></div>
    <div class="light-ray ray-2"></div>
    <div class="light-ray ray-3"></div>

    <!-- Rising Crystal Aqua Bubbles -->
    <div class="bubbles">
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
    </div>

    <!-- Swimming & Jumping Aqua Fish -->
    <div class="fish-container">
        <!-- Fish 1 - Cyan Aqua Fish Swimming Right -->
        <div class="swimming-fish fish-1">
            <svg viewBox="0 0 50 30" width="46" height="27">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#22d3ee"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#06b6d4"/> <!-- Tail -->
                <path d="M 22 8 Q 28 3 32 8" fill="#67e8f9"/> <!-- Dorsal fin -->
                <circle cx="38" cy="12" r="2.2" fill="#ffffff"/>
                <circle cx="39" cy="12" r="1.1" fill="#041c32"/>
            </svg>
        </div>

        <!-- Fish 2 - Deep Aqua Blue Swimming Left -->
        <div class="swimming-fish fish-2">
            <svg viewBox="0 0 50 30" width="38" height="23">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#38bdf8"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#0284c7"/> <!-- Tail -->
                <circle cx="38" cy="12" r="2" fill="#ffffff"/>
                <circle cx="39" cy="12" r="1" fill="#0f172a"/>
            </svg>
        </div>

        <!-- Fish 3 - Golden Tropical Aqua Fish Swimming Right Slow -->
        <div class="swimming-fish fish-3">
            <svg viewBox="0 0 50 30" width="40" height="24">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#facc15"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#eab308"/> <!-- Tail -->
                <circle cx="38" cy="12" r="2" fill="#ffffff"/>
                <circle cx="39" cy="12" r="1" fill="#000000"/>
            </svg>
        </div>

        <!-- Fish 4 - Small Electric Cyan Fish Swimming Left Fast -->
        <div class="swimming-fish fish-4">
            <svg viewBox="0 0 50 30" width="32" height="19">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#a5f3fc"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#06b6d4"/> <!-- Tail -->
                <circle cx="38" cy="12" r="1.8" fill="#ffffff"/>
                <circle cx="39" cy="12" r="0.9" fill="#000000"/>
            </svg>
        </div>

        <!-- Fish 5 - Jumping Fish 1 Left Side -->
        <div class="jumping-fish-1">
            <svg viewBox="0 0 50 30" width="48" height="28">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#38bdf8"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#0284c7"/>
                <circle cx="38" cy="12" r="2.5" fill="#ffffff"/>
                <circle cx="39" cy="12" r="1.2" fill="#000000"/>
            </svg>
        </div>

        <!-- Fish 6 - Jumping Fish 2 Right Side -->
        <div class="jumping-fish-2">
            <svg viewBox="0 0 50 30" width="44" height="26">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#67e8f9"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#0891b2"/>
                <circle cx="38" cy="12" r="2.2" fill="#ffffff"/>
                <circle cx="39" cy="12" r="1.1" fill="#000000"/>
            </svg>
        </div>
    </div>

    <!-- Aqua Waves SVG at bottom -->
    <div class="aqua-waves-wrapper">
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

    <!-- Glassmorphism Login Card -->
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
