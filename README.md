# Laravel Online Sales

Aplikasi penjualan online berbasis Laravel 13 dengan dua role pengguna: **Admin** dan **Pelanggan**. Proyek ini dibuat sebagai praktikum lanjutan (Program Studi S1 Sistem Informasi, Universitas Telkom Purwokerto), tidak berhenti pada CRUD sederhana, tetapi membangun alur bisnis lengkap dari master barang sampai validasi pesanan, lalu dikembangkan menjadi REST API dengan token Sanctum, pengujian otomatis, dan deployment cloud.

**Production:** https://laravel-online-sales-109062500135.vercel.app

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

**REST API (`/api/v1`)**
- Autentikasi token (Laravel Sanctum): login, me, logout
- Publik: produk, kategori, ekspedisi
- Pelanggan: checkout, riwayat/detail pesanan, pembatalan pesanan `pending`
- Admin: daftar transaksi, detail, konfirmasi, ubah status, pembatalan

## Teknologi

- PHP 8.3 atau lebih baru
- Laravel 13.x (Laravel 12 masih bisa dipakai, periksa kompatibilitas versi PHP)
- MySQL / MariaDB
- Blade dan CSS sederhana (`public/css/store.css`)
- Laragon (lingkungan lokal), Composer, Git
- Laravel Sanctum, Postman, Laravel Feature Test
- Docker (FrankenPHP), Vercel, Railway MySQL

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

Buka `http://127.0.0.1:8000`. Base URL API lokal: `http://127.0.0.1:8000/api/v1`.

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

## REST API

Prefix `/api/v1`, respons JSON. Kirim header `Accept: application/json`; endpoint terproteksi memakai `Authorization: Bearer <token>` dari hasil login.

| Method | URL | Akses | Keterangan |
|---|---|---|---|
| GET | `/products`, `/products/{slug}` | Publik | Produk aktif |
| GET | `/categories`, `/shipping-services` | Publik | Kategori dan ekspedisi |
| POST | `/auth/login` | Publik | Mengembalikan `access_token` |
| GET | `/auth/me` | Token | Profil user |
| POST | `/auth/logout` | Token | Cabut token aktif |
| POST | `/checkout` | Customer | Order `pending`, stok berkurang |
| GET | `/orders`, `/orders/{order}` | Customer | Riwayat dan detail (milik sendiri) |
| PATCH | `/orders/{order}/cancel` | Customer | Batalkan order `pending` milik sendiri |
| GET | `/admin/orders`, `/admin/orders/{order}` | Admin | Daftar dan detail transaksi |
| PATCH | `/admin/orders/{order}/confirm` | Admin | `pending` menjadi `confirmed` |
| PATCH | `/admin/orders/{order}/status` | Admin | `processed`, `shipped`, `completed` |
| PATCH | `/admin/orders/{order}/cancel` | Admin | Batalkan `pending`/`confirmed` |

### Endpoint tambahan: Customer Cancel Order

`PATCH /api/v1/orders/{order}/cancel` (Bearer token, role customer, tanpa request body)

- Hanya order `pending` milik sendiri. Stok dikembalikan, movement `IN` dengan reference `CANCEL-<kode order>`.

| Status | Penyebab |
|---|---|
| 200 | Berhasil, `data.status` = `cancelled` |
| 401 | Tanpa token atau token tidak valid |
| 403 | Token bukan role customer |
| 404 | Order tidak ada atau milik user lain |
| 409 | Status order bukan `pending` |

### Kode status umum

200/201 berhasil, 401 belum login, 403 role tidak berhak, 404 tidak ditemukan atau bukan milik user, 409 transisi status tidak valid, 422 validasi gagal.

## Desain Database

| Tabel | Fungsi |
|---|---|
| `users` | Akun admin dan pelanggan (kolom `role`) |
| `personal_access_tokens` | Token Sanctum untuk REST API |
| `sessions`, `cache` | Session dan cache di database (container production tanpa state) |
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
- `cancel` (admin): hanya dari `pending` atau `confirmed`. Stok dikembalikan dan dicatat `IN` dengan reference `CANCEL-<kode order>`
- `cancel` (customer, API): hanya dari `pending` dan hanya order miliknya

## Struktur Proyek

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/ (Category, Dashboard, Order, Product, ShippingService, Stock)
│   │   ├── Api/V1/ (Auth, Product, Checkout, CustomerOrder, Admin/Order)
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
routes/
├── web.php
└── api.php
tests/Feature/Api/
postman/
docs/
Dockerfile.vercel, Caddyfile, vercel.json, .dockerignore
```

## Riwayat Tag

```
m0-setup        chore: initialize Laravel online sales project
m1-auth-role    Merge branch 'milestone/01-auth-role'
m10-api-tested  add untracked files
m11-vercel-live merge: milestone M11 Vercel deployment
m12-extra-endpoint feat(api): customer cancel pending order
m2-master-product t tag m2-master-product
m3-inventory-shipping Merge branch 'milestone/03-inventory-shipping'
m4-storefront   Merge branch 'milestone/04-storefront'
m5-cart         Merge branch 'milestone/05-cart'
m6-checkout     Merge branch 'milestone/06-checkout'
m7-order-admin  Merge branch 'milestone/07-order-admin'
m8-final        t tag m8-final
m9-rest-api     merge: milestone M9 REST API

```

## Riwayat commit
```
* 95fe1b1 (HEAD -> main, tag: m12-extra-endpoint, origin/main) feat(api): customer cancel pending order

* 95fe1b1 (HEAD -> main, tag: m12-extra-endpoint, origin/main) feat(api): customer cancel pending order
* 95fe1b1 (HEAD -> main, tag: m12-extra-endpoint, origin/main) feat(api): customer cancel pending order
*   2c5599f (tag: m11-vercel-live, feature/customer-cancel-order) merge: milestone M11 Vercel deployment
|\
| * 1dad799 (milestone/m11-vercel-deployment) docs(deploy): add production environment notes
| * 5e77dc7 (origin/milestone/m11-vercel-deployment) fix(deploy): force https and trust proxies
| * 8e66e15 chore(deploy): add Vercel container configuration
|/
* 92a738d (tag: m10-api-tested) add untracked files
*   2410309 merge: milestone M10 API testing
|\
| * f817869 (milestone/m10-api-testing) test(api): add Postman collection for REST API
| * cceae7c test(api): add catalog auth and checkout feature tests
|/
*   b1b86a2 (tag: m9-rest-api) merge: milestone M9 REST API
|\
| * ac522fe (milestone/m9-rest-api) feat(api): add catalog auth checkout and order endpoints
| * 55a2eaf feat(api): add API requests and JSON resources
| * b12a2aa feat(api): install Sanctum and enable API routing
|/
* 7038641 edit: README.md
*   24b9151 (tag: m8-final) t tag m8-final Merge branch 'milestone/08-qa'
|\
| * 8e6f657 (milestone/08-qa) fix: resolve final QA findings
|/
* 8851310 fix: checkout/index.blade.php
* f2ce87a docs: fix productcontroller
*   2a354e6 (tag: m7-order-admin) Merge branch 'milestone/07-order-admin' t tag m7-order-admin
|\
| * c6f57b5 (milestone/07-order-admin) feat(order): add order status workflow and stock rollback on cancel
| * 8cce9c1 feat(admin): add transaction dashboard and order validation
|/
*   377df28 (tag: m6-checkout) Merge branch 'milestone/06-checkout' t tag m6-checkout
|\
| * 8f114b0 (milestone/06-checkout) feat(checkout): add transactional checkout and customer order history 
| * c17f00b feat(order): add order schema and relationships
|/
*   545661d (tag: m5-cart) Merge branch 'milestone/05-cart' t tag m5-cart
|\
| * f50c538 (milestone/05-cart) feat(cart): add session based shopping cart
|/
*   7adad17 (tag: m4-storefront) Merge branch 'milestone/04-storefront' t tag m4-storefront
|\
| * cde6b5c (milestone/04-storefront) feat(storefront): add product detail by slug
| * 8c670b2 feat(storefront): add product catalog filters and pagination
|/
*   7f2a47b (tag: m3-inventory-shipping) Merge branch 'milestone/03-inventory-shipping' t tag m3-inventory-shipping
|\
| * 28bc4cc (milestone/03-inventory-shipping) feat(shipping): add shipping service management
| * d76372a feat(inventory): add stock movement and stock-in transaction
|/
*   5ab6b7a (tag: m2-master-product) t tag m2-master-product Merge branch 'milestone/02-master-product'
|\
| * 2987fe9 (milestone/02-master-product) feat(admin): add category management
| * 14b04da feat(admin): add product management with image upload
| * bcba497 feat(catalog): add category and product schema
|/
*   a9d8d13 (tag: m1-auth-role) Merge branch 'milestone/01-auth-role'
|\
| * 6e74bb4 (milestone/01-auth-role) feat(auth): add role middleware and admin seeder
| * 337170f feat(auth): add customer registration and login
|/
* 0ca368f (tag: m0-setup) chore: initialize Laravel online sales project
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
| M9 | REST API dengan Sanctum | `<tag M9>` |
| M10 | Pengujian API (Postman, Feature Test) | `m10-api-tested` |
| M11 | Deployment Vercel dan Railway | `m11-vercel-live` |
| M12 | Endpoint tambahan customer cancel order | `m12-extra-endpoint` |

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

## Pengujian API

**Postman** (folder `postman/`): atur variable collection `base_url` ke `http://127.0.0.1:8000/api/v1` (lokal) atau `https://laravel-online-sales-109062500135.vercel.app/api/v1` (production). `customer_token`, `admin_token`, dan `order_id` terisi otomatis oleh script post-response.

**Feature Test:**

```bash
php artisan test
php artisan test --filter=CustomerCancelOrderTest
```

> Test memakai `RefreshDatabase`. Pastikan variable `DB_*` tidak menunjuk ke database cloud sebelum menjalankan test.

## Deployment

- Hosting: Vercel (Container Runtime) dengan `Dockerfile.vercel`, `Caddyfile` (FrankenPHP), dan `vercel.json`
- Database: Railway MySQL; migrasi dengan `php artisan migrate --force` dari lokal
- Environment production: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, `DB_*`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `LOG_CHANNEL=stderr`
- `trustProxies` dan `URL::forceScheme('https')` aktif di production
- `APP_KEY`, password database, dan token tidak boleh masuk repository
- Penyimpanan file container sementara, sehingga gambar produk yang diunggah tidak persisten di production

## Keamanan Dasar

- Semua form POST/PATCH/DELETE memakai `@csrf`
- Route admin dan pelanggan dipisah dengan `auth` dan `role:*`
- Upload gambar divalidasi sebagai `image` dengan batas ukuran
- Total order dihitung ulang oleh server, bukan dari input browser
- Checkout memakai DB transaction dan `lockForUpdate()`
- `CustomerOrderController` memeriksa kepemilikan order
- API memakai token Sanctum dan middleware `role`; order milik user lain dijawab 404

## Troubleshooting

- **Gambar tidak tampil**: jalankan `php artisan storage:link`
- **Perubahan `.env` tidak terbaca**: jalankan `php artisan config:clear`
- **Error `Undefined variable` di Blade**: pastikan variabel dikirim dari controller, lalu `php artisan view:clear`
- **HTTP 403 di halaman checkout atau keranjang**: login sebagai akun `customer`, bukan admin
- **API mengembalikan HTML (halaman login)**: tambahkan header `Accept: application/json` dan pastikan token Bearer valid
- **Aplikasi lokal membaca database cloud**: `$env:DB_*` masih aktif di terminal; hapus dengan `Remove-Item Env:DB_*` lalu `php artisan config:clear`
- **CSS tidak tampil di production**: pastikan `URL::forceScheme('https')` dan `trustProxies` aktif, lalu redeploy

## Penulis

Muhammad Taqiy Mumtaz\
10906250013