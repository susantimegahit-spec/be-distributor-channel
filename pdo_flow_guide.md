# Dokumentasi Alur & Integrasi Production Order (PDO)

Dokumen ini menjelaskan alur kerja (*business flow*), skema database, dan integrasi API untuk modul **Production Order (PDO)** pada sistem **Distributor Channel - PT Susanti Megah**.

---

## 1. Ringkasan Alur Baru PDO (Hybrid: Local DB + SAP Sync)

Sistem menggunakan alur bertahap (*staged workflow*) agar proses pembuatan rencana produksi lebih cepat dan aman sebelum dirilis ke SAP B1:

1. **Pembuatan PDO (Status: `PLANNED`):**
   * Saat user membuat / menyimpan PDO pertama kali dari Frontend, data disimpan langsung ke **Database Lokal** (`production_orders` & `production_order_items`) dengan status **`PLANNED`**.
   * Pada tahap ini, data **TIDAK langsung dikirim ke SAP B1**, sehingga user bebas mengedit atau merevisi bahan/komponen.
2. **Monitoring & Tampilan (List & Detail):**
   * Endpoint **Get List PDO** menggabungkan (*merge*) data PDO lokal yang masih `PLANNED` dengan PDO yang sudah ada di SAP.
   * Endpoint **Get Detail PDO** otomatis membaca data dari database lokal jika status masih `PLANNED`, atau mengambil dari SAP jika sudah tersinkron.
3. **Revisi / Edit PDO (Header & Detail Lines):**
   * User dapat mengedit informasi Header (gudang, tanggal, jumlah, dll.) maupun baris Detail bahan baku (`Lines`).
4. **Perilisan ke SAP (Status: `RELEASED`):**
   * Saat status PDO diubah menjadi **`RELEASED`**, backend secara otomatis menembakkan payload ke API SAP B1 (`/api/addpdo`), menerima `DocEntry` & `DocNum`, serta mengupdate `sap_status = 'SYNCED'` di database lokal.

---

## 2. Diagram Alur (Workflow Diagram)

```mermaid
flowchart TD
    A[Frontend: Form Buat PDO] -->|Submit dengan status PLANNED| B[POST /production/add-pdo-sap]
    B --> C[(Simpan ke Database Lokal: production_orders & items)]
    C --> D[Status: PLANNED, sap_status: PENDING]
    
    E[Frontend: Halaman List PDO] -->|GET /production/get-list-pdo-sap| F[Backend ProductionService]
    F -->|1. Fetch dari SAP| G[SAP /api/getListPDO]
    F -->|2. Fetch dari DB Lokal| C
    F -->|Gabungkan Data| H[Response List ke Frontend]
    
    I[Frontend: Edit PDO / Rilis Status] -->|PUT /production/orders/{id}| J[Backend updateOrder]
    J -->|Update Header & Lines| C
    
    J --> K{Status == RELEASED?}
    K -->|Ya, dan belum ada DocEntry| L[Kirim ke SAP /api/addpdo]
    L -->|Terima DocEntry & DocNum| M[Update Local DB: sap_status = SYNCED]
    K -->|Tidak / Tetap PLANNED| N[Simpan Lokal Selesai]
```

---

## 3. Struktur Tabel Database

### A. Tabel Header: `production.production_orders`
| Nama Kolom | Tipe Data | Deskripsi |
| :--- | :--- | :--- |
| `id` | `BIGSERIAL` (PK) | ID unik lokal PDO |
| `prod_order_no` | `VARCHAR(100)` | Nomor unik Production Order (e.g. `PO-20260819-A1B2C3D4`) |
| `doc_entry` | `INTEGER` (Nullable) | DocEntry dari SAP B1 |
| `doc_num` | `VARCHAR(50)` (Nullable) | DocNum dari SAP B1 |
| `series` | `INTEGER` | Nomor Series SAP (Default: `15`) |
| `status` | `VARCHAR(50)` | `PLANNED`, `RELEASED`, `CLOSED`, `CANCELLED` |
| `type` | `VARCHAR(50)` | `Standard`, `Special`, `Disassembly` |
| `item_code` | `VARCHAR(50)` | Kode Item Produk Jadi (Finish Good) |
| `planned_qty` | `DECIMAL(18,4)` | Jumlah rencana produksi |
| `cmplt_qty` | `DECIMAL(18,4)` | Kuantitas selesai |
| `receipt_qty` | `DECIMAL(18,4)` | Total kuantitas barang jadi yang sudah di-receipt dari produksi |
| `rjct_qty` | `DECIMAL(18,4)` | Jumlah reject |
| `warehouse` | `VARCHAR(50)` | Gudang tujuan barang jadi |
| `post_date` | `DATE` | Tanggal posting produksi |
| `due_date` | `DATE` | Tanggal jatuh tempo selesai produksi |
| `u_shift` | `VARCHAR(50)` | Shift kerja (`A`/`Shift 1`, `B`/`Shift 2`, `C`/`Shift 3`, `X`/`All`) |
| `u_unit` | `VARCHAR(50)` | Unit mesin / produksi |
| `production_bom_id` | `VARCHAR(50)` | Referensi BOM ID |
| `issue_for_production` | `TEXT` | Daftar nomor transaksi Issue yang terkait (e.g. `ISS-20260819-A1B2`) |
| `receipt_from_production` | `TEXT` | Daftar nomor transaksi Receipt yang terkait (e.g. `RCP-20260819-C3D4`) |
| `sap_status` | `VARCHAR(50)` | `PENDING`, `SYNCED`, `FAILED` |
| `sap_error` | `TEXT` (Nullable) | Pesan error jika gagal integrasi ke SAP |

### B. Tabel Detail / Komponen: `production.production_order_items`
| Nama Kolom | Tipe Data | Deskripsi |
| :--- | :--- | :--- |
| `id` | `BIGSERIAL` (PK) | ID unik baris bahan baku |
| `production_order_id` | `BIGINT` (FK) | Relasi ke `production_orders.id` (Cascade on delete) |
| `line_num` | `INTEGER` | Nomor urut baris (`0`, `1`, `2`, ...) |
| `type` | `VARCHAR(50)` | `Item` (I), `Resource` (R), `Text` (T) |
| `item_code` | `VARCHAR(50)` | Kode bahan baku / komponen |
| `base_qty` | `DECIMAL(18,4)` | Rasio kebutuhan per 1 unit FG |
| `planned_qty` | `DECIMAL(18,4)` | Total rencana kebutuhan bahan |
| `issued_qty` | `DECIMAL(18,4)` | Kuantitas bahan baku yang **sudah dikeluarkan (di-issue)** dari gudang |
| `warehouse` | `VARCHAR(50)` | Gudang asal bahan baku |
| `issue_mthd` | `VARCHAR(10)` | `M` (Manual) / `B` (Backflush) |
| `ocr_code` | `VARCHAR(50)` | Cost Center Dimensi 1 |
| `ocr_code2` | `VARCHAR(50)` | Cost Center Dimensi 2 |
| `ocr_code3` | `VARCHAR(50)` | Cost Center Dimensi 3 |

### C. Tabel Transaksi Issue: `production.production_issues` & `production.production_issue_items`
* Menyimpan riwayat pengeluaran bahan baku lokal (`ISS-YYYYMMDD-XXXX`).
* Setiap kali transaksi Issue terjadi, kolom `issued_qty` pada baris bahan baku `production_order_items` otomatis bertambah.

### D. Tabel Transaksi Receipt: `production.production_receipts` & `production.production_receipt_items`
* Menyimpan riwayat penerimaan barang jadi lokal (`RCP-YYYYMMDD-XXXX`).
* Setiap kali transaksi Receipt terjadi, kolom `receipt_qty` / `cmplt_qty` pada header `production_orders` otomatis bertambah. Jika telah memenuhi kuantitas rencana, status PDO otomatis menjadi `CLOSED`.

---

## 4. Spesifikasi Endpoint API

Semua endpoint dilindungi middleware autentikasi Sanctum:
```http
Authorization: Bearer <TOKEN>
Content-Type: application/json
```

---

### A. Create / Save PDO (Status Awal `PLANNED`)

Menyimpan draft rencana produksi baru ke database lokal.

* **Method:** `POST`
* **URL:** `/api/distributor-channel/v1/production/add-pdo-sap` *(atau `/api/distributor-channel/v1/production/orders`)*
* **Request Body (JSON):**
```json
{
  "ItemCode": "FG_GARAM_HALUS_1KG",
  "PlannedQty": 100,
  "PostingDate": "2026-08-19T00:00:00",
  "DueDate": "2026-08-25T00:00:00",
  "WhsCode": "WHS-PROD-01",
  "status": "PLANNED",
  "Remarks": "Rencana produksi batch 1",
  "Shift": "Shift 1",
  "Unit": "Unit 1",
  "Bomid": "BOM-001",
  "Lines": [
    {
      "ItemType": "I",
      "ItemCode": "RAW_GARAM_KASAR",
      "BaseQty": 1.05,
      "PlannedQty": 105,
      "WhsCode": "WHS-RAW-01",
      "IssueMethod": "M"
    },
    {
      "ItemType": "I",
      "ItemCode": "PKG_PLASTIK_1KG",
      "BaseQty": 1.0,
      "PlannedQty": 100,
      "WhsCode": "WHS-PKG-01",
      "IssueMethod": "M"
    }
  ]
}
```

* **Response Success (200 OK):**
```json
{
  "success": true,
  "message": "Production Order (PDO) berhasil disimpan dengan status PLANNED.",
  "data": {
    "order": {
      "id": 25,
      "prod_order_no": "PO-20260819-A1B2C3D4",
      "item_code": "FG_GARAM_HALUS_1KG",
      "status": "PLANNED",
      "planned_qty": "100.0000",
      "warehouse": "WHS-PROD-01",
      "sap_status": "PENDING",
      "details": [
        {
          "id": 51,
          "production_order_id": 25,
          "line_num": 0,
          "item_code": "RAW_GARAM_KASAR",
          "planned_qty": "105.0000",
          "warehouse": "WHS-RAW-01"
        }
      ]
    },
    "status": "PLANNED",
    "sap_response": null
  }
}
```

---

### B. Get List PDO (Gabungan Lokal + SAP)

Mengambil seluruh daftar PDO (baik yang masih draft `PLANNED` di lokal maupun yang sudah di SAP).

* **Method:** `GET` atau `POST`
* **URL:** `/api/distributor-channel/v1/production/get-list-pdo-sap` *(atau `/orders/sap-list`)*
* **Query / Body Parameter (Opsional):**
  * `From` (Format: `YYYY-MM-DD`, default awal tahun)
  * `To` (Format: `YYYY-MM-DD`, default akhir tahun)
  * `WhsCode` (Kode Gudang)
* **Response Success (200 OK):**
```json
{
  "success": true,
  "message": "Production Orders retrieved successfully from SAP.",
  "data": [
    {
      "id": 25,
      "DocEntry": "25",
      "DocNum": "PO-20260819-A1B2C3D4",
      "ItemCode": "FG_GARAM_HALUS_1KG",
      "ProdName": "Garam Halus 1 Kg",
      "Status": "PLANNED",
      "Type": "Standard",
      "PlannedQty": 100,
      "CmpltQty": 0,
      "RjctQty": 0,
      "PostDate": "2026-08-19T00:00:00",
      "DueDate": "2026-08-25T00:00:00",
      "WhsCode": "WHS-PROD-01",
      "Remarks": "Rencana produksi batch 1",
      "is_local": true
    },
    {
      "DocEntry": "1001",
      "DocNum": "2026001",
      "ItemCode": "FG_GARAM_MEJA",
      "ProdName": "Garam Meja 250gr",
      "Status": "Released",
      "PlannedQty": 500,
      "CmpltQty": 500,
      "WhsCode": "01",
      "is_local": false
    }
  ]
}
```

---

### C. Get Detail PDO

Mengambil detail lengkap 1 nomor PDO beserta baris komponen/bahan bakunya.

* **Method:** `GET` atau `POST`
* **URL:** `/api/distributor-channel/v1/production/get-pdo-by-id?custom_query={id}` *(atau `/orders/sap/{id}`)*
* **Response Success (200 OK):**
```json
{
  "success": true,
  "message": "Production Order detail retrieved successfully from SAP.",
  "data": {
    "header": {
      "id": 25,
      "DocEntry": "25",
      "DocNum": "PO-20260819-A1B2C3D4",
      "ItemCode": "FG_GARAM_HALUS_1KG",
      "ProdName": "Garam Halus 1 Kg",
      "Status": "PLANNED",
      "PlannedQty": 100,
      "WhsCode": "WHS-PROD-01",
      "PostDate": "2026-08-19T00:00:00",
      "DueDate": "2026-08-25T00:00:00",
      "Shift": "Shift 1",
      "Unit": "Unit 1",
      "is_local": true
    },
    "items": [
      {
        "LineNum": 0,
        "ItemType": "I",
        "ItemCode": "RAW_GARAM_KASAR",
        "ItemName": "Garam Kasar Bahan Baku",
        "BaseQty": 1.05,
        "PlannedQty": 105,
        "IssuedQty": 0,
        "WhsCode": "WHS-RAW-01",
        "IssueMethod": "M"
      },
      {
        "LineNum": 1,
        "ItemType": "I",
        "ItemCode": "PKG_PLASTIK_1KG",
        "ItemName": "Plastik Kemasan 1Kg",
        "BaseQty": 1.0,
        "PlannedQty": 100,
        "IssuedQty": 0,
        "WhsCode": "WHS-PKG-01",
        "IssueMethod": "M"
      }
    ]
  }
}
```

---

### D. Update / Edit PDO & Detail Komponen

Mengupdate informasi header dan daftar baris detail PDO. Jika field `status` diubah menjadi `"RELEASED"`, backend otomatis menyinkronkan data ke SAP B1.

* **Method:** `PUT`
* **URL:** `/api/distributor-channel/v1/production/orders/{id}`
* **Request Body (JSON):**
```json
{
  "PlannedQty": 150,
  "DueDate": "2026-08-30T00:00:00",
  "status": "PLANNED",
  "Remarks": "Revisi kuantitas jadi 150 unit",
  "Lines": [
    {
      "ItemType": "I",
      "ItemCode": "RAW_GARAM_KASAR",
      "BaseQty": 1.05,
      "PlannedQty": 157.5,
      "WhsCode": "WHS-RAW-01",
      "IssueMethod": "M"
    },
    {
      "ItemType": "I",
      "ItemCode": "PKG_PLASTIK_1KG",
      "BaseQty": 1.0,
      "PlannedQty": 150.0,
      "WhsCode": "WHS-PKG-01",
      "IssueMethod": "M"
    },
    {
      "ItemType": "I",
      "ItemCode": "RAW_YODIUM",
      "BaseQty": 0.001,
      "PlannedQty": 0.15,
      "WhsCode": "WHS-RAW-01",
      "IssueMethod": "M"
    }
  ]
}
```

* **Response Success (200 OK):**
```json
{
  "success": true,
  "message": "Production Order beserta detailnya berhasil diperbarui.",
  "data": {
    "id": 25,
    "prod_order_no": "PO-20260819-A1B2C3D4",
    "status": "PLANNED",
    "planned_qty": "150.0000",
    "details": [
      {
        "id": 55,
        "line_num": 0,
        "item_code": "RAW_GARAM_KASAR",
        "planned_qty": "157.5000"
      },
      {
        "id": 56,
        "line_num": 1,
        "item_code": "PKG_PLASTIK_1KG",
        "planned_qty": "150.0000"
      },
      {
        "id": 57,
        "line_num": 2,
        "item_code": "RAW_YODIUM",
        "planned_qty": "0.1500"
      }
    ]
  }
}
```

---

### E. Cancel PDO

Membatalkan PDO baik yang ada di database lokal maupun di SAP B1.

* **Method:** `POST`
* **URL:** `/api/distributor-channel/v1/production/cancel-pdo-sap`
* **Request Body (JSON):**
```json
{
  "doc_entry": 25
}
```
* **Response Success (200 OK):**
```json
{
  "success": true,
  "message": "Production Order berhasil dibatalkan.",
  "data": null
}
```

---

### F. Get Master Unit (SAP)

Mengambil daftar Master Unit mesin/lokasi produksi langsung dari SAP Business One (`/api/GetUnit`).

* **Method:** `GET` / `POST`
* **URL:** `/api/distributor-channel/v1/production/units` *(Alias: `/api/distributor-channel/v1/production/get-unit`)*
* **Headers:** `Authorization: Bearer <token>`
* **Response Success (200 OK):**
```json
{
  "success": true,
  "message": "Master Unit berhasil diambil dari SAP.",
  "data": [
    {
      "code": "BLR-UNIT1",
      "name": "BLR-UNIT1",
      "Code": "BLR-UNIT1",
      "Name": "BLR-UNIT1"
    },
    {
      "code": "BLR-UNIT2",
      "name": "BLR-UNIT2",
      "Code": "BLR-UNIT2",
      "Name": "BLR-UNIT2"
    },
    {
      "code": "FORKLIFT",
      "name": "FORKLIFT",
      "Code": "FORKLIFT",
      "Name": "FORKLIFT"
    },
    {
      "code": "GRESIK",
      "name": "GRESIK",
      "Code": "GRESIK",
      "Name": "GRESIK"
    },
    {
      "code": "GSK-UNIT1A",
      "name": "GSK-UNIT1A",
      "Code": "GSK-UNIT1A",
      "Name": "GSK-UNIT1A"
    },
    {
      "code": "GSK-UNIT1B",
      "name": "GSK-UNIT1B",
      "Code": "GSK-UNIT1B",
      "Name": "GSK-UNIT1B"
    },
    {
      "code": "GSK-UNIT2",
      "name": "GSK-UNIT2",
      "Code": "GSK-UNIT2",
      "Name": "GSK-UNIT2"
    }
  ]
}
```

---

## 5. Ringkasan Mapping Parameter Frontend ➔ Backend

| Parameter SAP / FE | Parameter Snake Case | Deskripsi |
| :--- | :--- | :--- |
| `ItemCode` / `product` | `item_code` | Kode produk jadi |
| `PlannedQty` / `quantity` | `planned_qty` | Kuantitas rencana |
| `PostingDate` | `post_date` | Tanggal posting |
| `DueDate` | `due_date` | Tanggal deadline |
| `WhsCode` / `warehouse` | `warehouse` | Kode gudang produk |
| `Status` | `status` | `PLANNED` / `RELEASED` |
| `Remarks` | `comments` | Catatan produksi |
| `Shift` | `u_shift` | Shift produksi |
| `Unit` | `u_unit` | Unit mesin |
| `Bomid` | `production_bom_id` | ID BOM referensi |
| `Lines` / `lines` / `details` | `details` | Array baris bahan baku / items |
