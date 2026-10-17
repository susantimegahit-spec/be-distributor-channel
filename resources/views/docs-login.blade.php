<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Dokumentasi API - SMESTA PT Susanti Megah</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Three.js untuk Skenario 3D Laut & Kapal -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <!-- Google Fonts: Inter & Plus Jakarta Sans -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap"
        rel="stylesheet">
    <!-- Phosphor Icons untuk Ikon Nautika -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow: hidden;
            background-color: #06152d;
            user-select: none;
        }

        h1,
        h2,
        .brand-font {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* Kanvas 3D di latar belakang */
        #ocean-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            cursor: grab;
        }

        #ocean-canvas:active {
            cursor: grabbing;
        }

        /* Kontainer Perspektif 3D untuk Kartu Login */
        .perspective-container {
            perspective: 1200px;
            z-index: 10;
        }

        /* Gaya Kartu Glassmorphism 3D */
        .card-3d {
            transform-style: preserve-3d;
            transition: transform 0.15s ease-out, box-shadow 0.3s ease;
            background: linear-gradient(135deg, rgba(15, 35, 65, 0.82) 0%, rgba(7, 20, 40, 0.92) 100%);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 2px solid rgba(250, 204, 21, 0.35);
            box-shadow:
                0 25px 50px -12px rgba(0, 0, 0, 0.75),
                0 0 35px rgba(250, 204, 21, 0.18),
                inset 0 1px 2px rgba(255, 255, 255, 0.2);
        }

        .card-3d:hover {
            border-color: rgba(250, 204, 21, 0.65);
            box-shadow:
                0 30px 60px -12px rgba(0, 0, 0, 0.85),
                0 0 45px rgba(250, 204, 21, 0.3),
                inset 0 1px 2px rgba(255, 255, 255, 0.3);
        }

        /* Elemen yang menonjol dalam ruang 3D (Z-axis) */
        .depth-layer-1 {
            transform: translateZ(20px);
        }

        .depth-layer-2 {
            transform: translateZ(40px);
        }

        .depth-layer-3 {
            transform: translateZ(60px);
        }

        /* Animasi Kemudi Kapal berputar perlahan */
        @keyframes spin-slow {
            0% {
                transform: rotate(0deg);
            }

            25% {
                transform: rotate(25deg);
            }

            50% {
                transform: rotate(0deg);
            }

            75% {
                transform: rotate(-25deg);
            }

            100% {
                transform: rotate(0deg);
            }
        }

        .helm-spin {
            animation: spin-slow 8s ease-in-out infinite;
        }

        /* Efek Kilau pada Tombol Kuning */
        .btn-shine {
            position: relative;
            overflow: hidden;
        }

        .btn-shine::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 40%;
            height: 200%;
            background: rgba(255, 255, 255, 0.4);
            transform: rotate(30deg);
            transition: all 0.6s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .btn-shine:hover::after {
            left: 130%;
        }

        /* Transisi Sembunyikan/Tampilkan Panel Login */
        .panel-transition {
            transition: opacity 0.45s cubic-bezier(0.16, 1, 0.3, 1), transform 0.45s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .panel-hidden {
            opacity: 0 !important;
            pointer-events: none !important;
            transform: translateX(60px) scale(0.95) !important;
        }

        /* Kustomisasi Scrollbar jika layar sangat kecil */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background: #eab308;
            border-radius: 3px;
        }
    </style>
</head>

<body
    class="relative w-full h-screen flex flex-col lg:flex-row items-center justify-end lg:justify-between p-3 sm:p-6 lg:px-12 xl:px-20 overflow-hidden">

    <!-- Kanvas Three.js 3D -->
    <canvas id="ocean-canvas"></canvas>

    <!-- Header & Kontrol Interaktif 3D di Pojok Kiri Atas -->
    <div class="fixed top-4 left-4 right-4 sm:right-auto z-30 flex flex-col gap-2.5 pointer-events-none sm:max-w-xs">
        <div
            class="flex items-center justify-between sm:justify-start gap-3 bg-slate-950/85 backdrop-blur-md border border-yellow-400/50 px-4 py-2.5 rounded-2xl shadow-xl pointer-events-auto">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-xl bg-gradient-to-br from-yellow-300 to-amber-500 flex items-center justify-center text-slate-950 shadow-md shadow-yellow-400/30">
                    <i class="ph-fill ph-boat text-2xl"></i>
                </div>
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-yellow-400 block">PT Susanti Megah • SMESTA API</span>
                    <h2 class="text-white font-extrabold text-sm tracking-tight">KM. Surya Kencana</h2>
                </div>
            </div>
            <!-- Tombol Cepat Sembunyikan/Tampilkan Form di Mobile -->
            <button id="toggle-form-mobile"
                class="lg:hidden flex items-center gap-1 bg-yellow-400/20 border border-yellow-400/50 text-yellow-300 text-xs font-bold px-2.5 py-1.5 rounded-xl">
                <i class="ph-bold ph-eye text-sm"></i>
                <span>Kapal</span>
            </button>
        </div>

        <!-- Panel Kontrol Skenario 3D & Kamera Kapal -->
        <div
            class="bg-slate-950/80 backdrop-blur-md border border-slate-700/80 p-3 rounded-2xl shadow-xl pointer-events-auto flex flex-col gap-2">
            <div class="flex items-center justify-between text-xs text-slate-300 px-1">
                <span class="flex items-center gap-1.5"><i class="ph-bold ph-hand-grabbing text-yellow-400"></i> Geser:
                    Putar • Scroll: Zoom</span>
                <span
                    class="text-[10px] bg-yellow-400/20 text-yellow-300 px-2 py-0.5 rounded-full font-semibold border border-yellow-400/30">3D
                    Aktif</span>
            </div>
            <div class="grid grid-cols-2 gap-2 mt-0.5">
                <button id="zoom-ship-btn"
                    class="flex items-center justify-center gap-1.5 bg-yellow-400 hover:bg-yellow-300 text-slate-950 text-xs font-extrabold py-2 px-3 rounded-xl transition active:scale-95 shadow-md shadow-yellow-400/20">
                    <i class="ph-bold ph-magnifying-glass-plus text-base"></i>
                    <span id="zoom-label">Zoom Kapal</span>
                </button>
                <button id="toggle-form-btn"
                    class="flex items-center justify-center gap-1.5 bg-slate-800/90 hover:bg-slate-700 border border-yellow-400/40 text-yellow-300 text-xs font-semibold py-2 px-3 rounded-xl transition active:scale-95">
                    <i id="form-toggle-icon" class="ph-bold ph-arrows-outSimple text-base text-yellow-400"></i>
                    <span id="form-toggle-label">Layar Penuh</span>
                </button>
                <button id="toggle-time-btn"
                    class="flex items-center justify-center gap-1.5 bg-slate-800/90 hover:bg-slate-700 border border-slate-600 text-slate-200 text-xs font-medium py-2 px-3 rounded-xl transition active:scale-95">
                    <i id="time-icon" class="ph-fill ph-sun-horizon text-base text-yellow-400"></i>
                    <span id="time-label">Mode Senja</span>
                </button>
                <button id="horn-btn"
                    class="flex items-center justify-center gap-1.5 bg-slate-800/90 hover:bg-slate-700 border border-slate-600 text-slate-200 text-xs font-medium py-2 px-3 rounded-xl transition active:scale-95"
                    title="Klakson, Sorot Lampu & Uji Ombak">
                    <i class="ph-fill ph-megaphone text-base text-yellow-400"></i>
                    <span>Uji Ombak</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Label Penunjuk Kapal Interaktif di Area Kiri-Tengah (Tidak menghalangi kapal) -->
    <div
        class="fixed bottom-4 left-4 z-20 hidden sm:flex items-center gap-3 bg-slate-950/80 backdrop-blur-md border border-yellow-400/30 px-4 py-2.5 rounded-2xl text-xs text-slate-300 pointer-events-auto shadow-lg">
        <span class="flex items-center gap-2 text-yellow-400 font-bold">
            <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 animate-ping"></span>
            KM. Surya Kencana (Cap Kapal)
        </span>
        <span class="text-slate-600">|</span>
        <span id="coord-display" class="font-mono text-slate-300">Kecepatan: 14 Knot • Status: Siap Berlayar</span>
    </div>

    <!-- Spacer Area Kiri untuk Panggung Pameran Kapal 3D di Desktop -->
    <div class="hidden lg:block lg:w-7/12 xl:w-8/12 h-full pointer-events-none"></div>

    <!-- Kontainer Utama Kartu Login 3D (Di Sisi Kanan pada Desktop, Kompak di Bawah pada Mobile) -->
    <div id="login-wrapper"
        class="perspective-container panel-transition w-full max-w-[400px] sm:max-w-[420px] lg:w-5/12 xl:w-4/12 max-h-[65vh] sm:max-h-[90vh] overflow-y-auto lg:overflow-visible z-20 mt-auto lg:my-auto">
        <div id="login-card" class="card-3d rounded-3xl p-5 sm:p-8 relative">

            <!-- Ornamen Sudut Baut Emas/Kuning -->
            <div class="w-2.5 h-2.5 rounded-full bg-yellow-400/60 absolute top-4 left-4 shadow-inner"></div>
            <div class="w-2.5 h-2.5 rounded-full bg-yellow-400/60 absolute top-4 right-4 shadow-inner"></div>
            <div class="w-2.5 h-2.5 rounded-full bg-yellow-400/60 absolute bottom-4 left-4 shadow-inner"></div>
            <div class="w-2.5 h-2.5 rounded-full bg-yellow-400/60 absolute bottom-4 right-4 shadow-inner"></div>

            <!-- Lencana Kemudi Kapal 3D & Logo SMESTA di Atas Kartu -->
            <div class="depth-layer-3 flex flex-col items-center -mt-2 sm:-mt-12 mb-3">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-yellow-300 via-yellow-400 to-amber-500 p-0.5 shadow-xl shadow-yellow-500/30 flex items-center justify-center relative group cursor-pointer"
                    id="badge-helm" title="Klik untuk membunyikan sinyal kapal">
                    <div
                        class="w-full h-full bg-slate-950/90 rounded-[14px] flex items-center justify-center border border-yellow-300/40">
                        <i class="ph-fill ph-steering-wheel text-3xl sm:text-4xl text-yellow-400 helm-spin"></i>
                    </div>
                    <span
                        class="absolute -bottom-2 bg-yellow-400 text-slate-950 text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-0.5 rounded-full shadow">
                        SMESTA API
                    </span>
                </div>
            </div>

            <!-- Judul & Subjudul -->
            <div class="depth-layer-2 text-center mb-4">
                <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight">
                    Anjungan <span
                        class="text-transparent bg-clip-text bg-gradient-to-r from-yellow-300 via-yellow-400 to-amber-400">Komando API</span>
                </h1>
                <p class="text-slate-300 text-xs mt-1">
                    Portal Dokumentasi & Integrasi PT Susanti Megah
                </p>
            </div>

            <!-- Kotak Pesan Status / Notifikasi Kustom & Flash Server -->
            @if(session('error'))
                <div id="status-box"
                    class="depth-layer-2 mb-4 p-3 rounded-2xl border text-xs flex items-center gap-3 bg-rose-500/20 border-rose-400 text-rose-200 transition-all duration-300">
                    <i id="status-icon" class="ph-fill ph-x-circle text-2xl text-rose-400 shrink-0"></i>
                    <div class="flex-1">
                        <p id="status-title" class="font-bold">Akses Ditolak</p>
                        <p id="status-desc" class="text-[11px] opacity-90 mt-0.5">{{ session('error') }}</p>
                    </div>
                </div>
            @elseif(request()->has('expired'))
                <div id="status-box"
                    class="depth-layer-2 mb-4 p-3 rounded-2xl border text-xs flex items-center gap-3 bg-yellow-500/20 border-yellow-400 text-yellow-200 transition-all duration-300">
                    <i id="status-icon" class="ph-fill ph-clock-countdown text-2xl text-yellow-400 shrink-0"></i>
                    <div class="flex-1">
                        <p id="status-title" class="font-bold">Sesi Berakhir</p>
                        <p id="status-desc" class="text-[11px] opacity-90 mt-0.5">Sesi login Anda telah kedaluwarsa. Silakan masuk kembali.</p>
                    </div>
                </div>
            @else
                <div id="status-box"
                    class="depth-layer-2 hidden mb-4 p-3 rounded-2xl border text-xs flex items-center gap-3 transition-all duration-300">
                    <i id="status-icon" class="text-xl shrink-0"></i>
                    <div class="flex-1">
                        <p id="status-title" class="font-bold"></p>
                        <p id="status-desc" class="text-[11px] opacity-90 mt-0.5"></p>
                    </div>
                </div>
            @endif

            <!-- Formulir Login -->
            <form id="login-form" class="space-y-3.5" action="{{ url('/docs/login') }}" method="POST">
                @csrf

                <!-- Input Nama Pengguna / ID Pengembang -->
                <div class="depth-layer-2">
                    <label for="username"
                        class="block text-[11px] font-semibold uppercase tracking-wider text-yellow-300/90 mb-1 ml-1">
                        Nama Pengguna / Username
                    </label>
                    <div class="relative group">
                        <div
                            class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-yellow-400/80 group-focus-within:text-yellow-300 transition">
                            <i class="ph-fill ph-anchor text-lg"></i>
                        </div>
                        <input type="text" id="username" name="username" required placeholder="Contoh: adminsm"
                            autocomplete="username" autofocus
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-900/85 border border-slate-700/90 focus:border-yellow-400 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/30 transition shadow-inner">
                    </div>
                </div>

                <!-- Input Kata Sandi / Password -->
                <div class="depth-layer-2">
                    <div class="flex items-center justify-between mb-1 ml-1">
                        <label for="password"
                            class="block text-[11px] font-semibold uppercase tracking-wider text-yellow-300/90">
                            Kata Sandi / Password
                        </label>
                        <button type="button" id="forgot-btn"
                            class="text-[11px] text-yellow-400 hover:text-yellow-300 font-medium underline-offset-4 hover:underline transition">
                            Lupa Sandi?
                        </button>
                    </div>
                    <div class="relative group">
                        <div
                            class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-yellow-400/80 group-focus-within:text-yellow-300 transition">
                            <i class="ph-fill ph-key text-lg"></i>
                        </div>
                        <input type="password" id="password" name="password" required placeholder="••••••••••••"
                            autocomplete="current-password"
                            class="w-full pl-10 pr-11 py-2.5 bg-slate-900/85 border border-slate-700/90 focus:border-yellow-400 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/30 transition shadow-inner">
                        <button type="button" id="toggle-password"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-yellow-400 transition"
                            aria-label="Tampilkan Kata Sandi">
                            <i id="eye-icon" class="ph-bold ph-eye text-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- Opsi Ingat Saya & Dermaga API -->
                <div class="depth-layer-1 flex items-center justify-between pt-0.5">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <div class="relative flex items-center justify-center">
                            <input type="checkbox" id="remember" name="remember" class="peer sr-only" checked>
                            <div
                                class="w-4 h-4 rounded bg-slate-900 border border-slate-600 peer-checked:bg-yellow-400 peer-checked:border-yellow-400 transition flex items-center justify-center">
                                <i
                                    class="ph-bold ph-check text-slate-950 text-[10px] opacity-0 peer-checked:opacity-100 transition"></i>
                            </div>
                        </div>
                        <span class="text-xs text-slate-300 group-hover:text-white transition">Ingat Sesi Login</span>
                    </label>
                    <span
                        class="text-[10px] text-yellow-400 bg-yellow-400/15 border border-yellow-400/30 px-2 py-0.5 rounded-full font-semibold">
                        SMESTA v1.0
                    </span>
                </div>

                <!-- Tombol Utama Masuk (Kuning Cerah 3D) -->
                <div class="depth-layer-3 pt-1">
                    <button type="submit" id="submit-btn"
                        class="btn-shine w-full py-3 px-5 rounded-xl bg-gradient-to-r from-yellow-400 via-amber-400 to-yellow-500 hover:from-yellow-300 hover:to-amber-400 text-slate-950 font-extrabold text-xs sm:text-sm uppercase tracking-wider shadow-lg shadow-yellow-400/25 hover:shadow-yellow-400/40 active:scale-[0.99] transition-all flex items-center justify-center gap-2">
                        <span>Buka Dokumentasi (Masuk)</span>
                        <i class="ph-bold ph-compass text-lg"></i>
                    </button>
                </div>
            </form>

            <!-- Pembatas Nautika -->
            <div class="depth-layer-1 relative flex py-3.5 items-center">
                <div class="flex-grow border-t border-slate-700/80"></div>
                <span
                    class="flex-shrink mx-3 text-[10px] uppercase tracking-widest text-slate-400 flex items-center gap-1">
                    <i class="ph-fill ph-waves text-yellow-400"></i> Akses Cepat Pengembang
                </span>
                <div class="flex-grow border-t border-slate-700/80"></div>
            </div>

            <!-- Tombol Login Cepat & Bantuan -->
            <div class="depth-layer-2 grid grid-cols-2 gap-2.5">
                <button type="button" id="demo-fill-btn"
                    class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-slate-800/90 hover:bg-slate-800 border border-yellow-400/30 hover:border-yellow-400/70 text-yellow-300 text-xs font-semibold transition active:scale-95">
                    <i class="ph-fill ph-key text-base text-yellow-400"></i>
                    <span>Isi Akun Cepat</span>
                </button>
                <button type="button" id="help-btn"
                    class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-slate-800/90 hover:bg-slate-800 border border-slate-700 hover:border-slate-500 text-slate-200 text-xs font-semibold transition active:scale-95">
                    <i class="ph-fill ph-question text-base text-yellow-400"></i>
                    <span>Bantuan API</span>
                </button>
            </div>

            <!-- Footer Hak Cipta -->
            <div class="depth-layer-1 mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
                <div class="flex items-center gap-1.5">
                    <i class="ph-fill ph-shield-check text-yellow-400 text-sm"></i>
                    <span>PT Susanti Megah</span>
                </div>
                <span class="text-slate-500">Confidential System</span>
            </div>

        </div>
    </div>

    <!-- Script Utama: Skenario Three.js 3D & Interaksi UI -->
    <script>
        // ==========================================
        // 1. LOGIKA UI, KARTU 3D TILT, & FORMULIR
        // ==========================================
        const loginWrapper = document.getElementById('login-wrapper');
        const loginCard = document.getElementById('login-card');
        const loginForm = document.getElementById('login-form');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const submitBtn = document.getElementById('submit-btn');
        const togglePasswordBtn = document.getElementById('toggle-password');
        const eyeIcon = document.getElementById('eye-icon');
        const statusBox = document.getElementById('status-box');
        const statusIcon = document.getElementById('status-icon');
        const statusTitle = document.getElementById('status-title');
        const statusDesc = document.getElementById('status-desc');
        const demoFillBtn = document.getElementById('demo-fill-btn');
        const forgotBtn = document.getElementById('forgot-btn');
        const helpBtn = document.getElementById('help-btn');
        const coordDisplay = document.getElementById('coord-display');

        // Variabel global untuk mengontrol kecepatan kapal & kamera
        let shipSpeedMultiplier = 1.0;
        let shipJumpImpulse = 0;
        let isZoomedIn = false;
        let isFormHidden = false;

        // Efek 3D Tilt pada Kartu Login saat kursor bergerak di atas kartu
        loginCard.addEventListener('mousemove', (e) => {
            const rect = loginCard.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX = ((y - centerY) / centerY) * -7;
            const rotateY = ((x - centerX) / centerX) * 7;

            loginCard.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });

        loginCard.addEventListener('mouseleave', () => {
            loginCard.style.transform = `rotateX(0deg) rotateY(0deg)`;
        });

        // Fungsi menampilkan pesan status tanpa alert()
        function showMessage(type, title, desc) {
            statusBox.classList.remove('hidden', 'bg-yellow-500/20', 'border-yellow-400', 'text-yellow-200', 'bg-emerald-500/20', 'border-emerald-400', 'text-emerald-200', 'bg-rose-500/20', 'border-rose-400', 'text-rose-200');

            if (type === 'success') {
                statusBox.classList.add('bg-emerald-500/20', 'border-emerald-400', 'text-emerald-200');
                statusIcon.className = 'ph-fill ph-check-circle text-2xl text-emerald-400 shrink-0';
            } else if (type === 'warning') {
                statusBox.classList.add('bg-yellow-500/20', 'border-yellow-400', 'text-yellow-200');
                statusIcon.className = 'ph-fill ph-warning-circle text-2xl text-yellow-400 shrink-0';
            } else {
                statusBox.classList.add('bg-rose-500/20', 'border-rose-400', 'text-rose-200');
                statusIcon.className = 'ph-fill ph-x-circle text-2xl text-rose-400 shrink-0';
            }

            statusTitle.textContent = title;
            statusDesc.textContent = desc;
        }

        // Toggle tampil/sembunyikan kata sandi
        togglePasswordBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            eyeIcon.className = isPassword ? 'ph-bold ph-eye-slash text-lg text-yellow-400' : 'ph-bold ph-eye text-lg';
        });

        // Isi otomatis dengan akun dokumentasi resmi
        demoFillBtn.addEventListener('click', () => {
            usernameInput.value = 'adminsm';
            passwordInput.value = 'adminsm!';
            shipJumpImpulse = 0.45;
            showMessage('warning', 'Kredensial Dokumentasi Diisi!', 'Username dan Password telah diisi otomatis. Klik "Buka Dokumentasi (Masuk)" untuk mengakses API.');
        });

        // Tombol Lupa Sandi & Bantuan API
        forgotBtn.addEventListener('click', () => {
            showMessage('warning', 'Pemulihan Kredensial', 'Silakan hubungi Tim IT Administrator PT Susanti Megah untuk pemulihan akses akun dokumentasi.');
        });

        if (helpBtn) {
            helpBtn.addEventListener('click', () => {
                showMessage('warning', 'Informasi Portal API', 'Portal ini menyediakan spesifikasi OpenAPI 3.0, Swagger UI, & Scalar API Reference untuk Distributor Channel.');
            });
        }

        // Submit Form Login ke Server Laravel
        loginForm.addEventListener('submit', (e) => {
            const user = usernameInput.value.trim();
            const pass = passwordInput.value.trim();

            if (!user || !pass) {
                e.preventDefault();
                showMessage('error', 'Kredensial Belum Lengkap', 'Mohon isi Nama Pengguna dan Kata Sandi terlebih dahulu.');
                return;
            }

            // Animasi kapal melaju kencang & feedback visual saat form disubmit
            shipSpeedMultiplier = 3.2;
            shipJumpImpulse = 0.55;
            if (coordDisplay) {
                coordDisplay.textContent = `Kecepatan Penuh: 38 Knot • Mengautentikasi: ${user}...`;
            }

            submitBtn.innerHTML = `<span>Memvalidasi...</span><i class="ph-bold ph-spinner animate-spin text-lg"></i>`;
            submitBtn.classList.add('opacity-90', 'cursor-wait');

            // Form diserahkan secara native ke action Laravel (/docs/login POST)
        });


        // ==========================================
        // 2. SKENARIO 3D THREE.JS (LAUT & KAPAL KUNING)
        // ==========================================
        let scene, camera, renderer, clock;
        let oceanMesh, oceanGeometry;
        let shipGroup, propellerMesh, radarBarMesh, shipSpotlightCone, lighthouseBeam, sunSphere;
        let foamRingGroup = [];
        let smokeParticles = [];
        let floatingBuoys = [];

        // Kontrol Kamera Interaktif (Berpusat pada Kapal Kuning agar selalu terlihat jelas)
        let isDragging = false;
        let prevMouseX = 0;
        let prevMouseY = 0;
        let targetCamAngleX = 0.52;  // Sudut tiga-perempat depan kapal yang menonjol
        let targetCamAngleY = 0.24;
        let currentCamAngleX = 0.52;
        let currentCamAngleY = 0.24;
        let targetCamRadius = 15.5;
        let currentCamRadius = 15.5;
        let isGoldenHour = true;

        function initThreeJS() {
            const canvas = document.getElementById('ocean-canvas');
            clock = new THREE.Clock();

            // 1. Inisialisasi Scene & Kabut (Fog)
            scene = new THREE.Scene();
            scene.background = new THREE.Color(0x0b2239);
            scene.fog = new THREE.FogExp2(0x0b2239, 0.010);

            // 2. Kamera
            camera = new THREE.PerspectiveCamera(46, window.innerWidth / window.innerHeight, 0.1, 300);

            // 3. Renderer
            renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true });
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            renderer.shadowMap.enabled = true;
            renderer.shadowMap.type = THREE.PCFSoftShadowMap;

            // 4. Pencahayaan Khusus (Menyorot Kapal Kuning Agar Sangat Jelas & Kontras)
            const ambientLight = new THREE.AmbientLight(0xe0f2fe, 0.78);
            scene.add(ambientLight);

            const sunLight = new THREE.DirectionalLight(0xfef08a, 1.6);
            sunLight.position.set(18, 26, 20);
            sunLight.castShadow = true;
            sunLight.shadow.mapSize.width = 1024;
            sunLight.shadow.mapSize.height = 1024;
            scene.add(sunLight);

            // Lampu Khusus Sorot Kapal (Key Light & Rim Light agar kapal kuning menyala tajam)
            const shipKeyLight = new THREE.PointLight(0xfffbeb, 2.2, 35);
            shipKeyLight.position.set(4, 9, 10);
            scene.add(shipKeyLight);

            const shipRimLight = new THREE.PointLight(0xfacc15, 1.8, 30);
            shipRimLight.position.set(-7, 6, -6);
            scene.add(shipRimLight);

            // 5. Buat Matahari / Bulan Kuning Raksasa di Cakrawala Kiri
            const sunGeo = new THREE.SphereGeometry(9.5, 32, 32);
            const sunMat = new THREE.MeshBasicMaterial({ color: 0xfde047 });
            sunSphere = new THREE.Mesh(sunGeo, sunMat);
            sunSphere.position.set(-32, 13, -55);
            scene.add(sunSphere);

            // Halo Matahari
            const haloGeo = new THREE.SphereGeometry(12.5, 32, 32);
            const haloMat = new THREE.MeshBasicMaterial({
                color: 0xfacc15,
                transparent: true,
                opacity: 0.28
            });
            const sunHalo = new THREE.Mesh(haloGeo, haloMat);
            sunSphere.add(sunHalo);

            // 6. Buat Permukaan Laut Berombak (Low-Poly Stylized Waves)
            createOcean();

            // 7. Buat Kapal Eksplorasi Kuning 3D Berukuran Besar & Detail di Pusat Panggung
            createYellowShip();

            // 8. Buat Mercusuar & Pulau Kecil di Kejauhan
            createLighthouseIsland();

            // 9. Buat Pelampung-Pelampung Kuning (Buoys) di Sekitar Kapal
            createFloatingBuoys();

            // Event Listener untuk Drag Kamera, Zoom, & Tombol UI
            setupInteractionEvents(canvas, ambientLight, sunLight);
        }

        // Fungsi menghitung tinggi gelombang pada titik (x, z) dan waktu t
        function getWaveHeight(x, z, t) {
            const wave1 = Math.sin(x * 0.22 + t * 1.8) * 0.42;
            const wave2 = Math.cos(z * 0.25 + t * 1.4) * 0.38;
            const wave3 = Math.sin((x + z) * 0.15 + t * 2.2) * 0.22;
            return wave1 + wave2 + wave3;
        }

        function createOcean() {
            oceanGeometry = new THREE.PlaneGeometry(180, 180, 80, 80);
            oceanGeometry.rotateX(-Math.PI / 2);

            const oceanMaterial = new THREE.MeshStandardMaterial({
                color: 0x0284c7,
                roughness: 0.22,
                metalness: 0.30,
                flatShading: true
            });

            oceanMesh = new THREE.Mesh(oceanGeometry, oceanMaterial);
            oceanMesh.receiveShadow = true;
            scene.add(oceanMesh);
        }

        function createYellowShip() {
            shipGroup = new THREE.Group();

            // Material Utama Kapal (Kuning Cerah Menyala, Emas, Putih Bersih, dan Aksen Gelap)
            const yellowPrimaryMat = new THREE.MeshStandardMaterial({
                color: 0xfacc15,
                emissive: 0xca8a04,
                emissiveIntensity: 0.18,
                roughness: 0.25,
                metalness: 0.12
            });
            const yellowDarkMat = new THREE.MeshStandardMaterial({
                color: 0xeab308,
                emissive: 0xa16207,
                emissiveIntensity: 0.12,
                roughness: 0.35,
                metalness: 0.18
            });
            const hullBottomMat = new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.5 });
            const deckFloorMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.6 });
            const cabinWhiteMat = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.2 });
            const windowGlowMat = new THREE.MeshBasicMaterial({ color: 0xfef08a });
            const darkMetalMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.4, metalness: 0.6 });
            const foamMat = new THREE.MeshBasicMaterial({ color: 0xe0f2fe, transparent: true, opacity: 0.75 });

            // 1. Lambung Bawah Kapal (Gelap)
            const hullBottomGeo = new THREE.BoxGeometry(3.4, 1.0, 7.6);
            const hullBottom = new THREE.Mesh(hullBottomGeo, hullBottomMat);
            hullBottom.position.y = 0.25;
            hullBottom.castShadow = true;
            shipGroup.add(hullBottom);

            // 2. Lambung Atas Utama (Kuning Cerah)
            const hullTopGeo = new THREE.BoxGeometry(3.7, 1.35, 8.0);
            const hullTop = new THREE.Mesh(hullTopGeo, yellowPrimaryMat);
            hullTop.position.y = 1.3;
            hullTop.castShadow = true;
            hullTop.receiveShadow = true;
            shipGroup.add(hullTop);

            // 3. Lantai Dek Atas
            const deckGeo = new THREE.BoxGeometry(3.45, 0.15, 7.7);
            const deckMesh = new THREE.Mesh(deckGeo, deckFloorMat);
            deckMesh.position.y = 2.0;
            shipGroup.add(deckMesh);

            // 4. Haluan Kapal Lancip di Depan (Kuning)
            const bowGeo = new THREE.ConeGeometry(2.62, 3.2, 4);
            bowGeo.rotateY(Math.PI / 4);
            bowGeo.rotateX(Math.PI / 2);
            const bowMesh = new THREE.Mesh(bowGeo, yellowPrimaryMat);
            bowMesh.scale.set(1, 0.52, 1);
            bowMesh.position.set(0, 1.3, 5.6);
            bowMesh.castShadow = true;
            shipGroup.add(bowMesh);

            // 5. Garis Sabuk Pelindung Lambung & Pagar Dek (Kuning Emas)
            const stripeGeo = new THREE.BoxGeometry(3.85, 0.3, 8.1);
            const stripeMesh = new THREE.Mesh(stripeGeo, yellowDarkMat);
            stripeMesh.position.set(0, 1.75, 0);
            shipGroup.add(stripeMesh);

            // 6. Dek Kabin Utama Lantai 1 (Putih Terang)
            const cabin1Geo = new THREE.BoxGeometry(2.75, 1.4, 4.5);
            const cabin1 = new THREE.Mesh(cabin1Geo, cabinWhiteMat);
            cabin1.position.set(0, 2.6, -0.5);
            cabin1.castShadow = true;
            shipGroup.add(cabin1);

            // 7. Ruang Kemudi / Anjungan Kapten (Kuning Cerah)
            const bridgeGeo = new THREE.BoxGeometry(2.45, 1.2, 2.7);
            const bridge = new THREE.Mesh(bridgeGeo, yellowPrimaryMat);
            bridge.position.set(0, 3.8, 0.1);
            bridge.castShadow = true;
            shipGroup.add(bridge);

            // Atap Anjungan
            const roofGeo = new THREE.BoxGeometry(2.75, 0.22, 3.1);
            const roof = new THREE.Mesh(roofGeo, yellowDarkMat);
            roof.position.set(0, 4.45, 0.15);
            shipGroup.add(roof);

            // 8. Kaca Jendela Anjungan (Menyala Kuning Hangat)
            const frontWindowGeo = new THREE.BoxGeometry(2.5, 0.5, 1.9);
            const frontWindow = new THREE.Mesh(frontWindowGeo, windowGlowMat);
            frontWindow.position.set(0, 3.9, 0.4);
            shipGroup.add(frontWindow);

            // Jendela Bulat Samping (Portholes)
            const portholeGeo = new THREE.CylinderGeometry(0.24, 0.24, 2.85, 16);
            portholeGeo.rotateZ(Math.PI / 2);
            for (let i = -1; i <= 1; i++) {
                const porthole = new THREE.Mesh(portholeGeo, windowGlowMat);
                porthole.position.set(0, 2.6, -0.5 + i * 1.15);
                shipGroup.add(porthole);
            }

            // 9. Sekoci Penyelamat Kuning (Lifeboats) di Sisi Kiri & Kanan Kabin
            const lifeboatGeo = new THREE.BoxGeometry(0.45, 0.38, 1.45);
            const leftLifeboat = new THREE.Mesh(lifeboatGeo, yellowPrimaryMat);
            leftLifeboat.position.set(1.6, 2.7, -0.5);
            shipGroup.add(leftLifeboat);

            const rightLifeboat = new THREE.Mesh(lifeboatGeo, yellowPrimaryMat);
            rightLifeboat.position.set(-1.6, 2.7, -0.5);
            shipGroup.add(rightLifeboat);

            // 10. Derek Kargo Kuning (Front Crane) di Dek Depan
            const craneGroup = new THREE.Group();
            const craneBase = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.35, 1.3, 12), darkMetalMat);
            craneBase.position.y = 0.65;
            craneGroup.add(craneBase);

            const craneArm = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.22, 2.6), yellowPrimaryMat);
            craneArm.position.set(0, 1.4, 0.8);
            craneArm.rotation.x = -0.35;
            craneGroup.add(craneArm);

            craneGroup.position.set(0, 2.0, 2.5);
            shipGroup.add(craneGroup);

            // 11. Cerobong Asap Kuning & Hitam
            const chimneyGroup = new THREE.Group();
            const chimBaseGeo = new THREE.CylinderGeometry(0.48, 0.58, 1.9, 16);
            const chimBase = new THREE.Mesh(chimBaseGeo, yellowPrimaryMat);
            chimneyGroup.add(chimBase);

            const chimTopGeo = new THREE.CylinderGeometry(0.5, 0.5, 0.42, 16);
            const chimTop = new THREE.Mesh(chimTopGeo, darkMetalMat);
            chimTop.position.y = 0.85;
            chimneyGroup.add(chimTop);

            chimneyGroup.position.set(0, 4.1, -1.85);
            chimneyGroup.rotation.x = -0.15;
            shipGroup.add(chimneyGroup);

            // 12. Tiang Radar & Scanner Radar Berputar di Atas Kapal
            const mastGeo = new THREE.CylinderGeometry(0.09, 0.14, 2.1, 8);
            const mast = new THREE.Mesh(mastGeo, darkMetalMat);
            mast.position.set(0, 5.4, 0.2);
            shipGroup.add(mast);

            // Palang Radar Berputar
            radarBarMesh = new THREE.Mesh(new THREE.BoxGeometry(1.5, 0.14, 0.22), yellowPrimaryMat);
            radarBarMesh.position.set(0, 6.1, 0.2);
            shipGroup.add(radarBarMesh);

            const beaconGeo = new THREE.SphereGeometry(0.24, 16, 16);
            const beacon = new THREE.Mesh(beaconGeo, windowGlowMat);
            beacon.position.set(0, 6.55, 0.2);
            shipGroup.add(beacon);

            // 13. Lampu Sorot Haluan Kapal (Front Searchlight Beam)
            const spotHousing = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.22, 0.4, 12), yellowDarkMat);
            spotHousing.rotation.x = Math.PI / 2;
            spotHousing.position.set(0, 2.2, 4.6);
            shipGroup.add(spotHousing);

            const beamConeGeo = new THREE.ConeGeometry(3.2, 14, 20, 1, true);
            beamConeGeo.translate(0, -7, 0);
            beamConeGeo.rotateX(-Math.PI / 2 - 0.12);
            const beamConeMat = new THREE.MeshBasicMaterial({
                color: 0xfef08a,
                transparent: true,
                opacity: 0.22,
                side: THREE.DoubleSide,
                depthWrite: false
            });
            shipSpotlightCone = new THREE.Mesh(beamConeGeo, beamConeMat);
            shipSpotlightCone.position.set(0, 2.2, 4.8);
            shipGroup.add(shipSpotlightCone);

            // 14. Pelampung Penyelamat Kuning di Sisi Kapal
            const ringGeo = new THREE.TorusGeometry(0.34, 0.1, 12, 24);
            ringGeo.rotateY(Math.PI / 2);
            const leftRing = new THREE.Mesh(ringGeo, yellowDarkMat);
            leftRing.position.set(1.42, 2.5, -1.7);
            shipGroup.add(leftRing);

            const rightRing = new THREE.Mesh(ringGeo, yellowDarkMat);
            rightRing.position.set(-1.42, 2.5, -1.7);
            shipGroup.add(rightRing);

            // 15. Buih Air Putih (Water Wake / Foam) di Sekeliling Lambung Kapal
            for (let i = 0; i < 14; i++) {
                const angle = (i / 14) * Math.PI * 2;
                const foamPiece = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.12, 0.9), foamMat);
                foamPiece.position.set(
                    Math.sin(angle) * 2.25,
                    0.05,
                    Math.cos(angle) * 4.8
                );
                foamPiece.rotation.y = angle;
                shipGroup.add(foamPiece);
                foamRingGroup.push({ mesh: foamPiece, baseAngle: angle, index: i });
            }

            // 16. Baling-Baling Belakang
            propellerMesh = new THREE.Mesh(
                new THREE.BoxGeometry(1.25, 0.28, 0.16),
                yellowDarkMat
            );
            propellerMesh.position.set(0, 0.35, -4.0);
            shipGroup.add(propellerMesh);

            // Perbesar skala kapal (1.28x) dan letakkan di titik pusat (0,0,0) agar mudah dilihat
            shipGroup.scale.set(1.28, 1.28, 1.28);
            shipGroup.position.set(0, 0, 0);
            shipGroup.rotation.y = 0.42; // Menghadap serong ke arah pengguna
            scene.add(shipGroup);
        }

        function createLighthouseIsland() {
            const islandGroup = new THREE.Group();

            // Batu Karang Pulau
            const rockGeo = new THREE.ConeGeometry(7, 4.5, 9);
            const rockMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, flatShading: true });
            const rock = new THREE.Mesh(rockGeo, rockMat);
            rock.position.y = 1.2;
            islandGroup.add(rock);

            // Menara Mercusuar Kuning-Hitam
            const towerGeo = new THREE.CylinderGeometry(1.1, 1.7, 9, 12);
            const towerMat = new THREE.MeshStandardMaterial({ color: 0xfacc15, roughness: 0.3 });
            const tower = new THREE.Mesh(towerGeo, towerMat);
            tower.position.y = 7.0;
            islandGroup.add(tower);

            // Lampu Mercusuar
            const lampGeo = new THREE.CylinderGeometry(1.0, 1.0, 1.4, 12);
            const lampMat = new THREE.MeshBasicMaterial({ color: 0xfef08a });
            const lamp = new THREE.Mesh(lampGeo, lampMat);
            lamp.position.y = 12.0;
            islandGroup.add(lamp);

            // Atap Mercusuar
            const topConeGeo = new THREE.ConeGeometry(1.4, 1.6, 12);
            const topCone = new THREE.Mesh(topConeGeo, rockMat);
            topCone.position.y = 13.4;
            islandGroup.add(topCone);

            // Sorot Cahaya Mercusuar Berputar (Kuning Transparan)
            const beamGeo = new THREE.ConeGeometry(6, 28, 16, 1, true);
            beamGeo.translate(0, -14, 0);
            beamGeo.rotateX(Math.PI / 2);
            const beamMat = new THREE.MeshBasicMaterial({
                color: 0xfde047,
                transparent: true,
                opacity: 0.2,
                side: THREE.DoubleSide,
                depthWrite: false
            });
            lighthouseBeam = new THREE.Mesh(beamGeo, beamMat);
            lighthouseBeam.position.y = 12.0;
            islandGroup.add(lighthouseBeam);

            islandGroup.position.set(-22, -0.5, -28);
            scene.add(islandGroup);
        }

        function createFloatingBuoys() {
            const buoyPositions = [
                { x: -6.5, z: 6.2 },
                { x: 6.8, z: 4.5 },
                { x: -8.5, z: -6.5 },
                { x: 5.5, z: -9.5 }
            ];

            const buoyMat = new THREE.MeshStandardMaterial({ color: 0xfacc15, roughness: 0.25 });
            const glowMat = new THREE.MeshBasicMaterial({ color: 0xfef08a });

            buoyPositions.forEach((pos) => {
                const buoy = new THREE.Group();
                const base = new THREE.Mesh(new THREE.CylinderGeometry(0.45, 0.58, 0.95, 12), buoyMat);
                const top = new THREE.Mesh(new THREE.SphereGeometry(0.24, 10, 10), glowMat);
                top.position.y = 0.7;
                buoy.add(base);
                buoy.add(top);
                buoy.position.set(pos.x, 0, pos.z);
                scene.add(buoy);
                floatingBuoys.push(buoy);
            });
        }

        // Membuat kepulan asap kuning-putih dari cerobong kapal
        function spawnSmokeParticle() {
            if (!shipGroup) return;
            const smokeGeo = new THREE.DodecahedronGeometry(0.32, 0);
            const smokeMat = new THREE.MeshBasicMaterial({
                color: 0xfef9c3,
                transparent: true,
                opacity: 0.7
            });
            const particle = new THREE.Mesh(smokeGeo, smokeMat);

            // Posisi cerobong dalam koordinat dunia
            const worldPos = new THREE.Vector3(0, 5.0, -1.9);
            shipGroup.localToWorld(worldPos);
            particle.position.copy(worldPos);

            scene.add(particle);
            smokeParticles.push({
                mesh: particle,
                life: 1.0,
                velX: (Math.random() - 0.5) * 0.025 - 0.02,
                velY: 0.045 + Math.random() * 0.025 * shipSpeedMultiplier,
                velZ: -0.035 * shipSpeedMultiplier
            });
        }

        function setupInteractionEvents(canvas, ambientLight, sunLight) {
            // Mouse Drag untuk memutar kamera mengelilingi kapal
            canvas.addEventListener('mousedown', (e) => {
                isDragging = true;
                prevMouseX = e.clientX;
                prevMouseY = e.clientY;
            });

            window.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                const deltaX = e.clientX - prevMouseX;
                const deltaY = e.clientY - prevMouseY;
                targetCamAngleX -= deltaX * 0.0055;
                targetCamAngleY = Math.max(0.08, Math.min(1.05, targetCamAngleY + deltaY * 0.0045));
                prevMouseX = e.clientX;
                prevMouseY = e.clientY;
            });

            window.addEventListener('mouseup', () => {
                isDragging = false;
            });

            // Scroll Wheel untuk Zoom In / Zoom Out Kapal
            canvas.addEventListener('wheel', (e) => {
                e.preventDefault();
                targetCamRadius = Math.max(9.0, Math.min(24.0, targetCamRadius + e.deltaY * 0.012));
            }, { passive: false });

            // Touch Drag untuk layar HP/Tablet
            canvas.addEventListener('touchstart', (e) => {
                if (e.touches.length === 1) {
                    isDragging = true;
                    prevMouseX = e.touches[0].clientX;
                    prevMouseY = e.touches[0].clientY;
                }
            }, { passive: true });

            window.addEventListener('touchmove', (e) => {
                if (!isDragging || e.touches.length !== 1) return;
                const deltaX = e.touches[0].clientX - prevMouseX;
                const deltaY = e.touches[0].clientY - prevMouseY;
                targetCamAngleX -= deltaX * 0.0055;
                targetCamAngleY = Math.max(0.08, Math.min(1.05, targetCamAngleY + deltaY * 0.0045));
                prevMouseX = e.touches[0].clientX;
                prevMouseY = e.touches[0].clientY;
            }, { passive: true });

            window.addEventListener('touchend', () => {
                isDragging = false;
            });

            // Tombol Zoom / Fokus Kapal
            const zoomShipBtn = document.getElementById('zoom-ship-btn');
            const zoomLabel = document.getElementById('zoom-label');
            zoomShipBtn.addEventListener('click', () => {
                isZoomedIn = !isZoomedIn;
                if (isZoomedIn) {
                    targetCamRadius = 10.5;
                    targetCamAngleX = 0.65;
                    targetCamAngleY = 0.18;
                    zoomLabel.textContent = 'Kamera Normal';
                } else {
                    targetCamRadius = 15.5;
                    targetCamAngleX = 0.52;
                    targetCamAngleY = 0.24;
                    zoomLabel.textContent = 'Zoom Kapal';
                }
            });

            // Tombol Sembunyikan / Tampilkan Form Login (Inspeksi Kapal Layar Penuh)
            const toggleFormBtn = document.getElementById('toggle-form-btn');
            const toggleFormMobile = document.getElementById('toggle-form-mobile');
            const formToggleLabel = document.getElementById('form-toggle-label');

            function toggleLoginPanel() {
                isFormHidden = !isFormHidden;
                if (isFormHidden) {
                    loginWrapper.classList.add('panel-hidden');
                    formToggleLabel.textContent = 'Tampilkan Form';
                } else {
                    loginWrapper.classList.remove('panel-hidden');
                    formToggleLabel.textContent = 'Layar Penuh';
                }
            }

            toggleFormBtn.addEventListener('click', toggleLoginPanel);
            if (toggleFormMobile) {
                toggleFormMobile.addEventListener('click', toggleLoginPanel);
            }

            // Tombol Ganti Suasana Langit (Senja Emas vs Malam Laut)
            const toggleTimeBtn = document.getElementById('toggle-time-btn');
            const timeLabel = document.getElementById('time-label');
            const timeIcon = document.getElementById('time-icon');

            toggleTimeBtn.addEventListener('click', () => {
                isGoldenHour = !isGoldenHour;
                if (isGoldenHour) {
                    scene.background.setHex(0x0b2239);
                    scene.fog.color.setHex(0x0b2239);
                    oceanMesh.material.color.setHex(0x0284c7);
                    sunLight.intensity = 1.6;
                    ambientLight.intensity = 0.78;
                    if (shipSpotlightCone) shipSpotlightCone.material.opacity = 0.22;
                    timeLabel.textContent = 'Mode Senja';
                    timeIcon.className = 'ph-fill ph-sun-horizon text-base text-yellow-400';
                } else {
                    scene.background.setHex(0x030914);
                    scene.fog.color.setHex(0x030914);
                    oceanMesh.material.color.setHex(0x072f49);
                    sunLight.intensity = 0.85;
                    ambientLight.intensity = 0.45;
                    if (shipSpotlightCone) shipSpotlightCone.material.opacity = 0.42;
                    timeLabel.textContent = 'Mode Malam';
                    timeIcon.className = 'ph-fill ph-moon-stars text-base text-yellow-400';
                }
            });

            // Tombol Uji Ombak / Lompatan Kapal & Sinyal Sorot
            document.getElementById('horn-btn').addEventListener('click', () => {
                shipJumpImpulse = 0.6;
                if (shipSpotlightCone) {
                    shipSpotlightCone.material.opacity = 0.65;
                    setTimeout(() => {
                        shipSpotlightCone.material.opacity = isGoldenHour ? 0.22 : 0.42;
                    }, 900);
                }
                for (let i = 0; i < 6; i++) spawnSmokeParticle();
            });

            document.getElementById('badge-helm').addEventListener('click', () => {
                shipJumpImpulse = 0.45;
            });

            // Responsif saat ukuran layar berubah
            window.addEventListener('resize', () => {
                camera.aspect = window.innerWidth / window.innerHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(window.innerWidth, window.innerHeight);
            });
        }

        // Loop Animasi Utama Three.js
        let lastSmokeTime = 0;
        function animate() {
            requestAnimationFrame(animate);

            const elapsedTime = clock.getElapsedTime() * shipSpeedMultiplier;

            // 1. Animasi Gelombang Laut
            const posAttr = oceanGeometry.attributes.position;
            for (let i = 0; i < posAttr.count; i++) {
                const vx = posAttr.getX(i);
                const vz = posAttr.getZ(i);
                const vy = getWaveHeight(vx, vz, elapsedTime);
                posAttr.setY(i, vy);
            }
            posAttr.needsUpdate = true;
            oceanGeometry.computeVertexNormals();

            // 2. Animasi Kapal Kuning Mengapung Mengikuti Gelombang
            if (shipGroup) {
                const shipX = shipGroup.position.x;
                const shipZ = shipGroup.position.z;
                const waveY = getWaveHeight(shipX, shipZ, elapsedTime);

                // Redam efek lompatan jika ada
                if (shipJumpImpulse > 0.01) {
                    shipJumpImpulse *= 0.92;
                } else {
                    shipJumpImpulse = 0;
                }

                shipGroup.position.y = waveY - 0.1 + shipJumpImpulse * 2.6;

                // Kemiringan kapal mengikuti turunan gelombang (Pitch & Roll)
                const waveFront = getWaveHeight(shipX, shipZ + 2.5, elapsedTime);
                const waveSide = getWaveHeight(shipX + 1.8, shipZ, elapsedTime);
                shipGroup.rotation.x = (waveFront - waveY) * 0.14 - shipJumpImpulse * 0.25;
                shipGroup.rotation.z = (waveY - waveSide) * 0.18;

                // Putar baling-baling kapal & radar atas
                if (propellerMesh) {
                    propellerMesh.rotation.z += 0.28 * shipSpeedMultiplier;
                }
                if (radarBarMesh) {
                    radarBarMesh.rotation.y += 0.06 * shipSpeedMultiplier;
                }

                // Animasi Buih Air (Water Wake) di sekitar lambung kapal
                foamRingGroup.forEach((item) => {
                    const pulse = Math.sin(elapsedTime * 4 + item.index) * 0.15;
                    item.mesh.scale.set(1 + pulse, 1, 1 + pulse);
                });
            }

            // 3. Animasi Pelampung Kuning
            floatingBuoys.forEach((buoy, idx) => {
                const by = getWaveHeight(buoy.position.x, buoy.position.z, elapsedTime);
                buoy.position.y = by;
                buoy.rotation.z = Math.sin(elapsedTime * 2 + idx) * 0.2;
                buoy.rotation.x = Math.cos(elapsedTime * 1.5 + idx) * 0.2;
            });

            // 4. Animasi Sorot Mercusuar
            if (lighthouseBeam) {
                lighthouseBeam.rotation.y += 0.016;
            }

            // 5. Partikel Asap Cerobong Kapal
            if (elapsedTime - lastSmokeTime > 0.20 / shipSpeedMultiplier) {
                spawnSmokeParticle();
                lastSmokeTime = elapsedTime;
            }

            for (let i = smokeParticles.length - 1; i >= 0; i--) {
                const p = smokeParticles[i];
                p.mesh.position.x += p.velX;
                p.mesh.position.y += p.velY;
                p.mesh.position.z += p.velZ;
                p.mesh.scale.multiplyScalar(1.022);
                p.life -= 0.015;
                p.mesh.material.opacity = p.life * 0.65;

                if (p.life <= 0) {
                    scene.remove(p.mesh);
                    p.mesh.geometry.dispose();
                    p.mesh.material.dispose();
                    smokeParticles.splice(i, 1);
                }
            }

            // 6. Perbarui Posisi Kamera dengan Penghalusan (Lerp)
            currentCamAngleX += (targetCamAngleX - currentCamAngleX) * 0.07;
            currentCamAngleY += (targetCamAngleY - currentCamAngleY) * 0.07;
            currentCamRadius += (targetCamRadius - currentCamRadius) * 0.07;

            camera.position.x = Math.sin(currentCamAngleX) * Math.cos(currentCamAngleY) * currentCamRadius;
            camera.position.y = Math.sin(currentCamAngleY) * currentCamRadius + 1.8;
            camera.position.z = Math.cos(currentCamAngleX) * Math.cos(currentCamAngleY) * currentCamRadius;

            // Pengaturan titik fokus kamera (lookAt) agar kapal selalu tampil di sisi kiri-tengah (Desktop)
            // atau di bagian atas layar (Mobile) dan TIDAK PERNAH tertutup oleh kartu login.
            const isDesktop = window.innerWidth >= 1024;
            if (isFormHidden || isZoomedIn) {
                // Jika form disembunyikan atau sedang mode Zoom Kapal, fokus tepat ke tengah kapal
                camera.lookAt(0, 2.2, 0);
            } else if (isDesktop) {
                // Geser titik pandang kamera ke kanan (+3.8) sehingga kapal otomatis berada di area kiri-tengah layar yang terbuka lebar
                camera.lookAt(3.8, 2.0, 0);
            } else {
                // Pada layar mobile, geser titik pandang ke bawah (-1.8) sehingga kapal berada jelas di paruh atas layar di atas form
                camera.lookAt(0, -1.6, 0);
            }

            renderer.render(scene, camera);
        }

        // Pastikan animasi dimulai setelah window selesai dimuat
        window.onload = function () {
            initThreeJS();
            animate();
        };
    </script>
</body>

</html>
