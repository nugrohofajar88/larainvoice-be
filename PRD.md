# PRD: Pioneer CNC Backend API

## 1. Ringkasan Produk

Dokumen ini mendefinisikan kebutuhan produk untuk `pioneer-cnc-be`, yaitu backend API utama yang melayani aplikasi internal Pioneer CNC. Backend ini bertugas menyediakan autentikasi, otorisasi, master data, transaksi, laporan, pengelolaan file, dan migrasi data lama ke sistem baru.

Backend ini menjadi sumber data utama untuk frontend `pioneer-cnc` dan harus menyediakan API yang stabil, aman, dan konsisten untuk kebutuhan operasional harian.

## 2. Latar Belakang

Operasional Pioneer CNC mencakup beberapa area yang saling terkait:

- pengelolaan cabang
- master customer, user, sales, mesin, material, komponen, dan harga
- transaksi invoice dan pembayaran
- pekerjaan produksi
- laporan penjualan dan KPI
- pengelolaan menu dan hak akses per role
- migrasi data dari sistem lama

Tanpa backend yang terstruktur, frontend akan sulit menjaga konsistensi data, validasi bisnis, dan kontrol akses. Karena itu, dibutuhkan backend API terpusat yang menjadi satu-satunya sumber kebenaran untuk seluruh proses bisnis.

## 3. Tujuan

- Menyediakan backend API terpusat untuk aplikasi Pioneer CNC.
- Menjamin konsistensi data antar modul master, transaksi, dan laporan.
- Menyediakan autentikasi dan otorisasi berbasis role dan permission.
- Menyediakan endpoint yang aman dan mudah dikonsumsi frontend.
- Mendukung alur operasional cabang dan super admin dengan pembatasan akses yang jelas.
- Mendukung migrasi bertahap dari sistem lama ke sistem baru.

## 4. Non-Goals

- Tidak membahas desain UI frontend.
- Tidak mencakup integrasi payment gateway online.
- Tidak mencakup aplikasi mobile native.
- Tidak mencakup orkestrasi deployment production.
- Tidak mencakup BI dashboard eksternal atau data warehouse.

## 5. Aktor

- `Super Admin`
  Memiliki akses lintas cabang dan dapat melihat atau mengelola seluruh data.

- `Admin Cabang`
  Mengelola data dan transaksi pada cabangnya sendiri.

- `Sales`
  Mengakses data yang relevan untuk penjualan dan relasi customer.

- `Tim Produksi`
  Mengakses data invoice, file desain, dan status produksi.

- `Frontend System`
  Aplikasi `pioneer-cnc` yang mengonsumsi API backend ini.

- `Migrasi Legacy Operator`
  Menjalankan command migrasi data dari sistem lama.

## 6. Problem Statement

Tanpa backend API yang baku, sistem berisiko mengalami:

- duplikasi logika bisnis antara frontend dan backend
- hak akses menu yang tidak konsisten
- validasi transaksi yang berbeda antar halaman
- stok dan pembayaran yang tidak sinkron
- file transaksi yang tidak terhubung ke entitas bisnis
- kesulitan migrasi data lama secara aman
- laporan yang tidak akurat karena sumber data tersebar

## 7. Scope Fitur

### In Scope

- API login dan logout
- API user profile berbasis token
- middleware autentikasi Sanctum
- middleware permission per modul dan aksi
- CRUD master data
- CRUD dan proses transaksi invoice
- pencatatan pembayaran dan file bukti bayar
- upload dan download file item invoice dan file mesin
- machine order
- dashboard summary
- laporan KPI sales dan rekap penjualan
- data menu, role, dan permission
- seeder awal untuk role, menu, dan permission
- command migrasi data legacy

### Out of Scope Sementara

- payment gateway
- notifikasi email/WhatsApp
- audit log yang lengkap per perubahan field
- webhook eksternal
- multi-tenant architecture di luar konsep cabang
- dokumentasi OpenAPI/Swagger penuh

## 8. Prinsip Produk

- `Single source of truth`
  Semua data inti harus berasal dari backend.

- `Secure by default`
  Endpoint sensitif harus dilindungi auth dan permission.

- `Role-aware`
  Data yang diterima user harus dibatasi sesuai role dan cabang.

- `Operationally practical`
  Endpoint dan payload harus mendukung kebutuhan harian operasional, bukan sekadar desain ideal.

- `Migration-friendly`
  Sistem baru harus mampu menerima dan menormalisasi data dari sistem lama.

## 9. Arsitektur Konseptual

### 9.1 Komponen Inti

- `Auth Layer`
  Login, logout, token Sanctum, user identity.

- `Permission Layer`
  Middleware permission berbasis modul dan aksi.

- `Master Data Layer`
  Menyediakan CRUD dan referensi data dasar.

- `Transaction Layer`
  Menangani invoice, pembayaran, machine order, file, dan status.

- `Reporting Layer`
  Menyediakan agregasi dashboard dan laporan ringkas.

- `Legacy Migration Layer`
  Menangani import data lama dan penyesuaian struktur data.

### 9.2 Integrasi Utama

- Frontend `pioneer-cnc` mengakses backend melalui HTTP API.
- Backend menggunakan database relasional sebagai penyimpanan utama.
- File transaksi disimpan di storage disk `public`.

## 10. Modul Produk

### 10.1 Auth

Fungsi utama:
- login user
- logout user
- mengambil profil user aktif

Acceptance:
- login berhasil mengembalikan token dan data user
- endpoint terproteksi menolak request tanpa token valid
- logout menghapus atau menonaktifkan token aktif

### 10.2 Permission dan Menu

Fungsi utama:
- menyimpan struktur menu
- menyimpan hak akses per role
- menentukan permission untuk modul dan aksi

Acceptance:
- setiap endpoint sensitif harus memiliki proteksi permission yang sesuai
- role dapat memiliki permission baca, buat, ubah, hapus, detail sesuai kebutuhan
- frontend dapat mengambil data menu untuk membangun navigasi

### 10.3 Master Data

Modul yang termasuk:
- branch
- branch setting
- branch bank account
- branch invoice counter
- user
- role
- customer
- sales
- machine
- machine type
- plate type
- size
- plate variant
- cutting price
- component category
- component
- supplier
- cost type

Acceptance:
- setiap master data memiliki endpoint list, detail, create, update, delete sesuai aturan bisnis
- non super admin hanya dapat melihat atau memodifikasi data cabangnya jika dibatasi demikian
- referensi master data dapat dipakai sebagai sumber dropdown atau selectable data di frontend

### 10.4 Invoice

Fungsi utama:
- membuat invoice
- melihat daftar invoice
- melihat detail invoice
- update status produksi
- upload file item invoice
- download file item invoice
- hapus invoice bila diizinkan

Aturan utama:
- invoice harus terkait cabang, customer, dan user pembuat
- invoice number harus unik berdasarkan aturan counter cabang
- status awal invoice ditentukan oleh isi transaksi
- invoice yang mengandung item tertentu harus memicu validasi tambahan sesuai bisnis

Acceptance:
- invoice dapat dibuat dengan item yang valid
- status produksi dapat diperbarui sesuai alur yang diizinkan
- file item dapat diunggah dan diunduh ulang
- data detail invoice cukup kaya untuk kebutuhan tampilan frontend dan cetak

### 10.5 Payment

Fungsi utama:
- mencatat pembayaran invoice
- menyimpan bukti pembayaran
- mengunduh file bukti pembayaran
- menampilkan daftar pembayaran dengan filter

Aturan utama:
- pembayaran harus terkait invoice dan cabang
- pembayaran tidak boleh melebihi sisa tagihan jika aturan itu berlaku
- bukti pembayaran dapat berupa file upload
- pembayaran harus menyimpan user yang menangani proses pembayaran

Acceptance:
- pembayaran dapat dibuat dari user yang berwenang
- file bukti pembayaran tersimpan dan dapat diunduh kembali
- daftar pembayaran dapat difilter berdasarkan invoice, metode, tanggal, dan cabang
- data pembayaran mengembalikan metadata petugas penanganan bila dibutuhkan frontend

### 10.6 Machine Order

Fungsi utama:
- membuat dan mengelola machine order
- menyimpan komponen, biaya, dan pembayaran terkait machine order
- menghitung total dan status pembayaran machine order

Acceptance:
- machine order dapat menyimpan beberapa komponen dan biaya
- payment machine order dapat mencatat penerima atau petugas
- detail machine order dapat dikonsumsi frontend untuk halaman operasional

### 10.7 Dashboard dan Report

Fungsi utama:
- dashboard summary
- report sales KPI
- report sales recap untuk plate dan cutting

Acceptance:
- data laporan dibatasi sesuai cabang untuk user non super admin
- target KPI dapat dikonfigurasi dari environment
- endpoint report merespons ringkas dan siap dipakai frontend tanpa pemrosesan berat tambahan

### 10.8 Legacy Migration

Fungsi utama:
- migrasi master data dari sistem lama
- migrasi transaksi invoice dan payment
- migrasi attachment lama
- mapping data lama ke struktur baru

Aturan utama:
- migrasi harus berjalan bertahap dan berurutan
- foreign key dapat dinonaktifkan sementara selama import
- data hasil migrasi harus kompatibel dengan fitur sistem baru

Acceptance:
- command migrasi dapat dijalankan secara manual
- data penting dari sistem lama masuk ke tabel baru dengan relasi yang benar
- file dan payment attachment lama tetap dapat dirujuk oleh sistem baru

## 11. User Flow Tingkat Tinggi

### 11.1 Login

1. User mengirim username/email dan password.
2. Backend memvalidasi kredensial.
3. Backend mengembalikan token dan data user.
4. Frontend memakai token untuk request berikutnya.

### 11.2 Akses Endpoint Terproteksi

1. Frontend mengirim request dengan token.
2. Middleware `auth:sanctum` memvalidasi user.
3. Middleware `permission` memvalidasi hak akses modul dan aksi.
4. Endpoint dijalankan atau ditolak.

### 11.3 Pembuatan Invoice

1. Frontend meminta master data invoice.
2. User memilih branch, customer, machine, dan item.
3. Frontend mengirim payload transaksi ke backend.
4. Backend memvalidasi payload dan konsistensi data.
5. Backend membuat invoice, item, payment awal bila ada, dan file terkait.
6. Backend mengembalikan invoice detail.

### 11.4 Pembayaran Invoice

1. Frontend memilih invoice untuk dibayar.
2. User mengirim data pembayaran dan file bukti bayar.
3. Backend memvalidasi sisa tagihan dan akses cabang.
4. Backend menyimpan payment dan attachment.
5. Backend mengembalikan data payment baru.

### 11.5 Migrasi Data Lama

1. Operator menjalankan `php artisan app:migrate-legacy-data`.
2. Backend mematikan foreign key sementara.
3. Backend memigrasi data master terlebih dahulu.
4. Backend memigrasi relasi dan transaksi.
5. Backend mengaktifkan kembali foreign key.
6. Operator memverifikasi hasil migrasi.

## 12. Functional Requirements

### 12.1 API Auth

- Sistem harus menyediakan endpoint login.
- Sistem harus menyediakan endpoint logout untuk token aktif.
- Sistem harus menyediakan endpoint `GET /api/user`.

### 12.2 API Permission

- Sistem harus memeriksa permission untuk endpoint yang sensitif.
- Sistem harus mendukung pemetaan modul seperti `invoice`, `payment`, `branch`, `role`, dan seterusnya.
- Sistem harus mendukung aksi minimal `read`, `create`, `update`, `delete`, dan aksi lain bila dibutuhkan.

### 12.3 API Master Data

- Sistem harus menyediakan endpoint CRUD untuk master data yang menjadi referensi transaksi.
- Sistem harus memfilter data cabang untuk user non super admin pada modul yang relevan.
- Sistem harus mengembalikan data dalam struktur yang mudah dipakai frontend.

### 12.4 API Invoice

- Sistem harus menyediakan endpoint daftar invoice dengan filter dan sorting.
- Sistem harus menyediakan endpoint detail invoice.
- Sistem harus menyediakan endpoint create invoice.
- Sistem harus menyediakan endpoint update status produksi.
- Sistem harus mendukung upload dan download file item invoice.

### 12.5 API Payment

- Sistem harus menyediakan endpoint daftar pembayaran.
- Sistem harus menyediakan endpoint create payment.
- Sistem harus menyediakan endpoint download file payment.
- Sistem harus menyimpan `user_id` petugas penanganan pembayaran.

### 12.6 API Report

- Sistem harus menyediakan endpoint dashboard summary.
- Sistem harus menyediakan endpoint KPI sales.
- Sistem harus menyediakan endpoint recap penjualan plate dan cutting.

### 12.7 Seeder dan Permission Template

- Sistem harus menyediakan seeder awal untuk role, menu, dan permission.
- Sistem harus menjaga konsistensi key menu dengan permission yang dipakai middleware.

### 12.8 Legacy Migration

- Sistem harus menyediakan command migrasi legacy.
- Sistem harus memigrasi data dengan urutan yang aman.
- Sistem harus mempertahankan relasi utama seperti branch, customer, invoice, payment, dan file.

## 13. Business Rules

### 13.1 Role dan Cabang

- Super admin dapat melihat lintas cabang.
- Non super admin dibatasi pada cabangnya sendiri untuk data yang relevan.

### 13.2 Invoice

- nomor invoice harus unik sesuai aturan counter cabang
- invoice harus mencatat user pembuat
- status produksi hanya boleh berpindah melalui transisi yang diizinkan

### 13.3 Payment

- payment harus terkait invoice valid
- payment branch harus konsisten dengan invoice atau user sesuai aturan role
- payment harus menyimpan tanggal, metode, amount, dan petugas yang menangani
- file payment harus tersimpan di storage yang dapat diunduh kembali

### 13.4 Permission

- endpoint tanpa permission yang sesuai harus mengembalikan forbidden
- permission menu harus sinkron dengan key menu di seeder

### 13.5 Legacy Data

- struktur data lama dapat berbeda dengan struktur baru, sehingga perlu mapping eksplisit
- path attachment lama harus disesuaikan dengan struktur storage sistem baru bila perlu

## 14. Non-Functional Requirements

- API harus merespons JSON secara konsisten.
- API harus aman terhadap akses tanpa token.
- Validasi request harus dilakukan di backend walaupun frontend juga melakukan validasi.
- Query utama harus cukup efisien untuk kebutuhan operasional harian.
- File upload dan download harus stabil untuk bukti transaksi dan lampiran pekerjaan.
- Seeder dan migration harus dapat dijalankan di environment lokal dan staging.

## 15. Acceptance Criteria

### AC-01 Auth

- User valid dapat login dan menerima token.
- User tanpa token tidak bisa mengakses endpoint terproteksi.

### AC-02 Permission

- User tanpa permission `read` pada modul tertentu tidak dapat mengakses endpoint list atau detail modul tersebut.
- User tanpa permission `create` tidak dapat membuat data baru.

### AC-03 Invoice

- Invoice valid dapat dibuat dan mengembalikan detail lengkap.
- Status produksi hanya berubah melalui transisi yang diizinkan.

### AC-04 Payment

- Payment valid dapat dibuat tanpa melebihi aturan sisa tagihan.
- File bukti bayar yang tersimpan dapat diunduh melalui endpoint backend.
- Payment baru menyimpan `user_id` penangan pembayaran.

### AC-05 Report

- Dashboard dan report mengembalikan data sesuai batas akses user.
- KPI sales menggunakan target yang dapat dikonfigurasi dari environment.

### AC-06 Seeder

- `db:seed` menghasilkan menu, role, dan permission awal yang konsisten.

### AC-07 Legacy Migration

- Command migrasi dapat dijalankan tanpa error fatal pada data lama yang valid.
- Data master dan transaksi utama masuk ke tabel baru.

## 16. Endpoint Ringkas

Contoh endpoint inti:

- `POST /api/login`
- `POST /api/logout`
- `GET /api/user`
- `GET /api/dashboard/summary`
- `GET /api/customers`
- `GET /api/plate-variants`
- `GET /api/cutting-prices`
- `GET /api/machines`
- `GET /api/invoices`
- `POST /api/invoices`
- `GET /api/payments`
- `POST /api/payments`
- `GET /api/payments/{paymentId}/files/{fileId}/download`
- `GET /api/menus`
- `GET /api/roles`
- `GET /api/users`
- `GET /api/sales`
- `GET /api/reports/sales-kpi`

## 17. Risiko dan Edge Cases

- path file hasil migrasi lama tidak sesuai dengan storage baru
- data lama tidak lengkap atau melanggar foreign key baru
- permission key berubah tetapi seeder frontend atau backend belum sinkron
- file fisik ada tetapi `file_path` di database tidak konsisten
- user cabang mengakses data lintas cabang akibat filter yang terlewat
- invoice dan payment lama tidak memiliki metadata user yang lengkap
- update seeder dapat menimpa konfigurasi role bila dijalankan sembarangan di environment aktif

## 18. Open Questions

- Apakah seluruh endpoint perlu dokumentasi OpenAPI formal?
- Apakah payment perlu mendukung update dan delete di masa depan?
- Apakah perlu audit log permanen untuk perubahan status invoice dan payment?
- Apakah attachment lama perlu dinormalisasi penuh ke pola path baru?
- Apakah report lain seperti piutang dan stok akan dipindahkan penuh ke backend agregat juga?

## 19. Rekomendasi Implementasi

- Pertahankan semua aturan bisnis penting di backend.
- Gunakan relation eager loading secara selektif untuk endpoint detail.
- Tambahkan test untuk auth, permission, invoice, payment, dan file download.
- Standarkan format path file untuk attachment baru dan hasil migrasi lama.
- Pastikan key menu dan permission selalu satu sumber dari seeder backend.
- Tambahkan dokumentasi payload request/response untuk endpoint utama pada iterasi berikutnya.

## 20. Lampiran Ringkas

Command penting:

```powershell
php artisan migrate
php artisan db:seed
php artisan app:migrate-legacy-data
php artisan storage:link
php artisan optimize:clear
```

File penting:

- `routes/api.php`
- `app/Http/Controllers/Api`
- `database/migrations`
- `database/seeders`
- `app/Console/Commands/MigrateLegacyData.php`
