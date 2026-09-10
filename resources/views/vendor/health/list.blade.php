<html lang="en" class="{{$theme == 'dark' ? 'dark' : ''}}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('health::notifications.health_results') }}</title>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Apply saved theme immediately to prevent flashing
        (function() {
            const savedTheme = localStorage.getItem('smesta_health_theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
            } else if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    {{$assets}}
</head>

<body class="antialiased bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-100 min-h-screen transition-colors duration-200">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        {{-- Top Navigation & Controls --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
            <div class="flex items-center gap-3">
                <a href="{{ url('/monitoringsm/hub') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 shadow-sm transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Admin Hub
                </a>
            </div>

            <div class="flex items-center gap-2.5">
                {{-- Light / Dark Mode Toggle Button --}}
                <button id="themeToggleBtn" type="button" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 shadow-sm transition" title="Ganti Mode Tampilan (Light / Dark)">
                    <span id="themeIconSun" class="hidden dark:inline">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-amber-400"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    </span>
                    <span id="themeIconMoon" class="inline dark:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-indigo-600"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                    </span>
                    <span id="themeLabelText" class="hidden sm:inline font-medium">Mode</span>
                </button>

                {{-- Refresh Button --}}
                <a href="{{ url('/monitoringsm/health?fresh') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-semibold text-white shadow-md shadow-indigo-500/20 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    Refresh Checks
                </a>
            </div>
        </div>

        {{-- Page Header --}}
        <div class="flex flex-col items-center justify-center space-y-3 mb-10 text-center">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                {{ __('health::notifications.laravel_health') }}
            </h1>
            <div class="flex justify-center">
                <x-health-logo/>
            </div>
            @if ($lastRanAt)
                <div class="{{ $lastRanAt->diffInMinutes() > 5 ? 'text-rose-500 font-semibold' : 'text-gray-500 dark:text-gray-400' }} text-sm font-medium flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full {{ $lastRanAt->diffInMinutes() > 5 ? 'bg-rose-500 animate-ping' : 'bg-emerald-500' }}"></span>
                    {{ __('health::notifications.check_results_from') }} {{ $lastRanAt->diffForHumans() }}
                </div>
            @endif
        </div>

        @php
            $partitions = \App\Services\ServerStorageService::getStorageBreakdown(['/', '/home', '/tmp']);
        @endphp

        {{-- Section 1: Spacious Partition Storage Breakdown & Dual Charts --}}
        <section class="mb-12">
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 lg:p-10 shadow-lg shadow-gray-200/50 dark:shadow-black/30 border border-gray-200/80 dark:border-gray-700/80">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-6 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                            <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                            </span>
                            Server Storage & Partition Analytics
                        </h2>
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Pemantauan visual ruang penyimpanan sistem operasi, direktori user cPanel, dan berkas temporary
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-3.5 py-1 text-xs font-bold rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800/80">
                            3 Partisi (/ , /home , /tmp)
                        </span>
                    </div>
                </div>

                {{-- Partition Cards Grid (Spacious 3 Columns with Dual Charts per Card) --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 py-8 border-b border-gray-200 dark:border-gray-700">
                    @foreach ($partitions as $idx => $p)
                        <div class="flex flex-col justify-between p-6 sm:p-7 rounded-2xl bg-gray-50/70 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 hover:border-indigo-300 dark:hover:border-indigo-500/40 hover:shadow-md transition-all">
                            {{-- Card Header --}}
                            <div>
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full {{ $p['used_percent'] >= 90 ? 'bg-rose-500' : ($p['used_percent'] >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                                            <h3 class="font-extrabold text-base text-gray-900 dark:text-white">
                                                {{ $p['name'] }}
                                            </h3>
                                        </div>
                                        <span class="text-xs font-mono text-gray-400 dark:text-gray-500 pl-4.5">Mount: {{ $p['path'] }}</span>
                                    </div>
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wider {{ $p['used_percent'] >= 90 ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800' : ($p['used_percent'] >= 80 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800') }}">
                                        {{ $p['status'] }}
                                    </span>
                                </div>

                                {{-- Primary Chart: Circular Pie/Donut Chart with Generous Breathing Space --}}
                                <div class="py-4 my-2 flex flex-col items-center justify-center">
                                    <div class="relative w-52 h-52 sm:w-56 sm:h-56 flex items-center justify-center">
                                        <canvas id="donut-partition-{{ $idx }}"></canvas>
                                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                            <span class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">
                                                {{ $p['used_percent'] }}%
                                            </span>
                                            <span class="text-[11px] uppercase font-bold text-gray-400 dark:text-gray-400 tracking-wider mt-0.5">
                                                Terpakai
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Secondary Chart: Linear Allocation Bar Chart per Partition --}}
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800/80">
                                    <div class="flex items-center justify-between text-xs font-semibold mb-2">
                                        <span class="text-gray-600 dark:text-gray-400">Distribusi Alokasi</span>
                                        <span class="font-mono text-gray-500 dark:text-gray-400">Total: {{ $p['total_formatted'] }}</span>
                                    </div>
                                    <div class="h-6 w-full relative">
                                        <canvas id="bar-partition-{{ $idx }}"></canvas>
                                    </div>
                                </div>
                            </div>

                            {{-- Detailed Metric Badges Grid --}}
                            <div class="mt-6 pt-5 border-t border-gray-200 dark:border-gray-800/80">
                                <div class="grid grid-cols-2 gap-2.5 text-xs">
                                    <div class="p-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80">
                                        <div class="text-gray-400 text-[10px] font-bold uppercase tracking-wider">Terpakai</div>
                                        <div class="font-extrabold text-sm text-gray-900 dark:text-white mt-0.5">{{ $p['used_formatted'] }}</div>
                                        <div class="text-[11px] font-mono text-gray-500 dark:text-gray-400">{{ $p['used_percent'] }}% dari total</div>
                                    </div>
                                    <div class="p-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80">
                                        <div class="text-gray-400 text-[10px] font-bold uppercase tracking-wider">Sisa Ruang</div>
                                        <div class="font-extrabold text-sm text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $p['free_formatted'] }}</div>
                                        <div class="text-[11px] font-mono text-gray-500 dark:text-gray-400">{{ $p['free_percent'] }}% tersisa</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Table: Storage, Total, Terpakai, Sisa, Status --}}
                <div class="mt-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Rincian Lengkap Kapasitas Partisi</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Batas Aman: &lt; 80% | Warning: 80% | Kritis: 90%</span>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-left text-xs sm:text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr class="text-gray-500 dark:text-gray-400 uppercase text-[11px] tracking-wider border-b border-gray-200 dark:border-gray-700">
                                    <th class="py-3.5 px-4 font-bold">Storage</th>
                                    <th class="py-3.5 px-4 font-bold">Total</th>
                                    <th class="py-3.5 px-4 font-bold">Terpakai</th>
                                    <th class="py-3.5 px-4 font-bold">Sisa</th>
                                    <th class="py-3.5 px-4 font-bold text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-800">
                                @foreach ($partitions as $p)
                                    <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition">
                                        <td class="py-4 px-4 font-semibold text-gray-900 dark:text-white flex items-center gap-3">
                                            <div class="p-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"></rect><rect width="20" height="8" x="2" y="14" rx="2" ry="2"></rect><line x1="6" x2="6.01" y1="6" y2="6"></line><line x1="6" x2="6.01" y1="18" y2="18"></line></svg>
                                            </div>
                                            <div>
                                                <div class="font-bold">{{ $p['name'] }}</div>
                                                <div class="text-xs font-mono text-gray-400 dark:text-gray-500">{{ $p['path'] }}</div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4 font-mono font-bold text-gray-800 dark:text-gray-200">
                                            {{ $p['total_formatted'] }}
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-extrabold text-gray-900 dark:text-white">{{ $p['used_formatted'] }}</span>
                                                <span class="text-xs font-mono font-bold px-1.5 py-0.5 rounded {{ $p['used_percent'] >= 90 ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400' : ($p['used_percent'] >= 80 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400') }}">
                                                    {{ $p['used_percent'] }}%
                                                </span>
                                            </div>
                                            <div class="w-32 sm:w-44 h-2 bg-gray-200 dark:bg-gray-700 rounded-full mt-2 overflow-hidden">
                                                <div class="h-full rounded-full {{ $p['used_percent'] >= 90 ? 'bg-rose-500' : ($p['used_percent'] >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $p['used_percent'] }}%"></div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $p['free_formatted'] }}</div>
                                            <div class="text-xs text-gray-400 font-mono">{{ $p['free_percent'] }}% ruang bebas</div>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            @if ($p['used_percent'] >= 90)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                                    Critical
                                                </span>
                                            @elseif ($p['used_percent'] >= 80)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                    Warning
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                    Normal
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        {{-- Section 2: Spatie Health Check Cards --}}
        <section>
            <div class="flex items-center justify-between mb-5 px-1">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    System & Service Health Status
                </h2>
                <span class="text-xs text-gray-400 font-medium">
                    Total: {{ count($checkResults?->storedCheckResults ?? []) }} Checks
                </span>
            </div>

            @if (count($checkResults?->storedCheckResults ?? []))
                <dl class="grid grid-cols-1 gap-4 sm:gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($checkResults->storedCheckResults as $result)
                        <div class="flex items-start p-5 rounded-2xl bg-white dark:bg-gray-800 shadow-md shadow-gray-200/50 dark:shadow-black/25 border border-gray-200/80 dark:border-gray-700/80 space-x-3.5 hover:shadow-lg transition-shadow">
                            <x-health-status-indicator :result="$result" />
                            <div class="min-w-0 flex-1">
                                <dd class="font-bold text-gray-900 dark:text-white text-base md:text-lg truncate">
                                    {{ $result->label }}
                                </dd>
                                <dt class="mt-1 text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-300 break-words">
                                    @if (!empty($result->notificationMessage))
                                        {{ $result->notificationMessage }}
                                    @else
                                        {{ $result->shortSummary }}
                                    @endif
                                </dt>
                            </div>
                        </div>
                    @endforeach
                </dl>
            @else
                <div class="max-w-md mx-auto my-12 p-8 text-center bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-3xl shadow-sm">
                    <div class="inline-flex items-center justify-center w-12 h-12 mb-4 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2">No Health Checks Recorded Yet</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-6 leading-relaxed">
                        Health metrics have not been run on this server yet. Click the button below to run all registered system checks immediately.
                    </p>
                    <a href="{{ url('/monitoringsm/health?fresh') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-bold text-white shadow-md shadow-indigo-500/20 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        Run Health Checks Now
                    </a>
                </div>
            @endif
        </section>
    </div>

    {{-- Chart.js Rendering & Theme Switcher Script --}}
    <script>
        const partitionsData = @json($partitions);
        const chartInstances = [];

        function getThemeColors() {
            const isDark = document.documentElement.classList.contains('dark');
            return {
                isDark: isDark,
                freeBg: isDark ? '#334155' : '#e2e8f0', // slate-700 / slate-200
                textMuted: isDark ? '#94a3b8' : '#64748b'
            };
        }

        function renderAllCharts() {
            // Destroy existing charts before re-rendering on theme change
            chartInstances.forEach(c => c.destroy());
            chartInstances.length = 0;

            const theme = getThemeColors();

            partitionsData.forEach((p, idx) => {
                let usedColor = '#10b981'; // emerald-500
                if (p.used_percent >= 90) {
                    usedColor = '#f43f5e'; // rose-500
                } else if (p.used_percent >= 80) {
                    usedColor = '#f59e0b'; // amber-500
                }

                // 1. Primary Donut / Pie Chart
                const donutCanvas = document.getElementById(`donut-partition-${idx}`);
                if (donutCanvas) {
                    const donutChart = new Chart(donutCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: ['Terpakai (' + p.used_formatted + ')', 'Sisa (' + p.free_formatted + ')'],
                            datasets: [{
                                data: [p.used_bytes, p.free_bytes],
                                backgroundColor: [usedColor, theme.freeBg],
                                borderWidth: 0,
                                hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            cutout: '74%',
                            animation: { duration: 600 },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            const label = context.label || '';
                                            const percent = context.dataIndex === 0 ? p.used_percent : p.free_percent;
                                            return ` ${label} : ${percent}%`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                    chartInstances.push(donutChart);
                }

                // 2. Secondary Horizontal Distribution Bar Chart
                const barCanvas = document.getElementById(`bar-partition-${idx}`);
                if (barCanvas) {
                    const barChart = new Chart(barCanvas, {
                        type: 'bar',
                        data: {
                            labels: ['Kapasitas'],
                            datasets: [
                                {
                                    label: 'Terpakai (' + p.used_formatted + ' - ' + p.used_percent + '%)',
                                    data: [p.used_bytes],
                                    backgroundColor: usedColor,
                                    borderRadius: { topLeft: 6, bottomLeft: 6, topRight: 0, bottomRight: 0 },
                                    barThickness: 14
                                },
                                {
                                    label: 'Sisa (' + p.free_formatted + ' - ' + p.free_percent + '%)',
                                    data: [p.free_bytes],
                                    backgroundColor: theme.freeBg,
                                    borderRadius: { topLeft: 0, bottomLeft: 0, topRight: 6, bottomRight: 6 },
                                    barThickness: 14
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: { stacked: true, display: false },
                                y: { stacked: true, display: false }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            return ' ' + context.dataset.label;
                                        }
                                    }
                                }
                            }
                        }
                    });
                    chartInstances.push(barChart);
                }
            });
        }

        // Theme Toggle Handler
        document.addEventListener('DOMContentLoaded', function () {
            renderAllCharts();

            const themeToggleBtn = document.getElementById('themeToggleBtn');
            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function () {
                    const isDark = document.documentElement.classList.contains('dark');
                    if (isDark) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('smesta_health_theme', 'light');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('smesta_health_theme', 'dark');
                    }
                    renderAllCharts();
                });
            }
        });
    </script>
</body>
</html>
