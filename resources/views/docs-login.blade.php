<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SMESTA API Documentation</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: #090d16;
            background-image: 
                radial-gradient(at 0% 0%, rgba(2, 132, 199, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(14, 165, 233, 0.08) 0px, transparent 50%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.5s ease-out;
        }

        .login-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 36px 32px 24px 32px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }

        .brand-logo {
            max-height: 52px;
            width: auto;
            object-fit: contain;
            margin-bottom: 16px;
        }

        .card-header h1 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }

        .card-header p {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        .card-body {
            padding: 28px 32px 36px 32px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.4;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background-color: #fffbeb;
            color: #92400e;
            border: 1px solid #fef3c7;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
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
            color: #94a3b8;
            pointer-events: none;
            display: flex;
            align-items: center;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px 12px 42px;
            font-size: 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            outline: none;
            transition: all 0.2s ease;
            background-color: #f8fafc;
            color: #0f172a;
        }

        .form-control:focus {
            border-color: #0284c7;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .btn-submit {
            width: 100%;
            padding: 13px 20px;
            background: #0284c7;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }

        .btn-submit:hover {
            background: #0369a1;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
            transform: translateY(-1px);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #64748b;
        }

        .footer-note a {
            color: #38bdf8;
            text-decoration: none;
            font-weight: 500;
        }

        .footer-note a:hover {
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            
            <!-- Card Header -->
            <div class="card-header">
                <img src="{{ asset('images/logo-smesta-transparent.png') }}" alt="SMESTA Logo" class="brand-logo" />
                <h1>API Documentation</h1>
                <p>PT Susanti Megah • IT Department</p>
            </div>

            <!-- Card Body -->
            <div class="card-body">
                @if(session('error'))
                    <div class="alert alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if(request()->has('expired'))
                    <div class="alert alert-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>Sesi telah berakhir. Silakan login kembali.</span>
                    </div>
                @endif

                <form action="{{ url('/docs/login') }}" method="POST">
                    @csrf
                    
                    <!-- Username -->
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-wrapper">
                            <div class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="form-control" 
                                placeholder="Masukkan username" 
                                required 
                                autofocus 
                                autocomplete="username"
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-wrapper">
                            <div class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </div>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="Masukkan password" 
                                required 
                                autocomplete="current-password"
                            >
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit">
                        <span>Masuk ke Dokumentasi</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </form>
            </div>
        </div>

        <div class="footer-note">
            &copy; {{ date('Y') }} PT Susanti Megah • <a href="{{ url('/') }}">Kembali ke Beranda</a>
        </div>
    </div>

</body>
</html>
