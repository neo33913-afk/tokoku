# 🛒 Tokoku – Sistem Informasi Penjualan Toko

**Tokoku** adalah aplikasi web sederhana untuk mengelola data penjualan toko yang dibangun menggunakan PHP dan MariaDB. Aplikasi ini dikembangkan sebagai proyek pembelajaran dalam penerapan pengelolaan database dan pemrograman web.

## 📌 Tentang Proyek

Tokoku dirancang untuk membantu pengelolaan data pelanggan, produk, kategori, transaksi, serta laporan penjualan dalam satu aplikasi. Sistem menggunakan database MariaDB sebagai penyimpanan data dan PHP sebagai penghubung antara website dengan database.

## ✨ Fitur Utama

- **Dashboard** – Menampilkan ringkasan jumlah pelanggan, produk, transaksi, dan total penjualan.
- **Manajemen Pelanggan** – Menambah, melihat, mengubah, dan menghapus data pelanggan.
- **Manajemen Produk** – Mengelola kode produk, nama produk, kategori, harga, dan stok.
- **Manajemen Transaksi** – Membuat transaksi penjualan, melihat riwayat, dan menghapus transaksi.
- **Laporan Penjualan** – Menampilkan laporan detail transaksi berdasarkan data database.
- **Log Aktivitas** – Mencatat aktivitas penambahan, perubahan, dan penghapusan data.

## 🛠️ Teknologi yang Digunakan

| Teknologi | Kegunaan |
|---|---|
| PHP | Backend aplikasi |
| MariaDB | Database |
| HTML5 | Struktur halaman |
| CSS3 | Desain tampilan |
| JavaScript | Interaksi halaman |
| Bootstrap 5 | Framework antarmuka |
| PDO | Koneksi dan pengelolaan query database |
| XAMPP | Lingkungan server lokal |

## 🗄️ Struktur Database

Database yang digunakan bernama `db_tokoku`, dengan enam tabel utama:

1. `tbl_pelanggan` – Menyimpan data pelanggan.
2. `tbl_produk` – Menyimpan data produk.
3. `tbl_kategori` – Menyimpan kategori produk.
4. `tbl_transaksi` – Menyimpan data transaksi penjualan.
5. `tbl_detail_transaksi` – Menyimpan detail produk dalam transaksi.
6. `tbl_log` – Menyimpan catatan aktivitas sistem.

Selain tabel utama, database juga menggunakan View, Stored Procedure, Function, Trigger, JOIN, Subquery, TCL, dan Index sebagai penerapan materi pemrograman database lanjutan.

## 📂 Struktur Folder

```text
tokoku/
│
├── assets/
│   └── css/
│       └── style.css
│
├── config/
│   └── database.php
│
├── includes/
│   ├── functions.php
│   ├── header.php
│   └── footer.php
│
├── pages/
│   ├── dashboard.php
│   ├── pelanggan.php
│   ├── produk.php
│   ├── transaksi.php
│   ├── laporan.php
│   └── log.php
│
├── actions.php
├── index.php
└── README.md
```

## ⚙️ Cara Instalasi

1. Pastikan XAMPP sudah terpasang.
2. Aktifkan Apache dan MySQL.
3. Clone repository ini ke folder `htdocs`.

```bash
git clone https://github.com/USERNAME/tokoku.git
```

4. Import database `db_tokoku` melalui phpMyAdmin.
5. Sesuaikan konfigurasi database pada `config/database.php`.

```php
$host = "localhost";
$dbname = "db_tokoku";
$username = "root";
$password = "";
```

6. Jalankan aplikasi melalui browser:

```text
http://localhost/tokoku/
```

## 🎯 Tujuan Pengembangan

Proyek ini bertujuan untuk menerapkan konsep pemrograman web dan database, khususnya:

- Implementasi CRUD menggunakan PHP.
- Penggunaan relasi antar tabel MariaDB.
- Penerapan JOIN dan Subquery.
- Penggunaan View untuk laporan penjualan.
- Penerapan Stored Procedure dan Function.
- Penggunaan Trigger untuk audit aktivitas.
- Penerapan transaksi database menggunakan COMMIT dan ROLLBACK.
- Penggunaan Index untuk optimasi query.

## 🚀 Pengembangan Selanjutnya

Beberapa fitur yang direncanakan untuk pengembangan berikutnya:

- Pencarian dan filter data.
- Pengelolaan stok otomatis.
- Detail transaksi yang lebih lengkap.
- Cetak laporan dalam format PDF.
- Sistem login dan hak akses pengguna.
- Pengembangan tampilan yang lebih responsif.

## 👨‍💻 Pengembang

Dikembangkan sebagai proyek pembelajaran Rekayasa Perangkat Lunak (RPL).

**Tokoku – Sistem Informasi Penjualan Toko**

---

*Dibuat untuk mempelajari dan menerapkan pengembangan aplikasi web serta pengelolaan database menggunakan PHP dan MariaDB.*
