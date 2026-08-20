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
    
    <!-- ApexCharts CDN -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        * { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0b1120; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }
        .glass-panel {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.07);
        }
        .glow-indigo { box-shadow: 0 0 25px -5px rgba(99, 102, 241, 0.15); }
        .glow-emerald { box-shadow: 0 0 25px -5px rgba(16, 185, 129, 0.15); }
        .glow-rose { box-shadow: 0 0 25px -5px rgba(244, 63, 94, 0.15); }
        .glow-amber { box-shadow: 0 0 25px -5px rgba(245, 158, 11, 0.15); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col selection:bg-indigo-500 selection:text-white">

    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-30 bg-slate-900/90 backdrop-blur-md border-b border-slate-800/80 px-4 sm:px-6 lg:px-8 py-3.5 transition-all">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            
            <!-- Branding & Title -->
            <div class="flex items-center gap-3.5">
                <a href="{{ url('/monitoringsm/hub') }}" class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-600 font-extrabold text-slate-950 text-base shadow-lg shadow-amber-500/20 hover:scale-105 transition-transform" title="Kembali ke Admin Hub">
                    SM
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                            ClickUp Task Reporting
                        </h1>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            Live Sync
                        </span>
                    </div>
                    <p class="text-xs text-slate-400">Monitoring & visualisasi task terpadu tim PT Susanti Megah</p>
                </div>
            </div>

            <!-- Sync Info & Quick Navigation -->
            <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto justify-between sm:justify-end">
                
                <!-- Last Sync Badge -->
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-800/60 border border-slate-700/60 text-xs text-slate-300">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span>Last Sync: <strong class="text-white font-medium">{{ $lastSyncedAt ? $lastSyncedAt->format('d M Y H:i') : 'Belum Ada' }}</strong></span>
                </div>

                <!-- Refresh Button -->
                <a href="{{ request()->fullUrl() }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white shadow-md shadow-indigo-600/20 transition active:scale-95">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </a>

                <!-- Hub Link -->
                <a href="{{ url('/monitoringsm/hub') }}" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-medium text-slate-300 transition">
                    Admin Hub
                </a>

                <!-- API Keys Link -->
                <a href="{{ url('/monitoringsm/api-keys') }}" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-medium text-slate-300 transition hidden md:inline-block">
                    API Keys
                </a>

                <!-- Pulse Link -->
                <a href="{{ url('/monitoringsm') }}" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-medium text-slate-300 transition hidden md:inline-block">
                    Pulse
                </a>

                <!-- Logout Link -->
                <a href="{{ url('/monitoringsm/logout') }}" class="px-3 py-1.5 rounded-lg bg-rose-950/40 hover:bg-rose-900/50 border border-rose-800/40 text-xs font-medium text-rose-300 transition">
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- ========================================== -->
        <!-- 1. FILTER SECTION                          -->
        <!-- ========================================== -->
        <section class="glass-panel rounded-2xl p-5 shadow-xl">
            <form action="{{ url('/reporting/tasks') }}" method="GET" class="space-y-4">
                
                <!-- Search & Quick Filters Header -->
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <h2 class="text-sm font-semibold text-slate-200">Filter & Pencarian Task</h2>
                    </div>
                    @if(array_filter($filters))
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                            Filter Aktif ({{ count(array_filter($filters)) }})
                        </span>
                    @endif
                </div>

                <!-- Input Rows -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    
                    <!-- Search Input -->
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Cari Task Name / ID / PIC</label>
                        <div class="relative">
                            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ketik kata kunci pencarian..." class="w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700/80 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                            <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Space Filter -->
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Space</label>
                        <select name="space_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500 transition">
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
                        <label class="block text-xs font-medium text-slate-400 mb-1">Folder</label>
                        <select name="folder_name" class="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500 transition">
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
                        <label class="block text-xs font-medium text-slate-400 mb-1">List</label>
                        <select name="list_name" class="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500 transition">
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
                        <label class="block text-xs font-medium text-slate-400 mb-1">Assignee (PIC)</label>
                        <select name="assignee" class="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500 transition">
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
                        <label class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500 transition">
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
                        <label class="block text-xs font-medium text-slate-400 mb-1">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500 transition">
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
                        <label class="block text-xs font-medium text-slate-400 mb-1">Start Date Dari</label>
                        <input type="date" name="start_date_from" value="{{ $filters['start_date_from'] ?? '' }}" class="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500 transition">
                    </div>

                    <!-- Due Date Range -->
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Due Date Sampai</label>
                        <input type="date" name="due_date_to" value="{{ $filters['due_date_to'] ?? '' }}" class="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-800/80">
                    <a href="{{ url('/reporting/tasks') }}" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-300 transition">
                        Reset Filter
                    </a>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white shadow-lg shadow-indigo-600/20 transition active:scale-95">
                        Terapkan Filter
                    </button>
                </div>
            </form>
        </section>

        <!-- ========================================== -->
        <!-- 2. KPI CARDS                               -->
        <!-- ========================================== -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            
            <!-- Total Tasks -->
            <div class="glass-panel glow-indigo rounded-2xl p-4 sm:p-5 flex flex-col justify-between relative overflow-hidden group">
                <div class="absolute -right-2 -bottom-2 w-16 h-16 bg-indigo-500/10 rounded-full blur-xl group-hover:bg-indigo-500/20 transition"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400">Total Tasks</span>
                    <span class="p-2 rounded-xl bg-indigo-500/10 text-indigo-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ number_format($kpis['total']) }}</div>
                    <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                        <span>Completion Rate:</span>
                        <strong class="text-emerald-400 font-semibold">{{ $kpis['completion_rate'] }}%</strong>
                    </div>
                </div>
            </div>

            <!-- In Progress -->
            <div class="glass-panel glow-indigo rounded-2xl p-4 sm:p-5 flex flex-col justify-between relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400">In Progress</span>
                    <span class="p-2 rounded-xl bg-sky-500/10 text-sky-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-sky-400 tracking-tight">{{ number_format($kpis['in_progress']) }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Sedang dikerjakan tim</div>
                </div>
            </div>

            <!-- Completed -->
            <div class="glass-panel glow-emerald rounded-2xl p-4 sm:p-5 flex flex-col justify-between relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400">Completed</span>
                    <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-emerald-400 tracking-tight">{{ number_format($kpis['completed']) }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Telah selesai / resolved</div>
                </div>
            </div>

            <!-- Overdue -->
            <div class="glass-panel glow-rose rounded-2xl p-4 sm:p-5 flex flex-col justify-between relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400">Overdue</span>
                    <span class="p-2 rounded-xl bg-rose-500/10 text-rose-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-rose-400 tracking-tight">{{ number_format($kpis['overdue']) }}</div>
                    <div class="text-[11px] text-rose-400/80 mt-1 font-medium">Melewati deadline</div>
                </div>
            </div>

            <!-- Due Soon -->
            <div class="glass-panel glow-amber rounded-2xl p-4 sm:p-5 flex flex-col justify-between relative overflow-hidden group col-span-2 sm:col-span-1">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400">Due Soon</span>
                    <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-amber-400 tracking-tight">{{ number_format($kpis['due_soon']) }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Jatuh tempo 7 hari ke depan</div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 3. INTERACTIVE CHARTS                      -->
        <!-- ========================================== -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Chart 1: Status Distribution -->
            <div class="glass-panel rounded-2xl p-5 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                        Distribusi Status Task
                    </h3>
                    <span class="text-xs text-slate-500">Berdasarkan data tersaring</span>
                </div>
                <div id="chart-status" class="min-h-[280px]"></div>
            </div>

            <!-- Chart 2: Top Assignees -->
            <div class="glass-panel rounded-2xl p-5 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span>
                        Task Berdasarkan Assignee (Top PIC)
                    </h3>
                    <span class="text-xs text-slate-500">Jumlah task per personil</span>
                </div>
                <div id="chart-assignee" class="min-h-[280px]"></div>
            </div>

            <!-- Chart 3: Tasks by List / Folder -->
            <div class="glass-panel rounded-2xl p-5 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        Task Berdasarkan List / Modul
                    </h3>
                    <span class="text-xs text-slate-500">Distribusi modul ClickUp</span>
                </div>
                <div id="chart-list" class="min-h-[280px]"></div>
            </div>

            <!-- Chart 4: Task Priority & Timeline -->
            <div class="glass-panel rounded-2xl p-5 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        Tingkat Prioritas (Priority Breakdown)
                    </h3>
                    <span class="text-xs text-slate-500">Urgent / High / Normal / Low</span>
                </div>
                <div id="chart-priority" class="min-h-[280px]"></div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 4. TASK DATA TABLE                         -->
        <!-- ========================================== -->
        <section class="glass-panel rounded-2xl shadow-xl overflow-hidden">
            
            <!-- Table Header Bar -->
            <div class="p-5 border-b border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        Daftar Pekerjaan & Task ClickUp
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Menampilkan {{ $tasks->firstItem() ?? 0 }} - {{ $tasks->lastItem() ?? 0 }} dari total {{ $tasks->total() }} task</p>
                </div>
                
                <!-- Items Per Page Selector -->
                <form action="{{ url('/reporting/tasks') }}" method="GET" class="flex items-center gap-2 text-xs text-slate-400">
                    @foreach($filters as $k => $v)
                        @if($k !== 'per_page' && !empty($v))
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <span>Tampilkan:</span>
                    <select name="per_page" onchange="this.form.submit()" class="px-2.5 py-1 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none">
                        <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15 baris</option>
                        <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25 baris</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 baris</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 baris</option>
                    </select>
                </form>
            </div>

            <!-- Table Responsive Wrapper -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
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
                    <tbody class="divide-y divide-slate-800/60 font-normal">
                        @forelse($tasks as $task)
                            @php
                                $isOverdue = $task->due_date && \Carbon\Carbon::parse($task->due_date)->isPast() && !in_array(strtolower($task->status ?? ''), ['complete', 'completed', 'done', 'closed', 'resolved']);
                                
                                // Priority styling
                                $prio = strtolower($task->priority ?? '');
                                $prioBadge = match($prio) {
                                    'urgent' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                                    'high'   => 'bg-orange-500/10 text-orange-400 border-orange-500/30',
                                    'normal' => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
                                    'low'    => 'bg-slate-500/10 text-slate-400 border-slate-500/30',
                                    default  => 'bg-slate-800 text-slate-500 border-transparent',
                                };

                                // Status styling
                                $st = strtolower($task->status ?? '');
                                $statusBadge = match(true) {
                                    in_array($st, ['complete', 'completed', 'done', 'closed', 'resolved']) => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                    in_array($st, ['in progress', 'progress', 'doing', 'working'])          => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
                                    in_array($st, ['review', 'in review', 'qa', 'testing'])                => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
                                    default                                                                => 'bg-slate-800 text-slate-300 border-slate-700',
                                };
                            @endphp
                            <tr class="hover:bg-slate-900/60 transition-colors group">
                                
                                <!-- Task Name & ID -->
                                <td class="px-4 py-3.5 max-w-xs sm:max-w-md">
                                    <div class="flex items-start gap-2">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-indigo-400 border border-slate-700/60 group-hover:border-indigo-500/40 transition">
                                            #{{ $task->task_id }}
                                        </span>
                                        <div>
                                            <div class="font-medium text-white line-clamp-2 leading-relaxed">
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
                                            <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center text-[10px] font-bold text-white uppercase">
                                                {{ substr($task->assignee, 0, 1) }}
                                            </div>
                                            <span class="text-xs text-slate-200 font-medium">{{ $task->assignee }}</span>
                                        </div>
                                    @else
                                        <span class="text-slate-500 italic text-[11px]">Unassigned</span>
                                    @endif
                                </td>

                                <!-- Folder / List -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="text-xs text-slate-300 font-medium">{{ $task->list_name ?: '-' }}</div>
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
                                <td class="px-4 py-3.5 whitespace-nowrap text-slate-400 text-[11px]">
                                    {{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('d M Y') : '-' }}
                                </td>

                                <!-- Due Date -->
                                <td class="px-4 py-3.5 whitespace-nowrap text-[11px]">
                                    @if($task->due_date)
                                        <span class="{{ $isOverdue ? 'text-rose-400 font-bold' : 'text-slate-300' }}">
                                            {{ \Carbon\Carbon::parse($task->due_date)->format('d M Y') }}
                                        </span>
                                        @if($isOverdue)
                                            <span class="block text-[9px] text-rose-500 uppercase font-extrabold tracking-wider">Overdue</span>
                                        @endif
                                    @else
                                        <span class="text-slate-500">-</span>
                                    @endif
                                </td>

                                <!-- Action (Modal Detail) -->
                                <td class="px-4 py-3.5 whitespace-nowrap text-right">
                                    <button type="button" onclick="openDetailModal({{ json_encode($task) }})" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-[11px] font-medium transition">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <p class="text-sm font-medium text-slate-400">Tidak ada task yang cocok dengan filter yang dipilih.</p>
                                        <a href="{{ url('/reporting/tasks') }}" class="text-xs text-indigo-400 hover:underline">Reset Filter</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Table Pagination Footer -->
            @if($tasks->hasPages())
                <div class="p-4 bg-slate-900/60 border-t border-slate-800 flex items-center justify-between">
                    {{ $tasks->links() }}
                </div>
            @endif
        </section>
    </main>

    <!-- ========================================== -->
    <!-- 5. TASK DETAIL MODAL                       -->
    <!-- ========================================== -->
    <div id="detailModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="glass-panel bg-slate-900 rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-700/80 space-y-5 animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="flex items-start justify-between border-b border-slate-800 pb-4">
                <div class="flex items-center gap-2.5">
                    <span id="modalTaskId" class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20"></span>
                    <h3 id="modalTaskName" class="text-base font-bold text-white"></h3>
                </div>
                <button type="button" onclick="closeDetailModal()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Content Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Status</span>
                    <strong id="modalStatus" class="text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Priority</span>
                    <strong id="modalPriority" class="text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Assignee (PIC)</span>
                    <strong id="modalAssignee" class="text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Space / Folder</span>
                    <strong id="modalSpaceFolder" class="text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                    <span class="text-slate-500 block mb-0.5">List</span>
                    <strong id="modalList" class="text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Task Type</span>
                    <strong id="modalType" class="text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Start Date</span>
                    <strong id="modalStartDate" class="text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Due Date</span>
                    <strong id="modalDueDate" class="text-slate-200 font-semibold"></strong>
                </div>
                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                    <span class="text-slate-500 block mb-0.5">Created By</span>
                    <strong id="modalCreatedBy" class="text-slate-200 font-semibold"></strong>
                </div>
            </div>

            <!-- Comment / Description Block -->
            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-300">Deskripsi / Komentar Task</label>
                <div id="modalComment" class="p-3.5 bg-slate-950 rounded-xl border border-slate-800 text-xs text-slate-300 whitespace-pre-wrap leading-relaxed max-h-48 overflow-y-auto">
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between border-t border-slate-800 pt-4">
                <span id="modalSyncedAt" class="text-[11px] text-slate-500"></span>
                <div class="flex items-center gap-2">
                    <a id="modalClickUpLink" href="#" target="_blank" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white transition">
                        Buka di ClickUp ↗
                    </a>
                    <button type="button" onclick="closeDetailModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-300 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-950 px-4 py-6 text-center text-xs text-slate-500">
        <p>&copy; 2026 PT Susanti Megah — Task Monitoring & Reporting Architecture</p>
    </footer>

    <!-- ========================================== -->
    <!-- APEXCHARTS INITIALIZATION                  -->
    <!-- ========================================== -->
    <script>
        // Chart 1: Status Distribution Donut Chart
        const statusLabels = {!! json_encode(array_keys($chartData['status'])) !!};
        const statusSeries = {!! json_encode(array_values($chartData['status'])) !!};

        if (statusLabels.length > 0) {
            new ApexCharts(document.querySelector("#chart-status"), {
                chart: { type: 'donut', height: 280, background: 'transparent' },
                labels: statusLabels,
                series: statusSeries,
                colors: ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#64748b'],
                theme: { mode: 'dark' },
                dataLabels: { enabled: true },
                legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
                stroke: { show: true, width: 2, colors: ['#0f172a'] },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%',
                            labels: {
                                show: true,
                                total: { show: true, label: 'Total', color: '#f8fafc' }
                            }
                        }
                    }
                }
            }).render();
        } else {
            document.querySelector("#chart-status").innerHTML = '<div class="flex items-center justify-center h-[280px] text-slate-500 text-xs">Tidak ada data status</div>';
        }

        // Chart 2: Top Assignees Bar Chart
        const assigneeLabels = {!! json_encode(array_keys($chartData['assignee'])) !!};
        const assigneeSeries = {!! json_encode(array_values($chartData['assignee'])) !!};

        if (assigneeLabels.length > 0) {
            new ApexCharts(document.querySelector("#chart-assignee"), {
                chart: { type: 'bar', height: 280, background: 'transparent', toolbar: { show: false } },
                plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '55%' } },
                colors: ['#38bdf8'],
                theme: { mode: 'dark' },
                dataLabels: { enabled: true, style: { colors: ['#fff'] } },
                xaxis: { categories: assigneeLabels, labels: { style: { colors: '#94a3b8' } } },
                yaxis: { labels: { style: { colors: '#cbd5e1' } } },
                grid: { borderColor: '#1e293b' },
                series: [{ name: 'Tasks', data: assigneeSeries }]
            }).render();
        } else {
            document.querySelector("#chart-assignee").innerHTML = '<div class="flex items-center justify-center h-[280px] text-slate-500 text-xs">Tidak ada data assignee</div>';
        }

        // Chart 3: Tasks by List
        const listLabels = {!! json_encode(array_keys($chartData['list'])) !!};
        const listSeries = {!! json_encode(array_values($chartData['list'])) !!};

        if (listLabels.length > 0) {
            new ApexCharts(document.querySelector("#chart-list"), {
                chart: { type: 'bar', height: 280, background: 'transparent', toolbar: { show: false } },
                plotOptions: { bar: { horizontal: false, borderRadius: 6, columnWidth: '45%' } },
                colors: ['#f59e0b'],
                theme: { mode: 'dark' },
                dataLabels: { enabled: false },
                xaxis: { categories: listLabels, labels: { style: { colors: '#94a3b8' } } },
                yaxis: { labels: { style: { colors: '#94a3b8' } } },
                grid: { borderColor: '#1e293b' },
                series: [{ name: 'Tasks', data: listSeries }]
            }).render();
        } else {
            document.querySelector("#chart-list").innerHTML = '<div class="flex items-center justify-center h-[280px] text-slate-500 text-xs">Tidak ada data list</div>';
        }

        // Chart 4: Priority Breakdown
        const priorityLabels = {!! json_encode(array_keys($chartData['priority'])) !!};
        const prioritySeries = {!! json_encode(array_values($chartData['priority'])) !!};

        if (priorityLabels.length > 0) {
            new ApexCharts(document.querySelector("#chart-priority"), {
                chart: { type: 'bar', height: 280, background: 'transparent', toolbar: { show: false } },
                plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '50%' } },
                colors: ['#f43f5e'],
                theme: { mode: 'dark' },
                dataLabels: { enabled: true },
                xaxis: { categories: priorityLabels, labels: { style: { colors: '#94a3b8' } } },
                yaxis: { labels: { style: { colors: '#cbd5e1' } } },
                grid: { borderColor: '#1e293b' },
                series: [{ name: 'Tasks', data: prioritySeries }]
            }).render();
        } else {
            document.querySelector("#chart-priority").innerHTML = '<div class="flex items-center justify-center h-[280px] text-slate-500 text-xs">Tidak ada data priority</div>';
        }

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

        // Close on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDetailModal();
            }
        });
    </script>
</body>
</html>
