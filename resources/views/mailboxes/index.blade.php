@extends('mailboxes.layout')

@section('title', 'Daftar Mailbox')

@section('breadcrumb')
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Daftar Mailbox</span>
@endsection

@section('header_actions')
    <button type="button" class="btn btn-outline" onclick="openImportModal()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Import</span>
    </button>
    <a href="{{ route('mailboxes.export.csv') }}" class="btn btn-emerald">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Download CSV</span>
    </a>
@endsection

@section('content')
    <!-- Filter & Search Toolbar -->
    <div class="card" style="padding: 18px 24px;">
        <form method="GET" action="{{ route('mailboxes.list') }}" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
            <!-- Search -->
            <div style="flex: 1; min-width: 220px;">
                <label class="form-label">Cari Email / Nama PIC</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Ketik email atau nama...">
            </div>

            <!-- Filter Department -->
            <div style="min-width: 180px;">
                <label class="form-label">Departemen</label>
                <select name="department_id" class="form-control">
                    <option value="">Semua Departemen</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Status -->
            <div style="min-width: 150px;">
                <label class="form-label">Status Kuota</label>
                <select name="status" class="form-control">
                    <option value="">Semua Status</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" {{ request('status') == $st ? 'selected' : '' }}>
                            {{ $st }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Sort By -->
            <div style="min-width: 160px;">
                <label class="form-label">Urutkan</label>
                <select name="sort_by" class="form-control">
                    <option value="usage_percentage" {{ request('sort_by', 'usage_percentage') == 'usage_percentage' ? 'selected' : '' }}>% Penggunaan (Tertinggi)</option>
                    <option value="current_usage_bytes" {{ request('sort_by') == 'current_usage_bytes' ? 'selected' : '' }}>Ukuran Storage (Terbesar)</option>
                    <option value="quota_bytes" {{ request('sort_by') == 'quota_bytes' ? 'selected' : '' }}>Total Kuota (Terbesar)</option>
                    <option value="email" {{ request('sort_by') == 'email' ? 'selected' : '' }}>Alamat Email (A-Z)</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <span>Filter</span>
                </button>
                @if(request()->hasAny(['search', 'department_id', 'status', 'sort_by']))
                    <a href="{{ route('mailboxes.list') }}" class="btn btn-outline">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Mailboxes Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="card-header" style="padding: 20px 24px; margin-bottom: 0; border-bottom: 1px solid var(--slate-200);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h3 class="card-title">Daftar Seluruh Mailbox</h3>
                <span style="font-size: 12px; font-weight: 700; background: var(--slate-100); color: var(--slate-600); padding: 3px 9px; border-radius: 9999px;">
                    Total: {{ $mailboxes->total() }} Akun
                </span>
            </div>
            <div style="font-size: 12px; color: var(--slate-500);">
                Menampilkan {{ $mailboxes->firstItem() ?? 0 }} - {{ $mailboxes->lastItem() ?? 0 }} dari {{ $mailboxes->total() }}
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Alamat Email</th>
                        <th>PIC / Pemilik</th>
                        <th>Departemen</th>
                        <th>Penggunaan / Kuota</th>
                        <th>Persentase</th>
                        <th>Status</th>
                        <th>Terakhir Sync</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mailboxes as $box)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--slate-100); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: var(--slate-600);">
                                        @if($box->status === 'CRITICAL')
                                            <span style="color: var(--danger);">!</span>
                                        @elseif($box->status === 'WARNING')
                                            <span style="color: var(--warning);">!</span>
                                        @else
                                            @
                                        @endif
                                    </div>
                                    <div>
                                        <a href="{{ route('mailboxes.show', $box->id) }}" style="font-weight: 700; color: var(--navy); text-decoration: none;">
                                            {{ $box->email }}
                                        </a>
                                        <div style="font-size: 11px; color: var(--slate-400);">Domain: {{ $box->domain }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">{{ $box->user_name ?? '-' }}</div>
                            </td>
                            <td>
                                @if($box->department)
                                    <span style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 12px;">
                                        <span style="width: 8px; height: 8px; border-radius: 50%; background-color: {{ $box->department->color }};"></span>
                                        {{ $box->department->name }}
                                    </span>
                                @else
                                    <span style="color: var(--slate-400); font-size: 12px;">(Belum dipetakan)</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 12.5px;">{{ $box->formatted_usage }} / {{ $box->formatted_quota }}</div>
                                <div class="progress-bar-container" style="margin-top: 4px; width: 140px;">
                                    <div class="progress-bar-fill fill-{{ strtolower($box->status) }}" style="width: {{ min(100, (float)$box->usage_percentage) }}%;"></div>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight: 800; font-size: 13.5px; color: {{ $box->status === 'CRITICAL' ? 'var(--danger)' : ($box->status === 'WARNING' ? 'var(--warning)' : 'inherit') }};">
                                    {{ $box->usage_percentage }}%
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-badge-{{ strtolower($box->status) }}">
                                    {{ $box->status }}
                                </span>
                            </td>
                            <td style="font-size: 12px; color: var(--slate-500);">
                                {{ $box->last_synced_at ? \Carbon\Carbon::parse($box->last_synced_at)->diffForHumans() : '-' }}
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                    <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11.5px;" 
                                            onclick="openEditModal({{ $box->id }}, '{{ addslashes($box->email) }}', '{{ addslashes($box->user_name ?? '') }}', '{{ $box->department_id }}')">
                                        Edit PIC
                                    </button>
                                    <a href="{{ route('mailboxes.show', $box->id) }}" class="btn btn-outline" style="padding: 4px 10px; font-size: 11.5px;">
                                        Detail &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--slate-400);">
                                Tidak ada data mailbox yang sesuai dengan filter pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($mailboxes->hasPages())
            <div style="padding: 16px 24px; border-top: 1px solid var(--slate-200); display: flex; justify-content: center; width: 100%;">
                {{ $mailboxes->links('mailboxes.pagination') }}
            </div>
        @endif
    </div>

    <!-- Modal Edit Mailbox PIC / Department -->
    <div class="modal-overlay" id="editMailboxModal">
        <div class="modal-box">
            <form id="editMailboxForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title">Edit PIC & Departemen</h3>
                    <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--slate-400);">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="margin-bottom: 14px;">
                        <span style="font-size: 12px; color: var(--slate-500);">Email:</span>
                        <div id="modalEmailDisplay" style="font-weight: 800; font-size: 15px; color: var(--navy);"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nama Pemilik / PIC</label>
                        <input type="text" name="user_name" id="modalUserName" class="form-control" placeholder="Contoh: Budi Santoso">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Departemen</label>
                        <select name="department_id" id="modalDepartmentId" class="form-control">
                            <option value="">-- Tanpa Departemen (Unassigned) --</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditModal()">Batal</button>
                    <button type="submit" class="btn btn-emerald">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    function openEditModal(id, email, userName, deptId) {
        document.getElementById('editMailboxForm').action = "{{ url('/monitoringsm/mailboxes') }}/" + id;
        document.getElementById('modalEmailDisplay').innerText = email;
        document.getElementById('modalUserName').value = userName;
        document.getElementById('modalDepartmentId').value = deptId || '';
        document.getElementById('editMailboxModal').classList.add('active');
    }

    function closeEditModal() {
        document.getElementById('editMailboxModal').classList.remove('active');
    }
</script>
@endsection
