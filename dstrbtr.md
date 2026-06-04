Buatkan base project Backend API menggunakan Laravel 12 untuk aplikasi Distributor Channel PT Susanti Megah.

Spesifikasi teknis:

* Framework: Laravel 12
* PHP: 8.3
* Database: PostgreSQL
* Authentication: Laravel Sanctum
* API Versioning: /api/v1
* Documentation: Swagger/OpenAPI
* Architecture Pattern: Controller → Service → Repository
* Coding Standard: PSR-12 dan SOLID Principle
* Project Type: Modular Monolith (bukan microservices)
* Frontend akan dikembangkan terpisah menggunakan React JS dan berkomunikasi melalui REST API.

Buatkan struktur project yang scalable dan siap untuk pengembangan module bisnis di masa depan.

Scope fase pertama hanya Authentication Module dengan fitur:

1. Login
2. Logout
3. Refresh Token
4. Change Password

Kebutuhan Authentication:

* Login menggunakan username dan password.
* Hanya user aktif yang dapat login.
* Password menggunakan bcrypt hashing.
* Menggunakan Laravel Sanctum sebagai token authentication.
* Logout harus menghapus token aktif.
* Refresh Token harus menghasilkan token baru dan menonaktifkan token lama.
* Change Password harus memvalidasi password lama dan memastikan password baru berbeda dengan password lama.
* Semua endpoint menggunakan Request Validation.
* Semua aktivitas Login, Logout, dan Change Password dicatat ke Audit Log.

Standarisasi Response API:

Success Response:

```json
{
    "success": true,
    "message": "Success",
    "data": {}
}
```

Error Response:

```json
{
    "success": false,
    "message": "Validation Error",
    "errors": {}
}
```

Endpoint yang harus dibuat:

```text
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/refresh
POST   /api/v1/auth/change-password
```

Buatkan:

* Migration
* Model
* Controller
* Service Layer
* Repository Layer
* Form Request Validation
* API Routes
* Sanctum Configuration
* Audit Log Table
* Audit Log Service
* Swagger Documentation
* Seeder User Default
* API Resource/Response Formatter
* Global Exception Handler

Siapkan struktur folder yang modular dan future-ready untuk penambahan module berikutnya seperti:

* User Management
* Role & Permission
* Distributor Management
* Product Management
* Order Management
* Reporting

Pastikan project dapat langsung dijalankan setelah migration dan seeder dieksekusi serta mengikuti best practice Laravel 12 untuk aplikasi enterprise.
