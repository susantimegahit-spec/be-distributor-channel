<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B2B API Key Monitoring - PT Susanti Megah</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Configure Tailwind dark mode via class strategy
        tailwind.config = { darkMode: 'class' };
        // Apply saved theme before page renders (avoid flash)
        (function() {
            const saved = localStorage.getItem('sm-theme') || 'dark';
            if (saved === 'dark') document.documentElement.classList.add('dark');
            else document.documentElement.classList.remove('dark');
        })();
    </script>
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }

        /* ── Light mode body & surface ── */
        html.light body                { background-color: #f1f5f9; color: #0f172a; }
        html.light header              { background: rgba(255,255,255,0.9) !important; border-color: #e2e8f0 !important; }
        html.light h1                  { color: #0f172a !important; }
        html.light p.text-slate-400,
        html.light .text-slate-400     { color: #64748b !important; }
        html.light .text-slate-100     { color: #0f172a !important; }
        html.light .text-slate-200     { color: #1e293b !important; }
        html.light .text-slate-300     { color: #334155 !important; }
        html.light .text-slate-500     { color: #94a3b8 !important; }
        html.light .bg-slate-800       { background-color: #e2e8f0 !important; }
        html.light .bg-slate-700       { background-color: #cbd5e1 !important; }
        html.light .bg-slate-900       { background-color: #f8fafc !important; }
        html.light .bg-slate-950\/70   { background-color: #ffffff !important; }
        html.light .bg-slate-950\/60   { background-color: rgba(0,0,0,0.04) !important; }
        html.light .bg-slate-950\/80   { background-color: rgba(255,255,255,0.92) !important; }
        html.light .bg-slate-950       { background-color: #f1f5f9 !important; }
        html.light .border-slate-800   { border-color: #e2e8f0 !important; }
        html.light .border-slate-700   { border-color: #cbd5e1 !important; }
        html.light .divide-slate-800\/60 > * { border-color: #e2e8f0 !important; }
        html.light .hover\:bg-slate-900\/50:hover { background-color: rgba(0,0,0,0.04) !important; }
        html.light .hover\:bg-slate-700:hover     { background-color: #cbd5e1 !important; }
        html.light .bg-slate-900\/60   { background-color: rgba(248,250,252,0.8) !important; }
        html.light .hover\:bg-slate-800\/60:hover { background-color: #e2e8f0 !important; }
        html.light .text-slate-950     { color: #f8fafc !important; }

        /* Toggle switch animation */
        .theme-toggle-track {
            width: 44px; height: 24px;
            border-radius: 9999px;
            position: relative;
            transition: background 0.3s;
            cursor: pointer;
        }
        .dark .theme-toggle-track  { background: #312e81; }
        html.light .theme-toggle-track { background: #fbbf24; }

        .theme-toggle-thumb {
            position: absolute; top: 3px; width: 18px; height: 18px;
            border-radius: 9999px; background: white;
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s;
            box-shadow: 0 1px 4px rgba(0,0,0,0.3);
        }
        .dark .theme-toggle-thumb  { transform: translateX(3px); }
        html.light .theme-toggle-thumb { transform: translateX(23px); }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">


    <!-- Top Navigation Header -->
    <header class="border-b border-slate-800 bg-slate-950/80 backdrop-blur sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-600 flex items-center justify-center font-bold text-slate-950 text-xl shadow-lg shadow-orange-500/20">
                    SM
                </div>
                <div>
                    <h1 class="font-semibold text-lg leading-none text-slate-100">B2B API Key Monitoring</h1>
                    <p class="text-xs text-slate-400 mt-1">PT Susanti Megah Distributor Channel</p>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <a href="/monitoringsm" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-300 transition">
                    ← System Pulse Monitoring
                </a>
                <a href="/docs" target="_blank" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-300 transition">
                    📖 API Docs
                </a>

                <!-- Dark / Light Mode Toggle -->
                <button onclick="toggleTheme()" title="Toggle Dark/Light Mode" class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-slate-800 transition group">
                    <span id="themeIcon" class="text-sm">🌙</span>
                    <div class="theme-toggle-track">
                        <div class="theme-toggle-thumb"></div>
                    </div>
                    <span id="themeLabel" class="text-xs font-medium text-slate-400 hidden sm:block">Dark</span>
                </button>

                <a href="/monitoringsm/logout" class="px-3 py-1.5 rounded-lg bg-red-950/50 hover:bg-red-900/60 border border-red-800/50 text-xs font-medium text-red-300 transition">
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="bg-emerald-950/60 border border-emerald-500/50 rounded-xl p-4 flex items-center justify-between text-emerald-200 text-sm shadow-lg">
                <div class="flex items-center space-x-2">
                    <span class="text-emerald-400 text-lg">✓</span>
                    <span>{{ session('success') }}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200">&times;</button>
            </div>
        @endif

        @if(session('info'))
            <div class="bg-blue-950/60 border border-blue-500/50 rounded-xl p-4 flex items-center justify-between text-blue-200 text-sm shadow-lg">
                <div class="flex items-center space-x-2">
                    <span class="text-blue-400 text-lg">ℹ</span>
                    <span>{{ session('info') }}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-blue-400 hover:text-blue-200">&times;</button>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-rose-950/60 border border-rose-500/50 rounded-xl p-4 text-rose-200 text-sm space-y-1">
                <p class="font-semibold">Perhatian:</p>
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Single-Time Generated Key Banner Alert -->
        @if(session('generated_key'))
            <div class="bg-gradient-to-r from-amber-950 to-orange-950 border-2 border-amber-500 rounded-2xl p-6 shadow-2xl shadow-amber-500/10 space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="px-2.5 py-1 rounded-md bg-amber-500/20 text-amber-300 text-xs font-semibold uppercase tracking-wider">
                            API Key Baru Ter-generate
                        </span>
                        <h3 class="text-lg font-bold text-slate-100 mt-2">
                            API Key untuk <span class="text-amber-400">{{ session('distributor_name') }}</span>
                        </h3>
                        <p class="text-xs text-amber-200/80 mt-1">
                            ⚠️ Simpan API Key ini sekarang. Key mentah ini <strong>hanya ditampilkan sekali saja</strong> demi keamanan.
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-2 bg-slate-950/90 border border-amber-500/40 rounded-xl p-3">
                    <code id="rawApiKeyText" class="font-mono text-sm sm:text-base text-amber-300 break-all select-all flex-1">
                        {{ session('generated_key') }}
                    </code>
                    <button onclick="copyToClipboard()" id="copyBtn" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold text-xs rounded-lg transition shrink-0 flex items-center space-x-1 shadow-md">
                        <span>📋 Copy Key</span>
                    </button>
                </div>
            </div>
        @endif

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-950/60 border border-slate-800/80 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-slate-400">Total API Keys</p>
                <div class="flex items-baseline space-x-2 mt-2">
                    <span class="text-3xl font-bold text-slate-100">{{ $stats['total_keys'] }}</span>
                    <span class="text-xs text-slate-500">keys registered</span>
                </div>
            </div>

            <div class="bg-slate-950/60 border border-slate-800/80 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-emerald-400">API Keys Aktif</p>
                <div class="flex items-baseline space-x-2 mt-2">
                    <span class="text-3xl font-bold text-emerald-400">{{ $stats['active_keys'] }}</span>
                    <span class="text-xs text-emerald-500/80">ready to use</span>
                </div>
            </div>

            <div class="bg-slate-950/60 border border-slate-800/80 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-rose-400">API Keys Nonaktif</p>
                <div class="flex items-baseline space-x-2 mt-2">
                    <span class="text-3xl font-bold text-rose-400">{{ $stats['inactive_keys'] }}</span>
                    <span class="text-xs text-rose-500/80">revoked/disabled</span>
                </div>
            </div>

            <div class="bg-slate-950/60 border border-slate-800/80 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-blue-400">Distributor Terdaftar</p>
                <div class="flex items-baseline space-x-2 mt-2">
                    <span class="text-3xl font-bold text-blue-400">{{ $stats['total_distributors'] }}</span>
                    <span class="text-xs text-blue-500/80">distributors in DB</span>
                </div>
            </div>
        </div>

        <!-- Action Bar & Generate Form Modal Toggle -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pt-2">
            <div>
                <h2 class="text-xl font-bold text-slate-100">Daftar API Key B2B</h2>
                <p class="text-xs text-slate-400">Monitoring akses API Add CMO dari sistem distributor luar</p>
            </div>

            <button onclick="document.getElementById('generateModal').classList.remove('hidden')" class="px-4 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-400 hover:to-amber-400 text-slate-950 font-semibold text-sm rounded-xl shadow-lg shadow-orange-500/20 transition flex items-center space-x-2">
                <span>🔑 Generate API Key Baru</span>
            </button>
        </div>

        <!-- Monitoring Data Table -->
        <div class="bg-slate-950/70 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/90 text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4">Label / Sistem</th>
                            <th class="py-3.5 px-4">Distributor</th>
                            <th class="py-3.5 px-4">Prefix Key</th>
                            <th class="py-3.5 px-4">Allowed IPs</th>
                            <th class="py-3.5 px-4">Terakhir Digunakan</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-xs sm:text-sm">
                        @forelse($apiKeys as $key)
                            @php
                                $distCount = $key->distributors->count();
                                $distJson  = $key->distributors->map(fn($d) => ['name' => $d->name, 'code' => $d->code_customer])->toJson();
                            @endphp
                            <tr class="hover:bg-slate-900/50 transition">
                                {{-- Kolom Label / Sistem --}}
                                <td class="py-4 px-4">
                                    <div class="font-semibold text-slate-100 text-sm">{{ $key->name }}</div>
                                    @if($key->company_name)
                                        <div class="text-xs text-amber-400 mt-0.5">{{ $key->company_name }}</div>
                                    @endif
                                </td>

                                {{-- Kolom Distributor (ringkasan) --}}
                                <td class="py-4 px-4">
                                    @if($distCount === 0)
                                        <span class="text-slate-500 italic text-xs">Tidak ada</span>
                                    @else
                                        <button
                                            type="button"
                                            onclick="openDistDetail({{ $key->id }}, '{{ addslashes($key->name) }}', {{ $distJson }})"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-amber-500/50 transition group"
                                        >
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-500/20 text-amber-400 text-[11px] font-bold">
                                                {{ $distCount }}
                                            </span>
                                            <span class="text-xs text-slate-300 group-hover:text-amber-300 transition">
                                                {{ $distCount === 1 ? $key->distributors->first()->name : 'Distributor' }}
                                            </span>
                                            <svg class="w-3.5 h-3.5 text-slate-500 group-hover:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                    @endif
                                </td>

                                {{-- Prefix Key --}}
                                <td class="py-4 px-4 font-mono text-xs text-amber-300/90">
                                    {{ $key->key_prefix }}...
                                </td>

                                {{-- Allowed IPs --}}
                                <td class="py-4 px-4">
                                    @if(!empty($key->allowed_ips))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach((array)$key->allowed_ips as $ip)
                                                <span class="px-2 py-0.5 rounded bg-slate-800 border border-slate-700 text-xs font-mono text-slate-300">
                                                    {{ $ip }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-500 italic">ANY (Semua IP)</span>
                                    @endif
                                </td>

                                {{-- Terakhir Digunakan --}}
                                <td class="py-4 px-4 text-xs text-slate-300">
                                    @if($key->last_used_at)
                                        <span class="text-emerald-400 font-medium">{{ $key->last_used_at->diffForHumans() }}</span>
                                        <div class="text-[11px] text-slate-500">{{ $key->last_used_at->format('Y-m-d H:i:s') }}</div>
                                    @else
                                        <span class="text-slate-500 italic">Belum Pernah</span>
                                    @endif
                                </td>

                                {{-- Status --}}
                                <td class="py-4 px-4">
                                    @if($key->is_active)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-950/80 text-emerald-300 border border-emerald-500/40">
                                            ● Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-950/80 text-rose-300 border border-rose-500/40">
                                            ○ Nonaktif
                                        </span>
                                    @endif
                                </td>

                                {{-- Aksi --}}
                                <td class="py-4 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Toggle Active Form -->
                                        <form action="/monitoringsm/api-keys/{{ $key->id }}/toggle" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded-lg text-xs font-medium border transition {{ $key->is_active ? 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-amber-300' : 'bg-emerald-950 hover:bg-emerald-900 border-emerald-700 text-emerald-200' }}">
                                                {{ $key->is_active ? 'Matikan' : 'Aktifkan' }}
                                            </button>
                                        </form>

                                        <!-- Delete/Revoke Form -->
                                        <form action="/monitoringsm/api-keys/{{ $key->id }}/delete" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin mencabut (revoke) API Key ini secara permanen?')">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded-lg text-xs font-medium bg-rose-950/60 hover:bg-rose-900 border border-rose-800 text-rose-300 transition">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-500">
                                    Belum ada API Key B2B yang di-generate. Klik tombol <strong>Generate API Key Baru</strong> untuk menambahkan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>

    </main>

    <!-- Modal Generate New API Key -->
    <div id="generateModal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <h3 class="text-lg font-bold text-slate-100">Generate API Key B2B Baru</h3>
                <button onclick="document.getElementById('generateModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-200 text-xl font-bold">&times;</button>
            </div>

            <form action="/monitoringsm/api-keys/generate" method="POST" class="space-y-4 text-xs sm:text-sm max-h-[80vh] overflow-y-auto pr-1">
                @csrf

                <div>
                    <label class="block font-medium text-slate-300 mb-1">Nama Perusahaan (Opsional)</label>
                    <input type="text" name="company_name" placeholder="Contoh: PT Sakti Setia Santosa" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-slate-200 focus:outline-none focus:border-amber-500 transition">
                    <p class="text-[11px] text-slate-500 mt-1">Label grup perusahaan jika 1 key digunakan untuk banyak distributor.</p>
                </div>

                <div>
                    <label class="block font-medium text-slate-300 mb-1">Label / Nama Sistem Integrasi <span class="text-rose-400">*</span></label>
                    <input type="text" name="name" placeholder="Contoh: ERP Sakti Setia Santosa" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-slate-200 focus:outline-none focus:border-amber-500 transition">
                </div>

                <div>
                    <label class="block font-medium text-slate-300 mb-2">Pilih Distributor <span class="text-rose-400">*</span> <span class="text-slate-500 font-normal">(bisa lebih dari 1)</span></label>
                    <input type="text" id="distSearch" placeholder="🔍 Cari nama distributor..." oninput="filterDist(this.value)" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-slate-200 text-xs focus:outline-none focus:border-amber-500 transition mb-2">
                    <div id="distList" class="bg-slate-950 border border-slate-800 rounded-xl max-h-48 overflow-y-auto p-2 space-y-1">
                        @foreach($distributors as $d)
                            <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-800 cursor-pointer dist-item">
                                <input type="checkbox" name="distributor_ids[]" value="{{ $d->id }}" class="accent-amber-500">
                                <span class="text-slate-200">{{ $d->name }}</span>
                                <span class="text-slate-500 font-mono ml-auto text-[11px]">{{ $d->code_customer }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-slate-300 mb-1">Allowed IPs (Opsional)</label>
                    <input type="text" name="allowed_ips" placeholder="Contoh: 203.0.113.195, 198.51.100.22" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-slate-200 focus:outline-none focus:border-amber-500 transition">
                    <p class="text-[11px] text-slate-500 mt-1">Pisahkan dengan koma jika lebih dari 1 IP. Kosongkan jika IP distributor dinamis.</p>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('generateModal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-400 hover:to-amber-400 text-slate-950 rounded-xl text-xs font-bold shadow-md transition">
                        Generate Token
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function copyToClipboard() {
            const keyText = document.getElementById('rawApiKeyText').innerText.trim();
            navigator.clipboard.writeText(keyText).then(() => {
                const btn = document.getElementById('copyBtn');
                btn.innerHTML = '<span>✅ Copied!</span>';
                btn.classList.replace('bg-amber-500', 'bg-emerald-500');
                btn.classList.replace('hover:bg-amber-400', 'hover:bg-emerald-400');
                setTimeout(() => {
                    btn.innerHTML = '<span>📋 Copy Key</span>';
                    btn.classList.replace('bg-emerald-500', 'bg-amber-500');
                    btn.classList.replace('hover:bg-emerald-400', 'hover:bg-amber-400');
                }, 2500);
            }).catch(err => {
                alert('Gagal menyalin text: ' + err);
            });
        }

        function filterDist(query) {
            const items = document.querySelectorAll('.dist-item');
            const q = query.toLowerCase();
            items.forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(q) ? '' : 'none';
            });
        }

        // ── Dark / Light Mode Toggle ────────────────────────────────────
        function toggleTheme() {
            const html    = document.documentElement;
            const isDark  = html.classList.contains('dark');
            const newMode = isDark ? 'light' : 'dark';

            html.classList.toggle('dark',  newMode === 'dark');
            html.classList.toggle('light', newMode === 'light');
            localStorage.setItem('sm-theme', newMode);
            updateThemeUI(newMode);
        }

        function updateThemeUI(mode) {
            const icon  = document.getElementById('themeIcon');
            const label = document.getElementById('themeLabel');
            if (!icon || !label) return;
            if (mode === 'dark') {
                icon.textContent  = '🌙';
                label.textContent = 'Dark';
            } else {
                icon.textContent  = '☀️';
                label.textContent = 'Light';
            }
        }

        // Init UI labels on load
        document.addEventListener('DOMContentLoaded', function() {
            const saved = localStorage.getItem('sm-theme') || 'dark';
            document.documentElement.classList.toggle('light', saved === 'light');
            document.documentElement.classList.toggle('dark',  saved === 'dark');
            updateThemeUI(saved);
        });

        // ── Distributor Detail Slide-over ───────────────────────────────
        function openDistDetail(keyId, keyName, distributors) {
            const panel   = document.getElementById('distDetailPanel');
            const title   = document.getElementById('distDetailTitle');
            const badge   = document.getElementById('distDetailBadge');
            const list    = document.getElementById('distDetailList');
            const search  = document.getElementById('distDetailSearch');

            title.textContent  = keyName;
            badge.textContent  = distributors.length + ' Distributor';
            search.value       = '';
            list.innerHTML     = '';

            distributors.forEach(d => {
                const row = document.createElement('div');
                row.className = 'dist-detail-row flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-800 bg-slate-900/60 hover:bg-slate-800/60 transition';
                row.setAttribute('data-name', d.name.toLowerCase());
                row.innerHTML = `
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 font-bold text-xs shrink-0">
                        ${d.code.replace('C', '')}
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-slate-100 truncate">${d.name}</div>
                        <div class="text-xs font-mono text-slate-500 mt-0.5">${d.code}</div>
                    </div>
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
    </script>

    <!-- Distributor Detail Slide-over Panel -->
    <div id="distDetailOverlay" class="hidden fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm" onclick="closeDistDetail()"></div>

    <div id="distDetailPanel"
         class="fixed top-0 right-0 h-full w-full max-w-sm z-50 bg-slate-900 border-l border-slate-800 shadow-2xl flex flex-col
                transform translate-x-full opacity-0 pointer-events-none transition-all duration-300 ease-in-out">

        <!-- Panel Header -->
        <div class="px-5 py-4 border-b border-slate-800 flex items-start justify-between shrink-0">
            <div>
                <p class="text-xs text-slate-500 mb-1 font-medium uppercase tracking-wider">Daftar Distributor</p>
                <h3 id="distDetailTitle" class="text-base font-bold text-slate-100 leading-snug"></h3>
                <span id="distDetailBadge" class="inline-block mt-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-400 border border-amber-500/30"></span>
            </div>
            <button onclick="closeDistDetail()" class="text-slate-500 hover:text-slate-200 transition mt-0.5 p-1 rounded-lg hover:bg-slate-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Search -->
        <div class="px-5 py-3 border-b border-slate-800/60 shrink-0">
            <input
                id="distDetailSearch"
                type="text"
                placeholder="🔍 Cari nama distributor..."
                oninput="filterDistDetail(this.value)"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500 transition"
            >
        </div>

        <!-- List -->
        <div id="distDetailList" class="flex-1 overflow-y-auto p-4 space-y-2"></div>

        <!-- Footer -->
        <div class="px-5 py-4 border-t border-slate-800 shrink-0">
            <button onclick="closeDistDetail()" class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold transition">
                Tutup
            </button>
        </div>
    </div>

</body>
</html>
