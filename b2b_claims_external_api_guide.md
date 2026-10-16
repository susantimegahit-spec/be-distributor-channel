# 🔌 Panduan B2B API Klaim Distributor (External Claim Submission)

Dokumen ini menjelaskan spesifikasi teknis dan alur integrasi **B2B API Klaim Promosi Distributor** untuk sistem eksternal distributor (ERP/DMS) yang terintegrasi langsung dengan platform PT Susanti Megah (SMESTA).

---

## 1. Ringkasan & Aturan Bisnis

1. **Keamanan & Autentikasi (Sama dengan API Public CMO):**
   - Menggunakan API Key Bearer Token (`Authorization: Bearer <API_KEY>`) atau request header `X-API-Key`.
   - API Key terikat dengan relasi resmi distributor (`code_customer` / `card_code`).
   - Dilengkapi proteksi **IP Whitelisting** (jika dikonfigurasikan pada master API Key) dan pembatasan frekuensi akses (**Rate Limiting** `throttle:60,1`).
2. **Kontrol Idempotency:**
   - Menggunakan field `distributor_ref_no` (Nomor Referensi Unik Klaim Distributor).
   - Jika distributor mengirim ulang request dengan `distributor_ref_no` yang sama untuk distributor yang sama, API akan merespon dengan status `200 OK` (`is_duplicate: true`) dengan data ringkasan batch yang sudah ada, tanpa menduplikasi batch maupun baris transaksi di database.
3. **Aturan Bisnis Pengajuan Klaim (Sesuai Web SMESTA):**
   - **`card_code`:** Wajib diisi dengan kode distributor resmi dan harus terdaftar di bawah wewenang API Key pengirim.
   - **`item_code`:** Wajib berupa kode item resmi yang terdaftar di master item PT Susanti Megah.
   - **`qty_kg`:** Kuantitas penjualan dalam satuan Kilogram (wajib berupa angka lebih besar dari 0, `qty_kg > 0`).
   - **`customer_type`:** Tipe customer penjualan distributor, wajib bernilai `'GT'` (*General Trade*) atau `'MT'` (*Modern Trade*).
   - **`transaction_date`:** Tanggal transaksi penjualan distributor dengan format `YYYY-MM-DD`.
   - **`sell_price_per_kg`:** Harga jual distributor per kilogram (opsional).
4. **Mesin Kalkulasi Otomatis (Automatic Calculation Engine):**
   - Setiap data yang dikirim akan otomatis dibuatkan nomor batch (`UPLOAD-YYYYMMDD-XXX`) dan langsung dikalkulasi terhadap `MstProgram` yang aktif pada tanggal transaksi tersebut.
   - Sistem mencocokkan kuantiti dengan strata program (`MstProgramStrata`) berdasarkan `customer_type` dan menghitung `diskon_per_kg` serta `total_diskon`.
   - Status kalkulasi per item akan disimpan:
     - `VALID_PROGRAM`: Transaksi memenuhi syarat program dan strata, diskon berhasil dihitung.
     - `ITEM_NOT_FOUND`: SKU item tidak terdaftar.
     - `PROGRAM_NOT_FOUND`: Tidak ada program promo aktif untuk item dan periode tanggal tersebut.
     - `STRATA_NOT_FOUND`: Kuantitas tidak masuk ke dalam rentang strata program.
5. **Dukungan Format Pengiriman:**
   - **JSON Body:** Payload langsung melalui format JSON (`application/json`) dengan array `lines`.
   - **Excel Upload:** Melalui `multipart/form-data` dengan mengunggah file spreadsheet Excel (.xlsx / .xls) template klaim resmi distributor.

---

## 2. Alur Proses (Sequence Diagram)

```mermaid
sequenceDiagram
    autonumber
    actor Dist as Sistem Distributor (ERP/DMS)
    participant API as B2B Claim API (/v1/external/claims)
    participant Svc as UploadService & CalculationEngine
    participant DB as Database (SMESTA)

    Dist->>API: POST /v1/external/claims (card_code, distributor_ref_no, lines / file)
    Note over API: Cek Bearer API Key, IP Whitelist & Hak Akses Distributor
    API->>API: Validasi Input (item_code, qty_kg > 0, customer_type GT/MT, date)
    API->>DB: Cek Idempotency (distributor_ref_no & card_code)
    alt Ref No Sudah Ada (Duplikat)
        DB-->>API: Batch Ditemukan
        API-->>Dist: 200 OK (Idempotent Response, is_duplicate: true)
    else Ref No Baru
        API->>Svc: processTransactions(rows, uploadedBy, fileName, refNo)
        Svc->>DB: Simpan Batch (trx_program_upload_batch)
        Svc->>DB: Simpan Baris Transaksi (trx_program_upload)
        Svc->>Svc: Jalankan Kalkulasi Klaim (calculateBatch)
        Svc->>DB: Simpan Hasil Kalkulasi (trx_program_result)
        DB-->>API: Kalkulasi Selesai & Ringkasan Tersedia
        API-->>Dist: 201 Created (Data Klaim Berhasil Dibuat & Dikalkulasi)
    end
```

---

## 3. Spesifikasi Endpoint

### **A. `POST /api/distributor-channel/v1/external/claims`**
Menambahkan data transaksi klaim distributor dan menjalankan kalkulasi diskon otomatis.

#### Request Headers
| Key | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `Authorization` | String | **Ya** | `Bearer <API_KEY>` |
| `Content-Type` | String | **Ya** | `application/json` atau `multipart/form-data` |
| `Accept` | String | **Ya** | `application/json` |

#### Request Body Fields
| Parameter | Tipe Data | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `card_code` | String | **Ya** | Kode Customer/Distributor terdaftar yang berhak pada API Key ini (misal `C110003074`) |
| `distributor_ref_no` | String | **Ya** | Nomor Referensi / Nomor Dokumen Klaim Distributor (**Idempotency Key**) |
| `lines` | Array / JSON String | **Kondisional** | Wajib diisi jika tidak mengunggah file Excel. Berisi daftar baris transaksi klaim. |
| `file` | File (Binary) | **Kondisional** | Wajib diisi jika `lines` kosong. File spreadsheet Excel (.xlsx / .xls, maks. 10 MB). |

#### Struktur Baris Objek `lines`
| Parameter Line | Tipe Data | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `item_code` | String | **Ya** | Kode SKU barang terdaftar di PT Susanti Megah (misal `E65`) |
| `qty_kg` | Numeric | **Ya** | Kuantitas transaksi dalam Kilogram (harus > 0) |
| `customer_type` | String | **Ya** | Tipe customer penjualan distributor: `GT` atau `MT` |
| `transaction_date` | Date | **Ya** | Tanggal transaksi penjualan distributor (`YYYY-MM-DD`) |
| `sell_price_per_kg` | Numeric | Tidak | Harga jual distributor per kilogram (default `0`) |

---

### **B. `GET /api/distributor-channel/v1/external/claims/batches`**
Mendapatkan daftar riwayat batch upload klaim distributor beserta metrik ringkasan diskon dan status saldo.

#### Query Parameters
| Parameter | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `card_code` | String | Tidak | Filter khusus satu kode distributor (harus dalam izin API Key) |
| `per_page` | Integer | Tidak | Jumlah baris per halaman (default: `15`) |

---

### **C. `GET /api/distributor-channel/v1/external/claims/batches/{identifier}`**
Mendapatkan detail batch dan rincian baris hasil kalkulasi diskon program.

#### Path Parameter
| Parameter | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `identifier` | String / Int | **Ya** | ID batch (misal `15`), nomor batch resmi (`UPLOAD-20261016-001`), atau nomor referensi distributor (`distributor_ref_no`) |

---

### **D. `GET /api/distributor-channel/v1/external/claims/programs`**
Mendapatkan daftar program promosi klaim yang sedang aktif beserta strata kuantiti dan nilai diskon per kg yang berlaku untuk distributor.

---

## 4. Contoh Request & Response

### 1. Request via JSON Body (cURL)
```bash
curl -X POST "https://api-dev.susantimegah.com/api/distributor-channel/v1/external/claims" \
  -H "Authorization: Bearer susanti_claim_sec_xxxxxx" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "card_code": "C110003074",
    "distributor_ref_no": "CLM-DIST-202610-001",
    "lines": [
      {
        "item_code": "E65",
        "qty_kg": 150.00,
        "customer_type": "GT",
        "transaction_date": "2026-10-15",
        "sell_price_per_kg": 7500.00
      }
    ]
  }'
```

### 2. Request via File Excel (cURL)
```bash
curl -X POST "https://api-dev.susantimegah.com/api/distributor-channel/v1/external/claims" \
  -H "Authorization: Bearer susanti_claim_sec_xxxxxx" \
  -H "Accept: application/json" \
  -F "card_code=C110003074" \
  -F "distributor_ref_no=CLM-DIST-202610-002" \
  -F "file=@/path/to/template_upload_klaim.xlsx"
```

### 3. Response Berhasil Dibuat & Dikalkulasi (201 Created)
```json
{
  "success": true,
  "message": "Data transaksi klaim distributor berhasil dikirim dan dikalkulasi secara otomatis.",
  "data": {
    "batch_id": 15,
    "batch_no": "UPLOAD-20261016-001",
    "distributor_ref_no": "CLM-DIST-202610-001",
    "card_code": "C110003074",
    "customer_name": "PT SUSANTI DISTRIBUSI MAKMUR",
    "total_rows": 1,
    "processed_rows": 1,
    "invalid_rows": 0,
    "total_diskon": 37500.00
  }
}
```

### 4. Response Idempotent Duplicate (200 OK)
Jika request dikirim ulang dengan `distributor_ref_no` yang sama:
```json
{
  "success": true,
  "message": "Klaim dengan distributor_ref_no ini sudah pernah dibuat sebelumnya (Idempotent Response).",
  "is_duplicate": true,
  "data": {
    "batch_id": 15,
    "batch_no": "UPLOAD-20261016-001",
    "distributor_ref_no": "CLM-DIST-202610-001",
    "total_rows": 1,
    "valid_rows": 1,
    "invalid_rows": 0,
    "total_diskon": 37500.00,
    "total_diskon_verified": 0.00,
    "available_balance": 0.00
  }
}
```

---

## 5. Ringkasan Kode Error (HTTP Status Codes)

| Status Code | Kondisi Penyebab | Solusi |
| :--- | :--- | :--- |
| **`401 Unauthorized`** | Header `Authorization: Bearer <API_KEY>` kosong atau token tidak valid | Periksa kredensial API Key pada header request |
| **`403 Forbidden`** | `card_code` tidak terdaftar atau tidak terhubung dengan API Key | Gunakan `card_code` yang telah di-mapping ke API Key |
| **`422 Unprocessable`** | Validasi input gagal (`qty_kg <= 0`, SKU tidak ditemukan, `customer_type` bukan GT/MT) | Perbaiki payload request sesuai pesan detail error pada response |
| **`404 Not Found`** | Batch klaim tidak ditemukan atau bukan milik distributor terkait | Periksa nomor batch atau nomor referensi distributor |
