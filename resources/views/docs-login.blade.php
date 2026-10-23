<!DOCTYPE html>
<html lang="id" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMESTA — API Docs</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Space Grotesk & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        navy: {
                            950: '#060D1A',
                            900: '#0B192C',
                            800: '#132742'
                        },
                        kapal: {
                            red: '#E11D48',
                            gold: '#F59E0B',
                            cyan: '#38BDF8',
                            salt: '#F8FAFC'
                        }
                    },
                    fontFamily: {
                        sans: ['Space Grotesk', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            background-color: #060D1A;
            color: #F8FAFC;
            font-family: 'Space Grotesk', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Crisp Pixel Art Rendering */
        .pixel-canvas {
            image-rendering: -moz-crisp-edges;
            image-rendering: -webkit-crisp-edges;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }

        /* Minimalist Input Focus */
        .clean-input:focus {
            outline: none;
            border-color: #38BDF8;
            box-shadow: 2px 2px 0px #38BDF8;
        }

        /* Subtle Pixel Button Press */
        .pixel-btn {
            box-shadow: 3px 3px 0px #881337;
            transition: transform 0.1s ease, box-shadow 0.1s ease, background-color 0.15s ease;
        }

        .pixel-btn:hover {
            background-color: #F43F5E;
        }

        .pixel-btn:active {
            transform: translate(2px, 2px);
            box-shadow: 1px 1px 0px #881337;
        }
    </style>
</head>

<body
    class="min-h-screen w-full flex items-center justify-center p-4 sm:p-6 selection:bg-kapal-cyan selection:text-navy-950">

    <!-- VIEW 1: MINIMALIST PIXEL LOGIN CARD -->
    <main id="loginView"
        class="w-full max-w-[820px] bg-navy-900 border border-white/15 grid grid-cols-1 md:grid-cols-12 overflow-hidden shadow-2xl">

        <!-- Left Panel: Handcrafted Interactive 2D Pixel Art Scene (7 Columns) -->
        <div
            class="md:col-span-7 relative bg-navy-950 border-b md:border-b-0 md:border-r border-white/15 flex flex-col justify-between min-h-[260px] sm:min-h-[380px] overflow-hidden">

            <!-- Low-Res Pixel Canvas (160x144 scaled up cleanly) -->
            <canvas id="pixelScene" width="160" height="144"
                class="pixel-canvas w-full h-full object-cover absolute inset-0 cursor-crosshair"></canvas>

            <!-- Minimal Top-Left Brand Tag Over Pixel Canvas -->
            <div class="relative z-10 p-4 flex items-center justify-between pointer-events-none">
                <span
                    class="px-2 py-0.5 bg-navy-950/80 border border-white/15 font-mono text-[10px] tracking-widest text-kapal-salt uppercase">
                    EST. 1978
                </span>
                <span class="px-2 py-0.5 bg-navy-950/80 border border-white/15 font-mono text-[10px] text-kapal-cyan">
                    NaCl • PIXEL ENGINE
                </span>
            </div>

            <!-- Minimal Bottom Caption -->
            <div class="relative z-10 p-4 pointer-events-none mt-auto">
                <p class="font-mono text-[11px] text-slate-400 tracking-tight">
                    PT SUSANTI MEGAH
                </p>
            </div>
        </div>

        <!-- Right Panel: Ultra-Clean Login Form (5 Columns) -->
        <div class="md:col-span-5 p-7 sm:p-9 flex flex-col justify-center bg-navy-900">

            <!-- Minimal Header -->
            <div class="mb-8">
                <div class="flex items-center gap-2.5 mb-2">
                    <!-- Mini 8-bit Pixel Ship Emblem SVG -->
                    <svg class="w-5 h-5 shrink-0" viewBox="0 0 16 16" fill="none" shape-rendering="crispEdges">
                        <rect x="7" y="2" width="1" height="9" fill="#F59E0B" />
                        <rect x="8" y="3" width="4" height="6" fill="#F8FAFC" />
                        <rect x="4" y="5" width="3" height="4" fill="#CBD5E1" />
                        <rect x="2" y="11" width="12" height="2" fill="#E11D48" />
                        <rect x="3" y="13" width="10" height="1" fill="#9F1239" />
                        <rect x="1" y="15" width="14" height="1" fill="#38BDF8" />
                    </svg>
                    <span class="font-bold tracking-wider text-sm text-white uppercase">
                        SMESTA
                    </span>
                </div>
                <h1 class="font-mono text-xs text-slate-400">
                    API Documentation
                </h1>
            </div>

            <!-- Status Alert from Laravel Session -->
            @if(session('error'))
                <div class="mb-4 p-3 bg-rose-950/70 border border-rose-500/60 font-mono text-xs text-rose-300 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 bg-rose-500 shrink-0"></span>
                    <span>{{ session('error') }}</span>
                </div>
            @elseif(request()->has('expired'))
                <div class="mb-4 p-3 bg-amber-950/70 border border-amber-500/60 font-mono text-xs text-amber-300 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 bg-amber-500 shrink-0"></span>
                    <span>Sesi Anda telah kedaluwarsa. Silakan masuk kembali.</span>
                </div>
            @endif

            <!-- Strictly Username, Password, and Login Button -->
            <form id="loginForm" action="{{ url('/docs/login') }}" method="POST" onsubmit="return handleLogin(event)" class="space-y-5" novalidate>
                @csrf
                <div>
                    <label for="username" class="block font-mono text-xs text-slate-300 mb-2">
                        Username
                    </label>
                    <input type="text" id="username" name="username" required autocomplete="username" autofocus
                        placeholder="username" oninput="triggerPixelSaltBurst()"
                        value="{{ old('username') }}"
                        class="clean-input w-full px-3.5 py-2.5 bg-navy-950 border border-white/20 font-mono text-sm text-white placeholder-slate-600 transition">
                </div>

                <div>
                    <label for="password" class="block font-mono text-xs text-slate-300 mb-2">
                        Password
                    </label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                        placeholder="••••••••" oninput="triggerPixelSaltBurst()"
                        class="clean-input w-full px-3.5 py-2.5 bg-navy-950 border border-white/20 font-mono text-sm text-white placeholder-slate-600 transition">
                </div>

                <p id="errorMsg" class="hidden font-mono text-[11px] text-kapal-red">
                    Masukkan username dan password.
                </p>

                <div class="pt-2">
                    <button type="submit"
                        class="pixel-btn w-full py-2.5 px-4 bg-kapal-red text-white font-mono text-xs font-semibold uppercase tracking-widest border border-rose-300/30">
                        Login
                    </button>
                </div>
            </form>

        </div>
    </main>

    <!-- VIEW 2: MINIMALIST API DOCUMENTATION (Fallback Preview) -->
    <section id="docsView"
        class="hidden w-full max-w-[820px] bg-navy-900 border border-white/15 shadow-2xl overflow-hidden">

        <!-- Top Bar -->
        <header class="px-6 py-4 bg-navy-950 border-b border-white/15 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none" shape-rendering="crispEdges">
                    <rect x="7" y="2" width="1" height="9" fill="#F59E0B" />
                    <rect x="8" y="3" width="4" height="6" fill="#F8FAFC" />
                    <rect x="4" y="5" width="3" height="4" fill="#CBD5E1" />
                    <rect x="2" y="11" width="12" height="2" fill="#E11D48" />
                    <rect x="1" y="14" width="14" height="1" fill="#38BDF8" />
                </svg>
                <span class="font-bold text-sm tracking-wide text-white uppercase">SMESTA</span>
                <span class="text-slate-600">/</span>
                <span class="font-mono text-xs text-kapal-cyan">API v1</span>
            </div>

            <div class="flex items-center gap-4">
                <span id="loggedUserLabel" class="font-mono text-xs text-slate-400"></span>
                <button onclick="handleLogout()"
                    class="px-3 py-1 bg-navy-800 hover:bg-navy-950 border border-white/15 font-mono text-xs text-slate-200 transition">
                    Logout
                </button>
            </div>
        </header>

        <!-- Clean Content Body -->
        <div class="p-6 space-y-6">

            <!-- Base URL & Auth -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-3.5 bg-navy-950 border border-white/10">
                    <div class="font-mono text-[10px] text-slate-400 uppercase mb-1">Base URL</div>
                    <code class="font-mono text-xs text-kapal-cyan select-all">{{ url('/v1') }}</code>
                </div>
                <div class="p-3.5 bg-navy-950 border border-white/10">
                    <div class="font-mono text-[10px] text-slate-400 uppercase mb-1">Auth Header</div>
                    <code
                        class="font-mono text-xs text-kapal-gold select-all">Authorization: Bearer &lt;token&gt;</code>
                </div>
            </div>

            <!-- Minimal Endpoint Tabs -->
            <div>
                <div class="flex border-b border-white/15 font-mono text-xs">
                    <button onclick="switchEndpoint(0)" id="tab-0"
                        class="px-4 py-2.5 border-b-2 border-kapal-cyan text-white bg-navy-950/60">
                        GET /products
                    </button>
                    <button onclick="switchEndpoint(1)" id="tab-1"
                        class="px-4 py-2.5 border-b-2 border-transparent text-slate-400 hover:text-white">
                        GET /batches/{id}
                    </button>
                    <button onclick="switchEndpoint(2)" id="tab-2"
                        class="px-4 py-2.5 border-b-2 border-transparent text-slate-400 hover:text-white">
                        POST /orders
                    </button>
                </div>

                <!-- Code Block -->
                <div class="bg-navy-950 border border-t-0 border-white/15 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span id="epDesc" class="font-mono text-xs text-slate-400">Daftar produk SMESTA
                            beryodium.</span>
                        <span class="font-mono text-[11px] text-emerald-400">200 OK</span>
                    </div>
                    <pre id="epCode"
                        class="font-mono text-xs text-slate-200 overflow-x-auto leading-relaxed select-all"></pre>
                </div>
            </div>

        </div>
    </section>

    <!-- PIXEL ART ENGINE & MINIMAL LOGIC -->
    <script>
        const canvas = document.getElementById('pixelScene');
        const ctx = canvas.getContext('2d');
        const W = canvas.width;  // 160
        const H = canvas.height; // 144

        let tick = 0;
        let mouseX = W / 2;
        let mouseY = H / 2;
        let typingEnergy = 0;

        // Floating Salt Crystal Pixels
        const saltPixels = Array.from({ length: 34 }, () => ({
            x: Math.floor(Math.random() * W),
            y: Math.floor(Math.random() * (H - 45)),
            speedY: 0.12 + Math.random() * 0.2,
            blinkOffset: Math.random() * 100,
            isBig: Math.random() > 0.78
        }));

        // Water Ripples on Mouse/Typing
        const ripples = [];

        // Ship Pixel Sprite Map ("Cap Kapal" Silhouette + Sails + Red Hull)
        // Legend:
        // '.' = transparent
        // 'W' = Pure Salt White Sail (#F8FAFC)
        // 'S' = Shaded Sail (#CBD5E1)
        // 'G' = Gold Mast/Flag (#F59E0B)
        // 'R' = Cap Kapal Red Hull (#E11D48)
        // 'D' = Dark Crimson Hull Bottom (#9F1239)
        // 'N' = Navy Cabin Detail (#060D1A)
        const shipSprite = [
            "................G...............",
            "..............G.G...............",
            "................G.......G.......",
            "...............WG......GG.......",
            "..............WWG.......G.......",
            ".......G.....WWWG......WG.......",
            "......GG....WWWWG.....WWG.......",
            ".......G...WWWWWG....WWWG.......",
            "......WG..WWWWWWG...WWWWG.......",
            ".....WWG.WWWWWWWG..WWWWWG.......",
            "....SSSG.WWWWWWWG.WWWWWWG.......",
            "...SSSSG.WWWWWWWG.WWWWWWG.......",
            "..SSSSSG.WWWWWWWG.WWWWWWG.......",
            ".SSSSSSG.WWWWWWWG.WWWWWWG.......",
            "................G.......G.......",
            "..RRRRRRRRRRRRRRRRRRRRRRRRRRRR..",
            "...RRRRNNRRRRNNRRRRNNRRRRRRRR...",
            "....DDDDDDDDDDDDDDDDDDDDDDDD...."
        ];

        const palette = {
            'W': '#F8FAFC',
            'S': '#94A3B8',
            'G': '#F59E0B',
            'R': '#E11D48',
            'D': '#9F1239',
            'N': '#060D1A'
        };

        // Track Mouse in Pixel Coordinates
        canvas.addEventListener('mousemove', (e) => {
            const rect = canvas.getBoundingClientRect();
            mouseX = Math.floor(((e.clientX - rect.left) / rect.width) * W);
            mouseY = Math.floor(((e.clientY - rect.top) / rect.height) * H);

            if (mouseY > 92 && Math.random() > 0.5) {
                ripples.push({ x: mouseX, y: mouseY, r: 1, maxR: 8 });
            }
        });

        canvas.addEventListener('click', () => {
            triggerPixelSaltBurst();
        });

        function triggerPixelSaltBurst() {
            typingEnergy = 12;
            // Spawn a water ripple near the ship
            ripples.push({
                x: 64 + Math.floor(Math.random() * 32),
                y: 102 + Math.floor(Math.random() * 14),
                r: 1,
                maxR: 12
            });
        }

        function drawPixel(x, y, color) {
            ctx.fillStyle = color;
            ctx.fillRect(Math.floor(x), Math.floor(y), 1, 1);
        }

        function drawRect(x, y, w, h, color) {
            ctx.fillStyle = color;
            ctx.fillRect(Math.floor(x), Math.floor(y), Math.floor(w), Math.floor(h));
        }

        function renderPixelScene() {
            tick++;
            if (typingEnergy > 0) typingEnergy *= 0.92;

            // 1. Deep Maritime Night Sky Bands (Pixel Dithered Look)
            drawRect(0, 0, W, 36, '#050B16');
            drawRect(0, 36, W, 32, '#081223');
            drawRect(0, 68, W, 28, '#0B192C');

            // 2. Pixel Crescent Moon / Salt Crystal Emblem in Sky (Top Right)
            const moonX = 126;
            const moonY = 26;
            drawRect(moonX - 5, moonY - 5, 11, 11, '#132742');
            // Diamond Salt Crystal Halo
            drawRect(moonX - 3, moonY - 3, 7, 7, '#F8FAFC');
            drawPixel(moonX, moonY - 5, '#38BDF8');
            drawPixel(moonX, moonY + 5, '#38BDF8');
            drawPixel(moonX - 5, moonY, '#38BDF8');
            drawPixel(moonX + 5, moonY, '#38BDF8');

            // 3. Falling / Twinkling Salt Crystal Pixels
            for (let i = 0; i < saltPixels.length; i++) {
                const p = saltPixels[i];
                p.y += p.speedY + (typingEnergy * 0.08);
                if (p.y > 94) {
                    p.y = 2;
                    p.x = Math.floor(Math.random() * W);
                }

                const twinkle = Math.sin(tick * 0.08 + p.blinkOffset);
                if (twinkle > -0.3) {
                    const color = twinkle > 0.6 ? '#F8FAFC' : '#38BDF8';
                    if (p.isBig && typingEnergy > 2) {
                        // Draw 3x3 mini crystal cross when typing
                        drawPixel(p.x, p.y, '#F8FAFC');
                        drawPixel(p.x - 1, p.y, '#38BDF8');
                        drawPixel(p.x + 1, p.y, '#38BDF8');
                        drawPixel(p.x - 1, p.y, '#38BDF8');
                        drawPixel(p.x, p.y + 1, '#38BDF8');
                    } else {
                        drawPixel(p.x, p.y, color);
                    }
                }
            }

            // 4. Calculate Ship Bobbing on Waves
            const shipBobY = Math.round(Math.sin(tick * 0.05) * 1.4);
            const shipX = 56;
            const shipY = 79 + shipBobY;

            // 5. Back Ocean Horizon Layer
            for (let x = 0; x < W; x++) {
                const waveH = Math.floor(Math.sin(x * 0.08 + tick * 0.04) * 1.5);
                drawRect(x, 94 + waveH, 1, H - (94 + waveH), '#0D223D');
                if ((x + Math.floor(tick * 0.3)) % 7 === 0) {
                    drawPixel(x, 94 + waveH, '#1E3A5F');
                }
            }

            // 6. Draw Iconic "Cap Kapal" Pixel Ship
            for (let row = 0; row < shipSprite.length; row++) {
                const line = shipSprite[row];
                for (let col = 0; col < line.length; col++) {
                    const ch = line[col];
                    if (ch !== '.') {
                        drawPixel(shipX + col, shipY + row, palette[ch]);
                    }
                }
            }

            // 7. Foreground Pixel Ocean Waves & White Salt Foam
            for (let x = 0; x < W; x++) {
                const wave1 = Math.floor(Math.sin(x * 0.11 - tick * 0.06) * 2);
                const y1 = 97 + wave1;

                // Water body
                drawRect(x, y1, 1, H - y1, '#09172A');

                // Crisp Cyan & Salt White Wave Crest
                if ((x + Math.floor(tick * 0.4)) % 4 !== 0) {
                    drawPixel(x, y1, '#38BDF8');
                } else {
                    drawPixel(x, y1, '#F8FAFC');
                }

                // Second deeper wave line
                const wave2 = Math.floor(Math.cos(x * 0.09 + tick * 0.05) * 1.5);
                const y2 = 112 + wave2;
                if ((x - Math.floor(tick * 0.3)) % 5 === 0) {
                    drawPixel(x, y2, '#38BDF8');
                }

                // Third foreground wave line
                const y3 = 128 + wave1;
                if ((x + Math.floor(tick * 0.25)) % 6 === 0) {
                    drawPixel(x, y3, '#1E3A5F');
                }
            }

            // 8. Moonlight / Salt Reflection Shimmer on Water
            for (let ry = 101; ry < 138; ry += 3) {
                const sway = Math.floor(Math.sin(ry * 0.3 + tick * 0.08) * 3);
                const width = Math.max(1, 6 - Math.floor((ry - 100) / 8));
                drawRect(moonX - Math.floor(width / 2) + sway, ry, width, 1, '#F8FAFC');
            }

            // 9. Interactive Pixel Ripples
            for (let i = ripples.length - 1; i >= 0; i--) {
                const rp = ripples[i];
                rp.r += 0.22;
                const rInt = Math.floor(rp.r);
                drawRect(rp.x - rInt, rp.y, 2, 1, '#F8FAFC');
                drawRect(rp.x + rInt, rp.y, 2, 1, '#38BDF8');
                if (rp.r >= rp.maxR) {
                    ripples.splice(i, 1);
                }
            }

            requestAnimationFrame(renderPixelScene);
        }

        // Minimal API Documentation Data
        const endpoints = [
            {
                desc: 'Daftar produk SMESTA beryodium (SNI 3556).',
                code: `{
  "producer": "PT Susanti Megah",
  "established": 1978,
  "products": [
    { "sku": "GCK-250G", "name": "SMESTA 250g", "nacl_purity": "97.8%" },
    { "sku": "GCK-500G", "name": "SMESTA 500g", "nacl_purity": "97.9%" }
  ]
}`
            },
            {
                desc: 'Status kemurnian batch & 5x proses pencucian.',
                code: `{
  "batch_id": "SBY-2026-1023",
  "wash_stages": 5,
  "kiln_temp_c": 200,
  "kio3_ppm": 45.2,
  "status": "VERIFIED"
}`
            },
            {
                desc: 'Buat pesanan distribusi B2B Surabaya Hub.',
                code: `{
  "order_id": "ORD-99281",
  "hub": "SURABAYA-DUPAK-81",
  "dispatch_status": "QUEUED"
}`
            }
        ];

        function switchEndpoint(idx) {
            for (let i = 0; i < 3; i++) {
                const tab = document.getElementById(`tab-${i}`);
                if (tab) {
                    if (i === idx) {
                        tab.className = "px-4 py-2.5 border-b-2 border-kapal-cyan text-white bg-navy-950/60";
                    } else {
                        tab.className = "px-4 py-2.5 border-b-2 border-transparent text-slate-400 hover:text-white";
                    }
                }
            }
            const descEl = document.getElementById('epDesc');
            const codeEl = document.getElementById('epCode');
            if (descEl && endpoints[idx]) descEl.textContent = endpoints[idx].desc;
            if (codeEl && endpoints[idx]) codeEl.textContent = endpoints[idx].code;
        }

        function handleLogin(e) {
            const user = document.getElementById('username').value.trim();
            const pass = document.getElementById('password').value.trim();
            const errorMsg = document.getElementById('errorMsg');

            if (!user || !pass) {
                if (e) e.preventDefault();
                if (errorMsg) errorMsg.classList.remove('hidden');
                triggerPixelSaltBurst();
                return false;
            }

            if (errorMsg) errorMsg.classList.add('hidden');
            // Allow form to submit to Laravel POST /docs/login
            return true;
        }

        function handleLogout() {
            window.location.href = "{{ url('/docs/logout') }}";
        }

        // Start Pixel Loop on Load
        window.onload = function () {
            renderPixelScene();
        };
    </script>
</body>

</html>
