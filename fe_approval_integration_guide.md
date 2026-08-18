# Panduan Integrasi Frontend (FE) — Mobile-First Dynamic Approval

Dokumen ini adalah panduan khusus untuk tim **Frontend (Mobile / Responsive Web)** dalam mengonsumsi API **Dynamic Approval Document**.

Di layar HP (*smartphone*), menampilkan tabel lebar (banyak kolom) sangat sulit dibaca. Oleh karena itu, panduan ini menggunakan **Pola Desain Mobile-First (Card-based List & Sticky Bottom Actions)**.

---

## 1. Pola Desain UI Mobile-First

```text
┌─────────────────────────────────────────┐
│ [←] Detail Approval          [Status]   │  ← Sticky App Bar
├─────────────────────────────────────────┤
│ 📄 Purchase Order — PO-50001            │
│ Diajukan oleh: Budi (Purchasing)        │
│ Tanggal: 18 Aug 2026                    │
├─────────────────────────────────────────┤
│ 📋 INFORMASI DOKUMEN                    │
│ Supplier      : PT Aneka Kimia Raya     │  ← 2-Column Key-Value Stack
│ Jatuh Tempo   : 25 Aug 2026             │
│ Catatan       : Pengadaan Garam Q3      │
├─────────────────────────────────────────┤
│ 📦 DAFTAR BARANG (3 Item)               │
│ ┌─────────────────────────────────────┐ │
│ │ 1. Garam Kasar (RM-001)             │ │  ← Mobile Item Card
│ │ Qty: 100 TON   |  Harga: Rp 1.25jt  │ │    (Bukan tabel lebar)
│ │ Total: Rp 125.000.000               │ │
│ └─────────────────────────────────────┘ │
│ ┌─────────────────────────────────────┐ │
│ │ 2. Karung Plastik 50kg (PM-002)     │ │
│ │ Qty: 5,000 PCS |  Harga: Rp 5.000   │ │
│ │ Total: Rp 25.000.000                │ │
│ └─────────────────────────────────────┘ │
├─────────────────────────────────────────┤
│ 💰 RINGKASAN BIAYA                      │
│ Subtotal       : Rp 150.000.000         │
│ PPN (11%)      : Rp  16.500.000         │
│ Grand Total    : Rp 166.500.000 (Bold)  │
├─────────────────────────────────────────┤
│ 🕒 RIWAYAT APPROVAL                     │
│ ● Level 1 - Approved (Spv Purchasing)   │  ← Vertical Mobile Timeline
│ ○ Level 2 - Menunggu Direktur Operasi   │
├─────────────────────────────────────────┤
│ [ Minta Revisi ] [ Tolak ] [ Setujui ]  │  ← Sticky Bottom Action Bar
└─────────────────────────────────────────┘
```

---

## 2. Katalog API Endpoints

**Base URL:** `/api/distributor-channel/v1/document-approval`

| No | Aksi | Method | Endpoint | Keterangan |
|:---|:---|:---|:---|:---|
| 1 | **Daftar Approval** | `GET` | `/approvals` | List pengajuan approval (`status`, `search`, `page`) |
| 2 | **Detail Approval** | `GET` | `/approvals/:id` | Mengambil data lengkap header, lines, summary, & timeline |
| 3 | **Approve Dokumen** | `POST` | `/approvals/:id/approve` | Menyetujui dokumen (`body: { notes?: string }`) |
| 4 | **Reject Dokumen** | `POST` | `/approvals/:id/reject` | Menolak dokumen (`body: { reason: string }`) |
| 5 | **Revise Dokumen** | `POST` | `/approvals/:id/revise` | Minta revisi ke pemohon (`body: { notes: string }`) |

---

## 3. Format Response JSON Backend

```json
{
  "success": true,
  "data": {
    "approval": {
      "id": 1,
      "status": "PENDING",
      "currentLevel": 2,
      "maxLevel": 3,
      "requester": { "id": 2, "name": "Purchasing Officer Balaraja" },
      "submittedAt": "2026-08-16 09:15:00"
    },
    "document": {
      "typeCode": "PO",
      "typeName": "Purchase Order",
      "docNum": "PO-50001",
      "currency": "IDR"
    },
    "header": [
      { "field": "DocNum", "label": "No. PO", "displayValue": "PO-50001" },
      { "field": "DocDate", "label": "Tanggal", "displayValue": "18 Aug 2026" },
      { "field": "CardCode", "label": "Supplier", "displayValue": "PT Aneka Kimia Raya" },
      { "field": "Comments", "label": "Catatan", "displayValue": "Pengadaan Garam Kasar Import Q3" }
    ],
    "lines": {
      "columns": [
        { "field": "ItemCode", "label": "Kode Barang" },
        { "field": "ItemDescription", "label": "Nama Barang" },
        { "field": "Quantity", "label": "Qty" },
        { "field": "UnitMsr", "label": "Satuan" },
        { "field": "Price", "label": "Harga" },
        { "field": "LineTotal", "label": "Total" }
      ],
      "data": [
        {
          "ItemCode": { "displayValue": "RM-SALT-RAW" },
          "ItemDescription": { "displayValue": "Garam Kasar (Raw Solar Salt)" },
          "Quantity": { "displayValue": "100" },
          "UnitMsr": { "displayValue": "TON" },
          "Price": { "displayValue": "Rp 1.250.000" },
          "LineTotal": { "displayValue": "Rp 125.000.000" }
        }
      ],
      "totalRows": 1
    },
    "summary": [
      { "field": "SubTotal", "label": "Subtotal", "displayValue": "Rp 125.000.000" },
      { "field": "VatSum", "label": "PPN (11%)", "displayValue": "Rp 13.750.000" },
      { "field": "DocTotal", "label": "Grand Total", "displayValue": "Rp 138.750.000", "ui": { "is_highlight": true } }
    ],
    "approvalHistory": [
      {
        "id": 1,
        "level": 1,
        "stageName": "Submission",
        "action": "SUBMIT",
        "userName": "Purchasing Staff",
        "notes": "Pengajuan PO",
        "actionAt": "2026-08-16 09:15:00"
      },
      {
        "id": 2,
        "level": 1,
        "stageName": "Review Spv",
        "action": "APPROVE",
        "userName": "Supervisor Purchasing",
        "notes": "Spesifikasi sesuai kuota Q3",
        "actionAt": "2026-08-17 14:30:00"
      }
    ]
  }
}
```

---

## 4. Contoh Komponen Mobile React (`MobileApprovalDetail.jsx`)

Komponen di bawah ini dioptimalkan khusus layar HP:

```jsx
import React, { useState, useEffect } from 'react';
import { Card, Badge, Button, Modal, Form, Spinner } from 'react-bootstrap';
import axios from 'axios';

export default function MobileApprovalDetail({ approvalId, onBack, onComplete }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [modal, setModal] = useState({ show: false, type: '', title: '', notes: '' });
  const [submitting, setSubmitting] = useState(false);

  const fetchDetail = async () => {
    try {
      setLoading(true);
      const res = await axios.get(`/api/distributor-channel/v1/document-approval/approvals/${approvalId}`);
      setData(res.data.data);
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal memuat detail approval');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (approvalId) fetchDetail();
  }, [approvalId]);

  const handleAction = async () => {
    try {
      setSubmitting(true);
      const endpoint = `/api/distributor-channel/v1/document-approval/approvals/${approvalId}/${modal.type}`;
      const payload = modal.type === 'reject' ? { reason: modal.notes } : { notes: modal.notes };
      
      await axios.post(endpoint, payload);
      setModal({ show: false, type: '', title: '', notes: '' });
      fetchDetail();
      if (onComplete) onComplete();
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal memproses aksi');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="d-flex flex-column align-items-center justify-content-center py-5">
        <Spinner animation="border" variant="primary" />
        <span className="mt-2 text-muted small">Memuat dokumen...</span>
      </div>
    );
  }

  if (!data) return null;

  const { approval, document, header, lines, summary, approvalHistory } = data;

  return (
    <div className="mobile-approval-page pb-5" style={{ background: '#f8f9fa', minHeight: '100vh', paddingBottom: '90px' }}>
      
      {/* 1. TOP HEADER APP BAR */}
      <div className="bg-white px-3 py-2 border-bottom d-flex justify-content-between align-items-center sticky-top shadow-sm">
        <Button variant="link" className="p-0 text-dark text-decoration-none" onClick={onBack}>
          ← Kembali
        </Button>
        <Badge bg={approval.status === 'APPROVED' ? 'success' : approval.status === 'REJECTED' ? 'danger' : 'warning'} className="px-2 py-1">
          {approval.status} (Lvl {approval.currentLevel}/{approval.maxLevel})
        </Badge>
      </div>

      <div className="p-3">
        {/* 2. DOKUMEN INFO BANNER */}
        <Card className="border-0 shadow-sm rounded-3 mb-3">
          <Card.Body className="p-3">
            <h6 className="fw-bold text-primary mb-1">{document.typeName}</h6>
            <div className="fs-5 fw-bold text-dark mb-1">{document.docNum}</div>
            <div className="text-muted small">
              Pemohon: <strong>{approval.requester?.name || '-'}</strong>
            </div>
            <div className="text-muted small">
              Diajukan: {approval.submittedAt || '-'}
            </div>
          </Card.Body>
        </Card>

        {/* 3. DYNAMIC HEADER INFO (2-COLUMN STACK) */}
        <Card className="border-0 shadow-sm rounded-3 mb-3">
          <Card.Header className="bg-white border-0 fw-bold pt-3 pb-2 text-secondary small">
            INFORMASI DOKUMEN
          </Card.Header>
          <Card.Body className="pt-0 px-3 pb-3">
            {header.map((field) => (
              <div key={field.field} className="d-flex justify-content-between py-2 border-bottom border-light">
                <span className="text-muted small">{field.label}</span>
                <span className="fw-semibold text-dark text-end small" style={{ maxWidth: '60%' }}>
                  {field.displayValue || '-'}
                </span>
              </div>
            ))}
          </Card.Body>
        </Card>

        {/* 4. DYNAMIC ITEM CARDS (MOBILE-FRIENDLY REPLACEMENT FOR WIDE TABLE) */}
        <div className="mb-2 d-flex justify-content-between align-items-center">
          <span className="fw-bold text-secondary small">RINCIAN BARANG ({lines.totalRows})</span>
        </div>

        {lines.data.map((row, idx) => (
          <Card key={idx} className="border-0 shadow-sm rounded-3 mb-2">
            <Card.Body className="p-3">
              <div className="d-flex justify-content-between align-items-start mb-1">
                <div className="fw-bold text-dark">
                  {row.ItemDescription?.displayValue || row.ItemCode?.displayValue || `Item #${idx + 1}`}
                </div>
              </div>
              
              {row.ItemCode && row.ItemDescription && (
                <div className="text-muted small mb-2">{row.ItemCode.displayValue}</div>
              )}

              <div className="d-flex justify-content-between align-items-center bg-light p-2 rounded-2 mt-2">
                <span className="small text-muted">
                  Qty: <strong>{row.Quantity?.displayValue || '0'} {row.UnitMsr?.displayValue || ''}</strong>
                  {row.Price && ` × ${row.Price.displayValue}`}
                </span>
                <span className="fw-bold text-primary small">
                  {row.LineTotal?.displayValue || row.Price?.displayValue || '-'}
                </span>
              </div>
            </Card.Body>
          </Card>
        ))}

        {/* 5. SUMMARY / TOTALS */}
        <Card className="border-0 shadow-sm rounded-3 my-3">
          <Card.Body className="p-3">
            {summary.map((sum) => (
              <div
                key={sum.field}
                className={`d-flex justify-content-between py-1 ${
                  sum.ui?.is_highlight ? 'border-top pt-2 mt-2 fw-bold text-dark fs-6' : 'text-muted small'
                }`}
              >
                <span>{sum.label}</span>
                <span className={sum.ui?.is_highlight ? 'text-primary' : ''}>{sum.displayValue}</span>
              </div>
            ))}
          </Card.Body>
        </Card>

        {/* 6. APPROVAL AUDIT TRAIL (VERTICAL TIMELINE) */}
        <Card className="border-0 shadow-sm rounded-3 mb-4">
          <Card.Header className="bg-white border-0 fw-bold pt-3 pb-2 text-secondary small">
            RIWAYAT PERSETUJUAN
          </Card.Header>
          <Card.Body className="pt-0 px-3 pb-3">
            {approvalHistory.length === 0 ? (
              <div className="text-muted small">Belum ada riwayat approval.</div>
            ) : (
              approvalHistory.map((hist, hIdx) => (
                <div key={hist.id} className="d-flex mb-3 position-relative">
                  <div className="me-2 text-center" style={{ width: '24px' }}>
                    <div
                      className={`rounded-circle d-flex align-items-center justify-content-center text-white ${
                        hist.action === 'APPROVE' ? 'bg-success' : hist.action === 'REJECT' ? 'bg-danger' : 'bg-warning'
                      }`}
                      style={{ width: '22px', height: '22px', fontSize: '10px' }}
                    >
                      ✓
                    </div>
                  </div>
                  <div className="flex-grow-1">
                    <div className="fw-bold text-dark small">
                      {hist.userName} <span className="text-muted fw-normal">({hist.userRole || hist.stageName})</span>
                    </div>
                    <div className="text-muted small">{hist.notes || hist.action}</div>
                    <div className="text-muted" style={{ fontSize: '10px' }}>{hist.actionAt}</div>
                  </div>
                </div>
              ))
            )}
          </Card.Body>
        </Card>
      </div>

      {/* 7. STICKY BOTTOM FLOATING ACTION BAR */}
      {approval.status === 'PENDING' && (
        <div
          className="fixed-bottom bg-white border-top p-3 d-flex gap-2 shadow"
          style={{ zIndex: 1050 }}
        >
          <Button
            variant="outline-warning"
            className="flex-fill py-2 fw-semibold btn-sm"
            onClick={() => setModal({ show: true, type: 'revise', title: 'Minta Revisi', notes: '' })}
          >
            Revisi
          </Button>
          <Button
            variant="outline-danger"
            className="flex-fill py-2 fw-semibold btn-sm"
            onClick={() => setModal({ show: true, type: 'reject', title: 'Tolak Dokumen', notes: '' })}
          >
            Tolak
          </Button>
          <Button
            variant="success"
            className="flex-fill py-2 fw-bold btn-sm"
            onClick={() => setModal({ show: true, type: 'approve', title: 'Setujui Dokumen', notes: '' })}
          >
            Setujui
          </Button>
        </div>
      )}

      {/* MODAL DIALOG MOBILE */}
      <Modal show={modal.show} onHide={() => setModal({ ...modal, show: false })} centered size="sm">
        <Modal.Header closeButton>
          <Modal.Title className="fs-6">{modal.title}</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          <Form.Group>
            <Form.Label className="small">{modal.type === 'reject' ? 'Alasan Penolakan (Wajib):' : 'Catatan (Opsional):'}</Form.Label>
            <Form.Control
              as="textarea"
              rows={3}
              placeholder="Ketik catatan..."
              value={modal.notes}
              onChange={(e) => setModal({ ...modal, notes: e.target.value })}
              className="small"
            />
          </Form.Group>
        </Modal.Body>
        <Modal.Footer className="p-2">
          <Button variant="light" size="sm" onClick={() => setModal({ ...modal, show: false })}>
            Batal
          </Button>
          <Button
            variant={modal.type === 'approve' ? 'success' : modal.type === 'reject' ? 'danger' : 'warning'}
            size="sm"
            onClick={handleAction}
            disabled={submitting || (modal.type === 'reject' && !modal.notes.trim())}
          >
            {submitting ? 'Menyimpan...' : 'Konfirmasi'}
          </Button>
        </Modal.Footer>
      </Modal>
    </div>
  );
}
```

---

## 5. Tips UX Mobile untuk Tim FE

1. **Gunakan Card List, Bukan `<table>`**: Di layar HP, tabel dengan 6-8 kolom akan terpotong. Menggunakan komponen Card per item dengan badge Qty dan subtotal jauh lebih nyaman dan profesional.
2. **Sticky Bottom Action Bar**: Letakkan tombol aksi persetujuan di bagian bawah layar (*fixed bottom*) agar mudah ditekan dengan satu jempol tanpa harus scroll ke paling bawah.
3. **Format Angka & Tanggal**: Selalu gunakan `displayValue` dari backend karena sudah diformat ke mata uang Rupiah (`Rp 125.000.000`) dan format tanggal standar (`18 Aug 2026`).
