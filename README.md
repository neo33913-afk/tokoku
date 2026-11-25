# Tokoku

Sistem informasi penjualan toko berbasis web. Dipakai untuk mengelola produk, pelanggan, member, transaksi kasir, laporan penjualan, dan belanja mandiri oleh pelanggan dengan pembayaran QRIS otomatis.

Dibuat dengan PHP native + MySQL dan dijalankan di XAMPP.

---

## Daftar Isi

1. [Teknologi](#teknologi)
2. [Peran Pengguna](#peran-pengguna)
3. [Fitur](#fitur)
4. [Alur Pembayaran QRIS](#alur-pembayaran-qris)
5. [Struktur Folder](#struktur-folder)
6. [Instalasi](#instalasi)
7. [Konfigurasi](#konfigurasi)
8. [Migrasi Database](#migrasi-database)
9. [Keamanan](#keamanan)
10. [Catatan Penting](#catatan-penting)

---

## Teknologi

| Bagian | Yang dipakai |
|---|---|
| Bahasa | PHP (native, tanpa framework) |
| Database | MySQL / MariaDB (`db_tokoku`), akses lewat PDO |
| Tampilan | Bootstrap 5.3.3, Bootstrap Icons, font Inter |
| Server lokal | XAMPP (Apache + MySQL) |
| Pembayaran | Midtrans Core API (QRIS) |
| Pembuat QR | Library JavaScript lokal (`assets/js/qrcode.js`, lisensi MIT) |

---

## Peran Pengguna

Tokoku memiliki tiga peran. Hak akses diatur di `includes/auth.php` dan dicek ulang ke database pada setiap permintaan, jadi perubahan peran langsung berlaku.

| Peran | Halaman yang bisa dibuka |
|---|---|
| **Admin** | Dashboard, Pelanggan, Member, Produk, Transaksi, Laporan Penjualan, Log Aktivitas, Kelola User |
| **Petugas** | Dashboard petugas, Transaksi, Member, Laporan Penjualan (hanya miliknya sendiri) |
| **Pelanggan** | Belanja, Riwayat Belanja |

---

## Fitur

### Akun dan Keamanan Akses
- Login dengan username dan password.
- Login dengan kartu RFID (`login_rfid.php`).
- Pendaftaran akun (`register.php`).
- Logout dengan perlindungan CSRF.
- Akses halaman dan aksi dibatasi per peran (halaman diatur `izin_halaman`, aksi diatur `izin_aksi`).
- Menu sidebar otomatis menyesuaikan peran yang sedang login.

### Dashboard
- Dashboard admin dan dashboard khusus petugas sebagai halaman awal setelah login.

### Manajemen Produk (Admin)
- Tambah, ubah, dan hapus produk.
- Pengelompokan produk berdasarkan kategori.
- Pengelolaan stok produk.
- Upload foto produk (disimpan di `uploads/produk/`, dilindungi `.htaccess`).

### Manajemen Pelanggan (Admin)
- Tambah, ubah, dan hapus data pelanggan.
- Akun pelanggan dapat dihubungkan ke data pelanggan agar bisa berbelanja mandiri.

### Member
- Kartu member dengan nomor otomatis berformat `MBR-000001`.
- Member aktif otomatis mendapat **diskon 15%** di setiap transaksi (dihitung ulang di server, tidak bisa diakali dari browser).
- Poin member: setiap kelipatan Rp10.000 belanja mendapat 1 poin.
- Bisa diakses oleh admin dan petugas.

### Transaksi Kasir (Admin dan Petugas)
- Memilih pelanggan, menambah produk ke keranjang, dan mengatur jumlah.
- Pencarian cepat produk dan pelanggan.
- Diskon member diterapkan otomatis.
- Stok berkurang otomatis, dengan pengunci baris database supaya stok tidak bisa minus saat ada transaksi bersamaan.
- Dua metode bayar:
  - **Tunai**.
  - **QRIS**: QR toko ditampilkan, pembeli mengetik nominal yang sama di aplikasi, lalu kasir menekan konfirmasi setelah uang masuk. Tombol bayar baru aktif setelah dikonfirmasi.
- Cetak struk otomatis setelah transaksi tersimpan.
- Hapus transaksi dengan **stok dikembalikan otomatis**. Petugas hanya boleh menghapus transaksi hari ini, riwayat lama hanya admin.
- Setiap transaksi mencatat petugas yang memprosesnya.

### Struk (`struk.php`)
- Format struk thermal lebar 57 mm.
- Tinggi kertas dihitung otomatis mengikuti isi struk.
- Berisi data toko, nomor transaksi, tanggal, pelanggan, rincian item, subtotal, diskon member, total, metode bayar, dan status lunas.
- Hanya bisa dicetak untuk transaksi yang sudah lunas.
- Pelanggan hanya bisa melihat struk miliknya sendiri.

### Laporan Penjualan
- Tabel detail penjualan per item.
- Filter berdasarkan periode (dari tanggal, sampai tanggal).
- Admin dapat memfilter per petugas.
- **Petugas hanya melihat transaksinya sendiri**, tidak tercampur dengan petugas lain.
- Hanya transaksi yang sudah lunas yang dihitung.
- **Cetak / Simpan PDF** (`laporan_cetak.php`): halaman A4 siap cetak berisi kop toko, periode, petugas, ringkasan (jumlah transaksi, barang terjual, penjualan kotor, diskon, pendapatan bersih), rincian per metode pembayaran, dan tabel detail dengan header yang berulang di setiap halaman.

### Belanja Mandiri Pelanggan
- Katalog produk dengan foto, harga, dan sisa stok.
- Pencarian produk dan filter kategori.
- Keranjang belanja yang tersimpan di browser.
- Diskon member otomatis untuk pelanggan yang punya kartu member aktif.
- Pembayaran **hanya lewat QRIS**, dipaksa di server sehingga tidak bisa diubah ke tunai dari browser.
- Halaman pembayaran dengan QR, nominal terisi otomatis, dan hitung mundur batas waktu.
- Status lunas terdeteksi otomatis tanpa perlu menekan tombol apa pun.

### Riwayat Belanja (Pelanggan)
- Daftar transaksi milik pelanggan beserta rincian item.
- Status pembayaran: Menunggu pembayaran, Lunas, atau Dibatalkan.
- Tombol **Bayar** untuk pesanan yang masih menunggu dan tombol **Struk** untuk yang sudah lunas.

### Log Aktivitas (Admin)
- Pencatatan aktivitas penting: penambahan dan penghapusan transaksi, perubahan role, pembayaran QRIS, dan lain-lain.

### Kelola User (Admin)
- Melihat daftar pengguna dan mengubah peran (admin, petugas, pelanggan).

### Tampilan
- Responsif untuk desktop dan ponsel.
- Mode terang dan gelap.
- Sidebar yang menyempit menjadi ikon, bisa disematkan tetap terbuka.
- Tanggal hari ini dalam bahasa Indonesia di bagian atas.
- Pesan notifikasi (flash) untuk setiap aksi.

---

## Alur Pembayaran QRIS

Ada dua cara QRIS dipakai, tergantung siapa yang bertransaksi.

### 1. Di kasir (admin / petugas)
QRIS statis toko ditampilkan apa adanya. Pembeli mengetik nominal sendiri, kasir mengecek notifikasi pembayaran, lalu mencentang konfirmasi sebelum transaksi disimpan.

### 2. Belanja mandiri (pelanggan) dengan Midtrans
1. Pelanggan menekan **Bayar dengan QRIS**.
2. Pesanan tersimpan dengan status **menunggu** dan stok ditahan.
3. Server meminta QRIS dinamis ke Midtrans, lalu halaman `bayar_qris.php` menampilkan QR dengan nominal terisi.
4. Halaman mengecek status ke server setiap 3 detik. Begitu pembayaran terdeteksi, status berubah menjadi **lunas** otomatis.
5. Jika batas waktu (15 menit) habis atau pembayaran gagal, pesanan menjadi **batal** dan stok dikembalikan otomatis.

> Catatan: QRIS dinamis buatan sendiri dari QRIS statis GoPay Merchant ditolak oleh GoPay ("barcode belum terdaftar"). Karena itu belanja mandiri memakai QRIS dinamis resmi dari Midtrans.

---

## Struktur Folder

```
tokoku/
│   actions.php              pemroses semua aksi form (simpan, hapus, belanja)
│   bayar_qris.php           halaman pembayaran QRIS + pengecekan lunas otomatis
│   index.php                pintu masuk halaman (routing + cek login/peran)
│   laporan_cetak.php        halaman cetak / simpan PDF laporan penjualan
│   login.php
│   login_rfid.php
│   logout.php
│   migrasi_qris.sql         perubahan tabel untuk pembayaran QRIS
│   register.php
│   struk.php                struk thermal 57 mm
│
├───assets
│   ├───css
│   │       style.css
│   └───js
│           belanja.js       katalog dan keranjang pelanggan
│           cari.js          pencarian
│           qrcode.js        pembuat QR lokal
│           script.js        logika halaman transaksi kasir
│           tema.js          mode terang/gelap dan sidebar
│
├───config
│       database.php         koneksi database
│       midtrans.php         Server Key Midtrans
│
├───includes
│       auth.php             login, peran, dan hak akses
│       footer.php
│       functions.php        fungsi bantu (rupiah, CSRF, diskon, poin, dll.)
│       header.php           layout, sidebar, topbar
│       midtrans.php         integrasi Midtrans (buat QRIS, cek status)
│       upload.php           upload foto produk
│
├───pages
│       belanja.php          katalog belanja pelanggan
│       dashboard.php        dashboard admin
│       laporan.php          laporan penjualan
│       log.php              log aktivitas
│       member.php           kartu member
│       pelanggan.php        data pelanggan
│       petugas.php          dashboard petugas
│       produk.php           data produk
│       riwayat.php          riwayat belanja pelanggan
│       transaksi.php        transaksi kasir
│       user.php             kelola user
│
└───uploads
    └───produk               foto produk
```

---

## Instalasi

1. Pasang **XAMPP** dan jalankan Apache serta MySQL.
2. Salin folder `tokoku` ke `C:\xampp\htdocs\`.
3. Buka phpMyAdmin, buat database `db_tokoku`, lalu impor struktur dan data database proyek.
4. Jalankan [migrasi database](#migrasi-database) di bawah.
5. Sesuaikan `config/database.php` bila nama database, pengguna, atau kata sandi MySQL berbeda.
6. Isi Server Key Midtrans di `config/midtrans.php` (lihat [Konfigurasi](#konfigurasi)).
7. Buka `http://localhost/tokoku/` di browser.

---

## Konfigurasi

### Database (`config/database.php`)
```php
$host = "localhost";
$dbname = "db_tokoku";
$username = "root";
$password = "";
```

### Midtrans (`config/midtrans.php`)
```php
const MIDTRANS_SERVER_KEY = 'ISI_SERVER_KEY_SANDBOX_DI_SINI';
const MIDTRANS_PRODUKSI = false;      // true untuk uang sungguhan
const MIDTRANS_MENIT_KADALUARSA = 15; // batas waktu pembayaran
```

- Cukup **Server Key**. Client Key tidak dipakai karena semua pemanggilan Midtrans dilakukan dari server.
- Selama `MIDTRANS_PRODUKSI = false`, QR hanya bisa dibayar lewat **simulator pembayaran Midtrans Sandbox**, bukan aplikasi pembayaran asli.
- Untuk produksi, ganti dengan Server Key produksi dan ubah `MIDTRANS_PRODUKSI` menjadi `true`.
- Jangan membagikan atau meng-upload Server Key ke repositori publik.

### Data toko di struk
Nama, alamat, kontak, dan slogan toko diatur di bagian atas `struk.php` dan `laporan_cetak.php`.

---

## Migrasi Database

Jalankan di phpMyAdmin pada database `db_tokoku`, **sebelum** memasang file terbaru.

**1. Pencatatan petugas pada transaksi** (untuk laporan per petugas):

```sql
ALTER TABLE tbl_transaksi
  ADD COLUMN id_user INT NULL AFTER id_member,
  ADD INDEX idx_transaksi_user (id_user);
```

**2. Pembayaran QRIS otomatis** (isi sama dengan `migrasi_qris.sql`):

```sql
ALTER TABLE tbl_transaksi
  ADD COLUMN status_bayar ENUM('lunas','menunggu','batal') NOT NULL DEFAULT 'lunas',
  ADD COLUMN midtrans_order_id VARCHAR(64) NULL,
  ADD COLUMN qr_data TEXT NULL,
  ADD COLUMN kadaluarsa DATETIME NULL,
  ADD INDEX idx_transaksi_status (status_bayar);
```

Transaksi lama otomatis berstatus `lunas`. Transaksi lama tidak punya data petugas, sehingga hanya terlihat oleh admin.

---

## Keamanan

- Semua query memakai prepared statement PDO.
- Token CSRF pada setiap form yang mengubah data.
- Output di halaman di-escape (`htmlspecialchars`) untuk mencegah XSS.
- ID sesi diganti saat login (mencegah session fixation).
- Peran pengguna dicek ulang ke database di setiap permintaan.
- Perhitungan diskon, total, metode bayar, dan hak akses selalu diputuskan di server, bukan dari data browser.
- Pelanggan hanya bisa membuka struk dan pesanan miliknya sendiri.
- Folder upload dilindungi `.htaccess`.

---

## Catatan Penting

- Pesanan QRIS pelanggan yang tidak dibayar menahan stok sampai batas waktu habis, lalu stok dikembalikan otomatis saat pesanan diproses.
- Status pembayaran QRIS diperbarui saat halaman bayar, riwayat, atau katalog dibuka (pengecekan berkala dari server). Notifikasi webhook Midtrans belum dipakai karena butuh alamat publik.
- Kalau terjadi error koneksi ke Midtrans di XAMPP, cek pengaturan sertifikat SSL cURL (`curl.cainfo` di `php.ini`).
- `qris_dinamis.php` dan fungsi `qris_dinamis()` merupakan sisa percobaan QRIS dinamis buatan sendiri dan tidak dipakai lagi.
