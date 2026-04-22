# Pioneer CNC Backend

Backend API untuk aplikasi internal Pioneer CNC. Repository ini dibangun dengan Laravel dan menyediakan layanan data utama untuk frontend `pioneer-cnc`.

Project ini menangani:
- autentikasi user
- master data
- role dan permission
- transaksi invoice
- pembayaran
- machine order
- laporan operasional
- migrasi data dari sistem lama

README ini fokus pada backend `pioneer-cnc-be`. Dokumentasi frontend dapat dilihat di repository `pioneer-cnc`.

## Stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- MySQL atau MariaDB

## Peran Repository Ini

`pioneer-cnc-be` adalah sumber data utama aplikasi. Frontend `pioneer-cnc` akan mengakses backend ini melalui endpoint API.

Secara umum backend ini menyediakan:
- login dan logout
- proteksi endpoint berbasis token Sanctum
- kontrol akses berbasis permission per menu
- endpoint CRUD untuk master data dan transaksi
- endpoint laporan untuk dashboard dan rekap data

## Fitur Utama

- Auth API dengan Sanctum
- Dashboard summary
- Master data:
  branch, user, customer, sales, machine, plate type, size, machine type, component, supplier, cost type
- Product dan pricing:
  plate variants, cutting prices
- Transaksi:
  invoice, payment, machine order
- File handling:
  file item invoice, file mesin, file bukti pembayaran
- Role dan menu permission
- Laporan:
  sales KPI, sales recap, invoice recap, payment recap, piutang, stok
- Import data lama melalui command custom

## Struktur Penting

- `app/Http/Controllers/Api`
  seluruh controller API utama
- `routes/api.php`
  daftar endpoint API
- `app/Http/Middleware`
  middleware autentikasi dan permission
- `database/migrations`
  struktur database
- `database/seeders`
  data awal menu, role, permission, dan master dasar
- `app/Console/Commands/MigrateLegacyData.php`
  command migrasi data dari sistem lama

## Autentikasi

Backend menggunakan Laravel Sanctum.

Endpoint publik:
- `POST /api/login`

Endpoint terproteksi berada di dalam middleware:
- `auth:sanctum`

Contoh endpoint penting:
- `GET /api/user`
- `POST /api/logout`
- `GET /api/dashboard/summary`
- `GET /api/invoices`
- `POST /api/payments`

## Permission

Akses endpoint diatur dengan middleware permission, misalnya:

```php
->middleware('permission:payment,read')
->middleware('permission:invoice,create')
```

Struktur menu, role menu, dan permission awal diatur melalui seeder pada folder:

- `database/seeders/MenuSeeder.php`
- `database/seeders/RoleMenuSeeder.php`
- `database/seeders/RoleMenuPermissionSeeder.php`

## Setup Lokal

1. Clone repository.
2. Install dependency Composer.
3. Copy `.env`.
4. Atur koneksi database.
5. Generate app key.
6. Jalankan migration dan seeder.

Contoh:

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
```

## Contoh Environment

Sesuaikan `.env` minimal seperti ini:

```env
APP_NAME="Pioneer CNC Backend"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8001

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pioneer_cnc
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

REPORT_SALES_KPI_TARGET_MONTHLY=150000000
REPORT_SALES_KPI_TARGET_YEARLY=1800000000
```

Jika memakai database lama untuk migrasi data, pastikan koneksi tambahannya juga didefinisikan di `config/database.php` dan `.env`.

## Menjalankan Project

Untuk development:

```powershell
php artisan serve --port=8001
```

Jika ingin menjalankan queue listener:

```powershell
php artisan queue:listen
```

## Seeder

Seeder penting yang biasa dipakai:

- `RoleSeeder`
- `BranchSeeder`
- `UserSeeder`
- `MenuSeeder`
- `RoleMenuSeeder`
- `RoleMenuPermissionSeeder`

Menjalankan seluruh seeder default:

```powershell
php artisan db:seed
```

Menjalankan seeder tertentu:

```powershell
php artisan db:seed --class=MenuSeeder
php artisan db:seed --class=RoleMenuSeeder
php artisan db:seed --class=RoleMenuPermissionSeeder
```

## Migrasi Data Lama

Repository ini memiliki command khusus untuk migrasi data dari sistem lama:

```powershell
php artisan app:migrate-legacy-data
```

Command ini didefinisikan di:
- `app/Console/Commands/MigrateLegacyData.php`

Secara umum prosesnya mencakup:
- migrasi master dasar
- migrasi user, sales, customer
- migrasi varian dan harga
- migrasi invoice dan payment
- migrasi attachment terkait transaksi lama

Sebelum menjalankan command ini, pastikan:
- koneksi database lama tersedia
- struktur tabel target sudah bermigrasi
- file attachment lama sudah berada di lokasi yang sesuai

## File Upload dan Storage

Backend ini menyimpan beberapa file ke disk `public`, misalnya:
- file item invoice
- file mesin
- bukti pembayaran

Pastikan symbolic link storage tersedia:

```powershell
php artisan storage:link
```

Lokasi file umumnya berada di:
- `storage/app/public/invoice-items`
- `storage/app/public/machines`
- `storage/app/public/payment-proofs`

## Testing dan Validasi

Menjalankan syntax check atau test:

```powershell
php artisan test
```

Kalau ada perubahan besar di config, cache, route, atau view:

```powershell
php artisan optimize:clear
```

## Endpoint Ringkas

Contoh area endpoint yang tersedia:

- Auth:
  `POST /api/login`, `POST /api/logout`, `GET /api/user`
- Dashboard:
  `GET /api/dashboard/summary`
- Master:
  `/api/customers`, `/api/branches`, `/api/users`, `/api/sales`
- Produk:
  `/api/plate-variants`, `/api/cutting-prices`, `/api/components`
- Transaksi:
  `/api/invoices`, `/api/payments`, `/api/machine-orders`
- Laporan:
  `/api/reports/sales-kpi`, `/api/reports/sales-recap/plate`, `/api/reports/sales-recap/cutting`

Detail lengkap ada di:
- `routes/api.php`

## Catatan Pengembangan

- Frontend `pioneer-cnc` mengonsumsi backend ini melalui `BACKEND_API_URL`.
- Jika menu frontend tidak sesuai, biasanya sumbernya ada di seeder menu dan permission backend.
- Jika file gagal diunduh, cek nilai `file_path` di database dan file fisik di `storage/app/public`.
- Jika ada masalah auth, cek token Sanctum dan middleware permission.

## Status

Repository ini adalah backend utama untuk aplikasi internal Pioneer CNC dan dipakai sebagai sumber data untuk frontend `pioneer-cnc`.
