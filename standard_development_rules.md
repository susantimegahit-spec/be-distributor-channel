# Standar Aturan Pengembangan & Dokumentasi (Standard Development Rules)

Dokumen ini adalah **aturan baku (SOP)** yang wajib ditaati dalam setiap proses pengembangan fitur, modifikasi kode, dan pemeliharaan sistem backend **Distributor Channel - PT Susanti Megah**.

---

## 📌 1. Aturan Wajib Pembaruan Swagger (OpenAPI Spec)

Setiap kali terjadi perubahan pada API Backend, developer / AI Agent **WAJIB langsung memperbarui file Swagger**:
* **File Target:** `resources/docs/openapi.yaml`
* **Kondisi yang Mewajibkan Update Swagger:**
  1. Menambah endpoint route baru (`GET`, `POST`, `PUT`, `DELETE`).
  2. Menambah, mengubah, atau menghapus field pada **Request Body** (termasuk alias parameter).
  3. Mengubah struktur atau menambah field pada **Response Data** (contoh: status code, schema response JSON).
  4. Menambah atau mengubah **Query Parameter** / **Path Parameter** / **Header**.
  5. Menambahkan validasi baru atau enum nilai status.

> [!IMPORTANT]
> Jangan biarkan ada endpoint atau perubahan payload yang tidak tercatat di Swagger. Swagger adalah *single source of truth* bagi tim Frontend dan Mobile.

---

## 📝 2. Aturan Wajib Dokumentasi Markdown (.MD)

Setiap kali membuat fitur baru atau mengubah alur sistem (*business flow*):
1. **Jika Fitur Sudah Memiliki Dokumen `.md` Terkait:**
   * **WAJIB langsung meng-update dokumen `.md` tersebut** agar tetap akurat dan relevan dengan kode terbaru.
2. **Jika Fitur Baru Belum Memiliki Dokumen `.md`:**
   * **WAJIB membuat file `.md` baru** di root repository dengan format penamaan jelas (contoh: `<nama_fitur>_guide.md` atau `<nama_fitur>_flow.md`).
3. **Format Standar Dokumen `.md`:**
   * **Ringkasan Alur & Tujuan Bisnis:** Penjelasan singkat cara kerja fitur.
   * **Diagram Alur (Mermaid Workflow):** Visualisasi alur antar Frontend, Backend, Database, dan Sistem Eksternal (SAP).
   * **Struktur Database:** Tabel header & detail yang digunakan beserta tipe kolom penting.
   * **Spesifikasi API Endpoint:** URL, HTTP Method, Request Body JSON (beserta tipe data & contoh), dan Response JSON.
   * **Tabel Mapping Parameter:** Padanan field antara Frontend / SAP / Backend.

### Daftar Dokumen Panduan yang Tersedia:
* 📦 [`pdo_flow_guide.md`](file:///c:/Project/PT%20SUSANTI/distributor_chnl/pdo_flow_guide.md) ➔ Panduan Alur Production Order (PDO).
* 🛡️ [`rbac_menu_crud_guide.md`](file:///c:/Project/PT%20SUSANTI/distributor_chnl/rbac_menu_crud_guide.md) ➔ Panduan RBAC Menu & Custom Permission Override User.
* 📑 [`dynamic_document_approval.md`](file:///c:/Project/PT%20SUSANTI/distributor_chnl/dynamic_document_approval.md) ➔ Panduan Document Approval & Multi-tier Workflow.
* 🚚 [`ekspedisi_plan.md`](file:///c:/Project/PT%20SUSANTI/distributor_chnl/ekspedisi_plan.md) ➔ Panduan Modul Ekspedisi & Ongkir.
* 📊 [`clickup_n8n_reporting_guide.md`](file:///c:/Project/PT%20SUSANTI/distributor_chnl/clickup_n8n_reporting_guide.md) ➔ Panduan Integrasi ClickUp ➔ n8n ➔ Laravel DB ➔ Looker Studio (Data Studio).
* 🔄 [`integrasi_sap.md`](file:///c:/Project/PT%20SUSANTI/distributor_chnl/integrasi_sap.md) ➔ Panduan Integrasi SAP B1.

---

## 🚫 3. Batasan Repositori & Tim (Scope Constraints)

* **Backend Focus:** Seluruh pekerjaan backend berada di repository `distributor_chnl`.
* **Frontend Isolation:** Repository frontend (`fe-distributor-channel`) dikelola oleh tim FE terpisah dan **TIDAK BOLEH diubah secara langsung oleh tim BE**, kecuali atas instruksi eksplisit.

---

## ⚙️ 4. Standar Validasi Kode (Zero Syntax Errors)

Sebelum pekerjaan dianggap selesai atau di-push:
1. Jalankan pengecekan sintaks PHP:
   ```bash
   php -l <path_to_file.php>
   ```
2. Pastikan tidak ada query error atau migrasi yang tertinggal.
3. Pastikan deployment pipeline GitHub Actions hanya aktif untuk akun resmi yang diotorisasi (`sanjayfirmansyah`).
