# Laravel Online Sales

Aplikasi penjualan online berbasis Laravel 13 dengan dua role pengguna: **Admin** dan **Pelanggan**. Proyek ini dibuat sebagai praktikum lanjutan (Program Studi S1 Sistem Informasi, Universitas Telkom Purwokerto), tidak berhenti pada CRUD sederhana, tetapi membangun alur bisnis lengkap dari master barang sampai validasi pesanan.

## Fitur

**Admin**
- Login dan dashboard ringkasan
- Kelola kategori dan produk (termasuk unggah gambar)
- Catat barang masuk dan monitoring stok (`stock_movements`)
- Kelola layanan ekspedisi
- Lihat transaksi, validasi pesanan, batalkan pesanan, dan perbarui status pengiriman

**Pelanggan**
- Registrasi dan login
- Katalog produk dengan pencarian, filter (kategori, harga, stok), pengurutan, dan pagination
- Detail produk
- Keranjang belanja berbasis session
- Pilih ekspedisi, isi alamat, dan checkout
- Riwayat dan detail pesanan

## Teknologi

- PHP 8.3 atau lebih baru
- Laravel 13.x (Laravel 12 masih bisa dipakai, periksa kompatibilitas versi PHP)
- MySQL / MariaDB
- Blade dan CSS sederhana (`public/css/store.css`)
- Laragon (lingkungan lokal), Composer, Git

## Instalasi

### 1. Prasyarat

```bash
php -v
composer -V
git --version
```

Pastikan MySQL/MariaDB aktif.

### 2. Clone dan install dependensi

```bash
git clone <url-repository> laravel-online-sales
cd laravel-online-sales
composer install
cp .env.example .env
php artisan key:generate
```

### 3. Buat database

```bash
mysql -u root -e "CREATE DATABASE laravel_online_sales CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 4. Konfigurasi `.env`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_online_sales
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Migrasi, seeder, dan storage

```bash
php artisan config:clear
php artisan migrate
php artisan db:seed
php artisan storage:link
```

Seeder membuat akun admin, 3 kategori (Elektronik, Aksesoris, Peralatan Rumah), dan 3 layanan ekspedisi (JNE REG, J&T EZ, SiCepat REG).

### 6. Jalankan aplikasi

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000`.

## Akun Latihan

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `Admin12345!` |
| Pelanggan | Daftar lewat `/register` | Minimal 8 karakter |

> Kredensial admin hanya untuk lingkungan praktikum lokal. Jangan dipakai di production.

## Hak Akses dan Route

| Area | Middleware | Contoh route |
|---|---|---|
| Publik | none | `/`, `/produk/{slug}` |
| Auth | `guest` | `/login`, `/register` |
| Pelanggan | `auth`, `role:customer` | `/keranjang`, `/checkout`, `/pesanan` |
| Admin | `auth`, `role:admin` | `/admin`, `/admin/products`, `/admin/orders` |

Pelanggan yang membuka `/admin` mendapat HTTP 403. Admin yang membuka route pelanggan juga ditolak.

Daftar lengkap: `php artisan route:list`.

## Desain Database

| Tabel | Fungsi |
|---|---|
| `users` | Akun admin dan pelanggan (kolom `role`) |
| `categories` | Master kategori produk |
| `products` | Master barang, harga, stok, status aktif, gambar |
| `stock_movements` | Audit stok: `IN` (barang masuk / pembatalan), `OUT` (checkout) |
| `shipping_services` | Master ekspedisi, layanan, estimasi, ongkir |
| `orders` | Header transaksi, alamat, ongkir snapshot, total, status, validator |
| `order_items` | Detail item. Nama dan harga disimpan sebagai snapshot saat checkout |

## Alur Bisnis

**Checkout**
- Berjalan dalam `DB::transaction` dengan `lockForUpdate()` untuk mencegah overselling
- Harga dan stok dibaca ulang dari database, total dihitung oleh server
- Membuat order `pending`, `order_items`, mengurangi stok, dan mencatat `stock_movements` tipe `OUT` dengan reference kode order
- Session cart dikosongkan setelah berhasil

**Status pesanan**

```
pending → confirmed → processed → shipped → completed
```

- `confirm`: `pending` menjadi `confirmed`
- `status`: hanya transisi `confirmed → processed → shipped → completed`
- `cancel`: hanya dari `pending` atau `confirmed`. Stok dikembalikan dan dicatat `IN` dengan reference `CANCEL-<kode order>`

## Struktur Proyek

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/ (Category, Dashboard, Order, Product, ShippingService, Stock)
│   │   ├── AuthController.php
│   │   ├── CartController.php
│   │   ├── CheckoutController.php
│   │   ├── CustomerOrderController.php
│   │   └── ProductCatalogController.php
│   ├── Middleware/RoleMiddleware.php
│   └── Requests/ (Checkout, StoreProduct, StoreStockIn, UpdateProduct)
└── Models/ (Category, Order, OrderItem, Product, ShippingService, StockMovement, User)
database/
├── migrations/
└── seeders/ (AdminUserSeeder, MasterDataSeeder, DatabaseSeeder)
resources/views/ (layouts, auth, shop, cart, checkout, orders, admin)
routes/web.php
```

## Workflow Git

Pengembangan memakai branch per milestone, merge dengan `--no-ff`, dan tag di setiap akhir milestone.

| Milestone | Fokus | Tag |
|---|---|---|
| M0 | Setup Laravel, database, repository | `m0-setup` |
| M1 | Autentikasi dan role | `m1-auth-role` |
| M2 | Kategori, produk, upload gambar | `m2-master-product` |
| M3 | Barang masuk, stok, ekspedisi | `m3-inventory-shipping` |
| M4 | Katalog, detail, filter, pagination | `m4-storefront` |
| M5 | Session cart | `m5-cart` |
| M6 | Checkout, order, stok OUT | `m6-checkout` |
| M7 | Admin transaksi, validasi, cancel, status | `m7-order-admin` |
| M8 | QA, data uji, dokumentasi | `m8-final` |

```bash
git checkout main
git checkout -b milestone/01-auth-role
# kerjakan dan commit
git checkout main
git merge --no-ff milestone/01-auth-role
git tag m1-auth-role
```

Lihat riwayat: `git log --oneline --graph --decorate --all`.

## Pengujian Manual

| No | Skenario | Hasil yang diharapkan |
|---|---|---|
| 1 | Pelanggan registrasi | Akun tersimpan `role=customer` dan langsung login |
| 2 | Pelanggan membuka `/admin` | HTTP 403 |
| 3 | Admin login | Masuk dashboard admin |
| 4 | Admin tambah kategori dan produk | Produk tampil di admin dan katalog jika aktif |
| 5 | Admin upload gambar | Gambar tersimpan di public storage dan tampil di katalog |
| 6 | Admin stock-in 10 unit | Stok +10, movement `IN` tercatat |
| 7 | Pelanggan filter produk | Hasil sesuai keyword, kategori, harga, stok, urutan |
| 8 | Tambah cart melebihi stok | Validasi menolak |
| 9 | Checkout 2 unit | Order `pending`, stok -2, movement `OUT` tercatat |
| 10 | Admin confirm | Status `confirmed`, validator dan waktu validasi tersimpan |
| 11 | Admin cancel pending/confirmed | Status `cancelled`, stok kembali, movement `IN` tercatat |
| 12 | Ubah status order | Hanya urutan `confirmed → processed → shipped → completed` yang diterima |

Perintah pemeriksaan:

```bash
php artisan route:list
php artisan migrate:status
php artisan model:show Product
php artisan model:show Order
php artisan about
```

## Keamanan Dasar

- Semua form POST/PATCH/DELETE memakai `@csrf`
- Route admin dan pelanggan dipisah dengan `auth` dan `role:*`
- Upload gambar divalidasi sebagai `image` dengan batas ukuran
- Total order dihitung ulang oleh server, bukan dari input browser
- Checkout memakai DB transaction dan `lockForUpdate()`
- `CustomerOrderController` memeriksa kepemilikan order

## Troubleshooting

- **Gambar tidak tampil**: jalankan `php artisan storage:link`
- **Perubahan `.env` tidak terbaca**: jalankan `php artisan config:clear`
- **Error `Undefined variable` di Blade**: pastikan variabel dikirim dari controller, lalu `php artisan view:clear`
- **HTTP 403 di halaman checkout atau keranjang**: login sebagai akun `customer`, bukan admin

## Penulis

Muhammad Taqiy Mumtaz\
10906250013
