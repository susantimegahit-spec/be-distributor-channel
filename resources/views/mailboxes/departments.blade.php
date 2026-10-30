@extends('mailboxes.layout')

@section('title', 'Pemetaan Departemen')

@section('breadcrumb')
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Pemetaan Departemen</span>
@endsection

@section('header_actions')
    <button type="button" class="btn btn-emerald" onclick="openCreateDeptModal()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        <span>Tambah Departemen</span>
    </button>
@endsection

@section('content')
    <!-- Department Overview Banner -->
    <div style="background: #ffffff; border: 1px solid var(--slate-200); border-radius: var(--radius-xl); padding: 22px 28px; box-shadow: var(--shadow-card); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 19px; font-weight: 800; color: var(--navy); margin-bottom: 4px;">Mapping Departemen & Unit Kerja</h1>
            <p style="font-size: 13.5px; color: var(--slate-600); max-width: 650px;">
                Klasifikasikan akun email ke dalam unit kerja untuk mempermudah monitoring alokasi kapasitas storage dan pelaporan anggaran per divisi.
            </p>
        </div>
        <div style="display: flex; gap: 14px;">
            <div style="text-align: right; background: var(--slate-50); padding: 10px 16px; border-radius: var(--radius-md); border: 1px solid var(--slate-200);">
                <span style="font-size: 11.5px; color: var(--slate-500); font-weight: 700;">TOTAL DEPARTEMEN</span>
                <div style="font-size: 20px; font-weight: 800; color: var(--navy);">{{ $departments->count() }} Divisi</div>
            </div>
            @if($unassignedCount > 0)
                <div style="text-align: right; background: #fffbeb; padding: 10px 16px; border-radius: var(--radius-md); border: 1px solid #fde68a;">
                    <span style="font-size: 11.5px; color: #92400e; font-weight: 700;">BELUM DIPETAKAN</span>
                    <div style="font-size: 20px; font-weight: 800; color: #b45309;">{{ $unassignedCount }} Akun</div>
                </div>
            @endif
        </div>
    </div>

    <!-- Departments Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 18px;">
        @foreach($departments as $dept)
            @php
                $usedGb = round($dept->total_used / (1024*1024*1024), 2);
                $quotaGb = round($dept->total_quota / (1024*1024*1024), 2);
            @endphp
            <div class="card" style="padding: 20px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="width: 14px; height: 14px; border-radius: 4px; background-color: {{ $dept->color ?? '#3B82F6' }};"></span>
                            <div>
                                <h3 style="font-size: 16px; font-weight: 800; color: var(--navy); margin: 0;">{{ $dept->name }}</h3>
                                @if($dept->code)
                                    <span style="font-size: 11px; font-weight: 700; color: var(--slate-500);">KODE: {{ $dept->code }}</span>
                                @endif
                            </div>
                        </div>

                        <span style="font-size: 12px; font-weight: 700; background: var(--slate-100); color: var(--slate-700); padding: 4px 10px; border-radius: 9999px;">
                            {{ $dept->mailboxes_count }} Akun
                        </span>
                    </div>

                    @if($dept->description)
                        <p style="font-size: 12.5px; color: var(--slate-500); line-height: 1.4; margin-bottom: 16px;">
                            {{ $dept->description }}
                        </p>
                    @endif

                    <div style="background: var(--slate-50); padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid var(--slate-100); margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: var(--slate-600); margin-bottom: 6px;">
                            <span>Digunakan: <strong>{{ $usedGb }} GB</strong></span>
                            <span>Kapasitas: <strong>{{ $quotaGb }} GB</strong></span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: {{ min(100, $dept->avg_usage) }}%; background-color: {{ $dept->color ?? '#3B82F6' }};"></div>
                        </div>
                        <div style="text-align: right; font-size: 11.5px; font-weight: 700; color: var(--slate-600); margin-top: 4px;">
                            Utilisasi: {{ $dept->avg_usage }}%
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--slate-100); padding-top: 12px; margin-top: 8px;">
                    <a href="{{ route('mailboxes.list', ['department_id' => $dept->id]) }}" style="font-size: 12.5px; font-weight: 600; color: var(--primary); text-decoration: none;">
                        Lihat Mailbox &rarr;
                    </a>

                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11.5px;"
                                onclick="openEditDeptModal({{ $dept->id }}, '{{ addslashes($dept->name) }}', '{{ addslashes($dept->code ?? '') }}', '{{ $dept->color ?? '#3B82F6' }}', '{{ addslashes($dept->description ?? '') }}')">
                            Edit
                        </button>
                        <form action="{{ route('mailboxes.departments.destroy', $dept->id) }}" method="POST" onsubmit="return confirm('Hapus departemen {{ $dept->name }}? Mailbox terkait akan menjadi unassigned.');" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11.5px; color: var(--danger); border-color: #fecaca;">
                                Hapus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Modal Tambah Departemen -->
    <div class="modal-overlay" id="createDeptModal">
        <div class="modal-box">
            <form action="{{ route('mailboxes.departments.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title">Tambah Departemen Baru</h3>
                    <button type="button" onclick="closeCreateDeptModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--slate-400);">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Nama Departemen *</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Sales & Distribution" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kode Singkatan</label>
                        <input type="text" name="code" class="form-control" placeholder="Contoh: SALES">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Warna Label</label>
                        <input type="color" name="color" class="form-control" value="#3B82F6" style="height: 40px; padding: 2px;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deskripsi / Catatan</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Keterangan singkat cakupan unit kerja..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeCreateDeptModal()">Batal</button>
                    <button type="submit" class="btn btn-emerald">Simpan Departemen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Departemen -->
    <div class="modal-overlay" id="editDeptModal">
        <div class="modal-box">
            <form id="editDeptForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title">Edit Departemen</h3>
                    <button type="button" onclick="closeEditDeptModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--slate-400);">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Nama Departemen *</label>
                        <input type="text" name="name" id="editDeptName" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kode Singkatan</label>
                        <input type="text" name="code" id="editDeptCode" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Warna Label</label>
                        <input type="color" name="color" id="editDeptColor" class="form-control" style="height: 40px; padding: 2px;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" id="editDeptDesc" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditDeptModal()">Batal</button>
                    <button type="submit" class="btn btn-emerald">Update Departemen</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    function openCreateDeptModal() {
        document.getElementById('createDeptModal').classList.add('active');
    }
    function closeCreateDeptModal() {
        document.getElementById('createDeptModal').classList.remove('active');
    }

    function openEditDeptModal(id, name, code, color, desc) {
        document.getElementById('editDeptForm').action = "{{ url('/monitoringsm/mailboxes/departments') }}/" + id;
        document.getElementById('editDeptName').value = name;
        document.getElementById('editDeptCode').value = code;
        document.getElementById('editDeptColor').value = color;
        document.getElementById('editDeptDesc').value = desc;
        document.getElementById('editDeptModal').classList.add('active');
    }
    function closeEditDeptModal() {
        document.getElementById('editDeptModal').classList.remove('active');
    }
</script>
@endsection
