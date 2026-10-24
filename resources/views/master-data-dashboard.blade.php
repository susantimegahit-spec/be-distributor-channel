<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Master Data Management &bull; Susanti Megah Monitoring Hub</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        primary: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        },
                        surface: {
                            base: 'var(--bg-base)',
                            card: 'var(--bg-card)',
                            subtle: 'var(--bg-subtle)',
                            header: 'var(--bg-header)',
                        },
                        border: {
                            color: 'var(--border-color)',
                            subtle: 'var(--border-subtle)',
                        }
                    },
                    textColor: {
                        heading: 'var(--text-heading)',
                        body: 'var(--text-body)',
                        muted: 'var(--text-muted)',
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --bg-base: #f8fafc;
            --bg-card: #ffffff;
            --bg-subtle: #f1f5f9;
            --bg-header: rgba(255, 255, 255, 0.85);
            --border-color: #e2e8f0;
            --border-subtle: #f1f5f9;
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
        }

        html.dark {
            --bg-base: #090d16;
            --bg-card: #0f172a;
            --bg-subtle: #1e293b;
            --bg-header: rgba(15, 23, 42, 0.85);
            --border-color: #1e293b;
            --border-subtle: #334155;
            --text-heading: #f8fafc;
            --text-body: #cbd5e1;
            --text-muted: #64748b;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-body);
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 9999px;
        }
    </style>
</head>
<body class="min-h-screen transition-colors duration-200 antialiased selection:bg-indigo-500 selection:text-white">

    <!-- Top Sticky Navigation Bar -->
    <header class="bg-surface-header backdrop-blur-md border-b border-border-color sticky top-0 z-40 transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            
            <!-- Brand Identity -->
            <div class="flex items-center space-x-3.5">
                <a href="/monitoringsm/hub" class="flex items-center space-x-3.5 group">
                    <div class="relative flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 via-violet-500 to-purple-500 font-extrabold text-white text-base shadow-lg shadow-indigo-500/25 ring-1 ring-white/20 shrink-0 group-hover:scale-105 transition-transform">
                        MD
                        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 border-2 border-slate-950 rounded-full" title="Active"></span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-bold text-base sm:text-lg leading-tight text-heading tracking-tight">Master Data Hub</h1>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/10 text-indigo-500 border border-indigo-500/20">
                                Dynamic CRUD
                            </span>
                        </div>
                        <p class="text-xs text-muted font-medium">PT Susanti Megah &bull; Central Logistics & Operations</p>
                    </div>
                </a>
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
                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    <span class="hidden sm:inline">OpenAPI Docs</span>
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

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/25 flex items-start gap-3 animate-fade-in">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="flex-1 text-xs sm:text-sm text-emerald-300 font-medium">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/25 flex items-start gap-3 animate-fade-in">
                <svg class="w-5 h-5 text-rose-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="flex-1 text-xs sm:text-sm text-rose-300 font-medium">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/25 space-y-1.5 animate-fade-in">
                <div class="flex items-center gap-2 text-rose-400 text-xs sm:text-sm font-semibold">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Terdapat beberapa kesalahan input:</span>
                </div>
                <ul class="list-disc list-inside text-xs text-rose-300/90 pl-1 space-y-0.5">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Dynamic Table Switcher Header Bar -->
        <div class="bg-surface-card border border-border-color rounded-2xl p-5 shadow-sm space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                
                <!-- Left: Table Selector Dropdown & Info -->
                <div class="space-y-1.5 flex-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <label for="tableSelector" class="text-xs font-bold text-muted uppercase tracking-wider">Pilih Master Data:</label>
                        <div class="relative inline-block">
                            <select id="tableSelector" onchange="switchTable(this.value)" class="appearance-none bg-surface-subtle border border-border-color text-heading text-sm font-bold rounded-xl px-4 py-2.5 pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer shadow-sm">
                                @foreach ($registry as $tblKey => $cfg)
                                    <option value="{{ $tblKey }}" {{ $selectedTable === $tblKey ? 'selected' : '' }}>
                                        {{ $cfg['label'] }} ({{ $counts[$tblKey] ?? 0 }} data)
                                    </option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-muted">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </div>

                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-500/15 text-indigo-400 border border-indigo-500/30">
                            {{ $tableConfig['badge'] ?? 'Master' }}
                        </span>
                    </div>

                    <p class="text-xs text-muted font-medium pt-1">
                        {{ $tableConfig['description'] }}
                    </p>
                </div>

                <!-- Right: Add Action Button -->
                <div class="flex items-center gap-3 shrink-0">
                    <button type="button" onclick="openAddModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs sm:text-sm font-bold shadow-lg shadow-indigo-500/25 transition-all transform active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Data {{ $tableConfig['label'] }}</span>
                    </button>
                </div>
            </div>

            <!-- Quick Pill Filter for Tables -->
            <div class="flex flex-wrap gap-2 pt-2 border-t border-border-color/60">
                @foreach ($registry as $tblKey => $cfg)
                    <a href="/monitoringsm/master-data?table={{ $tblKey }}" 
                       class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold transition {{ $selectedTable === $tblKey ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-surface-subtle hover:bg-slate-700/40 text-muted hover:text-heading border border-border-color' }}">
                        <span>{{ $cfg['label'] }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $selectedTable === $tblKey ? 'bg-white/20 text-white' : 'bg-slate-800 text-muted' }}">
                            {{ $counts[$tblKey] ?? 0 }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-surface-card border border-border-color rounded-2xl p-4 shadow-sm">
            <form action="/monitoringsm/master-data" method="GET" class="w-full sm:w-80 relative">
                <input type="hidden" name="table" value="{{ $selectedTable }}">
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari {{ strtolower($tableConfig['label']) }}..." class="w-full bg-surface-subtle border border-border-color rounded-xl pl-9 pr-4 py-2 text-xs sm:text-sm text-heading placeholder-muted focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </form>

            <div class="flex items-center gap-2 text-xs text-muted w-full sm:w-auto justify-between sm:justify-end">
                @if ($search !== '')
                    <span>Hasil pencarian: <strong class="text-heading">"{{ $search }}"</strong></span>
                    <a href="/monitoringsm/master-data?table={{ $selectedTable }}" class="text-indigo-400 hover:underline">Reset</a>
                @else
                    <span>Menampilkan <strong>{{ $rows->total() }}</strong> total data</span>
                @endif
            </div>
        </div>

        <!-- Dynamic Data Table -->
        <div class="bg-surface-card border border-border-color rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead>
                        <tr class="bg-surface-subtle border-b border-border-color text-muted font-bold tracking-wider uppercase text-[11px]">
                            <th class="py-3.5 px-4 w-16 text-center">#</th>
                            @foreach ($tableConfig['fields'] as $colKey => $field)
                                @if (!empty($field['table_visible']))
                                    <th class="py-3.5 px-4 whitespace-nowrap">{{ $field['label'] }}</th>
                                @endif
                            @endforeach
                            <th class="py-3.5 px-4 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-color">
                        @forelse ($rows as $index => $row)
                            <tr class="hover:bg-surface-subtle/50 transition-colors">
                                <td class="py-3 px-4 text-center font-mono text-xs text-muted">
                                    {{ $rows->firstItem() + $index }}
                                </td>

                                @foreach ($tableConfig['fields'] as $colKey => $field)
                                    @if (!empty($field['table_visible']))
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            @php
                                                $val = $row->{$colKey};
                                                $style = $field['badge_style'] ?? null;
                                            @endphp

                                            @if ($style === 'status')
                                                @if ($val === 'ACTIVE')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                        ACTIVE
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                        {{ $val ?? 'INACTIVE' }}
                                                    </span>
                                                @endif
                                            @elseif ($style === 'blue')
                                                <span class="inline-flex items-center gap-1 font-mono font-bold text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded-lg border border-indigo-500/20">
                                                    {{ number_format((float)$val, 2) }}
                                                    <span class="text-[10px] text-muted font-normal">{{ $row->lead_time_unit ?? 'DAYS' }}</span>
                                                </span>
                                            @else
                                                <span class="text-heading font-medium">
                                                    {{ $val !== null && $val !== '' ? $val : '-' }}
                                                </span>
                                            @endif
                                        </td>
                                    @endif
                                @endforeach

                                <!-- Action Buttons -->
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" 
                                                onclick='openEditModal(@json($row))' 
                                                class="p-1.5 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 text-xs font-semibold transition" 
                                                title="Ubah Data">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                        <button type="button" 
                                                onclick="openDeleteModal('{{ $row->id }}', '{{ addslashes($row->origin_warehouse_name ?? $row->whs_name_origin ?? $row->expedition_name ?? ('ID ' . $row->id)) }}')" 
                                                class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-semibold transition" 
                                                title="Hapus Data">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-12 text-center text-muted space-y-2">
                                    <svg class="w-10 h-10 mx-auto text-muted/50" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                    <p class="font-medium text-sm">Tidak ada data {{ strtolower($tableConfig['label']) }} ditemukan.</p>
                                    <button type="button" onclick="openAddModal()" class="text-xs text-indigo-400 hover:underline font-semibold">+ Tambah data pertama</button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="p-4 border-t border-border-color bg-surface-subtle/50">
                    {{ $rows->links() }}
                </div>
            @endif
        </div>
    </main>

    <!-- Dynamic Add / Edit Modal -->
    <div id="formModal" class="fixed inset-0 z-50 hidden bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-surface-card border border-border-color rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl animate-fade-in my-8">
            <div class="p-6 border-b border-border-color flex items-center justify-between bg-surface-subtle">
                <div class="space-y-0.5">
                    <h3 id="modalTitle" class="text-base sm:text-lg font-bold text-heading">Tambah Data</h3>
                    <p class="text-xs text-muted">Tabel: <strong class="text-indigo-400">{{ $tableConfig['label'] }}</strong></p>
                </div>
                <button type="button" onclick="closeFormModal()" class="p-2 rounded-lg text-muted hover:text-heading hover:bg-surface-card transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="dynamicForm" method="POST" action="" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto custom-scrollbar">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($tableConfig['fields'] as $colKey => $field)
                        <div class="{{ in_array($field['type'], ['textarea']) ? 'sm:col-span-2' : '' }} space-y-1.5">
                            <label for="input_{{ $colKey }}" class="block text-xs font-semibold text-heading">
                                {{ $field['label'] }}
                                @if (!empty($field['required']))
                                    <span class="text-rose-400">*</span>
                                @endif
                            </label>

                            @if ($field['type'] === 'select')
                                <select id="input_{{ $colKey }}" 
                                        name="{{ $colKey }}" 
                                        class="w-full bg-surface-subtle border border-border-color rounded-xl px-3 py-2 text-xs sm:text-sm text-heading focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        {{ !empty($field['required']) ? 'required' : '' }}>
                                    @foreach ($field['options'] ?? [] as $optVal => $optLabel)
                                        <option value="{{ $optVal }}" {{ ($field['default'] ?? '') == $optVal ? 'selected' : '' }}>
                                            {{ $optLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($field['type'] === 'textarea')
                                <textarea id="input_{{ $colKey }}" 
                                          name="{{ $colKey }}" 
                                          rows="3"
                                          placeholder="{{ $field['placeholder'] ?? '' }}"
                                          class="w-full bg-surface-subtle border border-border-color rounded-xl px-3 py-2 text-xs sm:text-sm text-heading placeholder-muted focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                          {{ !empty($field['required']) ? 'required' : '' }}></textarea>
                            @elseif ($field['type'] === 'number')
                                <input type="number" 
                                       id="input_{{ $colKey }}" 
                                       name="{{ $colKey }}" 
                                       step="{{ $field['step'] ?? 'any' }}"
                                       min="{{ $field['min'] ?? '' }}"
                                       placeholder="{{ $field['placeholder'] ?? '' }}"
                                       class="w-full bg-surface-subtle border border-border-color rounded-xl px-3 py-2 text-xs sm:text-sm text-heading placeholder-muted focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       {{ !empty($field['required']) ? 'required' : '' }}>
                            @else
                                <input type="text" 
                                       id="input_{{ $colKey }}" 
                                       name="{{ $colKey }}" 
                                       placeholder="{{ $field['placeholder'] ?? '' }}"
                                       class="w-full bg-surface-subtle border border-border-color rounded-xl px-3 py-2 text-xs sm:text-sm text-heading placeholder-muted focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       {{ !empty($field['required']) ? 'required' : '' }}>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="pt-4 border-t border-border-color flex items-center justify-end gap-3">
                    <button type="button" onclick="closeFormModal()" class="px-4 py-2 rounded-xl bg-surface-subtle hover:bg-slate-700/40 text-xs sm:text-sm font-semibold text-muted hover:text-heading transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-xs sm:text-sm font-bold text-white shadow-lg shadow-indigo-600/30 transition">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-surface-card border border-border-color rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl animate-fade-in">
            <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-heading">Konfirmasi Hapus Data</h3>
                <p class="text-xs text-muted mt-1">
                    Apakah Anda yakin ingin menghapus data <strong id="deleteTargetName" class="text-rose-400"></strong> dari tabel {{ $tableConfig['label'] }}? Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>
            <form id="deleteForm" method="POST" action="" class="flex items-center justify-end gap-3 pt-2">
                @csrf
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 rounded-xl bg-surface-subtle hover:bg-slate-700/40 text-xs font-semibold text-muted hover:text-heading transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-xs font-bold text-white shadow-lg shadow-rose-600/30 transition">
                    Ya, Hapus Data
                </button>
            </form>
        </div>
    </div>

    <!-- Javascript Interactions -->
    <script>
        const currentTable = "{{ $selectedTable }}";
        const tableLabel = "{{ $tableConfig['label'] }}";

        function switchTable(tbl) {
            window.location.href = `/monitoringsm/master-data?table=${tbl}`;
        }

        function openAddModal() {
            const form = document.getElementById('dynamicForm');
            form.action = `/monitoringsm/master-data/${currentTable}`;
            document.getElementById('modalTitle').innerText = `Tambah Data ${tableLabel}`;
            form.reset();
            document.getElementById('formModal').classList.remove('hidden');
        }

        function openEditModal(record) {
            const form = document.getElementById('dynamicForm');
            form.action = `/monitoringsm/master-data/${currentTable}/${record.id}/update`;
            document.getElementById('modalTitle').innerText = `Ubah Data ${tableLabel} (ID: ${record.id})`;

            // Populate form values
            for (const key in record) {
                const input = document.getElementById(`input_${key}`);
                if (input) {
                    input.value = record[key] !== null ? record[key] : '';
                }
            }

            document.getElementById('formModal').classList.remove('hidden');
        }

        function closeFormModal() {
            document.getElementById('formModal').classList.add('hidden');
        }

        function openDeleteModal(id, label) {
            const form = document.getElementById('deleteForm');
            form.action = `/monitoringsm/master-data/${currentTable}/${id}/delete`;
            document.getElementById('deleteTargetName').innerText = label;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Theme management
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateThemeIcon();
        }

        function updateThemeIcon() {
            const isDark = document.documentElement.classList.contains('dark');
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.innerText = isDark ? '☀️' : '🌙';
            }
        }

        (function initTheme() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
            updateThemeIcon();
        })();

        // ESC key closes modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeFormModal();
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
