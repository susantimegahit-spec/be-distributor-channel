@extends('mailboxes.layout')

@section('title', 'Rekomendasi Penambahan Kuota')

@section('breadcrumb')
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Rekomendasi Kuota</span>
@endsection

@section('content')
    <!-- Banner & Disclaimer -->
    <div style="background: #ffffff; border: 1px solid var(--slate-200); border-radius: var(--radius-xl); padding: 22px 28px; box-shadow: var(--shadow-card); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                <h1 style="font-size: 19px; font-weight: 800; color: var(--navy); margin: 0;">Pusat Rekomendasi Kapasitas Mailbox</h1>
                <span style="font-size: 11px; font-weight: 700; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 2px 7px; border-radius: 6px;">
                    Read-Only Safety
                </span>
            </div>
            <p style="font-size: 13.5px; color: var(--slate-600); max-width: 700px; line-height: 1.5;">
                Rekomendasi dihitung berdasarkan batas ambang pemakaian (&gt;85% Warning dan &gt;95% Critical) serta laju pertumbuhan email. Rekomendasi bersifat usulan untuk mempermudah perizinan IT dan <strong>tidak langsung mengubah konfigurasi cPanel</strong> tanpa tindakan manual admin.
            </p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div style="display: flex; gap: 10px; border-bottom: 1px solid var(--slate-200); padding-bottom: 4px; overflow-x: auto;">
        <a href="{{ route('mailboxes.recommendations', ['status' => 'PENDING']) }}" 
           class="btn {{ $status === 'PENDING' ? 'btn-primary' : 'btn-outline' }}" style="padding: 7px 14px; font-size: 12.5px;">
            <span>Menunggu Review (Pending)</span>
            <span style="background: rgba(255,255,255,0.25); padding: 1px 6px; border-radius: 9999px; font-size: 11px; margin-left: 4px;">
                {{ $counts['PENDING'] }}
            </span>
        </a>

        <a href="{{ route('mailboxes.recommendations', ['status' => 'APPROVED']) }}" 
           class="btn {{ $status === 'APPROVED' ? 'btn-primary' : 'btn-outline' }}" style="padding: 7px 14px; font-size: 12.5px;">
            <span>Disetujui IT (Approved)</span>
            <span style="background: rgba(255,255,255,0.25); padding: 1px 6px; border-radius: 9999px; font-size: 11px; margin-left: 4px;">
                {{ $counts['APPROVED'] }}
            </span>
        </a>

        <a href="{{ route('mailboxes.recommendations', ['status' => 'APPLIED']) }}" 
           class="btn {{ $status === 'APPLIED' ? 'btn-primary' : 'btn-outline' }}" style="padding: 7px 14px; font-size: 12.5px;">
            <span>Telah Diterapkan di Server (Applied)</span>
            <span style="background: rgba(255,255,255,0.25); padding: 1px 6px; border-radius: 9999px; font-size: 11px; margin-left: 4px;">
                {{ $counts['APPLIED'] }}
            </span>
        </a>

        <a href="{{ route('mailboxes.recommendations', ['status' => 'DISMISSED']) }}" 
           class="btn {{ $status === 'DISMISSED' ? 'btn-primary' : 'btn-outline' }}" style="padding: 7px 14px; font-size: 12.5px;">
            <span>Diabaikan (Dismissed)</span>
            <span style="background: rgba(255,255,255,0.25); padding: 1px 6px; border-radius: 9999px; font-size: 11px; margin-left: 4px;">
                {{ $counts['DISMISSED'] }}
            </span>
        </a>

        <a href="{{ route('mailboxes.recommendations', ['status' => 'ALL']) }}" 
           class="btn {{ $status === 'ALL' ? 'btn-primary' : 'btn-outline' }}" style="padding: 7px 14px; font-size: 12.5px;">
            <span>Semua Riwayat</span>
        </a>
    </div>

    <!-- Recommendations Cards List -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        @forelse($recommendations as $rec)
            @php
                $mailbox = $rec->mailbox;
                $currentGb = round($rec->current_quota_bytes / (1024*1024*1024), 2);
                $recGb = round($rec->recommended_quota_bytes / (1024*1024*1024), 2);
                $deltaGb = $recGb - $currentGb;
            @endphp
            <div class="card" style="padding: 22px; border-left: 5px solid {{ $mailbox && $mailbox->status === 'CRITICAL' ? 'var(--danger)' : 'var(--warning)' }};">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                            <span style="font-weight: 800; font-size: 17px; color: var(--navy);">
                                {{ $mailbox ? $mailbox->email : 'Mailbox Tidak Ditemukan' }}
                            </span>
                            @if($mailbox)
                                <span class="status-badge status-badge-{{ strtolower($mailbox->status) }}">
                                    {{ $mailbox->status }}
                                </span>
                            @endif
                            <span class="status-badge" style="background: {{ $rec->status === 'PENDING' ? '#fffbeb' : ($rec->status === 'APPROVED' ? '#ecfdf5' : '#f1f5f9') }};">
                                STATUS: {{ $rec->status }}
                            </span>
                        </div>

                        <div style="font-size: 13px; color: var(--slate-600); display: flex; gap: 14px; margin-bottom: 12px;">
                            <span><strong>PIC:</strong> {{ $mailbox->owner_name ?? '-' }}</span>
                            <span><strong>Departemen:</strong> {{ $mailbox && $mailbox->department ? $mailbox->department->name : 'Unassigned' }}</span>
                            <span><strong>Dibuat:</strong> {{ $rec->created_at ? $rec->created_at->isoFormat('D MMMM Y, HH:mm') : '-' }}</span>
                        </div>

                        <div style="background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 12px 16px; max-width: 800px; font-size: 13px; color: var(--slate-700); line-height: 1.5;">
                            <strong>Alasan & Dasar Pertimbangan:</strong><br>
                            {{ $rec->reason }}
                        </div>
                    </div>

                    <!-- Quota Comparison Box -->
                    <div style="text-align: right; background: #f8fafc; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); padding: 14px 20px; min-width: 200px;">
                        <div style="font-size: 11px; font-weight: 700; color: var(--slate-500); text-transform: uppercase;">Penyesuaian Kuota</div>
                        <div style="display: flex; align-items: baseline; justify-content: flex-end; gap: 8px; margin-top: 6px;">
                            <span style="font-size: 16px; color: var(--slate-500); text-decoration: line-through;">{{ $currentGb }} GB</span>
                            <span style="font-size: 24px; font-weight: 800; color: var(--primary);">{{ $recGb }} GB</span>
                        </div>
                        <div style="font-size: 12px; font-weight: 700; color: var(--success); margin-top: 2px;">
                            +{{ $deltaGb }} GB Tambahan
                        </div>
                    </div>
                </div>

                <!-- Action Controls -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--slate-100); padding-top: 14px; margin-top: 18px; flex-wrap: wrap; gap: 10px;">
                    <div>
                        @if($mailbox)
                            <a href="{{ route('mailboxes.show', $mailbox->id) }}" style="font-size: 12.5px; font-weight: 600; color: var(--primary); text-decoration: none;">
                                Lihat Tren Mailbox Ini &rarr;
                            </a>
                        @endif
                    </div>

                    <div style="display: flex; gap: 8px;">
                        @if($rec->status === 'PENDING')
                            <form action="{{ route('mailboxes.recommendations.update', $rec->id) }}" method="POST" style="display: inline;">
                                @csrf
                                <input type="hidden" name="status" value="APPROVED">
                                <button type="submit" class="btn btn-emerald" style="padding: 6px 12px; font-size: 12px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <span>Setujui (Approve)</span>
                                </button>
                            </form>

                            <form action="{{ route('mailboxes.recommendations.update', $rec->id) }}" method="POST" style="display: inline;">
                                @csrf
                                <input type="hidden" name="status" value="DISMISSED">
                                <button type="submit" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px; color: var(--slate-600);" onclick="return confirm('Abaikan rekomendasi ini?');">
                                    <span>Abaikan</span>
                                </button>
                            </form>
                        @elseif($rec->status === 'APPROVED')
                            <form action="{{ route('mailboxes.recommendations.update', $rec->id) }}" method="POST" style="display: inline;">
                                @csrf
                                <input type="hidden" name="status" value="APPLIED">
                                <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                    <span>Tandai Selesai di cPanel (Applied)</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="card" style="text-align: center; padding: 48px; color: var(--slate-400);">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 12px; opacity: 0.5;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                <div style="font-size: 15px; font-weight: 700; color: var(--slate-600);">Tidak ada rekomendasi dalam kategori ini</div>
                <p style="font-size: 13px; margin-top: 4px;">Seluruh mailbox berada dalam batas aman atau rekomendasi telah diproses.</p>
            </div>
        @endforelse
    </div>

    @if($recommendations->hasPages())
        <div style="display: flex; justify-content: center; margin-top: 20px;">
            {{ $recommendations->links() }}
        </div>
    @endif
@endsection
