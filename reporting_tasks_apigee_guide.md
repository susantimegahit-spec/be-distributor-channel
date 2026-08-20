# Panduan Integrasi API Reporting Tasks: Backend ➔ Apigee ➔ Google Looker Studio (Data Studio)

Dokumen ini menjelaskan arsitektur, spesifikasi API, rekomendasi otorisasi (*authorization strategy*), dan panduan teknis untuk mengonsumsi data tabel `reporting_tasks` melalui **Google Apigee API Gateway** menuju **Google Looker Studio (Data Studio)**.

---

## 1. Arsitektur End-to-End Workflow

```mermaid
flowchart LR
    A[ClickUp Tasks] -->|1. Webhook / Polling| B[n8n Automation]
    B -->|2. Ingest POST /tasks/sync| C[(Database: reporting_tasks)]
    
    subgraph Google Cloud Platform
        E[Google Looker Studio] -->|3. Fetch / Poll Dataset| D[Google Apigee API Gateway]
    end
    
    D -->|4. GET /v1/reporting/tasks/all<br/>(Header: X-API-Key)| F[Backend Laravel: distributor_chnl]
    F -->|5. Query data| C
    F -->|6. JSON Response Full List| D
    D -->|7. Verified Response| E
```

### Penjelasan Komponen:
1. **ClickUp & n8n:** Menyuplai dan menyinkronkan data tugas (*tasks, status, deadlines, assignees, space_id*) ke tabel database `reporting_tasks`.
2. **Backend Laravel (`distributor_chnl`):** Menyediakan REST API performa tinggi untuk mengekspor seluruh data tabel `reporting_tasks` (baik *unpaginated* maupun dengan filter).
3. **Google Apigee API Gateway:** Bertindak sebagai pintu gerbang (Gateway), mengamankan akses, mengelola *rate limiting*, *caching*, dan otorisasi sebelum diteruskan ke Looker Studio.
4. **Google Looker Studio (Data Studio):** Mengonsumsi data dari Apigee untuk visualisasi dashboard manajemen (Burndown chart, task distribution by assignee, overdue tasks, sprint velocity, dll.).

---

## 2. Rekomendasi Strategi Otorisasi (Authorization Strategy)

Karena endpoint ini akan diakses secara otomatis oleh **Google Looker Studio via Apigee Gateway**, berikut adalah analisis dan rekomendasi model otorisasi terbaik:

### A. Rekomendasi Utama: **Pre-Shared API Key / Secret Token (SANGAT DIREKOMENDASIKAN)**

```text
[Looker Studio] ──(Custom Header)──> [Apigee Gateway] ──(X-API-Key)──> [Backend Laravel]
```

* **Mengapa?**
  * Dashboard Looker Studio melakukan *background polling* / *scheduled refresh* secara berkala.
  * Jika menggunakan token User Login (JWT / OAuth2 Sanctum), token akan *expired* (kedaluwarsa) dalam hitungan jam/hari sehingga dashboard Data Studio sering mendadak rusak (*error 401 Unauthorized*).
  * Dengan **Pre-Shared API Key / Secret**, Apigee dapat menyimpan token ini secara aman di **Apigee KVM (Key Value Map)** dan otomatis menyisipkannya ke header request menuju Backend Laravel.
* **Header yang Didukung:**
  * `X-API-Key: <SECRET_KEY>`
  * `X-Apigee-Secret: <SECRET_KEY>`
  * `Authorization: Bearer <SECRET_KEY>`
  * Query Parameter (Opsional): `?api_key=<SECRET_KEY>`

### B. Opsi Keamanan Tambahan: **IP Whitelisting Apigee**
* Backend Laravel dapat dikonfigurasi untuk hanya menerima request pelaporan dari rentang IP NAT Gateway Apigee GCP.

---

## 3. Konfigurasi Environment Backend (`.env`)

Tambahkan konfigurasi berikut pada file `.env` di backend Laravel:

```env
# Secret API Key untuk konsumsi Apigee & Reporting
REPORTING_API_KEY=susanti-reporting-secret-key-2026-prod

# (Opsional) IP Whitelisting Gateway Apigee (pisahkan dengan koma)
# REPORTING_ALLOWED_IPS=35.190.0.0/16,34.120.0.0/16
```

> **Catatan:** Jika `REPORTING_API_KEY` tidak diisi (kosong), endpoint reporting akan berjalan dalam mode terbuka (public) tanpa validasi header.

---

## 4. Spesifikasi Endpoint API (Get All Data)

Base URL: `https://api-dev.susantimegah.com/api/distributor-channel` *(atau domain production)*

---

### Endpoint: `GET /v1/reporting/tasks/all`

Mengambil **seluruh baris data** dari tabel `reporting_tasks` tanpa batasan paginasi (optimal untuk konsumsi Apigee & Data Studio).

* **Method:** `GET`
* **URL:** `/api/distributor-channel/v1/reporting/tasks/all`
* **Headers:**
  ```http
  Accept: application/json
  X-API-Key: susanti-reporting-secret-key-2026-prod
  ```
  *(Atau menggunakan `Authorization: Bearer <token>`)*

#### Query Parameters (Opsional untuk Filter Spesifik):
| Parameter | Tipe | Contoh | Keterangan |
| :--- | :--- | :--- | :--- |
| `space_id` | String | `901811229210` | Filter berdasarkan ClickUp Space ID |
| `space_name` | String | `Engineering` | Filter berdasarkan nama Space |
| `folder_name`| String | `CUSTOMER PORTAL` | Filter berdasarkan nama Folder |
| `list_name` | String | `Support` | Filter berdasarkan nama List |
| `status` | String | `in progress` | Filter status task |
| `assignee` | String | `sanjay firmansyah` | Filter nama PIC penanggung jawab |
| `priority` | String | `high` | Filter tingkat prioritas |
| `start_date_from` | Date | `2026-08-01` | Filter tanggal mulai pengerjaan |
| `due_date_to` | Date | `2026-08-31` | Filter tanggal tenggat waktu |
| `search` | String | `monitoring` | Pencarian teks bebas pada task name, ID, atau komentar |
| `sort_by` | String | `updated_at` | Kolom pengurutan (default: `updated_at`) |
| `sort_order` | String | `desc` | Arah pengurutan (`asc` / `desc`) |

---

### Contoh Response Sukses (`200 OK`):

```json
{
  "success": true,
  "status_code": 200,
  "message": "Seluruh data reporting tasks berhasil diambil.",
  "data": [
    {
      "id": 16,
      "task_id": "z8mx155a9h",
      "task_name": "Buat dashboard untuk kebutuhan monitoring pencapaian sales",
      "space_id": "901811229210",
      "space_name": "Engineering",
      "folder_name": "CUSTOMER PORTAL",
      "list_name": "Support",
      "assignee": "sanjay firmansyah",
      "timeline": "Sprint 3",
      "start_date": "2026-08-20T04:00:00.000000Z",
      "due_date": "2026-08-27T04:00:00.000000Z",
      "priority": "high",
      "task_type": "Task",
      "created_by": "Aida",
      "comment": "Integrasi backend dengan Apigee dan Data Studio.",
      "status": "in progress",
      "synced_at": "2026-08-20T08:50:37.000000Z",
      "created_at": "2026-08-20T08:50:37.000000Z",
      "updated_at": "2026-08-20T08:50:37.000000Z"
    }
  ]
}
```

---

### Contoh Response Error (`401 Unauthorized`):

```json
{
  "success": false,
  "status_code": 401,
  "message": "API Key / Secret Token tidak valid. Akses ditolak.",
  "errors": {}
}
```

---

## 5. Panduan Konfigurasi di Google Apigee API Gateway

### A. Konfigurasi Target Endpoint:
* **Target URL:** `https://api-dev.susantimegah.com/api/distributor-channel/v1/reporting/tasks/all`

### B. Kebijakan Apigee (Policies):
1. **Verify API Key Policy (Inbound from Data Studio):**
   * Memvalidasi API Key milik Looker Studio / Client.
   ```xml
   <VerifyAPIKey async="false" continueOnError="false" enabled="true" name="VAK-VerifyLookerStudioKey">
       <ApiKey ref="request.header.x-api-key"/>
   </VerifyAPIKey>
   ```

2. **Assign Message Policy (Outbound to Backend Laravel):**
   * Menginjeksi Secret Key backend dari Apigee KVM (*Encrypted Key-Value Map*).
   ```xml
   <AssignMessage async="false" continueOnError="false" enabled="true" name="AM-InjectBackendSecret">
       <Set>
           <Headers>
               <Header name="X-API-Key">{private.reporting_backend_key}</Header>
               <Header name="Accept">application/json</Header>
           </Headers>
       </Set>
       <IgnoreUnresolvedVariables>true</IgnoreUnresolvedVariables>
       <AssignTo createNew="false" transport="http" type="request"/>
   </AssignMessage>
   ```

3. **Response Cache Policy (Optimasi Performa Data Studio):**
   * Looker Studio sering memicu query agregasi berulang. Gunakan cache 5-15 menit di Apigee untuk menghemat beban database backend.
   ```xml
   <ResponseCache async="false" continueOnError="false" enabled="true" name="RC-CacheReportingTasks">
       <ExpirySettings>
           <TimeoutInSec>300</TimeoutInSec>
       </ExpirySettings>
   </ResponseCache>
   ```

---

## 6. Panduan Menghubungkan ke Google Looker Studio (Data Studio)

Ada 2 cara menghubungkan Looker Studio:

### Cara 1: Menggunakan Community JSON Connector (via Apigee URL)
1. Buka [Google Looker Studio](https://lookerstudio.google.com/).
2. Klik **Create** ➔ **Data Source**.
3. Pilih konektor: **Supermetrics** / **JSON Connector** / **Custom Apps Script Connector**.
4. Masukkan URL Apigee: `https://<apigee-domain>/v1/reporting/tasks/all`.
5. Masukkan Header: `X-API-Key: <LookerStudio_Apigee_Key>`.
6. Set path data JSON: `data`.

### Cara 2: Koneksi Langsung ke Database PostgreSQL (Jika dalam 1 VPC Cloud)
* Masukkan Host, Database Name, User, Password.
* Pilih tabel `public.reporting_tasks` atau buat Custom SQL Query:
  ```sql
  SELECT 
      task_id, 
      task_name, 
      space_id, 
      folder_name, 
      list_name, 
      assignee, 
      status, 
      priority, 
      start_date, 
      due_date, 
      synced_at 
  FROM reporting_tasks;
  ```
