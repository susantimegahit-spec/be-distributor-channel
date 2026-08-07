<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login System Monitoring - PT Susanti Megah</title>
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
            background: linear-gradient(180deg, #020617 0%, #0f172a 50%, #1e1b4b 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        .glow-bg {
            position: absolute;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(14, 165, 233, 0.08) 50%, transparent 75%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 0;
        }

        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(2, 6, 23, 0.8), 0 0 30px rgba(99, 102, 241, 0.2);
            overflow: hidden;
            position: relative;
            z-index: 10;
            animation: cardAppear 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .login-header {
            background: linear-gradient(135deg, #4f46e5 0%, #0284c7 100%);
            color: #ffffff;
            padding: 36px 26px 26px 26px;
            text-align: center;
            position: relative;
        }

        .header-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 12px auto;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .header-icon svg {
            width: 28px;
            height: 28px;
            stroke: #ffffff;
        }

        .login-header h1 {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .badge-pulse {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: #e0e7ff;
            font-weight: 600;
        }

        .dot-green {
            width: 7px;
            height: 7px;
            background-color: #22c55e;
            border-radius: 50%;
            box-shadow: 0 0 8px #22c55e;
        }

        .login-body {
            padding: 30px 26px;
            background-color: #ffffff;
            color: #0f172a;
        }

        .alert-error {
            padding: 12px 16px;
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            border-color: #4f46e5;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
            transform: translateY(-1px);
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #4f46e5 0%, #0284c7 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #4338ca 0%, #0369a1 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.45);
        }

        .login-footer {
            text-align: center;
            padding: 16px 26px;
            background-color: #f8fafc;
            border-top: 1px solid #f1f5f9;
            font-size: 12px;
            color: #64748b;
        }

        @keyframes cardAppear {
            from { opacity: 0; transform: translateY(20px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
    </style>
</head>
<body>

    <div class="glow-bg"></div>

    <div class="login-card">
        <div class="login-header">
            <div class="header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
            <h1>SYSTEM MONITORING</h1>
            <div class="badge-pulse">
                <span class="dot-green"></span>
                PT SUSANTI MEGAH • MONITORING SM
            </div>
        </div>

        <div class="login-body">
            @if(session('error'))
                <div class="alert-error">
                    <span>⚠️</span>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            <form action="{{ url('/monitoringsm/login') }}" method="POST">
                <div class="form-group">
                    <label for="username">Username Admin</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required autofocus autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">Password Admin</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn-submit">
                    <span>Masuk ke System Monitoring</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
            </form>
        </div>

        <div class="login-footer">
            Pulse Monitoring Control Panel &copy; {{ date('Y') }} PT Susanti Megah
        </div>
    </div>

</body>
</html>
