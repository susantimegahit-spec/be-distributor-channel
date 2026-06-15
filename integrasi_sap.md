# Integrasi SAP & Claim Reward

## 1. Overview
Aplikasi Distributor Channel digunakan oleh distributor untuk membuat Sales Order yang akan direview oleh Admin Sales sebelum dikirim ke SAP Business One.
Sistem berfungsi sebagai:
* Portal Order Distributor 
* Menu Claim Reward
* Approval Management 
* Discount Management 
* Integration Layer SAP B1 

---

## 2. User Role
| Role | Hak Akses |
| :--- | :--- |
| **Distributor** | Create Order, Save Draft, Edit Draft, Submit Order, Monitoring Status, Request Claim Reward |
| **Admin Sales** | Review Order, Input Discount, Approve / Reject Order, View Claim Reward |
| **Admin Finance** | Monitoring Order, Monitoring Discount, View Claim Reward |
| **Admin Logistic** | Update Status Delivery & Arrived, View Claim Reward |
| **Administrator** | Full Access |

---

## 3. Status Order
| Code | Status | Deskripsi |
| :--- | :--- | :--- |
| **DRAFT** | Draft | Draft pesanan baru oleh distributor |
| **CREATED** | Order Has Been Created | Pesanan telah dikirim oleh distributor |
| **WAITING_APPROVAL** | Waiting Approval Sales SM | Menunggu persetujuan dari Admin Sales / Sales Manager |
| **APPROVED** | Prepared Item by Tim SM | Disetujui, barang sedang disiapkan oleh tim |
| **DELIVERY** | Delivery | Barang dalam proses pengiriman |
| **ARRIVED** | Arrived | Barang telah diterima oleh distributor |
| **REJECTED** | Rejected | Pesanan ditolak oleh Sales |
| **FAILED** | Failed Integration | Gagal saat integrasi ke SAP B1 |

---

## 4. Database Schema (PostgreSQL)

### Tabel 1: sales_orders (Header)
Menyimpan data header Sales Order.
```sql
CREATE TABLE sales_orders (
    id BIGSERIAL PRIMARY KEY,
    order_no VARCHAR(50) UNIQUE NOT NULL,
    distributor_id BIGINT NOT NULL,
    card_code VARCHAR(50) NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    po_number VARCHAR(100),
    doc_date DATE NOT NULL,
    doc_due_date DATE,
    slp_code INT,
    cntct_code INT DEFAULT -1,
    pay_to_code VARCHAR(255),
    address TEXT,
    ship_to_code VARCHAR(255),
    address2 TEXT,
    disc_percent DECIMAL(18,2) DEFAULT 0.00,
    doc_total DECIMAL(18,2) DEFAULT 0.00,
    comments TEXT,
    id_discount VARCHAR(100),
    
    status VARCHAR(50) DEFAULT 'DRAFT' CHECK (status IN (
        'DRAFT',
        'CREATED',
        'WAITING_APPROVAL',
        'APPROVED',
        'DELIVERY',
        'ARRIVED',
        'REJECTED',
        'FAILED'
    )),

    sap_doc_entry INT NULL,
    sap_doc_num VARCHAR(50) NULL,
    sap_error TEXT NULL,
    
    sap_discount_code VARCHAR(100) NULL,

    submitted_at TIMESTAMP NULL,
    integrated_at TIMESTAMP NULL,
    delivery_date TIMESTAMP NULL,
    arrived_date TIMESTAMP NULL,

    approved_by BIGINT NULL,
    approved_at TIMESTAMP NULL,
    rejected_by BIGINT NULL,
    rejected_at TIMESTAMP NULL,
    reject_reason TEXT NULL,

    created_by BIGINT,
    updated_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel 2: sales_order_details
Menyimpan item per baris detail.
```sql
CREATE TABLE sales_order_details (
    id BIGSERIAL PRIMARY KEY,
    sales_order_id BIGINT REFERENCES sales_orders(id) ON DELETE CASCADE,
    item_code VARCHAR(50) NOT NULL,
    quantity DECIMAL(18,4) NOT NULL,
    unit_msr VARCHAR(50),
    uom_entry INT,
    whs_code VARCHAR(20),
    unit_price DECIMAL(18,2) NOT NULL,
    disc_percent DECIMAL(18,2) DEFAULT 0.00,
    vat_group VARCHAR(10),
    line_total DECIMAL(18,2) NOT NULL,
    free_text TEXT,
    ocr_code VARCHAR(20),
    ocr_code2 VARCHAR(20),
    ocr_code3 VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel 3: sales_order_integration_logs
Untuk log request dan response integrasi SAP B1.
```sql
CREATE TABLE sales_order_integration_logs (
    id BIGSERIAL PRIMARY KEY,
    sales_order_id BIGINT REFERENCES sales_orders(id) ON DELETE SET NULL,
    request_json TEXT,
    response_json TEXT,
    status VARCHAR(20) CHECK (status IN ('SUCCESS', 'FAILED')),
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel 4: customers
Master customer hasil sinkronisasi SAP.
```sql
CREATE TABLE customers (
    id BIGSERIAL PRIMARY KEY,
    card_code VARCHAR(50) UNIQUE NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    address TEXT,
    address2 TEXT,
    phone VARCHAR(50),
    email VARCHAR(100),
    status SMALLINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel 5: items
Master barang dari SAP.
```sql
CREATE TABLE items (
    id BIGSERIAL PRIMARY KEY,
    item_code VARCHAR(50) UNIQUE NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    uom_entry INT,
    unit_msr VARCHAR(50),
    vat_group VARCHAR(10),
    default_whs_code VARCHAR(20),
    status SMALLINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel 6: reward_table
Menyimpan data master/transaksi reward distributor.
```sql
CREATE TABLE reward_table (
    id BIGSERIAL PRIMARY KEY,
    distributor_id BIGINT NOT NULL,
    name_reward VARCHAR(255) NOT NULL,
    start_periode DATE NOT NULL,
    end_periode DATE NOT NULL,
    total_reward DECIMAL(18,2) DEFAULT 0.00,
    claim_date DATE NULL,
    
    created_by BIGINT NULL,
    updated_by BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel 7: sell_out_table
Menyimpan detail sell-out dari invoice klaim reward.
```sql
CREATE TABLE sell_out_table (
    id BIGSERIAL PRIMARY KEY,
    reward_id BIGINT REFERENCES reward_table(id) ON DELETE CASCADE,
    no_invoice VARCHAR(100) NOT NULL,
    product_code VARCHAR(50) NOT NULL,
    buy_price DECIMAL(18,2) NOT NULL,
    sell_price DECIMAL(18,2) NOT NULL,
    diff_price DECIMAL(18,2) NOT NULL,
    margins DECIMAL(18,2) NOT NULL,
    
    created_by BIGINT NULL,
    updated_by BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

### Tabel 8: distributors
Menyimpan data master distributor yang disinkronisasi dari SAP.
```sql
CREATE TABLE distributors (
    id BIGSERIAL PRIMARY KEY,
    code_customer VARCHAR(50) UNIQUE NOT NULL, -- Kode Distributor / CardCode dari SAP
    name VARCHAR(255) NOT NULL,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(100),
    status SMALLINT DEFAULT 1, -- 1 = Aktif, 0 = Non-Aktif
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel 9: users (Relasi ke Distributor)
Menyimpan data user aplikasi. Untuk user role Distributor, akan berelasi dengan tabel `distributors` melalui kolom `code_customer`.
```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    role_id BIGINT REFERENCES roles(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL, -- Login menggunakan username (bisa berupa code_customer)
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    code_customer VARCHAR(50) NULL REFERENCES distributors(code_customer) ON DELETE SET NULL, -- Relasi ke distributor
    is_active BOOLEAN DEFAULT TRUE,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Seed Data (Hardcode 1 Distributor & User)
Untuk keperluan pengembangan awal, berikut data distributor dan user distributor yang di-hardcode:
```sql
-- 1. Insert Master Distributor
INSERT INTO distributors (code_customer, name, address, phone, email, status)
VALUES ('CUST001', 'PT Distributor Utama', 'Jl. Sudirman No. 123, Jakarta', '021-5551234', 'info@distributorutama.com', 1);

-- 2. Insert User untuk Distributor (Login dengan username = code_customer)
INSERT INTO users (role_id, name, username, email, password, code_customer, is_active)
VALUES (
    2, -- Asumsi ID Role untuk Distributor
    'User Distributor Utama', 
    'CUST001', -- Login menggunakan code_customer sebagai username
    'distributor@distributorutama.com', 
    '$2y$12$e0M2/V326S1n.7hZ4c8XKeWlVb4.4g5s5U1x4cT2s7fG9Y9i5Q2hO', -- Password terenkripsi (contoh: 'password')
    'CUST001', 
    TRUE
);
```

---

## 5. Fitur Menu Claim Reward
Berikut adalah kebutuhan fungsional tambahan untuk modul Claim Reward:
1. **Sync Distributor**: Proses sinkronisasi data master distributor dari SAP ke database Distributor Channel.
2. **Sync Product**: Proses sinkronisasi data master barang/produk beserta UoM dan harga dari SAP ke database lokal.
3. **Membuat Master Area**: Pengelolaan area/wilayah distributor untuk mempermudah pemetaan wilayah distribusi dan program reward.
4. **Master Program**: Pengelolaan program reward (promo, syarat claim, periode aktif, dan skema margin).

---

## 6. Workflow Order & Claim Reward

### Workflow Claim Reward
```
Distributor
  |
  v
Input Sell Out dan pilih kolom (Invoice, Produk, Harga Beli, Harga Jual, Selisih, Margin)
  |
  v
Submit Claim Reward
  |
  v
Reward muncul sesuai perhitungan & divalidasi oleh Admin Sales/Finance
```

### Workflow Order
```
Distributor
  |
  +-- Save Draft (membuat atau memperbarui draf pesanan)
  |
  v
Draft
  |
  +-- Submit
  |
  v
Order Has Been Created (CREATED)
  |
  v
Waiting Approval Sales SM (WAITING_APPROVAL)
  |
  +-- Admin Sales Review & Input Discount
  |
  +-- Reject
  |     |
  |     v
  |   Rejected (REJECTED)
  |
  +-- Approve
        |
        v
      Prepared Item by Tim SM (APPROVED) -> Create UDO Discount SAP -> Create SO SAP
        |
        v
      Delivery (DELIVERY)
        |
        v
      Arrived (ARRIVED)
```

---

## 7. Integrasi Sales Order SAP B1

Modul ini melakukan posting data Sales Order ke API integrasi SAP B1.

* **Method:** `POST`
* **Route:** `/api/v1/sales-orders/{id}/post-sap`
* **Target API SAP:** `http://103.18.133.187:3100/api/addso`

### Payload Request ke SAP B1 (`addso`)
Payload dikonversi dari detail lokal menjadi struktur PascalCase standard SAP:
```json
{
  "CardCode": "C110000411",
  "NumAtCard": "100002",
  "DocDate": "2026-06-11",
  "DocDueDate": "2026-07-11",
  "SlpCode": 4,
  "CntctCode": -1,
  "PayToCode": "BILL",
  "Address": "JL.KH. MANSYUR 55A",
  "ShipToCode": "Alamat Kirim",
  "Address2": "JL.KH. MANSYUR 55A",
  "Comments": "",
  "IdDiskon": null,
  "Lines": [
    {
      "ItemCode": "B26",
      "Quantity": 10.0,
      "UomEntry": 4,
      "DiscPrcnt": 0.0,
      "WhsCode": "CVS02",
      "UnitMsr": "Bal",
      "UnitPrice": 100000.0,
      "VatGroup": "S5",
      "LineTotal": 1000000.0,
      "FreeTxt": "",
      "OcrCode": "SBY",
      "OcrCode2": "GRM",
      "OcrCode3": "IT"
    },
    {
      "ItemCode": "B26",
      "Quantity": 10.0,
      "UomEntry": 4,
      "DiscPrcnt": 0.0,
      "WhsCode": "RMI01",
      "UnitMsr": "Bal",
      "UnitPrice": 100000.0,
      "VatGroup": "S4",
      "LineTotal": 1000000.0,
      "FreeTxt": "",
      "OcrCode": "SBY",
      "OcrCode2": "GRM",
      "OcrCode3": "IT"
    }
  ]
}
```

### Response Sukses dari SAP
```json
{
  "ErrorCode": 0,
  "Message": "Sales Order added successfully",
  "Result": [
    {
      "DocEntry": 9999,
      "DocNum": "SO9999"
    }
  ]
}
```

### Konsekuensi Status:
* Jika Sukses: Status diubah menjadi `APPROVED`, kolom `sap_doc_entry`, `sap_doc_num`, dan `integrated_at` akan diisi. Log disimpan di `sales_order_integration_logs` dengan status `SUCCESS`.
* Jika Gagal: Status diubah menjadi `FAILED`, kolom `sap_error` diisi dengan pesan error. Log disimpan di `sales_order_integration_logs` dengan status `FAILED`.
