---
trigger: always_on
description: Standard Development & Documentation Rules for PT Susanti Megah Backend
---

# Standar Rule Pengembangan & Dokumentasi (Mandatory AI Agent Guidelines)

1. **Swagger Update Mandatory:**
   - Setiap kali membuat/mengubah route endpoint, request payload, response structure, atau parameter query/path, WAJIB langsung memperbarui `resources/docs/openapi.yaml`.

2. **Markdown Documentation Mandatory:**
   - Setiap kali membuat fitur baru atau mengubah alur bisnis (flow):
     - Jika file dokumentasi `.md` terkait sudah ada, WAJIB meng-update file `.md` tersebut.
     - Jika file dokumentasi `.md` belum ada, WAJIB membuat file `.md` baru di root repository (contoh: `<feature_name>_guide.md` atau `<feature_name>_flow.md`) yang berisi penjelasan alur, struktur database, diagram mermaid, dan spesifikasi API endpoint.

3. **Frontend Isolation:**
   - Jangan melakukan modifikasi pada workspace `fe-distributor-channel` kecuali diminta secara eksplisit oleh user.

4. **Code Quality:**
   - Selalu periksa sintaks PHP (`php -l`) pada setiap file yang dimodifikasi.

5. **SAP Empty / Dummy Response Handling (Data Not Found):**
   - Setiap API yang mengonsumsi endpoint SAP B1, jika SAP mengembalikan data kosong atau baris dummy `0` (contoh: array berisi object dengan string kosong / `ItemCode: ""`, `DocEntry: "0"`, `AbsEntry: ""`, `SisaQty: "0.000000"`), backend WAJIB memfilter baris dummy tersebut dan mengembalikan respon standar dengan pesan `'Data not found.'` serta payload kosong (`[]` atau `['header' => null, 'items' => []]`).

6. **API Route Cleanliness (No Duplicate Endpoints):**
   - Gunakan satu format URL endpoint RESTful standar (`kebab-case`) yang konsisten dan terpusat di masing-masing modul route.
   - DILARANG membuat multiple route alias yang menduplikasi fungsionalitas yang sama (contoh: hindari mendaftarkan `/stock-by-item`, `/get-stock-by-item`, `/get-stok-by-item` sekaligus) kecuali secara eksplisit diminta oleh user.
   - Jika endpoint membutuhkan dukungan method `GET` dan `POST` sekaligus, gunakan `Route::match(['get', 'post'], ...)` pada satu baris route tunggal yang rapi.

7. **Clean & Normalized Parameters (No Duplicate Keys in Payload / Response):**
   - **Request Normalization:** Jika backend mendukung fleksibilitas penamaan parameter input dari FE (contoh: menerima `whs_code` atau `WhsCode`), segera lakukan normalisasi di awal dan lakukan pembersihan (`unset`) terhadap alias keys sebelum data diproses lebih lanjut.
   - **Single Canonical Response Key:** DILARANG mengembalikan key ganda dengan variasi casing/penamaan yang berbeda dalam satu JSON object respon (contoh: jangan mengirimkan `'unit'`, `'units'`, `'u_unit'`, `'U_Unit'` sekaligus di satu object). Respon WAJIB bersih dan hanya menggunakan satu key kanonikal yang telah disepakati.
