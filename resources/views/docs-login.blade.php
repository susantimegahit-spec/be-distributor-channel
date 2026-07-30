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
            background: linear-gradient(180deg, #030712 0%, #0c4a6e 45%, #0284c7 85%, #0369a1 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        /* Ambient Ocean Sunlight Glow */
        .ocean-glow {
            position: absolute;
            top: -20%;
            left: 50%;
            transform: translateX(-50%);
            width: 800px;
            height: 600px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.25) 0%, rgba(14, 165, 233, 0.08) 50%, transparent 80%);
            pointer-events: none;
            z-index: 0;
            animation: pulseSunlight 8s ease-in-out infinite alternate;
        }

        /* SVG Ocean Waves Section */
        .ocean-waves-wrapper {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 35vh;
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
            animation: move-wave 25s cubic-bezier(.55, .5, .45, .5) infinite;
        }
        .parallax > use:nth-child(1) {
            animation-delay: -2s;
            animation-duration: 9s;
            fill: rgba(56, 189, 248, 0.4);
        }
        .parallax > use:nth-child(2) {
            animation-delay: -4s;
            animation-duration: 14s;
            fill: rgba(14, 165, 233, 0.35);
        }
        .parallax > use:nth-child(3) {
            animation-delay: -6s;
            animation-duration: 19s;
            fill: rgba(3, 105, 161, 0.5);
        }
        .parallax > use:nth-child(4) {
            animation-delay: -8s;
            animation-duration: 25s;
            fill: #030712;
        }

        /* Fisherman & Boat Wrapper */
        .fisherman-wrapper {
            position: absolute;
            bottom: 22vh;
            right: 6%;
            z-index: 3;
            pointer-events: none;
            animation: rockBoat 4.5s ease-in-out infinite alternate;
            transform-origin: center bottom;
        }

        .fisherman-svg {
            filter: drop-shadow(0 8px 12px rgba(0, 0, 0, 0.4));
        }

        /* Fishing Bobber / Pelampung */
        .fishing-bobber {
            position: absolute;
            bottom: -20px;
            right: -8px;
            width: 10px;
            height: 14px;
            background: linear-gradient(to bottom, #ef4444 50%, #ffffff 50%);
            border-radius: 5px;
            box-shadow: 0 0 6px rgba(239, 68, 68, 0.8);
            animation: floatBobbing 2s ease-in-out infinite;
        }

        /* Swimming & Jumping Fish */
        .fish-container {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 35vh;
            pointer-events: none;
            z-index: 4;
            overflow: hidden;
        }

        .swimming-fish {
            position: absolute;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.3));
        }

        .fish-1 {
            bottom: 120px;
            animation: swimRight 18s linear infinite;
        }

        .fish-2 {
            bottom: 60px;
            animation: swimLeft 24s linear infinite;
        }

        .jumping-fish {
            position: absolute;
            bottom: 140px;
            right: 12%;
            animation: fishJump 7s ease-in-out infinite;
        }

        /* Ocean Bubbles Animation */
        .bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .bubble {
            position: absolute;
            bottom: -50px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: riseBubble linear infinite;
        }

        .bubble:nth-child(1) { left: 10%; width: 18px; height: 18px; animation-duration: 12s; animation-delay: 0s; }
        .bubble:nth-child(2) { left: 25%; width: 12px; height: 12px; animation-duration: 9s; animation-delay: 2s; }
        .bubble:nth-child(3) { left: 40%; width: 24px; height: 24px; animation-duration: 15s; animation-delay: 4s; }
        .bubble:nth-child(4) { left: 60%; width: 14px; height: 14px; animation-duration: 10s; animation-delay: 1s; }
        .bubble:nth-child(5) { left: 75%; width: 20px; height: 20px; animation-duration: 13s; animation-delay: 3s; }
        .bubble:nth-child(6) { left: 88%; width: 10px; height: 10px; animation-duration: 8s; animation-delay: 5s; }
        .bubble:nth-child(7) { left: 52%; width: 16px; height: 16px; animation-duration: 11s; animation-delay: 6s; }

        /* Glassmorphism Card Container */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            width: 100%;
            max-width: 440px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(3, 7, 18, 0.6), 0 0 35px rgba(56, 189, 248, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.6);
            overflow: hidden;
            position: relative;
            z-index: 10;
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .login-header {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 50%, #075985 100%);
            background-size: 200% 200%;
            animation: gradientWave 10s ease infinite;
            color: #ffffff;
            padding: 36px 28px 28px 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header-icon-wrapper {
            width: 56px;
            height: 56px;
            margin: 0 auto 14px auto;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
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
            background: linear-gradient(90deg, #ffffff 0%, #e0f2fe 50%, #ffffff 100%);
            background-size: 200% auto;
            color: transparent;
            -webkit-background-clip: text;
            background-clip: text;
            animation: textShimmer 4s linear infinite;
            text-transform: uppercase;
            text-shadow: 0 2px 12px rgba(2, 132, 199, 0.4);
        }

        .subtitle-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 6px 16px;
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
            box-shadow: 0 0 10px #38bdf8;
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
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.35);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            box-shadow: 0 8px 22px rgba(2, 132, 199, 0.45);
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
        @keyframes move-wave {
            0% { transform: translate3d(-90px, 0, 0); }
            100% { transform: translate3d(85px, 0, 0); }
        }

        @keyframes rockBoat {
            0% { transform: translateY(0px) rotate(-3deg); }
            50% { transform: translateY(-8px) rotate(2deg); }
            100% { transform: translateY(3px) rotate(-2deg); }
        }

        @keyframes floatBobbing {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(6px); }
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

        @keyframes fishJump {
            0%, 65%, 100% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 0;
            }
            15% {
                opacity: 1;
            }
            30% {
                transform: translate(-65px, -85px) rotate(-40deg);
                opacity: 1;
            }
            45% {
                transform: translate(-130px, 15px) rotate(40deg);
                opacity: 0.8;
            }
            52% {
                opacity: 0;
            }
        }

        @keyframes riseBubble {
            0% {
                transform: translateY(0) scale(0.8);
                opacity: 0;
            }
            20% { opacity: 0.6; }
            80% { opacity: 0.6; }
            100% {
                transform: translateY(-110vh) scale(1.2);
                opacity: 0;
            }
        }

        @keyframes pulseSunlight {
            0% { opacity: 0.5; transform: translateX(-50%) scale(1); }
            100% { opacity: 0.9; transform: translateX(-50%) scale(1.1); }
        }

        @keyframes gradientWave {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes textShimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15); }
            50% { transform: translateY(-6px); box-shadow: 0 16px 28px rgba(2, 132, 199, 0.4); }
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

        @media (max-width: 768px) {
            .fisherman-wrapper { transform: scale(0.75); right: 2%; bottom: 18vh; }
            .ocean-waves-wrapper { height: 25vh; }
            .login-card { border-radius: 18px; }
        }
    </style>
</head>
<body>

    <!-- Ambient Ocean Sunlight Glow -->
    <div class="ocean-glow"></div>

    <!-- Rising Ocean Bubbles -->
    <div class="bubbles">
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
    </div>

    <!-- Fisherman on Boat -->
    <div class="fisherman-wrapper">
        <svg class="fisherman-svg" viewBox="0 0 160 120" width="140" height="105">
            <!-- Wooden Boat Hull -->
            <path d="M 20 80 Q 80 102 140 80 L 125 98 Q 80 108 35 98 Z" fill="#b45309" stroke="#78350f" stroke-width="2"/>
            <path d="M 25 80 L 135 80" stroke="#f59e0b" stroke-width="3"/>
            <path d="M 40 85 L 120 85" stroke="#92400e" stroke-width="1.5"/>
            
            <!-- Fisherman Silhouette -->
            <circle cx="55" cy="50" r="7.5" fill="#fde047"/> <!-- Cap / Head -->
            <path d="M 48 45 L 60 45 L 66 50 L 46 50 Z" fill="#eab308"/> <!-- Hat Brim -->
            <path d="M 50 58 Q 55 56 62 58 L 68 78 L 48 78 Z" fill="#0284c7"/> <!-- Jacket -->
            
            <!-- Arm & Fishing Rod -->
            <path d="M 58 63 L 82 56" stroke="#38bdf8" stroke-width="3" stroke-linecap="round"/>
            <path d="M 78 58 L 148 22" stroke="#fcd34d" stroke-width="2.5" stroke-linecap="round"/> <!-- Fishing Rod -->
            
            <!-- Fishing Line going into ocean -->
            <path d="M 148 22 Q 155 60 148 105" stroke="rgba(255,255,255,0.75)" stroke-width="1" fill="none" stroke-dasharray="3,2"/>
        </svg>
        
        <!-- Red/White Fishing Bobber on water -->
        <div class="fishing-bobber"></div>
    </div>

    <!-- Swimming & Jumping Fish -->
    <div class="fish-container">
        <!-- Fish 1 Swimming Right -->
        <div class="swimming-fish fish-1">
            <svg viewBox="0 0 50 30" width="42" height="25">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#f97316"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#ea580c"/> <!-- Tail -->
                <circle cx="38" cy="12" r="2" fill="#ffffff"/> <!-- Eye -->
                <circle cx="39" cy="12" r="1" fill="#000000"/>
                <path d="M 25 10 Q 30 15 25 20" stroke="#c2410c" stroke-width="1.5" fill="none"/>
            </svg>
        </div>

        <!-- Fish 2 Swimming Left -->
        <div class="swimming-fish fish-2">
            <svg viewBox="0 0 50 30" width="36" height="22">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#38bdf8"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#0284c7"/> <!-- Tail -->
                <circle cx="38" cy="12" r="2" fill="#ffffff"/> <!-- Eye -->
                <circle cx="39" cy="12" r="1" fill="#000000"/>
            </svg>
        </div>

        <!-- Fish 3 Jumping out of ocean waves -->
        <div class="jumping-fish">
            <svg viewBox="0 0 50 30" width="45" height="27">
                <path d="M 10 15 C 20 5, 35 5, 45 15 C 35 25, 20 25, 10 15 Z" fill="#facc15"/>
                <path d="M 10 15 L 0 5 L 4 15 L 0 25 Z" fill="#eab308"/> <!-- Tail -->
                <circle cx="38" cy="12" r="2.5" fill="#ffffff"/> <!-- Eye -->
                <circle cx="39" cy="12" r="1" fill="#000000"/>
            </svg>
        </div>
    </div>

    <!-- Ocean Waves SVG at bottom -->
    <div class="ocean-waves-wrapper">
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
