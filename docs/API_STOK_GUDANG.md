# API Stok Gudang IMS

Kontrak respons sama dengan API gudang lain dan stok yang dikirim hanya berasal dari Gudang Nanggewer.

```env
STOCK_API_ENABLED=true
STOCK_API_WAREHOUSE_CODE=IMS
STOCK_API_SOURCE_WAREHOUSE_NAME="Gudang Nanggewer"
STOCK_API_TOKEN=<token-rahasia-panjang-dan-acak>
STOCK_API_RATE_LIMIT_PER_MINUTE=60
```

Deploy tanpa mengubah stok atau riwayat mutasi:

```bash
php artisan migrate
php artisan db:seed --class=MenuSeeder
php artisan stock-api:backfill
php artisan optimize:clear
```

Setelah deploy, masuk ke **Masterdata → Akses API Stok** lalu tambahkan IP publik server pusat. Jika tidak ada IP aktif pada daftar tersebut, semua request API ditolak.

Semua request membutuhkan:

```http
Authorization: Bearer <API_TOKEN>
Accept: application/json
```

- `GET /api/v1/health`
- `GET /api/v1/stocks?updated_since=<ISO-8601>&updated_until=<ISO-8601>&page=1&per_page=100`
- `GET /api/v1/stocks?as_of=YYYY-MM-DD&page=1&per_page=100`

`as_of` menggunakan WIB dan memberikan saldo penutup Gudang Nanggewer pada tanggal tersebut. `updated_since` dan `updated_until` tidak dapat digabung dengan `as_of`. Barang nonaktif tetap dikirim dengan `status: inactive`; item yang dihapus dikirim dengan `status: deleted` dan qty `0`.
