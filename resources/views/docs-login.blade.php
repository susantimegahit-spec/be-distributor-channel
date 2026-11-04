<!DOCTYPE html>
<html lang="id" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMESTA — API Docs & Pixel Voyage</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Space Grotesk, JetBrains Mono & Press Start 2P -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Press+Start+2P&family=Space+Grotesk:wght@500;600;700&display=swap"
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
                        pixel: ['"Press Start 2P"', 'monospace']
                    }
                }
            }
        }
    </script>

    <style>
        * {
            box-sizing: border-box;
            user-select: none;
        }

        input,
        code,
        pre {
            user-select: text;
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
            touch-action: manipulation;
        }

        /* Minimalist Input Focus */
        .clean-input:focus {
            outline: none;
            border-color: #38BDF8;
            box-shadow: 2px 2px 0px #38BDF8;
        }

        /* Prevent ugly yellow browser autofill background */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-text-fill-color: #F8FAFC !important;
            -webkit-box-shadow: 0 0 0 1000px #060D1A inset !important;
            box-shadow: 0 0 0 1000px #060D1A inset !important;
            transition: background-color 5000s ease-in-out 0s;
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
    class="min-h-screen w-full flex items-center justify-center p-3 sm:p-5 selection:bg-kapal-cyan selection:text-navy-950">

    <!-- VIEW 1: COMPACT, BALANCED RETRO ARCADE LOGIN CARD -->
    <main id="loginView"
        class="w-full max-w-[900px] bg-navy-900 border border-white/15 grid grid-cols-1 md:grid-cols-12 overflow-hidden shadow-2xl rounded-sm">

        <!-- Left Panel: Playable 2D Pixel Art Game ("Cap Kapal Pixel Voyage") (7 Columns) -->
        <div
            class="md:col-span-7 relative bg-navy-950 border-b md:border-b-0 md:border-r border-white/15 flex flex-col justify-between min-h-[300px] sm:min-h-[420px] overflow-hidden">

            <!-- Low-Res Playable Pixel Canvas (200x160 scaled up cleanly) -->
            <canvas id="pixelScene" width="200" height="160"
                class="pixel-canvas w-full h-full object-cover absolute inset-0 cursor-pointer"></canvas>

            <!-- Top Right: ONLY Mute Toggle Button so HUD at top-left is completely unobscured -->
            <div class="relative z-10 p-3 flex items-center justify-end pointer-events-none">
                <button type="button" id="muteBtn" onclick="toggleMute(event)" title="Toggle 8-Bit Sound"
                    class="pointer-events-auto px-2 py-1 bg-navy-950/85 hover:bg-navy-800 border border-white/15 font-mono text-[10px] text-slate-300 flex items-center gap-1.5 transition rounded-sm">
                    <span id="muteIcon" class="text-kapal-cyan">♪ ON</span>
                </button>
            </div>

            <!-- Minimal Bottom Control Prompt -->
            <div
                class="relative z-10 px-3 py-2 bg-navy-950/85 border-t border-white/10 flex items-center justify-between pointer-events-none mt-auto">
                <span class="font-mono text-[10px] text-slate-300 tracking-tight">
                    [W/S • ↑/↓ • KLIK] JALUR
                </span>
                <span class="font-mono text-[10px] text-kapal-cyan">
                    +10 NaCl • +50 CRATE • -20 REEF
                </span>
            </div>
        </div>

        <!-- Right Panel: Login Form (5 Columns) -->
        <div class="md:col-span-5 p-6 sm:p-8 flex flex-col justify-center bg-navy-900">

            <!-- Minimal Header -->
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-2">
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
                    <span class="text-slate-600">/</span>
                    <span class="font-mono text-[11px] text-kapal-gold tracking-wide uppercase">
                        Apps
                    </span>
                </div>

                <div class="flex items-center gap-2 mb-1.5">
                    <span
                        class="px-1.5 py-0.5 bg-navy-950 border border-white/15 font-mono text-[9px] tracking-widest text-kapal-salt uppercase">
                        EST. 1978
                    </span>
                    <span
                        class="px-1.5 py-0.5 bg-navy-950 border border-white/15 font-mono text-[9px] text-kapal-cyan">
                        NaCl • PIXEL ENGINE
                    </span>
                </div>

                <h1 class="font-mono text-xs text-slate-400">
                    API Documentation Portal
                </h1>
            </div>

            <!-- Server Flash Status Messages -->
            @if(session('error'))
            <div
                class="mb-4 p-2.5 bg-rose-950/70 border border-rose-500/60 font-mono text-xs text-rose-300 flex items-center gap-2 rounded-sm">
                <span class="inline-block w-1.5 h-1.5 bg-rose-500 shrink-0"></span>
                <span>{{ session('error') }}</span>
            </div>
            @elseif(request()->has('expired'))
            <div
                class="mb-4 p-2.5 bg-amber-950/70 border border-amber-500/60 font-mono text-xs text-amber-300 flex items-center gap-2 rounded-sm">
                <span class="inline-block w-1.5 h-1.5 bg-amber-500 shrink-0"></span>
                <span>Sesi Anda telah kedaluwarsa. Silakan masuk kembali.</span>
            </div>
            @endif

            <!-- Login Form -->
            <form id="loginForm" action="{{ url('/docs/login') }}" method="POST" onsubmit="return handleLogin(event)"
                class="space-y-4" novalidate>
                @csrf
                <div>
                    <label for="username" class="block font-mono text-xs text-slate-300 mb-1.5">
                        Username
                    </label>
                    <input type="text" id="username" name="username" required autocomplete="username" autofocus
                        placeholder="username" oninput="triggerTypingBoost()" value="{{ old('username') }}"
                        class="clean-input w-full px-3.5 py-2.5 bg-navy-950 border border-white/20 font-mono text-sm text-white placeholder-slate-600 transition rounded-sm">
                </div>

                <div>
                    <label for="password" class="block font-mono text-xs text-slate-300 mb-1.5">
                        Password
                    </label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                        placeholder="••••••••" oninput="triggerTypingBoost()"
                        class="clean-input w-full px-3.5 py-2.5 bg-navy-950 border border-white/20 font-mono text-sm text-white placeholder-slate-600 transition rounded-sm">
                </div>

                <p id="errorMsg" class="hidden font-mono text-[11px] text-kapal-red">
                    Masukkan username dan password.
                </p>

                <div class="pt-1">
                    <button type="submit"
                        class="pixel-btn w-full py-2.5 px-4 bg-kapal-red text-white font-mono text-xs font-semibold uppercase tracking-widest border border-rose-300/30 rounded-sm">
                        Masuk ke Dokumentasi
                    </button>
                </div>
            </form>

            <div
                class="pt-4 mt-4 border-t border-white/10 flex items-center justify-between text-slate-500 font-mono text-[10px]">
                <span>PT SUSANTI MEGAH</span>
                <span>HTTPS SECURE</span>
            </div>

        </div>
    </main>

    <!-- VIEW 2: MINIMALIST API DOCUMENTATION (Fallback Preview) -->
    <section id="docsView"
        class="hidden w-full max-w-[900px] bg-navy-900 border border-white/15 shadow-2xl overflow-hidden rounded-sm">

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
                    class="px-3 py-1 bg-navy-800 hover:bg-navy-950 border border-white/15 font-mono text-xs text-slate-200 transition rounded-sm">
                    Logout
                </button>
            </div>
        </header>

        <!-- Clean Content Body -->
        <div class="p-6 space-y-6">

            <!-- Base URL & Auth -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-3.5 bg-navy-950 border border-white/10 rounded-sm">
                    <div class="font-mono text-[10px] text-slate-400 uppercase mb-1">Base URL</div>
                    <code class="font-mono text-xs text-kapal-cyan select-all">{{ url('/v1') }}</code>
                </div>
                <div class="p-3.5 bg-navy-950 border border-white/10 rounded-sm">
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

    <!-- PLAYABLE 2D PIXEL SHIP GAME ENGINE ("CAP KAPAL PIXEL VOYAGE") -->
    <script>
        const canvas = document.getElementById('pixelScene');
        const ctx = canvas.getContext('2d');
        const W = canvas.width; // 200
        const H = canvas.height; // 160

        // 8-Bit Web Audio Synthesizer
        let audioCtx = null;
        let isMuted = false;

        function ensureAudio() {
            if (isMuted) return null;
            if (!audioCtx) {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (AudioContextClass) audioCtx = new AudioContextClass();
            }
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            return audioCtx;
        }

        function play8BitSound(type) {
            const actx = ensureAudio();
            if (!actx) return;

            const now = actx.currentTime;
            const osc = actx.createOscillator();
            const gain = actx.createGain();
            osc.connect(gain);
            gain.connect(actx.destination);

            if (type === 'move') {
                osc.type = 'square';
                osc.frequency.setValueAtTime(240, now);
                osc.frequency.exponentialRampToValueAtTime(360, now + 0.045);
                gain.gain.setValueAtTime(0.03, now);
                gain.gain.linearRampToValueAtTime(0.001, now + 0.045);
                osc.start(now);
                osc.stop(now + 0.045);
            } else if (type === 'salt') {
                osc.type = 'square';
                osc.frequency.setValueAtTime(587.33, now); // D5
                osc.frequency.setValueAtTime(880, now + 0.045); // A5
                gain.gain.setValueAtTime(0.04, now);
                gain.gain.linearRampToValueAtTime(0.001, now + 0.11);
                osc.start(now);
                osc.stop(now + 0.11);
            } else if (type === 'crate') {
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(523.25, now); // C5
                osc.frequency.setValueAtTime(659.25, now + 0.05); // E5
                osc.frequency.setValueAtTime(783.99, now + 0.10); // G5
                osc.frequency.setValueAtTime(1046.50, now + 0.15); // C6
                gain.gain.setValueAtTime(0.06, now);
                gain.gain.linearRampToValueAtTime(0.001, now + 0.24);
                osc.start(now);
                osc.stop(now + 0.24);
            } else if (type === 'hit') {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(150, now);
                osc.frequency.linearRampToValueAtTime(55, now + 0.18);
                gain.gain.setValueAtTime(0.06, now);
                gain.gain.linearRampToValueAtTime(0.001, now + 0.18);
                osc.start(now);
                osc.stop(now + 0.18);
            }
        }

        function toggleMute(e) {
            if (e) e.stopPropagation();
            isMuted = !isMuted;
            const icon = document.getElementById('muteIcon');
            icon.textContent = isMuted ? '♪ OFF' : '♪ ON';
            icon.className = isMuted ? 'text-slate-500' : 'text-kapal-cyan';
            if (!isMuted) play8BitSound('move');
        }

        // Sea Lanes Y-Coordinates (3 Horizontal Sea Lanes)
        const LANES = [90, 112, 134];

        // Game State
        let tick = 0;
        let score = 0;
        let bestScore = 0;
        let speedBoost = 0;
        let hitFlash = 0;
        let spawnTimer = 25;

        // Player Ship State
        const ship = {
            x: 24,
            lane: 1,
            y: LANES[1],
            targetY: LANES[1]
        };

        // Entities: Collectibles & Reef Obstacles
        const entities = [];
        // Floating Score Popups
        const popups = [];
        // Water Wake Particles
        const wakes = [];

        // Twinkling Night Sky Stars / Salt Dust
        const stars = Array.from({
            length: 28
        }, () => ({
            x: Math.floor(Math.random() * W),
            y: 18 + Math.floor(Math.random() * 52),
            speed: 0.15 + Math.random() * 0.25,
            phase: Math.random() * 100
        }));

        // Compact Iconic "Cap Kapal" Sailboat Sprite (24x16)
        const shipSprite = [
            "...........G............",
            ".........G.G............",
            "...........G.....G......",
            "..........WG....GG......",
            ".....G...WWG.....G......",
            "....GG..WWWG....WG......",
            ".....G.WWWWG...WWG......",
            "....WG.WWWWG..WWWG......",
            "...SSG.WWWWG.WWWWG......",
            "..SSSG.WWWWG.WWWWG......",
            ".SSSSG.WWWWG.WWWWG......",
            "...........G.....G......",
            ".RRRRRRRRRRRRRRRRRRRRRR.",
            "..RRRNRRRNRRRNRRRRRRRR..",
            "...DDDDDDDDDDDDDDDDDD..."
        ];

        // Halite Crystal Sprite (+10 NaCl) (7x7)
        const crystalSprite = [
            "...C...",
            "..CCC..",
            ".CWKWC.",
            "CCKKKCC",
            ".CWKWC.",
            "..CCC..",
            "...C..."
        ];

        // Golden Salt Crate Sprite (+50 NaCl) (9x8)
        const crateSprite = [
            ".GGGGGGG.",
            "GWWWWWWWG",
            "GWGGGGGWg",
            "GWGWWWGWG",
            "GWGGGGGWg",
            "GWWWWWWWG",
            ".DDDDDDD."
        ];

        // Dark Sea Reef Obstacle Sprite (11x8)
        const reefSprite = [
            ".....M.....",
            "....MMM....",
            "..MMLLLMM..",
            ".MLLLLLLLM.",
            "MLLLLLLLLLM",
            "RRRRRRRRRRR",
            ".CCCCCCCC.."
        ];

        const palette = {
            'W': '#F8FAFC',
            'K': '#FFFFFF',
            'S': '#94A3B8',
            'G': '#F59E0B',
            'g': '#D97706',
            'R': '#E11D48',
            'D': '#9F1239',
            'N': '#060D1A',
            'C': '#38BDF8',
            'M': '#334155',
            'L': '#1E293B'
        };

        // Tiny 3x5 Pixel Font Map for Crisp Canvas HUD
        const pixelFont = {
            '0': ["111", "101", "101", "101", "111"],
            '1': ["010", "110", "010", "010", "111"],
            '2': ["111", "001", "111", "100", "111"],
            '3': ["111", "001", "111", "001", "111"],
            '4': ["101", "101", "111", "001", "001"],
            '5': ["111", "100", "111", "001", "111"],
            '6': ["111", "100", "111", "101", "111"],
            '7': ["111", "001", "001", "001", "001"],
            '8': ["111", "101", "111", "101", "111"],
            '9': ["111", "101", "111", "001", "111"],
            'A': ["010", "101", "111", "101", "101"],
            'B': ["110", "101", "110", "101", "110"],
            'C': ["111", "100", "100", "100", "111"],
            'E': ["111", "100", "110", "100", "111"],
            'I': ["111", "010", "010", "010", "111"],
            'L': ["100", "100", "100", "100", "111"],
            'N': ["111", "101", "101", "101", "101"],
            'O': ["111", "101", "101", "101", "111"],
            'P': ["111", "101", "111", "100", "100"],
            'R': ["111", "101", "110", "101", "101"],
            'S': ["111", "100", "111", "001", "111"],
            'T': ["111", "010", "010", "010", "010"],
            'V': ["101", "101", "101", "101", "010"],
            'Y': ["101", "101", "010", "010", "010"],
            '+': ["000", "010", "111", "010", "000"],
            '-': ["000", "000", "111", "000", "000"],
            ':': ["000", "010", "000", "010", "000"],
            ' ': ["000", "000", "000", "000", "000"]
        };

        function drawPixelText(text, x, y, color) {
            const str = String(text).toUpperCase();
            let curX = Math.floor(x);
            const curY = Math.floor(y);
            ctx.fillStyle = color;

            for (let i = 0; i < str.length; i++) {
                const glyph = pixelFont[str[i]] || pixelFont[' '];
                for (let r = 0; r < 5; r++) {
                    for (let c = 0; c < 3; c++) {
                        if (glyph[r][c] === '1') {
                            ctx.fillRect(curX + c, curY + r, 1, 1);
                        }
                    }
                }
                curX += 4;
            }
        }

        function drawSprite(sprite, x, y) {
            const sx = Math.floor(x);
            const sy = Math.floor(y);
            for (let r = 0; r < sprite.length; r++) {
                const row = sprite[r];
                for (let c = 0; c < row.length; c++) {
                    const ch = row[c];
                    if (ch !== '.' && palette[ch]) {
                        ctx.fillStyle = palette[ch];
                        ctx.fillRect(sx + c, sy + r, 1, 1);
                    }
                }
            }
        }

        function changeLane(delta) {
            const nextLane = Math.max(0, Math.min(2, ship.lane + delta));
            if (nextLane !== ship.lane) {
                ship.lane = nextLane;
                ship.targetY = LANES[ship.lane];
                play8BitSound('move');
                // Spawn wake splash
                for (let i = 0; i < 4; i++) {
                    wakes.push({
                        x: ship.x + 6 + Math.random() * 10,
                        y: ship.y + 13,
                        vx: -0.8 - Math.random(),
                        life: 14
                    });
                }
            }
        }

        // Keyboard Controls
        window.addEventListener('keydown', (e) => {
            const activeTag = document.activeElement ? document.activeElement.tagName : '';
            const isInput = activeTag === 'INPUT' || activeTag === 'TEXTAREA';

            if (e.key === 'ArrowUp' || (!isInput && (e.key === 'w' || e.key === 'W'))) {
                e.preventDefault();
                changeLane(-1);
            } else if (e.key === 'ArrowDown' || (!isInput && (e.key === 's' || e.key === 'S'))) {
                e.preventDefault();
                changeLane(1);
            } else if (!isInput && e.key === ' ') {
                e.preventDefault();
                ship.lane = (ship.lane + 1) % 3;
                ship.targetY = LANES[ship.lane];
                play8BitSound('move');
            }
        });

        // Click / Tap on Canvas to Steer Ship to Clicked Sea Lane
        canvas.addEventListener('pointerdown', (e) => {
            const rect = canvas.getBoundingClientRect();
            const clickY = ((e.clientY - rect.top) / rect.height) * H;

            // Find closest sea lane to tap position
            let closestLane = 0;
            let minDiff = 999;
            for (let i = 0; i < LANES.length; i++) {
                const diff = Math.abs(clickY - LANES[i]);
                if (diff < minDiff) {
                    minDiff = diff;
                    closestLane = i;
                }
            }

            if (closestLane !== ship.lane) {
                ship.lane = closestLane;
                ship.targetY = LANES[ship.lane];
                play8BitSound('move');
            } else {
                // Cycle lane if user taps directly on current lane
                ship.lane = (ship.lane + 1) % 3;
                ship.targetY = LANES[ship.lane];
                play8BitSound('move');
            }
        });

        // Typing in Username/Password Boosts Ship & Spawns Bonus Salt Crystal
        function triggerTypingBoost() {
            speedBoost = 18;
            const bonusLane = Math.floor(Math.random() * 3);
            entities.push({
                type: Math.random() > 0.8 ? 'crate' : 'crystal',
                lane: bonusLane,
                x: W + 4,
                y: LANES[bonusLane]
            });
        }

        function spawnEntity() {
            const lane = Math.floor(Math.random() * 3);
            const roll = Math.random();
            let type = 'crystal';
            if (roll > 0.84) {
                type = 'crate';
            } else if (roll < 0.32) {
                type = 'reef';
            }

            // Prevent impossible 3-lane reef blockade
            const recentReefs = entities.filter(e => e.type === 'reef' && e.x > W - 45);
            if (type === 'reef' && recentReefs.length >= 2) {
                type = 'crystal';
            }

            entities.push({
                type,
                lane,
                x: W + 10,
                y: LANES[lane]
            });
        }

        function updateGame() {
            tick++;
            if (speedBoost > 0) speedBoost *= 0.94;
            if (hitFlash > 0) hitFlash--;

            const currentSpeed = 1.05 + Math.min(0.6, score / 800) + (speedBoost * 0.06);

            // Smooth vertical lane interpolation
            ship.y += (ship.targetY - ship.y) * 0.28;

            // Spawn water wake behind ship hull
            if (tick % 3 === 0) {
                wakes.push({
                    x: ship.x + 2,
                    y: Math.round(ship.y) + 13 + (Math.random() > 0.5 ? 1 : 0),
                    vx: -currentSpeed * 0.9,
                    life: 16
                });
            }

            for (let i = wakes.length - 1; i >= 0; i--) {
                wakes[i].x += wakes[i].vx;
                wakes[i].life--;
                if (wakes[i].life <= 0) wakes.splice(i, 1);
            }

            // Spawn Collectibles & Reefs
            spawnTimer--;
            if (spawnTimer <= 0) {
                spawnEntity();
                spawnTimer = Math.max(22, Math.floor(42 - currentSpeed * 6 + Math.random() * 16));
            }

            // Update Entities & Check Collisions
            for (let i = entities.length - 1; i >= 0; i--) {
                const en = entities[i];
                en.x -= currentSpeed;

                // Hitbox Check with Ship
                const dx = Math.abs((ship.x + 11) - (en.x + 4));
                const dy = Math.abs((ship.y + 10) - (en.y + 4));

                if (dx < 11 && dy < 8) {
                    if (en.type === 'crystal') {
                        score += 10;
                        if (score > bestScore) bestScore = score;
                        play8BitSound('salt');
                        popups.push({
                            text: '+10',
                            x: ship.x + 12,
                            y: ship.y - 2,
                            color: '#38BDF8',
                            life: 22
                        });
                    } else if (en.type === 'crate') {
                        score += 50;
                        if (score > bestScore) bestScore = score;
                        play8BitSound('crate');
                        popups.push({
                            text: '+50',
                            x: ship.x + 12,
                            y: ship.y - 2,
                            color: '#F59E0B',
                            life: 26
                        });
                    } else if (en.type === 'reef') {
                        score = Math.max(0, score - 20);
                        hitFlash = 10;
                        play8BitSound('hit');
                        popups.push({
                            text: '-20',
                            x: ship.x + 12,
                            y: ship.y - 2,
                            color: '#E11D48',
                            life: 22
                        });
                    }
                    entities.splice(i, 1);
                    continue;
                }

                if (en.x < -20) {
                    entities.splice(i, 1);
                }
            }

            // Update Popups
            for (let i = popups.length - 1; i >= 0; i--) {
                popups[i].y -= 0.35;
                popups[i].life--;
                if (popups[i].life <= 0) popups.splice(i, 1);
            }
        }

        function renderGame() {
            updateGame();

            // 1. Sky Background Bands
            ctx.fillStyle = hitFlash > 0 ? '#2A0A18' : '#050B16';
            ctx.fillRect(0, 0, W, 38);
            ctx.fillStyle = '#081223';
            ctx.fillRect(0, 38, W, 24);
            ctx.fillStyle = '#0B192C';
            ctx.fillRect(0, 62, W, 20);

            // 2. Twinkling Stars & Salt Dust in Sky
            for (let i = 0; i < stars.length; i++) {
                const s = stars[i];
                s.x -= s.speed + (speedBoost * 0.03);
                if (s.x < 0) s.x = W - 1;
                if (Math.sin(tick * 0.07 + s.phase) > -0.2) {
                    ctx.fillStyle = (i % 3 === 0) ? '#38BDF8' : '#F8FAFC';
                    ctx.fillRect(Math.floor(s.x), s.y, 1, 1);
                }
            }

            // 3. Pixel Moon & Distant Surabaya Lighthouse Silhouette
            const moonX = 166;
            const moonY = 28;
            ctx.fillStyle = '#132742';
            ctx.fillRect(moonX - 5, moonY - 5, 11, 11);
            ctx.fillStyle = '#F8FAFC';
            ctx.fillRect(moonX - 3, moonY - 3, 7, 7);

            // 4. Sea Background & 3 Distinct Sailing Lanes
            ctx.fillStyle = '#09172A';
            ctx.fillRect(0, 82, W, H - 82);

            for (let laneIdx = 0; laneIdx < LANES.length; laneIdx++) {
                const ly = LANES[laneIdx] + 13;
                // Subtle wave crest along each lane
                for (let x = 0; x < W; x += 2) {
                    const waveOffset = Math.floor(Math.sin((x * 0.08) - (tick * 0.08) + laneIdx) * 1.2);
                    const isFoam = ((x + Math.floor(tick * 0.6)) % 14 === 0);
                    ctx.fillStyle = isFoam ? '#38BDF8' : '#132742';
                    ctx.fillRect(x, ly + waveOffset, 2, 1);
                }
            }

            // 5. Ship Wake Particles
            for (let i = 0; i < wakes.length; i++) {
                const wk = wakes[i];
                ctx.fillStyle = wk.life > 8 ? '#F8FAFC' : '#38BDF8';
                ctx.fillRect(Math.floor(wk.x), Math.floor(wk.y), 2, 1);
            }

            // 6. Draw Entities (Halite Crystals, Golden Crates, Sea Reefs)
            for (let i = 0; i < entities.length; i++) {
                const en = entities[i];
                const bob = Math.round(Math.sin(tick * 0.12 + en.x * 0.1) * 1);
                if (en.type === 'crystal') {
                    drawSprite(crystalSprite, en.x, en.y + 4 + bob);
                } else if (en.type === 'crate') {
                    drawSprite(crateSprite, en.x, en.y + 4 + bob);
                } else if (en.type === 'reef') {
                    drawSprite(reefSprite, en.x, en.y + 6);
                }
            }

            // 7. Draw Player "Cap Kapal" Sailboat
            const shipBob = Math.round(Math.sin(tick * 0.1) * 1);
            drawSprite(shipSprite, ship.x, Math.round(ship.y) + shipBob);

            // 8. Draw Floating Score Popups
            for (let i = 0; i < popups.length; i++) {
                const p = popups[i];
                drawPixelText(p.text, p.x, p.y, p.color);
            }

            // 9. Clean Pixel HUD Bar (Top Left inside Canvas, completely unobstructed)
            ctx.fillStyle = 'rgba(6, 13, 26, 0.85)';
            ctx.fillRect(4, 4, 138, 12);
            drawPixelText(`NACL:${score}`, 7, 8, '#F8FAFC');
            drawPixelText(`BEST:${bestScore}`, 76, 8, '#F59E0B');

            requestAnimationFrame(renderGame);
        }

        // Minimal API Documentation Data
        const endpoints = [{
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
                play8BitSound('hit');
                return false;
            }

            if (errorMsg) errorMsg.classList.add('hidden');
            play8BitSound('crate');
            // Allow form submission to Laravel POST /docs/login
            return true;
        }

        function handleLogout() {
            window.location.href = "{{ url('/docs/logout') }}";
        }

        // Start Playable Pixel Game Loop on Load
        window.onload = function() {
            renderGame();
        };
    </script>
</body>

</html>