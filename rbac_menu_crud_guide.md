# Panduan Granular Role-Based Access Control (Matrix Hak Akses CRUD per Menu)

Dokumen ini menjelaskan sistem hak akses bertingkat (*Granular RBAC*) yang memungkinkan pengaturan izin aksi (**Create, Read, Update, Delete, Approve, Export**) untuk setiap menu dan role di aplikasi PT Susanti Megah.

---

## 1. Konsep Hak Akses (Matrix Actions)

Setiap role dapat diberikan izin spesifik per menu:
* **Read (Lihat):** Akses melihat halaman dan daftar data.
* **Create (Tambah):** Akses menambah data baru.
* **Update (Edit):** Akses mengubah data yang sudah ada.
* **Delete (Hapus):** Akses menghapus data.
* **Approve:** Akses menyetujui / menolak transaksi approval.
* **Export:** Akses mendownload file Excel / PDF / laporan.

---

## 2. Struktur Data Hak Akses di Database

Disimpan di tabel `role_menus` kolom `menu` (JSON) dengan format:

```json
[
  {
    "menu_key": "customer-portal.order",
    "actions": {
      "create": true,
      "read": true,
      "update": true,
      "delete": false,
      "approve": false,
      "export": true
    }
  },
  {
    "menu_key": "expedition.rates",
    "actions": {
      "create": false,
      "read": true,
      "update": false,
      "delete": true,
      "approve": false,
      "export": false
    }
  }
]
```

---

## 3. Response Saat Login (`POST /api/distributor-channel/v1/auth/login`)

Saat user berhasil login, backend langsung mengembalikan struktur data izin lengkap (Role default + Custom User Overrides yang sudah di-merge):

```json
{
  "success": true,
  "message": "Login berhasil.",
  "data": {
    "user": {
      "id": 14,
      "name": "Ani Sales",
      "username": "ani_sales",
      "email": "ani@susantimegah.com",
      "role_id": 3,
      "role_name": "Staff Sales",
      "has_custom_override": true,
      "custom_permissions": [
        {
          "menu_key": "sales-order",
          "actions": {
            "create": false,
            "read": true,
            "update": false,
            "delete": false,
            "approve": false,
            "export": true
          }
        }
      ]
    },
    "menu": ["customer-portal.order", "expedition.rates"],
    "permissions": [
      {
        "menu_key": "sales-order",
        "actions": {
          "create": false,
          "read": true,
          "update": false,
          "delete": false,
          "approve": false,
          "export": true
        }
      }
    ],
    "permissions_map": {
      "sales-order": {
        "create": false,
        "read": true,
        "update": false,
        "delete": false,
        "approve": false,
        "export": true
      }
    },
    "access_token": "123|abcdef...",
    "token_type": "Bearer"
  }
}
```

---

## 4. Backend Implementation (Laravel)

### A. Endpoint API Role & Permissions
Prefix: `/api/distributor-channel/v1/roles`

| Method | Endpoint | Fungsi |
| :--- | :--- | :--- |
| `GET` | `/my-permissions` | Mengambil seluruh matrix hak akses user yang sedang login |
| `GET` | `/{id}/permissions` | Mengambil detail matrix hak akses untuk role tertentu |
| `POST` / `PUT` | `/{id}/permissions` | Menyimpan matrix hak akses CRUD untuk role |

### B. Endpoint API User-Level Custom Permissions (Override)
Prefix: `/api/distributor-channel/v1/users`

| Method | Endpoint | Fungsi |
| :--- | :--- | :--- |
| `GET` | `/{id}/custom-permissions` | Mengambil custom permissions override milik user tertentu |
| `POST` / `PUT` | `/{id}/custom-permissions` | Mengatur / meng-override hak akses khusus user ini |
| `DELETE` | `/{id}/custom-permissions` | Menghapus override user (kembali 100% mengikuti default Role) |

### C. Proteksi Route API via Middleware `check.permission`
Tambahkan middleware `check.permission:{menu_key},{action}` pada route yang ingin diproteksi:

```php
Route::prefix('v1/sales-orders')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [SalesOrderController::class, 'index'])->middleware('check.permission:sales-order,read');
    Route::post('/', [SalesOrderController::class, 'store'])->middleware('check.permission:sales-order,create');
    Route::put('/{id}', [SalesOrderController::class, 'update'])->middleware('check.permission:sales-order,update');
    Route::delete('/{id}', [SalesOrderController::class, 'destroy'])->middleware('check.permission:sales-order,delete');
    Route::post('/{id}/approve', [SalesOrderController::class, 'approve'])->middleware('check.permission:sales-order,approve');
});
```

### C. Helper di Model `User` & `RoleMenu`
```php
// Cek izin di controller / service
if (!$request->user()->hasPermission('sales-order', 'delete')) {
    return response()->json(['message' => 'Anda tidak memiliki izin menghapus sales order'], 403);
}
```

---

## 4. Frontend Implementation (React 19)

### A. Custom Hook `usePermission`
Gunakan hook `usePermission(menuKey)` di komponen view:

```jsx
import usePermission from 'hooks/usePermission';

function OrderList() {
  const { canCreate, canUpdate, canDelete, canExport } = usePermission('customer-portal.order');

  return (
    <div>
      {/* Tombol Create */}
      {canCreate && (
        <Button variant="primary" onClick={handleCreate}>
          Tambah Order Baru
        </Button>
      )}

      {/* Tombol Export */}
      {canExport && (
        <Button variant="outline-success" onClick={handleExport}>
          Export Excel
        </Button>
      )}

      {/* Tombol Delete di tabel */}
      {canDelete && (
        <Button variant="danger" size="sm" onClick={() => handleDelete(item.id)}>
          Hapus
        </Button>
      )}
    </div>
  );
}
```

### B. Component Guard `<Can>`
Gunakan wrapper `<Can>` untuk menyembunyikan elemen UI secara deklaratif:

```jsx
import Can from 'components/Can';

<Can menu="customer-portal.order" action="create">
  <Button variant="primary">Tambah Order</Button>
</Can>

<Can menu="customer-portal.order" action="delete" fallback={<Badge bg="secondary">No Delete Access</Badge>}>
  <Button variant="danger">Hapus</Button>
</Can>
```

---

## 5. Tampilan UI Matrix Checklist (Permission List)

Pada menu **Settings ➔ Role & Permission** (`PermissionList.jsx`):
1. Buka modal **Edit / Add Role**.
2. Tab **1. Pilih Menu:** Centang menu yang ingin dimunculkan di sidebar.
3. Tab **2. Matrix Hak Akses CRUD:** Centang izin aksi per menu (Read, Create, Update, Delete, Approve, Export) atau klik tombol **All** per baris untuk memilih semua aksi.
4. Klik **Save Role & Permissions**.
