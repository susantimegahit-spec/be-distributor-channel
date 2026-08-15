<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SMESTA Documentation API</title>
    
    <!-- Google Fonts: Poppins & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #0b1329 0%, #030712 100%);
        }

        /* Outer Modern Container Box */
        .login-canvas {
            position: relative;
            width: 100%;
            max-width: 960px;
            min-height: 580px;
            background-color: #1e3a5f; /* Elegant Deep Ocean Blue */
            border-radius: 28px;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            display: flex;
            animation: zoomIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* ---------------------------------------------------- */
        /* RIGHT SIDE: Dynamic Fluid Wave (SMESTA Theme)        */
        /* ---------------------------------------------------- */
        .wave-backdrop {
            position: absolute;
            right: 0;
            top: 0;
            width: 48%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .wave-svg {
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            object-fit: fill;
        }

        /* Floating Lamp Element (from design) */
        .hanging-lamp {
            position: absolute;
            top: 0;
            right: 28%;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            animation: swingLamp 6s ease-in-out infinite alternate;
            transform-origin: top center;
        }

        .lamp-cord {
            width: 2px;
            height: 60px;
            background: rgba(255, 255, 255, 0.4);
        }

        .lamp-shade {
            width: 70px;
            height: 36px;
            background: #38bdf8;
            border-radius: 35px 35px 6px 6px;
            box-shadow: 0 0 25px rgba(56, 189, 248, 0.7);
            position: relative;
        }

        .lamp-bulb {
            width: 24px;
            height: 14px;
            background: #ffffff;
            border-radius: 0 0 12px 12px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.9);
        }

        /* 3D Illustration Figure Container */
        .illustration-wrapper {
            position: absolute;
            right: 8%;
            top: 50%;
            transform: translateY(-40%);
            z-index: 3;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 320px;
        }

        .illustration-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 24px 28px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            animation: floatSlow 5s ease-in-out infinite alternate;
        }

        .illustration-card img {
            max-height: 75px;
            width: auto;
            object-fit: contain;
        }

        .illustration-tagline {
            font-size: 11px;
            font-weight: 700;
            color: #0369a1;
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }

        /* ---------------------------------------------------- */
        /* LEFT SIDE: Login Form                                */
        /* ---------------------------------------------------- */
        .form-side {
            width: 52%;
            padding: 50px 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            z-index: 5;
            position: relative;
        }

        .avatar-circle {
            width: 72px;
            height: 72px;
            background: rgba(255, 255, 255, 0.12);
            border: 2px solid rgba(56, 189, 248, 0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(8px);
        }

        .avatar-circle svg {
            width: 38px;
            height: 38px;
            fill: #38bdf8;
        }

        .title-welcome {
            font-size: 38px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 28px;
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        /* Rounded Inputs */
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group label {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 8px;
            letter-spacing: 0.02em;
        }

        .input-line {
            width: 100%;
            background: rgba(255, 255, 255, 0.08);
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            padding: 12px 18px;
            font-size: 14.5px;
            font-weight: 500;
            color: #ffffff;
            outline: none;
            transition: all 0.25s ease;
            font-family: 'Inter', sans-serif;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        .input-line:focus {
            background: rgba(255, 255, 255, 0.14);
            border-color: #38bdf8;
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.2), inset 0 2px 4px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .input-line::placeholder {
            color: rgba(203, 213, 225, 0.45);
            font-size: 13.5px;
        }

        /* Submit Button (SMESTA Cyan/Sky Gradient with Rounded Shape) */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%);
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(2, 132, 199, 0.4);
            transition: all 0.25s ease;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:hover {
            background: linear-gradient(90deg, #0369a1 0%, #0284c7 100%);
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(2, 132, 199, 0.5);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Alerts */
        .alert-box {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-err {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
        }

        .alert-warn {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.5);
            color: #fde68a;
        }

        /* Bottom Footer Brand */
        .bottom-brand {
            margin-top: 36px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #94a3b8;
            font-size: 12.5px;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s;
        }

        .bottom-brand:hover {
            color: #ffffff;
        }

        /* Keyframe Animations */
        @keyframes zoomIn {
            from { opacity: 0; transform: scale(0.94); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes swingLamp {
            0% { transform: rotate(-3deg); }
            100% { transform: rotate(3deg); }
        }

        @keyframes floatSlow {
            0% { transform: translateY(0); }
            100% { transform: translateY(-12px); }
        }

        @media (max-width: 820px) {
            .login-canvas { flex-direction: column; min-height: auto; max-width: 440px; }
            .form-side { width: 100%; padding: 40px 30px; }
            .wave-backdrop, .illustration-wrapper, .hanging-lamp { display: none; }
        }
    </style>
</head>
<body>

    <div class="login-canvas">
        
        <!-- RIGHT FLUID SHAPE (SMESTA Colors: Blue & Warm Golden Sun Wave) -->
        <div class="wave-backdrop">
            <svg class="wave-svg" viewBox="0 0 500 700" preserveAspectRatio="none">
                <path d="M 120 0 Q 30 250 180 380 T 10 700 L 500 700 L 500 0 Z" fill="#0284c7" opacity="0.9" />
                <path d="M 220 0 Q 140 280 290 420 T 140 700 L 500 700 L 500 0 Z" fill="#f59e0b" />
            </svg>
        </div>

        <!-- Hanging Lamp Element -->
        <div class="hanging-lamp">
            <div class="lamp-cord"></div>
            <div class="lamp-shade"></div>
            <div class="lamp-bulb"></div>
        </div>

        <!-- Right Side Card with SMESTA Logo -->
        <div class="illustration-wrapper">
            <div class="illustration-card">
                <img src="{{ asset('images/logo-smesta-transparent.png') }}" alt="SMESTA" />
                <div class="illustration-tagline">INTEGRATED • CONNECTED • INTELLIGENT</div>
            </div>
        </div>

        <!-- LEFT SIDE: Modern Welcome Form -->
        <div class="form-side">
            
            <div class="avatar-circle">
                <svg viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>

            <h1 class="title-welcome">WELCOME</h1>

            @if(session('error'))
                <div class="alert-box alert-err">
                    <span>⚠️</span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if(request()->has('expired'))
                <div class="alert-box alert-warn">
                    <span>⏱️</span>
                    <span>Sesi telah berakhir. Silakan login kembali.</span>
                </div>
            @endif

            <form action="{{ url('/docs/login') }}" method="POST">
                @csrf

                <!-- Username Input (Line Style) -->
                <div class="input-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="input-line" 
                        placeholder="Ketik username Anda..." 
                        required 
                        autofocus 
                        autocomplete="username"
                    >
                </div>

                <!-- Password Input (Line Style) -->
                <div class="input-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="input-line" 
                        placeholder="Ketik password Anda..." 
                        required 
                        autocomplete="current-password"
                    >
                </div>

                <!-- Action Button -->
                <button type="submit" class="btn-login">
                    <span>LOGIN</span>
                </button>
            </form>

            <!-- Bottom Brand Info -->
            <a href="{{ url('/') }}" class="bottom-brand">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>PT Susanti Megah • SMESTA API</span>
            </a>

        </div>

    </div>

</body>
</html>
