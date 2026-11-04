<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClickUp Task Reporting Dashboard - PT Susanti Megah</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        slate: {
                            850: '#0f172a',
                            900: '#0b1120',
                            950: '#060913',
                        },
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Theme State Initializer (Prevent FOUC) -->
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>
    
    <!-- ApexCharts CDN -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        * { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        .dark ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        
        /* Glass Panel */
        .glass-panel {
            transition: background-color 0.2s, border-color 0.2s, box-shadow 0.2s;
        }
        .dark .glass-panel {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.07);
        }
        .glass-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }

        /* Interactive KPI Cards with Smooth 3D Elevation */
        .kpi-card {
            transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.28s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease, background-color 0.2s ease;
            cursor: pointer;
            user-select: none;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }
        .kpi-card:hover {
            transform: translateY(-8px) scale(1.025);
            box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.14), 0 10px 18px -6px rgba(0, 0, 0, 0.08);
            z-index: 20;
        }
        .dark .kpi-card:hover {
            transform: translateY(-8px) scale(1.025);
            box-shadow: 0 22px 38px -8px rgba(0, 0, 0, 0.7), 0 0 24px 2px rgba(99, 102, 241, 0.2);
            z-index: 20;
        }
        .kpi-card:active {
            transform: translateY(-2px) scale(0.99);
        }

        /* Glow Accents */
        .dark .glow-indigo { box-shadow: 0 0 25px -5px rgba(99, 102, 241, 0.15); }
        .dark .glow-sky { box-shadow: 0 0 25px -5px rgba(14, 165, 233, 0.15); }
        .dark .glow-emerald { box-shadow: 0 0 25px -5px rgba(16, 185, 129, 0.15); }
        .dark .glow-rose { box-shadow: 0 0 25px -5px rgba(244, 63, 94, 0.15); }
        .dark .glow-amber { box-shadow: 0 0 25px -5px rgba(245, 158, 11, 0.15); }
        .glow-indigo { box-shadow: 0 4px 14px rgba(99, 102, 241, 0.08); }
        .glow-sky { box-shadow: 0 4px 14px rgba(14, 165, 233, 0.08); }
        .glow-emerald { box-shadow: 0 4px 14px rgba(16, 185, 129, 0.08); }
        .glow-rose { box-shadow: 0 4px 14px rgba(244, 63, 94, 0.08); }
        .glow-amber { box-shadow: 0 4px 14px rgba(245, 158, 11, 0.08); }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 min-h-screen antialiased flex flex-col selection:bg-indigo-500 selection:text-white transition-colors duration-200">

    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-30 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800/80 px-4 sm:px-6 lg:px-8 py-3.5 transition-colors">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            
            <!-- Branding & Title -->
            <div class="flex items-center gap-3.5">
                <a href="{{ url('/monitoringsm/hub') }}" class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-600 font-extrabold text-white text-base shadow-lg shadow-amber-500/20 hover:scale-105 transition-transform" title="Kembali ke Admin Hub">
                    SM
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                            ClickUp Task Reporting
                        </h1>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20">
                            Live Sync
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Monitoring & visualisasi task terpadu tim PT Susanti Megah</p>
                </div>
            </div>

            <!-- Header Quick Actions -->
            <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                
                <!-- Last Synced At Status -->
                <div class="text-right hidden md:block">
                    <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Terakhir Disinkronkan:</span>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ $lastSyncedAt ? $lastSyncedAt->format('d M Y, H:i') . ' WIB' : 'Belum pernah' }}
                    </span>
                </div>

                <!-- Theme Switcher Button -->
                <button type="button" id="theme-toggle" class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition" title="Ganti Mode Tampilan (Light / Dark)">
                    <!-- Sun Icon (for dark mode to switch to light) -->
                    <svg id="theme-toggle-light-icon" class="w-4 h-4 hidden" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path>
                    </svg>
                    <!-- Moon Icon (for light mode to switch to dark) -->
                    <svg id="theme-toggle-dark-icon" class="w-4 h-4 hidden" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                    </svg>
                </button>

                <!-- Refresh Button -->
                <a href="{{ request()->fullUrl() }}" class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition" title="Muat Ulang Data">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </a>

                <!-- Hub Navigation Button -->
                <a href="{{ url('/monitoringsm/hub') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-900 dark:bg-slate-800 text-xs font-semibold text-white hover:bg-slate-800 dark:hover:bg-slate-700 transition">
                    <span>Admin Hub</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- ========================================== -->
        <!-- 1. FILTER & SEARCH CONTROLS               -->
        <!-- ========================================== -->
        <section class="glass-panel rounded-2xl p-5 shadow-sm dark:shadow-xl">
            <form id="filter-form" action="{{ url('/reporting/tasks') }}" method="GET" class="space-y-4">
                
                @if(!empty($quickFilter))
                    <input type="hidden" name="quick_filter" value="{{ $quickFilter }}">
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                    
                    <!-- Search Input -->
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Pencarian Bebas (Task / PIC / Komentar)</label>
                        <div class="relative">
                            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama task, task ID ClickUp (#z8mx...), assignee..." class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500 transition">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Space Filter -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Space</label>
                        <select name="space_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">Semua Space</option>
                            @foreach($filterOptions['spaces'] as $space)
                                <option value="{{ $space->space_id }}" {{ ($filters['space_id'] ?? '') == $space->space_id ? 'selected' : '' }}>
                                    {{ $space->space_name ?: 'Space #' . $space->space_id }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Folder Filter -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Folder</label>
                        <select name="folder_name" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">Semua Folder</option>
                            @foreach($filterOptions['folders'] as $folder)
                                <option value="{{ $folder }}" {{ ($filters['folder_name'] ?? '') == $folder ? 'selected' : '' }}>
                                    {{ $folder }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- List Filter -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">List</label>
                        <select name="list_name" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">Semua List</option>
                            @foreach($filterOptions['lists'] as $list)
                                <option value="{{ $list }}" {{ ($filters['list_name'] ?? '') == $list ? 'selected' : '' }}>
                                    {{ $list }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Assignee Filter -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Assignee (PIC)</label>
                        <select name="assignee" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">Semua Assignee</option>
                            @foreach($filterOptions['assignees'] as $assignee)
                                <option value="{{ $assignee }}" {{ ($filters['assignee'] ?? '') == $assignee ? 'selected' : '' }}>
                                    {{ $assignee }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">Semua Status</option>
                            @foreach($filterOptions['statuses'] as $status)
                                <option value="{{ $status }}" {{ ($filters['status'] ?? '') == $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Priority Filter -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">Semua Priority</option>
                            @foreach($filterOptions['priorities'] as $priority)
                                <option value="{{ $priority }}" {{ ($filters['priority'] ?? '') == $priority ? 'selected' : '' }}>
                                    {{ ucfirst($priority) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Start Date Range -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Start Date Dari</label>
                        <input type="date" name="start_date_from" value="{{ $filters['start_date_from'] ?? '' }}" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                    </div>

                    <!-- Due Date Range -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Due Date Sampai</label>
                        <input type="date" name="due_date_to" value="{{ $filters['due_date_to'] ?? '' }}" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-200 dark:border-slate-800/80">
                    <a href="{{ url('/reporting/tasks') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-medium text-slate-700 dark:text-slate-300 transition">
                        Reset Filter
                    </a>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white shadow-lg shadow-indigo-600/20 transition active:scale-95">
                        Terapkan Filter
                    </button>
                </div>
            </form>
        </section>

        <!-- ========================================== -->
        <!-- 2. KPI CARDS (INTERACTIVE & CLICKABLE)     -->
        <!-- ========================================== -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            
            <!-- Total Tasks (Click to view all tasks) -->
            @php
                $isTotalActive = empty($quickFilter);
                $totalUrl = request()->fullUrlWithQuery(['quick_filter' => null, 'page' => null]) . '#tasks-table';
            @endphp
            <a href="{{ $totalUrl }}" class="glass-panel glow-indigo rounded-2xl p-4 sm:p-5 kpi-card group {{ $isTotalActive ? 'ring-2 ring-indigo-500 border-indigo-500 bg-indigo-50/60 dark:bg-indigo-950/30' : '' }}" title="Klik untuk menampilkan semua task">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold {{ $isTotalActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-600 dark:text-slate-400' }}">Total Tasks</span>
                    <span class="p-2 rounded-xl {{ $isTotalActive ? 'bg-indigo-500 text-white shadow-md shadow-indigo-500/30' : 'bg-indigo-100 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' }} transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ number_format($kpis['total']) }}</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 flex items-center justify-between">
                        <span>Completion Rate:</span>
                        <strong class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ $kpis['completion_rate'] }}%</strong>
                    </div>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-[10px] text-slate-400 group-hover:text-indigo-500 dark:group-hover:text-indigo-400 font-medium transition-colors">
                    <span>{{ $isTotalActive ? '✓ Menampilkan Semua' : 'Klik untuk tampilkan semua' }}</span>
                    <span>→</span>
                </div>
            </a>

            <!-- In Progress -->
            @php
                $isInProgressActive = ($quickFilter === 'in_progress');
                $inProgressUrl = request()->fullUrlWithQuery(['quick_filter' => ($isInProgressActive ? null : 'in_progress'), 'page' => null]) . '#tasks-table';
            @endphp
            <a href="{{ $inProgressUrl }}" class="glass-panel glow-sky rounded-2xl p-4 sm:p-5 kpi-card group {{ $isInProgressActive ? 'ring-2 ring-sky-500 border-sky-500 bg-sky-50/70 dark:bg-sky-950/40 shadow-lg shadow-sky-500/20 -translate-y-1' : '' }}" title="Klik untuk filter task In Progress">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold {{ $isInProgressActive ? 'text-sky-600 dark:text-sky-400' : 'text-slate-600 dark:text-slate-400' }}">In Progress</span>
                    <span class="p-2 rounded-xl {{ $isInProgressActive ? 'bg-sky-500 text-white shadow-md shadow-sky-500/30' : 'bg-sky-100 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400' }} transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-sky-600 dark:text-sky-400 tracking-tight">{{ number_format($kpis['in_progress']) }}</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Sedang dikerjakan tim</div>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-[10px] text-slate-400 group-hover:text-sky-500 dark:group-hover:text-sky-400 font-medium transition-colors">
                    <span>{{ $isInProgressActive ? '✓ Filter Aktif (Klik utk lepas)' : 'Klik untuk filter data' }}</span>
                    <span>→</span>
                </div>
            </a>

            <!-- Completed -->
            @php
                $isCompletedActive = ($quickFilter === 'completed');
                $completedUrl = request()->fullUrlWithQuery(['quick_filter' => ($isCompletedActive ? null : 'completed'), 'page' => null]) . '#tasks-table';
            @endphp
            <a href="{{ $completedUrl }}" class="glass-panel glow-emerald rounded-2xl p-4 sm:p-5 kpi-card group {{ $isCompletedActive ? 'ring-2 ring-emerald-500 border-emerald-500 bg-emerald-50/70 dark:bg-emerald-950/40 shadow-lg shadow-emerald-500/20 -translate-y-1' : '' }}" title="Klik untuk filter task Selesai">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold {{ $isCompletedActive ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400' }}">Completed</span>
                    <span class="p-2 rounded-xl {{ $isCompletedActive ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/30' : 'bg-emerald-100 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' }} transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 tracking-tight">{{ number_format($kpis['completed']) }}</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Telah selesai / resolved</div>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-[10px] text-slate-400 group-hover:text-emerald-500 dark:group-hover:text-emerald-400 font-medium transition-colors">
                    <span>{{ $isCompletedActive ? '✓ Filter Aktif (Klik utk lepas)' : 'Klik untuk filter data' }}</span>
                    <span>→</span>
                </div>
            </a>

            <!-- Overdue -->
            @php
                $isOverdueActive = ($quickFilter === 'overdue');
                $overdueUrl = request()->fullUrlWithQuery(['quick_filter' => ($isOverdueActive ? null : 'overdue'), 'page' => null]) . '#tasks-table';
            @endphp
            <a href="{{ $overdueUrl }}" class="glass-panel glow-rose rounded-2xl p-4 sm:p-5 kpi-card group {{ $isOverdueActive ? 'ring-2 ring-rose-500 border-rose-500 bg-rose-50/70 dark:bg-rose-950/40 shadow-lg shadow-rose-500/20 -translate-y-1' : '' }}" title="Klik untuk filter task Terlambat">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold {{ $isOverdueActive ? 'text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-400' }}">Overdue</span>
                    <span class="p-2 rounded-xl {{ $isOverdueActive ? 'bg-rose-500 text-white shadow-md shadow-rose-500/30' : 'bg-rose-100 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400' }} transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-rose-600 dark:text-rose-400 tracking-tight">{{ number_format($kpis['overdue']) }}</div>
                    <div class="text-[11px] text-rose-600 dark:text-rose-400 mt-1 font-medium">Melewati deadline</div>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-[10px] text-slate-400 group-hover:text-rose-500 dark:group-hover:text-rose-400 font-medium transition-colors">
                    <span>{{ $isOverdueActive ? '✓ Filter Aktif (Klik utk lepas)' : 'Klik untuk filter data' }}</span>
                    <span>→</span>
                </div>
            </a>

            <!-- Due Soon -->
            @php
                $isDueSoonActive = ($quickFilter === 'due_soon');
                $dueSoonUrl = request()->fullUrlWithQuery(['quick_filter' => ($isDueSoonActive ? null : 'due_soon'), 'page' => null]) . '#tasks-table';
            @endphp
            <a href="{{ $dueSoonUrl }}" class="glass-panel glow-amber rounded-2xl p-4 sm:p-5 kpi-card group col-span-2 sm:col-span-1 {{ $isDueSoonActive ? 'ring-2 ring-amber-500 border-amber-500 bg-amber-50/70 dark:bg-amber-950/40 shadow-lg shadow-amber-500/20 -translate-y-1' : '' }}" title="Klik untuk filter task Jatuh Tempo 7 Hari">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold {{ $isDueSoonActive ? 'text-amber-600 dark:text-amber-400' : 'text-slate-600 dark:text-slate-400' }}">Due Soon</span>
                    <span class="p-2 rounded-xl {{ $isDueSoonActive ? 'bg-amber-500 text-white shadow-md shadow-amber-500/30' : 'bg-amber-100 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400' }} transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-amber-600 dark:text-amber-400 tracking-tight">{{ number_format($kpis['due_soon']) }}</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Jatuh tempo 7 hari ke depan</div>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-[10px] text-slate-400 group-hover:text-amber-500 dark:group-hover:text-amber-400 font-medium transition-colors">
                    <span>{{ $isDueSoonActive ? '✓ Filter Aktif (Klik utk lepas)' : 'Klik untuk filter data' }}</span>
                    <span>→</span>
                </div>
            </a>
        </section>

        <!-- ========================================== -->
        <!-- 3. INTERACTIVE CHARTS                      -->
        <!-- ========================================== -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Chart 1: Status Distribution -->
            <div class="glass-panel rounded-2xl p-5 shadow-sm dark:shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-4">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                        Distribusi Status Task
                    </h3>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Berdasarkan data tersaring</span>
                </div>
                <div id="chart-status" class="min-h-[280px]"></div>
            </div>

            <!-- Chart 2: Top Assignees -->
            <div class="glass-panel rounded-2xl p-5 shadow-sm dark:shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-4">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span>
                        Task Berdasarkan Assignee (Top PIC)
                    </h3>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Jumlah task per personil</span>
                </div>
                <div id="chart-assignee" class="min-h-[280px]"></div>
            </div>

            <!-- Chart 3: Tasks by List / Folder -->
            <div class="glass-panel rounded-2xl p-5 shadow-sm dark:shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-4">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        Task Berdasarkan List / Modul
                    </h3>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Distribusi modul ClickUp</span>
                </div>
                <div id="chart-list" class="min-h-[280px]"></div>
            </div>

            <!-- Chart 4: Task Priority & Timeline -->
            <div class="glass-panel rounded-2xl p-5 shadow-sm dark:shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-4">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        Tingkat Prioritas (Priority Breakdown)
                    </h3>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Urgent / High / Normal / Low</span>
                </div>
                <div id="chart-priority" class="min-h-[280px]"></div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 4. TASK DATA TABLE                         -->
        <!-- ========================================== -->
        <section id="tasks-table" class="glass-panel rounded-2xl shadow-sm dark:shadow-xl overflow-hidden scroll-mt-24">
            
            <!-- Active Quick Filter Notification Banner -->
            @if(!empty($quickFilter))
                <div class="px-5 py-3 bg-gradient-to-r from-indigo-500/10 via-sky-500/10 to-indigo-500/10 dark:from-indigo-950/40 dark:via-sky-950/30 dark:to-indigo-950/40 border-b border-indigo-200 dark:border-indigo-800/60 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-bold text-indigo-700 dark:text-indigo-300">
                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/>
                            </svg>
                            Filter Cepat Aktif:
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-bold text-xs bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-700/80 text-slate-900 dark:text-white shadow-sm">
                            @if($quickFilter === 'in_progress')
                                <span class="w-2 h-2 rounded-full bg-sky-500"></span> In Progress ({{ $kpis['in_progress'] }} task)
                            @elseif($quickFilter === 'completed')
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Completed ({{ $kpis['completed'] }} task)
                            @elseif($quickFilter === 'overdue')
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span> Overdue ({{ $kpis['overdue'] }} task)
                            @elseif($quickFilter === 'due_soon')
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span> Due Soon ({{ $kpis['due_soon'] }} task)
                            @else
                                {{ ucfirst($quickFilter) }}
                            @endif
                        </span>
                    </div>
                    <a href="{{ request()->fullUrlWithQuery(['quick_filter' => null, 'page' => null]) }}#tasks-table" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-white dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-semibold text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/60 shadow-sm transition">
                        <span>✕ Hapus Filter Cepat</span>
                    </a>
                </div>
            @endif

            <!-- Table Header Bar -->
            <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        Daftar Pekerjaan & Task ClickUp
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Menampilkan {{ $tasks->firstItem() ?? 0 }} - {{ $tasks->lastItem() ?? 0 }} dari total {{ $tasks->total() }} task</p>
                </div>
                
                <!-- Items Per Page Selector -->
                <form action="{{ url('/reporting/tasks') }}" method="GET" class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                    @foreach($filters as $k => $v)
                        @if($k !== 'per_page' && !empty($v))
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <span>Tampilkan:</span>
                    <select name="per_page" onchange="this.form.submit()" class="px-2.5 py-1 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-white focus:outline-none">
                        <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15 baris</option>
                        <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25 baris</option>
                        <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50 baris</option>
                        <option value="100" {{ request('per_page', 100) == 100 ? 'selected' : '' }}>100 baris</option>
                    </select>
                </form>
            </div>

            <!-- Table Responsive Wrapper -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                    <thead class="bg-slate-100/90 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3.5">Task Name & ID</th>
                            <th class="px-4 py-3.5">Assignee</th>
                            <th class="px-4 py-3.5">Folder / List</th>
                            <th class="px-4 py-3.5">Status</th>
                            <th class="px-4 py-3.5">Priority</th>
                            <th class="px-4 py-3.5">Start Date</th>
                            <th class="px-4 py-3.5">Due Date</th>
                            <th class="px-4 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-normal">
                        @forelse($tasks as $task)
                            @php
                                $isOverdue = $task->due_date && \Carbon\Carbon::parse($task->due_date)->isPast() && !in_array(strtolower($task->status ?? ''), ['complete', 'completed', 'done', 'closed', 'resolved']);
                                
                                // Priority styling
                                $prio = strtolower($task->priority ?? '');
                                $prioBadge = match($prio) {
                                    'urgent' => 'bg-rose-100 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-300 dark:border-rose-500/30',
                                    'high'   => 'bg-orange-100 dark:bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-300 dark:border-orange-500/30',
                                    'normal' => 'bg-sky-100 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-300 dark:border-sky-500/30',
                                    'low'    => 'bg-slate-100 dark:bg-slate-500/10 text-slate-700 dark:text-slate-400 border-slate-300 dark:border-slate-500/30',
                                    default  => 'bg-slate-100 dark:bg-slate-800 text-slate-500 border-transparent',
                                };

                                // Status styling
                                $st = strtolower($task->status ?? '');
                                $statusBadge = match(true) {
                                    in_array($st, ['complete', 'completed', 'done', 'closed', 'resolved']) => 'bg-emerald-100 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-500/30',
                                    in_array($st, ['in progress', 'progress', 'doing', 'working'])          => 'bg-sky-100 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-300 dark:border-sky-500/30',
                                    in_array($st, ['review', 'in review', 'qa', 'testing'])                => 'bg-purple-100 dark:bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-300 dark:border-purple-500/30',
                                    default                                                                => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700',
                                };
                            @endphp
                            <tr class="hover:bg-slate-100/60 dark:hover:bg-slate-900/60 transition-colors group">
                                
                                <!-- Task Name & ID -->
                                <td class="px-4 py-3.5 max-w-xs sm:max-w-md">
                                    <div class="flex items-start gap-2">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 border border-slate-300 dark:border-slate-700/60 group-hover:border-indigo-500 transition">
                                            #{{ $task->task_id }}
                                        </span>
                                        <div>
                                            <div class="font-medium text-slate-900 dark:text-white line-clamp-2 leading-relaxed">
                                                {{ $task->task_name ?: 'Unnamed Task' }}
                                            </div>
                                            @if($task->timeline)
                                                <span class="inline-block text-[10px] text-slate-500 mt-0.5">
                                                    Sprint / Timeline: {{ $task->timeline }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <!-- Assignee -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    @if($task->assignee)
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center text-[10px] font-bold text-white uppercase shadow-sm">
                                                {{ substr($task->assignee, 0, 1) }}
                                            </div>
                                            <span class="text-xs text-slate-800 dark:text-slate-200 font-medium">{{ $task->assignee }}</span>
                                        </div>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500 italic text-[11px]">Unassigned</span>
                                    @endif
                                </td>

                                <!-- Folder / List -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="text-xs text-slate-800 dark:text-slate-300 font-medium">{{ $task->list_name ?: '-' }}</div>
                                    @if($task->folder_name)
                                        <div class="text-[10px] text-slate-500">{{ $task->folder_name }}</div>
                                    @endif
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $statusBadge }}">
                                        {{ ucfirst($task->status ?: 'No Status') }}
                                    </span>
                                </td>

                                <!-- Priority -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $prioBadge }}">
                                        {{ ucfirst($task->priority ?: '-') }}
                                    </span>
                                </td>

                                <!-- Start Date -->
                                <td class="px-4 py-3.5 whitespace-nowrap text-slate-500 dark:text-slate-400 text-[11px]">
                                    {{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('d M Y') : '-' }}
                                </td>

                                <!-- Due Date -->
                                <td class="px-4 py-3.5 whitespace-nowrap text-[11px]">
                                    @if($task->due_date)
                                        <span class="{{ $isOverdue ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-700 dark:text-slate-300' }}">
                                            {{ \Carbon\Carbon::parse($task->due_date)->format('d M Y') }}
                                        </span>
                                        @if($isOverdue)
                                            <span class="block text-[9px] text-rose-600 dark:text-rose-500 uppercase font-extrabold tracking-wider">Overdue</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">-</span>
                                    @endif
                                </td>

                                <!-- Action (Modal Detail) -->
                                <td class="px-4 py-3.5 whitespace-nowrap text-right">
                                    <button type="button" onclick="openDetailModal({{ json_encode($task) }})" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-[11px] font-medium transition">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="w-8 h-8 text-slate-400 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Tidak ada task yang cocok dengan filter yang dipilih.</p>
                                        <a href="{{ url('/reporting/tasks') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Reset Filter</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Table Pagination Footer -->
            @if($tasks->hasPages())
                <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    {{ $tasks->links() }}
                </div>
            @endif
        </section>
    </main>

    <!-- ========================================== -->
    <!-- 5. TASK DETAIL MODAL                       -->
    <!-- ========================================== -->
    <div id="detailModal" class="fixed inset-0 z-50 bg-slate-950/70 dark:bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="glass-panel bg-white dark:bg-slate-900 rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700/80 space-y-5">
            
            <!-- Modal Header -->
            <div class="flex items-start justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                <div class="flex items-center gap-2.5">
                    <span id="modalTaskId" class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20"></span>
                    <h3 id="modalTaskName" class="text-base font-bold text-slate-900 dark:text-white"></h3>
                </div>
                <button type="button" onclick="closeDetailModal()" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Content Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                <div class="bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Status</span>
                    <strong id="modalStatus" class="text-slate-800 dark:text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Priority</span>
                    <strong id="modalPriority" class="text-slate-800 dark:text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Assignee (PIC)</span>
                    <strong id="modalAssignee" class="text-slate-800 dark:text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Space / Folder</span>
                    <strong id="modalSpaceFolder" class="text-slate-800 dark:text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 block mb-0.5">List</span>
                    <strong id="modalList" class="text-slate-800 dark:text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Task Type</span>
                    <strong id="modalType" class="text-slate-800 dark:text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Start Date</span>
                    <strong id="modalStartDate" class="text-slate-800 dark:text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Due Date</span>
                    <strong id="modalDueDate" class="text-slate-800 dark:text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Created By</span>
                    <strong id="modalCreatedBy" class="text-slate-800 dark:text-slate-200 font-semibold"></strong>
                </div>
            </div>

            <!-- Comment / Description Block -->
            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Deskripsi / Komentar Task</label>
                <div id="modalComment" class="p-3.5 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-300 whitespace-pre-wrap leading-relaxed max-h-48 overflow-y-auto">
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between border-t border-slate-200 dark:border-slate-800 pt-4">
                <span id="modalSyncedAt" class="text-[11px] text-slate-500"></span>
                <div class="flex items-center gap-2">
                    <a id="modalClickUpLink" href="#" target="_blank" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white transition">
                        Buka di ClickUp ↗
                    </a>
                    <button type="button" onclick="closeDetailModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-medium text-slate-700 dark:text-slate-300 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="border-t border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-950 px-4 py-6 text-center text-xs text-slate-500">
        <p>&copy; 2026 PT Susanti Megah — Task Monitoring & Reporting Architecture</p>
    </footer>

    <!-- ========================================== -->
    <!-- APEXCHARTS INITIALIZATION & THEME HANDLER   -->
    <!-- ========================================== -->
    <script>
        const statusLabels = {!! json_encode(array_keys($chartData['status'])) !!};
        const statusSeries = {!! json_encode(array_values($chartData['status'])) !!};

        const assigneeLabels = {!! json_encode(array_keys($chartData['assignee'])) !!};
        const assigneeSeries = {!! json_encode(array_values($chartData['assignee'])) !!};

        const listLabels = {!! json_encode(array_keys($chartData['list'])) !!};
        const listSeries = {!! json_encode(array_values($chartData['list'])) !!};

        const priorityLabels = {!! json_encode(array_keys($chartData['priority'])) !!};
        const prioritySeries = {!! json_encode(array_values($chartData['priority'])) !!};

        let chartStatus, chartAssignee, chartList, chartPriority;

        function isDarkMode() {
            return document.documentElement.classList.contains('dark');
        }

        function renderCharts() {
            const dark = isDarkMode();
            const textColor = dark ? '#94a3b8' : '#64748b';
            const titleColor = dark ? '#f8fafc' : '#0f172a';
            const borderColor = dark ? '#1e293b' : '#e2e8f0';
            const themeMode = dark ? 'dark' : 'light';

            // 1. Status Chart
            if (chartStatus) chartStatus.destroy();
            if (statusLabels.length > 0) {
                chartStatus = new ApexCharts(document.querySelector("#chart-status"), {
                    chart: { type: 'donut', height: 280, background: 'transparent' },
                    labels: statusLabels,
                    series: statusSeries,
                    colors: ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#64748b'],
                    theme: { mode: themeMode },
                    dataLabels: { enabled: true },
                    legend: { position: 'bottom', labels: { colors: textColor } },
                    stroke: { show: true, width: 2, colors: [dark ? '#0f172a' : '#ffffff'] },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '65%',
                                labels: {
                                    show: true,
                                    total: { show: true, label: 'Total', color: titleColor }
                                }
                            }
                        }
                    }
                });
                chartStatus.render();
            } else {
                document.querySelector("#chart-status").innerHTML = '<div class="flex items-center justify-center h-[280px] text-slate-400 text-xs">Tidak ada data status</div>';
            }

            // 2. Assignee Chart
            if (chartAssignee) chartAssignee.destroy();
            if (assigneeLabels.length > 0) {
                chartAssignee = new ApexCharts(document.querySelector("#chart-assignee"), {
                    chart: { type: 'bar', height: 280, background: 'transparent', toolbar: { show: false } },
                    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '55%' } },
                    colors: ['#38bdf8'],
                    theme: { mode: themeMode },
                    dataLabels: { enabled: true, style: { colors: ['#fff'] } },
                    xaxis: { categories: assigneeLabels, labels: { style: { colors: textColor } } },
                    yaxis: { labels: { style: { colors: textColor } } },
                    grid: { borderColor: borderColor },
                    series: [{ name: 'Tasks', data: assigneeSeries }]
                });
                chartAssignee.render();
            } else {
                document.querySelector("#chart-assignee").innerHTML = '<div class="flex items-center justify-center h-[280px] text-slate-400 text-xs">Tidak ada data assignee</div>';
            }

            // 3. List Chart
            if (chartList) chartList.destroy();
            if (listLabels.length > 0) {
                chartList = new ApexCharts(document.querySelector("#chart-list"), {
                    chart: { type: 'bar', height: 280, background: 'transparent', toolbar: { show: false } },
                    plotOptions: { bar: { horizontal: false, borderRadius: 6, columnWidth: '45%' } },
                    colors: ['#f59e0b'],
                    theme: { mode: themeMode },
                    dataLabels: { enabled: false },
                    xaxis: { categories: listLabels, labels: { style: { colors: textColor } } },
                    yaxis: { labels: { style: { colors: textColor } } },
                    grid: { borderColor: borderColor },
                    series: [{ name: 'Tasks', data: listSeries }]
                });
                chartList.render();
            } else {
                document.querySelector("#chart-list").innerHTML = '<div class="flex items-center justify-center h-[280px] text-slate-400 text-xs">Tidak ada data list</div>';
            }

            // 4. Priority Chart
            if (chartPriority) chartPriority.destroy();
            if (priorityLabels.length > 0) {
                chartPriority = new ApexCharts(document.querySelector("#chart-priority"), {
                    chart: { type: 'bar', height: 280, background: 'transparent', toolbar: { show: false } },
                    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '50%' } },
                    colors: ['#f43f5e'],
                    theme: { mode: themeMode },
                    dataLabels: { enabled: true },
                    xaxis: { categories: priorityLabels, labels: { style: { colors: textColor } } },
                    yaxis: { labels: { style: { colors: textColor } } },
                    grid: { borderColor: borderColor },
                    series: [{ name: 'Tasks', data: prioritySeries }]
                });
                chartPriority.render();
            } else {
                document.querySelector("#chart-priority").innerHTML = '<div class="flex items-center justify-center h-[280px] text-slate-400 text-xs">Tidak ada data priority</div>';
            }
        }

        // Theme Toggle Function
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
            renderCharts();
        }

        // Initialize Charts on Load
        document.addEventListener('DOMContentLoaded', function() {
            renderCharts();
        });

        // Modal Functions
        function openDetailModal(task) {
            document.getElementById('modalTaskId').innerText = '#' + (task.task_id || '-');
            document.getElementById('modalTaskName').innerText = task.task_name || 'Unnamed Task';
            document.getElementById('modalStatus').innerText = task.status || '-';
            document.getElementById('modalPriority').innerText = task.priority || '-';
            document.getElementById('modalAssignee').innerText = task.assignee || 'Unassigned';
            document.getElementById('modalSpaceFolder').innerText = (task.space_name || task.space_id || '-') + (task.folder_name ? ' / ' + task.folder_name : '');
            document.getElementById('modalList').innerText = task.list_name || '-';
            document.getElementById('modalType').innerText = task.task_type || 'Task';
            document.getElementById('modalStartDate').innerText = task.start_date ? task.start_date.substring(0, 10) : '-';
            document.getElementById('modalDueDate').innerText = task.due_date ? task.due_date.substring(0, 10) : '-';
            document.getElementById('modalCreatedBy').innerText = task.created_by || '-';
            document.getElementById('modalComment').innerText = task.comment || 'Tidak ada deskripsi/komentar tambahan.';
            document.getElementById('modalSyncedAt').innerText = 'Synced at: ' + (task.synced_at || '-');
            
            const clickUpUrl = 'https://app.clickup.com/t/' + encodeURIComponent(task.task_id);
            document.getElementById('modalClickUpLink').href = clickUpUrl;

            const modal = document.getElementById('detailModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDetailModal() {
            const modal = document.getElementById('detailModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Close on Escape Key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDetailModal();
            }
        });
    </script>
</body>
</html>
