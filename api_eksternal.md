---

tags:

* b2b-integration
* api-spec
* pt-susanti-megah
  date: 2026-08-10
  status: Complete

---

# Integrasi API B2B: Customer Monthly Order (CMO)

Dokumen ini merupakan panduan teknis integrasi **Customer Monthly Order (CMO)** antara sistem ERP/DMS Distributor dengan **SM Connect B2B API** milik **PT Susanti Megah**.

Dokumen ditujukan untuk tim teknis/developer Distributor yang melakukan integrasi sistem internal dengan API B2B PT Susanti Megah.

---

## 🔐 Autentikasi

Semua request ke API wajib menyertakan **API Key** pada HTTP Header.

| Header          | Value                   |
| --------------- | ----------------------- |
| `Authorization` | `Bearer <API_KEY_ANDA>` |

### Contoh Header

```http
Authorization: Bearer xxxxxxxxxxxxxxxxxxxxx
```

> [!warning] Keamanan API Key
> API Key bersifat rahasia dan tidak boleh disimpan pada source code yang bersifat public, repository terbuka, atau dibagikan kepada pihak yang tidak berkepentingan.

---

# 📌 Rangkuman API Endpoint

Secara umum, integrasi API B2B terdiri dari empat endpoint utama:

```mermaid
graph TD
    A[Distributor ERP / DMS] -->|Authorization: Bearer API Key| B[SM Connect B2B API Gateway]

    B -->|POST| C[Buat CMO / Upload PO]
    B -->|GET| D[Cek Status CMO]
    B -->|GET| E[List Distributor Diizinkan]
    B -->|GET| F[List Barang & Harga]
```

### Daftar Endpoint

| No | Method | Endpoint                                                                            | Fungsi                                             |
| -: | ------ | ----------------------------------------------------------------------------------- | -------------------------------------------------- |
|  1 | `POST` | `/api/distributor-channel/v1/external/customer-monthly-orders`                      | Membuat CMO dan upload dokumen PO                  |
|  2 | `GET`  | `/api/distributor-channel/v1/external/customer-monthly-orders/{distributor_ref_no}` | Mengecek status CMO                                |
|  3 | `GET`  | `/api/distributor-channel/v1/external/customer-monthly-orders/distributors`         | Mengambil daftar distributor yang diizinkan        |
|  4 | `GET`  | `/api/distributor-channel/v1/external/customer-monthly-orders/items`                | Mengambil daftar barang dan harga yang ter-mapping |

---

# 1. Buat CMO & Upload PO

Endpoint ini digunakan oleh Distributor untuk membuat **Customer Monthly Order (CMO)** sekaligus mengirimkan dokumen PO.

## Endpoint

```http
POST /api/distributor-channel/v1/external/customer-monthly-orders
```

### Request

| Parameter      | Value                 |
| -------------- | --------------------- |
| Method         | `POST`                |
| Content-Type   | `multipart/form-data` |
| Authentication | Bearer API Key        |

### Header

```http
Authorization: Bearer <API_KEY_ANDA>
Content-Type: multipart/form-data
```

---

## Parameter Request

| Parameter            | Tipe        | Wajib | Keterangan                                                             |
| -------------------- | ----------- | :---: | ---------------------------------------------------------------------- |
| `card_code`          | String      |   ✅   | Kode customer/distributor yang telah terdaftar pada API Key            |
| `distributor_ref_no` | String      |   ✅   | Nomor referensi/PO dari Distributor. Digunakan sebagai Idempotency Key |
| `doc_date`           | Date        |   ✅   | Tanggal dokumen dengan format `YYYY-MM-DD`                             |
| `attachment`         | File        |   ✅   | Dokumen PO dalam format PDF, maksimal 10 MB                            |
| `lines`              | JSON String |   ✅   | Detail item CMO dalam format JSON                                      |

---

## Aturan Khusus

### 1. Idempotency

Field `distributor_ref_no` digunakan sebagai **Idempotency Key**.

Apabila Distributor mengirimkan request yang sama dengan `distributor_ref_no` yang sudah pernah berhasil diproses, sistem tidak akan membuat transaksi baru.

Sistem akan mengembalikan informasi transaksi yang telah dibuat sebelumnya.

```text
Request pertama
      ↓
distributor_ref_no = PO-DIST-2026-0899
      ↓
CMO berhasil dibuat
      ↓
Request dikirim kembali dengan ref_no yang sama
      ↓
Sistem mendeteksi duplicate
      ↓
Tidak membuat CMO baru
```

> [!important] Catatan
> Distributor disarankan menggunakan `distributor_ref_no` yang unik untuk setiap PO/CMO.

---

### 2. Auto-Populate Data

Beberapa data akan diisi otomatis oleh sistem apabila tidak dikirimkan oleh Distributor.

| Field            | Mekanisme                                         |
| ---------------- | ------------------------------------------------- |
| `eta_date`       | Jika kosong, otomatis diisi `doc_date + 7 hari`   |
| `doc_due_date`   | Jika kosong, otomatis disamakan dengan `doc_date` |
| Billing Address  | Otomatis diambil dari SAP berdasarkan `card_code` |
| Shipping Address | Otomatis diambil dari SAP berdasarkan `card_code` |

---

### 3. Attachment / Dokumen PO

Dokumen PO wajib dikirimkan pada parameter:

```text
attachment
```

Ketentuan:

* Format file: **PDF**
* Extension: `.pdf`
* Maksimal ukuran: **10 MB**
* Attachment wajib disertakan pada setiap pembuatan CMO.

Contoh:

```text
attachment = PO-DIST-2026-0899.pdf
```

---

### 4. Format `lines`

Karena request menggunakan:

```http
Content-Type: multipart/form-data
```

maka parameter `lines` dikirim sebagai **JSON String**.

Contoh:

```json
[
  {
    "item_code": "B26",
    "quantity": 100,
    "unit_price": 150000,
    "unit_msr": "Bal"
  }
]
```

---

## Contoh Request

### Form Data

```text
card_code:
C110003419

distributor_ref_no:
PO-DIST-2026-0899

doc_date:
2026-08-10

attachment:
PO-DIST-2026-0899.pdf

lines:
[
  {
    "item_code": "B26",
    "quantity": 100,
    "unit_price": 150000,
    "unit_msr": "Bal"
  }
]
```

### Contoh cURL

```bash
curl --location 'https://<API_HOST>/api/distributor-channel/v1/external/customer-monthly-orders' \
--header 'Authorization: Bearer <API_KEY_ANDA>' \
--form 'card_code="C110003419"' \
--form 'distributor_ref_no="PO-DIST-2026-0899"' \
--form 'doc_date="2026-08-10"' \
--form 'attachment=@"/path/to/PO-DIST-2026-0899.pdf"' \
--form 'lines="[{\"item_code\":\"B26\",\"quantity\":100,\"unit_price\":150000,\"unit_msr\":\"Bal\"}]"'
```

> [!note] Placeholder
> Ganti `<API_HOST>` dengan alamat server API yang diberikan oleh PT Susanti Megah.

---

## Response Sukses

### HTTP 201 Created

```json
{
  "success": true,
  "message": "Customer Monthly Order berhasil dibuat via External API.",
  "is_duplicate": false
}
```

### Response Duplicate

Apabila `distributor_ref_no` sudah pernah digunakan, API akan mengembalikan informasi bahwa request merupakan transaksi yang sebelumnya sudah diproses.

Field:

```json
{
  "is_duplicate": true
}
```

> [!important] Idempotency
> Response duplicate tidak berarti transaksi gagal. Artinya transaksi dengan `distributor_ref_no` tersebut sudah pernah dibuat sehingga sistem tidak membuat duplikasi transaksi.

---

# 2. Cek Status CMO

Endpoint ini digunakan untuk mengecek status CMO berdasarkan nomor referensi Distributor.

## Endpoint

```http
GET /api/distributor-channel/v1/external/customer-monthly-orders/{distributor_ref_no}
```

### Contoh Request

```http
GET /api/distributor-channel/v1/external/customer-monthly-orders/PO-DIST-2026-0899
```

### Header

```http
Authorization: Bearer <API_KEY_ANDA>
```

---

## Status CMO

Status menunjukkan posisi transaksi dalam proses integrasi.

| Status   | Keterangan                                                            |
| -------- | --------------------------------------------------------------------- |
| `DRAFT`  | CMO sudah dibuat pada portal tetapi belum terkirim/ter-posting ke SAP |
| `POSTED` | CMO sudah berhasil terkirim/ter-posting ke SAP                        |

---

## Response Sukses

### HTTP 200 OK

```json
{
  "success": true,
  "message": "Detail Customer Monthly Order berhasil diambil.",
  "data": {
    "order_no": "CMO-20260810-4736",
    "distributor_ref_no": "PO-DIST-2026-08924",
    "status": "DRAFT",
    "doc_total": "22500000.00",
    "sap_doc_num": null
  }
}
```

### Keterangan Response

| Field                | Keterangan                                                          |
| -------------------- | ------------------------------------------------------------------- |
| `order_no`           | Nomor CMO yang dibuat oleh sistem                                   |
| `distributor_ref_no` | Nomor referensi dari Distributor                                    |
| `status`             | Status proses CMO                                                   |
| `doc_total`          | Total nilai dokumen                                                 |
| `sap_doc_num`        | Nomor dokumen SAP. Bernilai `null` apabila belum ter-posting ke SAP |

---

# 3. List Distributor yang Diizinkan

Endpoint ini digunakan untuk mendapatkan daftar customer/distributor yang telah dipetakan ke API Key yang digunakan.

## Endpoint

```http
GET /api/distributor-channel/v1/external/customer-monthly-orders/distributors
```

### Header

```http
Authorization: Bearer <API_KEY_ANDA>
```

### Fungsi

API akan mengembalikan daftar `card_code` yang memiliki hak akses untuk API Key tersebut.

Distributor dapat menggunakan endpoint ini sebelum membuat CMO untuk memastikan `card_code` yang digunakan memang diperbolehkan.

---

## Response Sukses

### HTTP 200 OK

```json
{
  "success": true,
  "message": "Daftar distributor berhasil diambil.",
  "data": [
    {
      "card_code": "C110003419",
      "customer_name": "SAKTI SETIA SANTOSA, PT.",
      "depo": "SITUBONDO",
      "address": "JL.KERTOPATEN NOMOR 16 SURABAYA"
    }
  ]
}
```

---

# 4. List Barang & Harga

Endpoint ini digunakan untuk mengambil daftar barang dan harga yang telah di-mapping untuk Distributor tertentu.

## Endpoint

```http
GET /api/distributor-channel/v1/external/customer-monthly-orders/items
```

### Header

```http
Authorization: Bearer <API_KEY_ANDA>
```

---

## Query Parameters

| Parameter   | Wajib | Keterangan                                            |
| ----------- | :---: | ----------------------------------------------------- |
| `card_code` |   ✅   | Kode customer/distributor yang terdaftar pada API Key |
| `search`    |   ❌   | Pencarian berdasarkan kode atau nama barang           |

### Contoh Request

Tanpa pencarian:

```http
GET /api/distributor-channel/v1/external/customer-monthly-orders/items?card_code=C110003419
```

Dengan pencarian:

```http
GET /api/distributor-channel/v1/external/customer-monthly-orders/items?card_code=C110003419&search=MINYAK
```

---

## Response Sukses

### HTTP 200 OK

```json
{
  "success": true,
  "message": "Daftar barang berhasil diambil.",
  "data": [
    {
      "item_code": "B26",
      "item_name": "MINYAK GORENG 1L",
      "sales_uom": "Bal",
      "price": 150000.0,
      "brand": "Sania"
    }
  ]
}
```

---

## Keamanan `card_code`

API akan melakukan validasi apakah `card_code` yang dikirimkan memiliki hak akses pada API Key yang digunakan.

Jika Distributor mencoba mengakses `card_code` milik Distributor lain, request akan ditolak.

### Response

**HTTP 403 Forbidden**

```json
{
  "success": false,
  "message": "Akses ditolak. card_code 'C999999999' tidak terdaftar untuk API Key ini. Distributor yang diizinkan: [C110003419]",
  "errors": []
}
```

> [!warning] Authorization
> `card_code` tidak hanya digunakan sebagai parameter pencarian. Sistem juga melakukan validasi hak akses berdasarkan API Key.

---

# 🔄 Alur Integrasi yang Direkomendasikan

Berikut alur integrasi yang direkomendasikan untuk sistem ERP/DMS Distributor:

```mermaid
flowchart TD
    A[Distributor ERP / DMS] --> B[Authorization dengan API Key]

    B --> C{Valid API Key?}

    C -- Tidak --> X[401 Unauthorized]
    C -- Ya --> D[GET List Distributor]

    D --> E[Pilih card_code]

    E --> F[GET List Barang & Harga]

    F --> G[Pilih Item dan Harga]

    G --> H[Generate PO]

    H --> I[POST Create CMO + Attachment PDF]

    I --> J{Response}

    J -- Berhasil --> K[Simpan order_no & distributor_ref_no]
    J -- Duplicate --> L[Gunakan transaksi yang sudah ada]
    J -- Error --> M[Handle Error]

    K --> N[GET Cek Status CMO]

    N --> O{Status}

    O -- DRAFT --> N
    O -- POSTED --> P[CMO Berhasil Terintegrasi ke SAP]
```

---

# 🔐 Security & Access Control

API menggunakan kombinasi:

1. **API Key Authentication**
2. **Card Code Authorization**
3. **Idempotency Key**

### API Key

API Key menentukan identitas dan akses Distributor.

```http
Authorization: Bearer <API_KEY_ANDA>
```

### Card Code Authorization

API Key hanya dapat mengakses `card_code` yang telah dipetakan.

```text
API Key Distributor A
        ↓
C110003419
C110003420
C110003421
```

Distributor tidak dapat mengakses `card_code` di luar mapping tersebut.

### Idempotency

`distributor_ref_no` memastikan request yang sama tidak menghasilkan transaksi CMO ganda.

```text
distributor_ref_no
        ↓
   Unique Reference
        ↓
 Prevent Duplicate CMO
```

---

# 📎 Ketentuan Attachment

| Ketentuan            | Nilai                 |
| -------------------- | --------------------- |
| Parameter            | `attachment`          |
| Format               | PDF                   |
| Extension            | `.pdf`                |
| Maksimal ukuran      | 10 MB                 |
| Wajib                | Ya                    |
| Content-Type Request | `multipart/form-data` |

Contoh nama file yang direkomendasikan:

```text
PO-DIST-2026-0899.pdf
```

---

# 🧪 Testing Checklist

Sebelum implementasi di production, Distributor disarankan melakukan pengujian berikut:

* [ ] API Key valid dapat melakukan request.
* [ ] API Key tidak valid ditolak.
* [ ] `card_code` yang diizinkan dapat digunakan.
* [ ] `card_code` yang tidak diizinkan menghasilkan `403 Forbidden`.
* [ ] CMO berhasil dibuat menggunakan `POST`.
* [ ] Attachment PDF berhasil dikirim.
* [ ] Attachment dengan ukuran > 10 MB ditolak.
* [ ] Attachment selain PDF ditolak.
* [ ] `lines` berhasil dikirim dalam format JSON String.
* [ ] `distributor_ref_no` yang sama tidak membuat CMO duplikat.
* [ ] Status CMO dapat dicek menggunakan `GET`.
* [ ] Status `DRAFT` dapat dikenali.
* [ ] Status `POSTED` dapat dikenali.
* [ ] `sap_doc_num` dapat diterima ketika transaksi telah ter-posting ke SAP.
* [ ] List distributor dapat diambil.
* [ ] List barang dan harga dapat diambil.
* [ ] Parameter `search` dapat digunakan.

---

# 📋 Ringkasan Integrasi

| Fitur                  | Method | Endpoint                                                                            | Authentication                      |
| ---------------------- | ------ | ----------------------------------------------------------------------------------- | ----------------------------------- |
| Create CMO + Upload PO | `POST` | `/api/distributor-channel/v1/external/customer-monthly-orders`                      | API Key                             |
| Cek Status CMO         | `GET`  | `/api/distributor-channel/v1/external/customer-monthly-orders/{distributor_ref_no}` | API Key                             |
| List Distributor       | `GET`  | `/api/distributor-channel/v1/external/customer-monthly-orders/distributors`         | API Key                             |
| List Barang & Harga    | `GET`  | `/api/distributor-channel/v1/external/customer-monthly-orders/items`                | API Key + `card_code` authorization |

---

# 📌 Catatan Implementasi

1. Setiap request wajib menggunakan API Key yang diberikan oleh PT Susanti Megah.
2. API Key harus disimpan secara aman dan tidak boleh diekspos ke client/public application.
3. `card_code` hanya dapat digunakan apabila telah terdaftar pada API Key Distributor.
4. `distributor_ref_no` harus dibuat unik oleh sistem Distributor.
5. Dokumen PO wajib berupa PDF dengan ukuran maksimal 10 MB.
6. Parameter `lines` harus dikirim sebagai JSON String karena endpoint Create CMO menggunakan `multipart/form-data`.
7. Setelah CMO berhasil dibuat, Distributor disarankan menyimpan `order_no` dan `distributor_ref_no` untuk kebutuhan tracking.
8. Status CMO dapat dipantau menggunakan endpoint **Cek Status CMO**.
9. `sap_doc_num` digunakan sebagai referensi nomor dokumen setelah transaksi berhasil terintegrasi/ter-posting ke SAP.

---

# 📞 Support Integrasi

Apabila terdapat kendala pada proses integrasi, informasi berikut sebaiknya disertakan saat melakukan eskalasi:

* API Endpoint yang digunakan
* HTTP Method
* Timestamp request
* `distributor_ref_no`
* `card_code`
* HTTP Status Code
* Response API
* Request ID / Trace ID apabila tersedia

> [!warning] Jangan mengirimkan API Key
> API Key tidak boleh disertakan pada screenshot, log, email, atau tiket support.
