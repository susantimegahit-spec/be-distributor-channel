<html lang="en" class="{{$theme == 'dark' ? 'dark' : ''}}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('health::notifications.health_results') }}</title>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    {{$assets}}
</head>

<body class="antialiased bg-gray-100 mt-7 md:mt-12 dark:bg-gray-900 text-gray-800 dark:text-gray-100">
    <div class="mx-auto max-w-7xl lg:px-8 sm:px-6">
        {{-- Navigation Header --}}
        <div class="flex items-center justify-between mb-6 px-4">
            <a href="{{ url('/monitoringsm/hub') }}" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-300 transition">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Admin Hub
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ url('/monitoringsm/health?fresh') }}" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-xs font-semibold text-white shadow-sm transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    Refresh Checks
                </a>
            </div>
        </div>

        <div class="flex flex-wrap justify-center space-y-3">
            <h4 class="w-full text-2xl font-bold text-center text-gray-900 dark:text-white">{{ __('health::notifications.laravel_health') }}</h4>
            <div class="flex justify-center w-full">
                <x-health-logo/>
            </div>
            @if ($lastRanAt)
                <div class="{{ $lastRanAt->diffInMinutes() > 5 ? 'text-red-400' : 'text-gray-400 dark:text-gray-500' }} text-sm text-center font-medium">
                    {{ __('health::notifications.check_results_from') }} {{ $lastRanAt->diffForHumans() }}
                </div>
            @endif
        </div>

        @php
            $partitions = \App\Services\ServerStorageService::getStorageBreakdown(['/', '/home', '/tmp']);
        @endphp

        {{-- Disk Storage & Partition Pie Charts Section --}}
        <div class="px-2 my-8 md:px-0">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 sm:p-6 shadow-md shadow-gray-200/50 dark:shadow-black/25 border border-gray-200/80 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-5 border-b border-gray-200 dark:border-gray-700/60">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                            Server Storage & Partition Analytics
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Real-time disk space distribution for root (/), user home (/home), and temp (/tmp)</p>
                    </div>
                    <span class="self-start sm:self-center px-3 py-1 text-xs font-semibold rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                        3 Partitions Monitored
                    </span>
                </div>

                {{-- Pie / Donut Charts Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 py-6 border-b border-gray-200 dark:border-gray-700/60">
                    @foreach ($partitions as $idx => $p)
                        <div class="flex flex-col items-center p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200/70 dark:border-gray-800">
                            <div class="flex items-center justify-between w-full mb-3">
                                <span class="font-bold text-sm text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                                    <span class="inline-block w-2 h-2 rounded-full {{ $p['used_percent'] >= 90 ? 'bg-rose-500' : ($p['used_percent'] >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                                    {{ $p['name'] }}
                                </span>
                                <span class="text-xs font-mono font-bold px-2 py-0.5 rounded {{ $p['used_percent'] >= 90 ? 'bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400' : ($p['used_percent'] >= 80 ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400' : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400') }}">
                                    {{ $p['used_percent'] }}%
                                </span>
                            </div>

                            <div class="relative w-44 h-44 flex items-center justify-center my-2">
                                <canvas id="chart-partition-{{ $idx }}"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <span class="text-xl font-extrabold text-gray-900 dark:text-white">{{ $p['used_percent'] }}%</span>
                                    <span class="text-[10px] uppercase font-semibold text-gray-400 tracking-wider">Terpakai</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-center gap-4 text-xs mt-3 w-full pt-2 border-t border-gray-200/60 dark:border-gray-800 text-gray-600 dark:text-gray-400">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $p['used_percent'] >= 90 ? 'bg-rose-500' : ($p['used_percent'] >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                                    <span>Pakai: <strong>{{ $p['used_formatted'] }}</strong></span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                                    <span>Sisa: <strong>{{ $p['free_formatted'] }}</strong></span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Detailed Storage Breakdown Table --}}
                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 uppercase text-[11px] tracking-wider">
                                <th class="py-3 px-3">Storage</th>
                                <th class="py-3 px-3">Total</th>
                                <th class="py-3 px-3">Terpakai</th>
                                <th class="py-3 px-3">Sisa</th>
                                <th class="py-3 px-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($partitions as $p)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40 transition">
                                    <td class="py-3.5 px-3 font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                        <div class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"></rect><rect width="20" height="8" x="2" y="14" rx="2" ry="2"></rect><line x1="6" x2="6.01" y1="6" y2="6"></line><line x1="6" x2="6.01" y1="18" y2="18"></line></svg>
                                        </div>
                                        <div>
                                            <div class="font-bold">{{ $p['name'] }}</div>
                                            <div class="text-[11px] font-mono text-gray-400">{{ $p['path'] }}</div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-3 font-mono font-semibold text-gray-700 dark:text-gray-300">
                                        {{ $p['total_formatted'] }}
                                    </td>
                                    <td class="py-3.5 px-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ $p['used_formatted'] }}</span>
                                            <span class="text-xs text-gray-400 font-mono">({{ $p['used_percent'] }}%)</span>
                                        </div>
                                        <div class="w-28 sm:w-36 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full mt-1.5 overflow-hidden">
                                            <div class="h-full rounded-full {{ $p['used_percent'] >= 90 ? 'bg-rose-500' : ($p['used_percent'] >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $p['used_percent'] }}%"></div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-3">
                                        <div class="font-mono font-semibold text-gray-900 dark:text-white">{{ $p['free_formatted'] }}</div>
                                        <div class="text-xs text-gray-400 font-mono">({{ $p['free_percent'] }}%)</div>
                                    </td>
                                    <td class="py-3.5 px-3 text-center">
                                        @if ($p['used_percent'] >= 90)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800/60">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                                Critical
                                            </span>
                                        @elseif ($p['used_percent'] >= 80)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                Warning
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
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

        {{-- Spatie Health Check Cards --}}
        <div class="px-2 my-6 md:mt-8 md:px-0">
            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4 px-1">System & Service Health Status</h3>
            @if (count($checkResults?->storedCheckResults ?? []))
                <dl class=" grid grid-cols-1 gap-2.5 sm:gap-3 md:gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($checkResults->storedCheckResults as $result)
                        <div class="flex items-start px-4 space-x-2 overflow-hidden py-5 text-opacity-0 transition transform bg-white shadow-md shadow-gray-200 dark:shadow-black/25 dark:shadow-md dark:bg-gray-800 rounded-xl sm:p-6 md:space-x-3 md:min-h-[130px] dark:border-t dark:border-gray-700">
                            <x-health-status-indicator :result="$result" />
                            <div>
                                <dd class="-mt-1 font-bold text-gray-900 dark:text-white md:mt-1 md:text-xl">
                                    {{ $result->label }}
                                </dd>
                                <dt class="mt-0 text-sm font-medium text-gray-600 dark:text-gray-300 md:mt-1">
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
                <div class="max-w-md mx-auto my-12 p-8 text-center bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm">
                    <div class="inline-flex items-center justify-center w-12 h-12 mb-4 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2">No Health Checks Recorded Yet</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-6 leading-relaxed">
                        Health metrics have not been run on this server yet. Click the button below to run all registered system checks immediately.
                    </p>
                    <a href="{{ url('/monitoringsm/health?fresh') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-semibold text-white shadow-md shadow-indigo-500/20 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        Run Health Checks Now
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Render Chart.js Pie/Donut Charts --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const partitionsData = @json($partitions);
            const isDark = document.documentElement.classList.contains('dark');
            const freeBgColor = isDark ? '#334155' : '#e2e8f0'; // slate-700 / slate-200

            partitionsData.forEach((p, idx) => {
                const canvas = document.getElementById(`chart-partition-${idx}`);
                if (!canvas) return;

                let usedColor = '#10b981'; // emerald-500
                if (p.used_percent >= 90) {
                    usedColor = '#f43f5e'; // rose-500
                } else if (p.used_percent >= 80) {
                    usedColor = '#f59e0b'; // amber-500
                }

                new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Terpakai (' + p.used_formatted + ')', 'Sisa (' + p.free_formatted + ')'],
                        datasets: [{
                            data: [p.used_bytes, p.free_bytes],
                            backgroundColor: [usedColor, freeBgColor],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutout: '76%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const percent = context.dataIndex === 0 ? p.used_percent : p.free_percent;
                                        return ` ${label} : ${percent}%`;
                                    }
                                }
                            }
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
