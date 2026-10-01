<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B2B API Key Management - PT Susanti Megah</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            200: '#fde68a',
                            300: '#fcd34d',
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                            800: '#92400e',
                            900: '#78350f',
                            950: '#451a03',
                        }
                    }
                }
            }
        };
        // Apply saved theme immediately to prevent flashing
        (function() {
            const saved = localStorage.getItem('sm-theme') || 'dark';
            if (saved === 'dark') document.documentElement.classList.add('dark');
            else document.documentElement.classList.remove('dark');
        })();
    </script>
    <!-- Google Fonts Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace !important; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.25); border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.45); }

        /* Light Mode Overrides */
        html:not(.dark) body                  { background-color: #F8FAFC; color: #0F172A; }
        html:not(.dark) .bg-surface-main       { background-color: #FFFFFF !important; border-color: #E2E8F0 !important; }
        html:not(.dark) .bg-surface-card       { background-color: #FFFFFF !important; border-color: #E2E8F0 !important; }
        html:not(.dark) .bg-surface-subtle     { background-color: #F1F5F9 !important; border-color: #E2E8F0 !important; }
        html:not(.dark) .bg-surface-header     { background-color: rgba(255, 255, 255, 0.85) !important; border-color: #E2E8F0 !important; }
        html:not(.dark) .text-heading          { color: #0F172A !important; }
        html:not(.dark) .text-body             { color: #334155 !important; }
        html:not(.dark) .text-muted            { color: #64748B !important; }
        html:not(.dark) .table-row-hover:hover { background-color: #F8FAFC !important; }
        html:not(.dark) .border-border-color   { border-color: #E2E8F0 !important; }
        html:not(.dark) .divide-border-color > * { border-color: #E2E8F0 !important; }
        html:not(.dark) .badge-neutral         { background-color: #F1F5F9 !important; border-color: #CBD5E1 !important; color: #475569 !important; }

        /* Dark Mode Definitions */
        html.dark body                  { background-color: #0B0F17; color: #F1F5F9; }
        html.dark .bg-surface-main       { background-color: #111827; border-color: #1F2937; }
        html.dark .bg-surface-card       { background-color: #111827; border-color: #1F2937; }
        html.dark .bg-surface-subtle     { background-color: #1E293B; border-color: #334155; }
        html.dark .bg-surface-header     { background-color: rgba(11, 15, 23, 0.85); border-color: #1F2937; }
        html.dark .text-heading          { color: #F8FAFC; }
        html.dark .text-body             { color: #CBD5E1; }
        html.dark .text-muted            { color: #94A3B8; }
        html.dark .table-row-hover:hover { background-color: rgba(30, 41, 59, 0.5); }
        html.dark .border-border-color   { border-color: #1F2937; }
        html.dark .divide-border-color > * { border-color: rgba(31, 41, 55, 0.6); }
        html.dark .badge-neutral         { background-color: #1E293B; border-color: #334155; color: #94A3B8; }

        /* Ambient Glow effect */
        .glow-amber {
            box-shadow: 0 0 40px -10px rgba(245, 158, 11, 0.15);
        }
        .glow-btn {
            box-shadow: 0 4px 20px -2px rgba(245, 158, 11, 0.35);
        }
        .glow-btn:hover {
            box-shadow: 0 6px 25px -1px rgba(245, 158, 11, 0.5);
        }
    </style>
</head>
<body class="min-h-screen transition-colors duration-200 antialiased selection:bg-amber-500 selection:text-slate-950">

    <!-- Top Sticky Navigation Bar -->
    <header class="bg-surface-header backdrop-blur-md border-b border-border-color sticky top-0 z-40 transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            
            <!-- Brand Identity -->
            <div class="flex items-center space-x-3.5">
                <div class="relative flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-600 via-orange-500 to-amber-400 font-extrabold text-slate-950 text-base shadow-lg shadow-orange-500/25 ring-1 ring-white/20 shrink-0">
                    SM
                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 border-2 border-slate-950 rounded-full" title="Gateway Online"></span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="font-bold text-base sm:text-lg leading-tight text-heading tracking-tight">B2B API Gateway</h1>
                        <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                            v1.0 CMO
                        </span>
                    </div>
                    <p class="text-xs text-muted font-medium">PT Susanti Megah &bull; Distributor Channel Portal</p>
                </div>
            </div>

            <!-- Quick Navigation & Controls -->
            <div class="flex items-center space-x-2 sm:space-x-2.5">
                <a href="/monitoringsm/hub" class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-subtle hover:bg-slate-700/50 border border-border-color text-xs font-semibold text-body transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Admin Hub</span>
                </a>

                <a href="/monitoringsm" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-subtle hover:bg-slate-700/50 border border-border-color text-xs font-semibold text-body transition">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>System Pulse</span>
                </a>

                <a href="/docs" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-subtle hover:bg-slate-700/50 border border-border-color text-xs font-semibold text-body transition">
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    <span class="hidden sm:inline">OpenAPI Docs</span>
                    <span class="sm:hidden">Docs</span>
                </a>

                <!-- Theme Toggle Button -->
                <button onclick="toggleTheme()" title="Ganti Tema (Dark/Light)" class="p-2 rounded-lg bg-surface-subtle hover:bg-slate-700/50 border border-border-color text-body transition flex items-center justify-center">
                    <span id="themeIcon" class="text-sm">🌙</span>
                </button>

                <a href="/monitoringsm/logout" title="Logout" class="p-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-400 text-xs font-semibold transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Flash Alert Messages -->
        @if(session('success'))
            <div class="flex items-center justify-between p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm shadow-sm backdrop-blur">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 flex items-center justify-center font-bold text-emerald-400 shrink-0">✓</div>
                    <span class="font-medium">{{ session('success') }}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="p-1 text-emerald-400 hover:text-emerald-200 text-xl font-bold leading-none">&times;</button>
            </div>
        @endif

        @if(session('info'))
            <div class="flex items-center justify-between p-4 rounded-2xl bg-blue-500/10 border border-blue-500/30 text-blue-400 text-sm shadow-sm backdrop-blur">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-blue-500/20 flex items-center justify-center font-bold text-blue-400 shrink-0">ℹ</div>
                    <span class="font-medium">{{ session('info') }}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="p-1 text-blue-400 hover:text-blue-200 text-xl font-bold leading-none">&times;</button>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm shadow-sm space-y-1 backdrop-blur">
                <div class="flex items-center gap-2 font-semibold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>Perhatian / Terjadi Kesalahan:</span>
                </div>
                <ul class="list-disc list-inside text-xs pl-2 space-y-0.5 text-rose-300/90">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Single-Time Generated Key Banner (High Security Alert) -->
        @if(session('generated_key'))
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-950/90 via-slate-900 to-orange-950/90 border-2 border-amber-500/80 p-6 sm:p-7 shadow-2xl glow-amber">
                <div class="absolute -right-10 -bottom-10 w-44 h-44 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-amber-500/30">
                    <div class="space-y-1">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-bold uppercase tracking-wider">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span>Token Baru Berhasil Dibuat</span>
                        </div>
                        <h3 class="text-lg font-bold text-white tracking-tight">
                            API Key untuk <span class="text-amber-400 underline decoration-amber-500/40 underline-offset-4">{{ session('distributor_name') }}</span>
                        </h3>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-amber-200/80 bg-amber-500/10 px-3 py-2 rounded-xl border border-amber-500/20 shrink-0">
                        <span>⚠️ Key mentah ini <strong>hanya muncul 1x saja</strong>. Segera simpan di secret vault Anda.</span>
                    </div>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <div class="flex-1 flex items-center bg-slate-950/90 border border-amber-500/40 rounded-xl px-4 py-3 shadow-inner overflow-hidden">
                        <code id="rawApiKeyText" class="font-mono text-sm sm:text-base font-semibold text-amber-300 tracking-wide break-all select-all flex-1">
                            {{ session('generated_key') }}
                        </code>
                    </div>
                    <button onclick="copyToClipboard()" id="copyBtn" class="px-5 py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-bold text-sm rounded-xl transition flex items-center justify-center gap-2 shadow-lg shadow-amber-500/30 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span>Salin API Key</span>
                    </button>
                </div>
            </div>
        @endif

        <!-- Stat Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Stat 1: Total Keys -->
            <div class="p-5 rounded-2xl bg-surface-card border border-border-color shadow-sm transition hover:border-amber-500/40 relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-muted tracking-wider uppercase">Total API Keys</span>
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500 group-hover:scale-110 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-heading tracking-tight">{{ $stats['total_keys'] }}</span>
                    <span class="text-xs font-medium text-muted">terdaftar</span>
                </div>
                <div class="mt-2 text-[11px] text-muted flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    <span>Seluruh kredensial ERP distributor</span>
                </div>
            </div>

            <!-- Stat 2: Active Keys -->
            <div class="p-5 rounded-2xl bg-surface-card border border-border-color shadow-sm transition hover:border-emerald-500/40 relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-emerald-500 tracking-wider uppercase">Keys Aktif</span>
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 group-hover:scale-110 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-emerald-500 tracking-tight">{{ $stats['active_keys'] }}</span>
                    <span class="text-xs font-medium text-emerald-500/70">ready to use</span>
                </div>
                <div class="mt-2 text-[11px] text-muted flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>Bisa akses POST /external/cmo</span>
                </div>
            </div>

            <!-- Stat 3: Inactive Keys -->
            <div class="p-5 rounded-2xl bg-surface-card border border-border-color shadow-sm transition hover:border-rose-500/40 relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-rose-400 tracking-wider uppercase">Keys Dinonaktifkan</span>
                    <div class="w-9 h-9 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 group-hover:scale-110 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-rose-400 tracking-tight">{{ $stats['inactive_keys'] }}</span>
                    <span class="text-xs font-medium text-rose-500/70">revoked/off</span>
                </div>
                <div class="mt-2 text-[11px] text-muted flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                    <span>Akses ditolak (HTTP 401)</span>
                </div>
            </div>

            <!-- Stat 4: Total Distributors -->
            <div class="p-5 rounded-2xl bg-surface-card border border-border-color shadow-sm transition hover:border-blue-500/40 relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-blue-400 tracking-wider uppercase">Distributor Partner</span>
                    <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 group-hover:scale-110 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-blue-400 tracking-tight">{{ $stats['total_distributors'] }}</span>
                    <span class="text-xs font-medium text-blue-500/70">partners in DB</span>
                </div>
                <div class="mt-2 text-[11px] text-muted flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                    <span>Database master distributor</span>
                </div>
            </div>

        </div>

        <!-- Action & Search Toolbar -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pt-2">
            <div>
                <h2 class="text-xl font-bold text-heading tracking-tight flex items-center gap-2">
                    <span>Daftar Kredensial API B2B</span>
                    <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-surface-subtle border border-border-color text-muted">
                        {{ $apiKeys->count() }} Key
                    </span>
                </h2>
                <p class="text-xs text-muted mt-0.5">Kelola otorisasi akses API Add CMO untuk sistem ERP/DMS pihak ketiga</p>
            </div>

            <!-- Action Controls: Search & Generate Button -->
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="relative flex-1 sm:w-64">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input
                        type="text"
                        id="tableSearch"
                        oninput="filterTableRows(this.value)"
                        placeholder="Cari label, prefix, distributor..."
                        class="w-full bg-surface-card border border-border-color rounded-xl pl-9 pr-3 py-2 text-xs text-heading placeholder:text-muted focus:outline-none focus:border-amber-500 transition"
                    >
                </div>

                <button onclick="document.getElementById('generateModal').classList.remove('hidden')" class="px-4 py-2.5 bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-bold text-xs sm:text-sm rounded-xl glow-btn transition flex items-center gap-2 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Generate Key Baru</span>
                </button>
            </div>
        </div>

        <!-- Monitoring Data Table Card -->
        <div class="bg-surface-card border border-border-color rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" id="keysTable">
                    <thead class="bg-surface-subtle text-[11px] font-bold text-muted uppercase tracking-wider border-b border-border-color">
                        <tr>
                            <th class="py-4 px-5">Label & Sistem Integrasi</th>
                            <th class="py-4 px-4">Distributor Ditautkan</th>
                            <th class="py-4 px-4">Prefix Key</th>
                            <th class="py-4 px-4">Allowed IPs</th>
                            <th class="py-4 px-4">Terakhir Digunakan</th>
                            <th class="py-4 px-4">Status</th>
                            <th class="py-4 px-5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-color text-xs sm:text-sm" id="keysTableBody">
                        @forelse($apiKeys as $key)
                            @php
                                $distCount = $key->distributors->count();
                                $distJson  = $key->distributors->map(fn($d) => ['name' => $d->name, 'code' => $d->code_customer])->toJson();
                            @endphp
                            <tr class="table-row-hover transition api-key-row" data-search="{{ strtolower($key->name . ' ' . $key->company_name . ' ' . $key->key_prefix . ' ' . $key->distributors->pluck('name')->implode(' ') . ' ' . $key->distributors->pluck('code_customer')->implode(' ')) }}">
                                
                                {{-- Kolom Label & Sistem --}}
                                <td class="py-4 px-5">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                                            {{ strtoupper(substr($key->name, 0, 2)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-heading text-sm leading-snug">{{ $key->name }}</div>
                                            @if($key->company_name)
                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-400 text-[11px] font-medium mt-1">
                                                    <span>🏢 {{ $key->company_name }}</span>
                                                </div>
                                            @endif
                                            <div class="text-[10px] text-muted mt-1 font-mono">Dibuat {{ $key->created_at ? $key->created_at->format('d M Y') : '-' }}</div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Kolom Distributor --}}
                                <td class="py-4 px-4">
                                    @if($distCount === 0)
                                        <span class="text-muted italic text-xs">Belum ada</span>
                                    @else
                                        <button
                                            type="button"
                                            onclick="openDistDetail({{ $key->id }}, '{{ addslashes($key->name) }}', {{ $distJson }})"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-slate-700/50 border border-border-color hover:border-amber-500/50 transition group"
                                            title="Klik untuk melihat distributor yang berhak menggunakan key ini"
                                        >
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500/20 text-amber-400 text-[11px] font-bold shrink-0">
                                                {{ $distCount }}
                                            </span>
                                            <span class="text-xs font-semibold text-body group-hover:text-amber-400 transition max-w-[130px] truncate">
                                                {{ $distCount === 1 ? $key->distributors->first()->name : $distCount . ' Distributor' }}
                                            </span>
                                            <svg class="w-3.5 h-3.5 text-muted group-hover:text-amber-400 group-hover:translate-x-0.5 transition shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                    @endif
                                </td>

                                {{-- Kolom Prefix Key dengan Tombol Copy --}}
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-surface-subtle border border-border-color font-mono text-xs text-amber-300">
                                        <span class="tracking-wide">{{ $key->key_prefix }}...</span>
                                        <button
                                            type="button"
                                            onclick="copyPrefix('{{ $key->key_prefix }}', this)"
                                            title="Salin Prefix Key"
                                            class="p-1 rounded-lg text-muted hover:text-amber-300 hover:bg-slate-700/50 transition"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>

                                {{-- Kolom Allowed IPs --}}
                                <td class="py-4 px-4">
                                    @if(!empty($key->allowed_ips))
                                        <div class="flex flex-wrap gap-1 max-w-[170px]">
                                            @foreach((array)$key->allowed_ips as $ip)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface-subtle border border-border-color text-[11px] font-mono text-body">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                                                    <span>{{ $ip }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-800/40 border border-border-color text-[11px] text-muted">
                                            <span>🌐</span>
                                            <span>ANY (Semua IP)</span>
                                        </span>
                                    @endif
                                </td>

                                {{-- Kolom Terakhir Digunakan --}}
                                <td class="py-4 px-4 text-xs">
                                    @if($key->last_used_at)
                                        <div class="font-semibold text-emerald-400 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                            <span>{{ $key->last_used_at->diffForHumans() }}</span>
                                        </div>
                                        <div class="text-[11px] text-muted font-mono mt-0.5">{{ $key->last_used_at->format('Y-m-d H:i') }}</div>
                                    @else
                                        <span class="text-muted italic text-xs">Belum Pernah</span>
                                    @endif
                                </td>

                                {{-- Kolom Status --}}
                                <td class="py-4 px-4 whitespace-nowrap">
                                    @if($key->is_active)
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                            <span>Aktif</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-400 border border-rose-500/30">
                                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                            <span>Nonaktif</span>
                                        </span>
                                    @endif
                                </td>

                                {{-- Kolom Aksi --}}
                                <td class="py-4 px-5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Form Toggle Status -->
                                        <form action="/monitoringsm/api-keys/{{ $key->id }}/toggle" method="POST" class="inline">
                                            @csrf
                                            <button
                                                type="submit"
                                                title="{{ $key->is_active ? 'Nonaktifkan Key' : 'Aktifkan Kembali Key' }}"
                                                class="px-3 py-1.5 rounded-xl text-xs font-semibold border transition {{ $key->is_active ? 'bg-surface-subtle hover:bg-slate-700/50 border-border-color text-amber-400 hover:border-amber-500/50' : 'bg-emerald-500/15 hover:bg-emerald-500/25 border-emerald-500/40 text-emerald-400' }}"
                                            >
                                                {{ $key->is_active ? 'Matikan' : 'Aktifkan' }}
                                            </button>
                                        </form>

                                        <!-- Form Revoke/Delete -->
                                        <form action="/monitoringsm/api-keys/{{ $key->id }}/delete" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin mencabut (revoke) API Key ini secara permanen?')">
                                            @csrf
                                            <button
                                                type="submit"
                                                title="Hapus / Cabut API Key secara permanen"
                                                class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-400 transition"
                                            >
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr id="emptyRow">
                                <td colspan="7" class="py-12 text-center text-muted">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <svg class="w-10 h-10 text-muted/50" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                        <p class="font-semibold text-sm text-heading">Belum ada API Key B2B yang di-generate</p>
                                        <p class="text-xs">Klik tombol <strong>Generate Key Baru</strong> di atas untuk membuat kredensial pertama.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Table Footer Status Summary -->
            <div class="px-5 py-3.5 bg-surface-subtle border-t border-border-color flex items-center justify-between text-xs text-muted">
                <div>Menampilkan <strong class="text-heading" id="rowCount">{{ $apiKeys->count() }}</strong> kredensial API B2B</div>
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> {{ $stats['active_keys'] }} Aktif</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-400"></span> {{ $stats['inactive_keys'] }} Nonaktif</span>
                </div>
            </div>
        </div>

    </main>

    <!-- Modal Generate New API Key -->
    <div id="generateModal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 transition-opacity">
        <div class="bg-surface-card border border-border-color rounded-2xl max-w-lg w-full p-6 sm:p-7 shadow-2xl space-y-5 animate-in fade-in zoom-in duration-200">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-border-color pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-heading">Generate API Key B2B Baru</h3>
                        <p class="text-xs text-muted">Buat token otentikasi baru untuk integrasi ERP distributor</p>
                    </div>
                </div>
                <button onclick="document.getElementById('generateModal').classList.add('hidden')" class="p-1 rounded-lg text-muted hover:text-heading hover:bg-surface-subtle transition text-xl font-bold leading-none">&times;</button>
            </div>

            <!-- Modal Form -->
            <form action="/monitoringsm/api-keys/generate" method="POST" class="space-y-4 text-xs sm:text-sm max-h-[75vh] overflow-y-auto pr-1">
                @csrf

                <div>
                    <label class="block font-semibold text-heading mb-1">Nama Perusahaan / Grup <span class="text-muted font-normal">(Opsional)</span></label>
                    <input type="text" name="company_name" placeholder="Contoh: PT Sakti Setia Santosa Group" class="w-full bg-surface-subtle border border-border-color rounded-xl px-3.5 py-2.5 text-heading placeholder:text-muted focus:outline-none focus:border-amber-500 transition">
                    <p class="text-[11px] text-muted mt-1">Gunakan label grup perusahaan jika 1 key digunakan untuk banyak distributor/cabang.</p>
                </div>

                <div>
                    <label class="block font-semibold text-heading mb-1">Label / Nama Sistem Integrasi <span class="text-rose-400">*</span></label>
                    <input type="text" name="name" placeholder="Contoh: ERP SAP Sakti Distributor" required class="w-full bg-surface-subtle border border-border-color rounded-xl px-3.5 py-2.5 text-heading placeholder:text-muted focus:outline-none focus:border-amber-500 transition">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="font-semibold text-heading">Pilih Distributor Berhak <span class="text-rose-400">*</span></label>
                        <span id="distSelectedCount" class="text-xs font-bold text-amber-500">0 dipilih</span>
                    </div>

                    <div class="relative mb-2">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted">🔍</span>
                        <input
                            type="text"
                            id="distSearch"
                            placeholder="Cari nama atau kode distributor..."
                            oninput="filterDist(this.value)"
                            class="w-full bg-surface-subtle border border-border-color rounded-xl pl-8 pr-3 py-2 text-xs text-heading placeholder:text-muted focus:outline-none focus:border-amber-500 transition"
                        >
                    </div>

                    <div id="distList" class="bg-surface-subtle border border-border-color rounded-xl max-h-48 overflow-y-auto p-2 space-y-1">
                        @foreach($distributors as $d)
                            <label class="flex items-center gap-2 px-2.5 py-2 rounded-lg hover:bg-slate-800/40 cursor-pointer dist-item transition">
                                <input type="checkbox" name="distributor_ids[]" value="{{ $d->id }}" onchange="updateSelectedCount()" class="accent-amber-500 w-4 h-4 rounded">
                                <span class="text-heading font-medium text-xs">{{ $d->name }}</span>
                                <span class="text-muted font-mono ml-auto text-[11px]">{{ $d->code_customer }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-heading mb-1">Allowed IPs <span class="text-muted font-normal">(Opsional)</span></label>
                    <input type="text" name="allowed_ips" placeholder="Contoh: 203.0.113.195, 198.51.100.22" class="w-full bg-surface-subtle border border-border-color rounded-xl px-3.5 py-2.5 text-heading placeholder:text-muted focus:outline-none focus:border-amber-500 transition">
                    <p class="text-[11px] text-muted mt-1">Pisahkan dengan koma jika lebih dari 1 IP. Kosongkan jika IP server distributor bersifat dinamis.</p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border-color">
                    <button type="button" onclick="document.getElementById('generateModal').classList.add('hidden')" class="px-4 py-2.5 bg-surface-subtle hover:bg-slate-700/50 border border-border-color text-body rounded-xl text-xs font-semibold transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 rounded-xl text-xs font-bold glow-btn transition">
                        Generate & Terbitkan Key
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Distributor Detail Slide-over Panel -->
    <div id="distDetailOverlay" class="hidden fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-sm transition-opacity" onclick="closeDistDetail()"></div>

    <div id="distDetailPanel"
         class="fixed top-0 right-0 h-full w-full max-w-md z-50 bg-surface-card border-l border-border-color shadow-2xl flex flex-col
                transform translate-x-full opacity-0 pointer-events-none transition-all duration-300 ease-in-out">

        <!-- Panel Header -->
        <div class="px-6 py-5 border-b border-border-color flex items-start justify-between shrink-0">
            <div>
                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-500 border border-amber-500/20 uppercase tracking-wider mb-1.5">
                    Otorisasi Distributor
                </span>
                <h3 id="distDetailTitle" class="text-base font-bold text-heading leading-snug"></h3>
                <span id="distDetailBadge" class="inline-block mt-2 px-2.5 py-1 rounded-xl text-xs font-semibold bg-surface-subtle text-muted border border-border-color"></span>
            </div>
            <button onclick="closeDistDetail()" class="p-1 rounded-lg text-muted hover:text-heading hover:bg-surface-subtle transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Search in Panel -->
        <div class="px-6 py-3.5 border-b border-border-color shrink-0">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted">🔍</span>
                <input
                    id="distDetailSearch"
                    type="text"
                    placeholder="Cari nama atau kode distributor..."
                    oninput="filterDistDetail(this.value)"
                    class="w-full bg-surface-subtle border border-border-color rounded-xl pl-8 pr-3 py-2 text-xs text-heading placeholder:text-muted focus:outline-none focus:border-amber-500 transition"
                >
            </div>
        </div>

        <!-- Distributor List Container -->
        <div id="distDetailList" class="flex-1 overflow-y-auto p-6 space-y-2.5"></div>

        <!-- Panel Footer -->
        <div class="px-6 py-4 border-t border-border-color shrink-0">
            <button onclick="closeDistDetail()" class="w-full py-2.5 rounded-xl bg-surface-subtle hover:bg-slate-700/50 border border-border-color text-body text-xs font-semibold transition">
                Tutup Panel
            </button>
        </div>
    </div>

    <!-- JavaScript Interactions -->
    <script>
        // ── Salin Full Key (Banner Single-time) ──────────────────────────
        function copyToClipboard() {
            const keyText = document.getElementById('rawApiKeyText').innerText.trim();
            navigator.clipboard.writeText(keyText).then(() => {
                const btn = document.getElementById('copyBtn');
                btn.innerHTML = '<span>✅ Berhasil Disalin!</span>';
                btn.classList.replace('from-amber-500', 'from-emerald-500');
                btn.classList.replace('to-orange-500', 'to-emerald-600');
                setTimeout(() => {
                    btn.innerHTML = `
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span>Salin API Key</span>
                    `;
                    btn.classList.replace('from-emerald-500', 'from-amber-500');
                    btn.classList.replace('to-emerald-600', 'to-orange-500');
                }, 2500);
            }).catch(err => {
                alert('Gagal menyalin text: ' + err);
            });
        }

        // ── Salin Prefix Key (di Kolom Tabel) ────────────────────────────
        function copyPrefix(prefix, btn) {
            const originalHtml = btn.innerHTML;
            const copyPromise = (navigator.clipboard && window.isSecureContext)
                ? navigator.clipboard.writeText(prefix)
                : new Promise((resolve, reject) => {
                    const temp = document.createElement('textarea');
                    temp.value = prefix;
                    temp.style.position = 'fixed';
                    temp.style.left = '-9999px';
                    document.body.appendChild(temp);
                    temp.select();
                    try {
                        document.execCommand('copy');
                        resolve();
                    } catch (err) {
                        reject(err);
                    } finally {
                        document.body.removeChild(temp);
                    }
                });

            copyPromise.then(() => {
                btn.innerHTML = `<svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>`;
                btn.title = "Tersalin!";
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.title = "Salin Prefix Key";
                }, 1500);
            }).catch(err => {
                alert('Gagal menyalin: ' + err);
            });
        }

        // ── Filter Tabel Kredensial Realtime ─────────────────────────────
        function filterTableRows(query) {
            const rows = document.querySelectorAll('.api-key-row');
            const q = query.toLowerCase().trim();
            let visibleCount = 0;

            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                const match = searchData.includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            const rowCountEl = document.getElementById('rowCount');
            if (rowCountEl) rowCountEl.textContent = visibleCount;
        }

        // ── Filter Distributor Modal ────────────────────────────────────
        function filterDist(query) {
            const items = document.querySelectorAll('.dist-item');
            const q = query.toLowerCase();
            items.forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(q) ? '' : 'none';
            });
        }

        function updateSelectedCount() {
            const checked = document.querySelectorAll('input[name="distributor_ids[]"]:checked').length;
            const badge = document.getElementById('distSelectedCount');
            if (badge) badge.textContent = checked + ' dipilih';
        }

        // ── Distributor Detail Slide-over Panel ─────────────────────────
        function openDistDetail(keyId, keyName, distributors) {
            const panel   = document.getElementById('distDetailPanel');
            const title   = document.getElementById('distDetailTitle');
            const badge   = document.getElementById('distDetailBadge');
            const list    = document.getElementById('distDetailList');
            const search  = document.getElementById('distDetailSearch');

            title.textContent  = keyName;
            badge.textContent  = distributors.length + ' Distributor Diberi Akses';
            search.value       = '';
            list.innerHTML     = '';

            distributors.forEach(d => {
                const row = document.createElement('div');
                row.className = 'dist-detail-row flex items-center justify-between gap-3 px-4 py-3 rounded-xl border border-border-color bg-surface-subtle hover:border-amber-500/40 transition';
                row.setAttribute('data-name', (d.name + ' ' + d.code).toLowerCase());
                row.innerHTML = `
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500 font-bold text-xs shrink-0">
                            ${d.code.replace('C', '')}
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-heading truncate">${d.name}</div>
                            <div class="text-[11px] font-mono text-muted mt-0.5">CardCode: ${d.code}</div>
                        </div>
                    </div>
                    <button
                        type="button"
                        onclick="copyPrefix('${d.code}', this)"
                        title="Salin Kode Customer"
                        class="p-1.5 rounded-lg text-muted hover:text-amber-400 hover:bg-slate-700/50 transition shrink-0"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                `;
                list.appendChild(row);
            });

            panel.classList.remove('translate-x-full', 'opacity-0', 'pointer-events-none');
            panel.classList.add('translate-x-0', 'opacity-100');
            document.getElementById('distDetailOverlay').classList.remove('hidden');
        }

        function closeDistDetail() {
            const panel = document.getElementById('distDetailPanel');
            panel.classList.add('translate-x-full', 'opacity-0', 'pointer-events-none');
            panel.classList.remove('translate-x-0', 'opacity-100');
            document.getElementById('distDetailOverlay').classList.add('hidden');
        }

        function filterDistDetail(query) {
            const rows = document.querySelectorAll('.dist-detail-row');
            const q = query.toLowerCase();
            rows.forEach(row => {
                row.style.display = row.getAttribute('data-name').includes(q) ? '' : 'none';
            });
        }

        // ── Dark / Light Mode Toggle ────────────────────────────────────
        function toggleTheme() {
            const html    = document.documentElement;
            const isDark  = html.classList.contains('dark');
            const newMode = isDark ? 'light' : 'dark';

            html.classList.toggle('dark',  newMode === 'dark');
            localStorage.setItem('sm-theme', newMode);
            updateThemeUI(newMode);
        }

        function updateThemeUI(mode) {
            const icon = document.getElementById('themeIcon');
            if (!icon) return;
            icon.textContent = (mode === 'dark') ? '🌙' : '☀️';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const saved = localStorage.getItem('sm-theme') || 'dark';
            document.documentElement.classList.toggle('dark', saved === 'dark');
            updateThemeUI(saved);
        });
    </script>

</body>
</html>
