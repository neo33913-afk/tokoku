<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

function kembali($page)
{
    header("Location: index.php?page=" . urlencode($page));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kembali('dashboard');
}

try {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    switch ($action) {

        // =========================
        // SIMPAN PELANGGAN
        // =========================
        case 'pelanggan_simpan':

            $id = (int) ($_POST['id_pelanggan'] ?? 0);
            $nama = trim($_POST['nama_pelanggan'] ?? '');
            $kota = trim($_POST['kota'] ?? '');

            if ($nama === '' || $kota === '') {
                throw new RuntimeException('Nama dan kota wajib diisi.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE tbl_pelanggan
                    SET nama_pelanggan = ?, kota = ?
                    WHERE id_pelanggan = ?
                ");

                $stmt->execute([$nama, $kota, $id]);

                log_activity(
                    $pdo,
                    'UPDATE PELANGGAN',
                    "Mengubah data pelanggan: $nama"
                );

                set_flash('Data pelanggan berhasil diperbarui.');

            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO tbl_pelanggan
                    (nama_pelanggan, kota)
                    VALUES (?, ?)
                ");

                $stmt->execute([$nama, $kota]);

                log_activity(
                    $pdo,
                    'INSERT PELANGGAN',
                    "Menambahkan pelanggan: $nama"
                );

                set_flash('Pelanggan berhasil ditambahkan.');
            }

            kembali('pelanggan');
            break;


        // =========================
        // HAPUS PELANGGAN
        // =========================
        case 'pelanggan_hapus':

            $id = (int) ($_POST['id_pelanggan'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT nama_pelanggan
                FROM tbl_pelanggan
                WHERE id_pelanggan = ?
            ");

            $stmt->execute([$id]);
            $pelanggan = $stmt->fetch();

            if (!$pelanggan) {
                throw new RuntimeException('Pelanggan tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                DELETE FROM tbl_pelanggan
                WHERE id_pelanggan = ?
            ");

            $stmt->execute([$id]);

            log_activity(
                $pdo,
                'DELETE PELANGGAN',
                "Menghapus pelanggan: " . $pelanggan['nama_pelanggan']
            );

            set_flash('Pelanggan berhasil dihapus.');

            kembali('pelanggan');
            break;


        // =========================
        // SIMPAN PRODUK
        // =========================
        case 'produk_simpan':

            $id = (int) ($_POST['id_produk'] ?? 0);
            $kode = trim($_POST['kode_produk'] ?? '');
            $nama = trim($_POST['nama_produk'] ?? '');
            $kategori = $_POST['id_kategori'] ?? '';
            $harga = $_POST['harga'] ?? '';
            $stok = filter_var($_POST['stok'] ?? null, FILTER_VALIDATE_INT);

            if (
                $kode === '' ||
                $nama === '' ||
                !is_numeric($harga) ||
                $stok === false ||
                $stok === null ||
                (float) $harga < 0 ||
                $stok < 0
            ) {
                throw new RuntimeException('Data produk tidak valid.');
            }

            $kategori = $kategori === '' ? null : (int) $kategori;

            if ($kategori !== null) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM tbl_kategori
                    WHERE id_kategori = ?
                ");

                $stmt->execute([$kategori]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException('Kategori tidak ditemukan.');
                }
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE tbl_produk
                    SET kode_produk = ?,
                        nama_produk = ?,
                        id_kategori = ?,
                        harga = ?,
                        stok = ?
                    WHERE id_produk = ?
                ");

                $stmt->execute([
                    $kode, $nama, $kategori, $harga, $stok, $id
                ]);

                log_activity(
                    $pdo,
                    'UPDATE PRODUK',
                    "Mengubah produk: $nama"
                );

                set_flash('Data produk berhasil diperbarui.');

            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO tbl_produk
                    (kode_produk, nama_produk, id_kategori, harga, stok)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $kode, $nama, $kategori, $harga, $stok
                ]);

                log_activity(
                    $pdo,
                    'INSERT PRODUK',
                    "Menambahkan produk: $nama"
                );

                set_flash('Produk berhasil ditambahkan.');
            }

            kembali('produk');
            break;


        // =========================
        // HAPUS PRODUK
        // =========================
        case 'produk_hapus':

            $id = (int) ($_POST['id_produk'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT nama_produk
                FROM tbl_produk
                WHERE id_produk = ?
            ");

            $stmt->execute([$id]);
            $produk = $stmt->fetch();

            if (!$produk) {
                throw new RuntimeException('Produk tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                DELETE FROM tbl_produk
                WHERE id_produk = ?
            ");

            $stmt->execute([$id]);

            log_activity(
                $pdo,
                'DELETE PRODUK',
                "Menghapus produk: " . $produk['nama_produk']
            );

            set_flash('Produk berhasil dihapus.');

            kembali('produk');
            break;


        // =========================
        // TAMBAH TRANSAKSI
        // =========================
        case 'transaksi_simpan':

            $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
            $idProduk = (int) ($_POST['id_produk'] ?? 0);
            $jumlah = filter_var($_POST['jumlah'] ?? null, FILTER_VALIDATE_INT);
            $tanggal = $_POST['tanggal'] ?? date('Y-m-d');

            if (
                $idPelanggan <= 0 ||
                $idProduk <= 0 ||
                $jumlah === false ||
                $jumlah === null ||
                $jumlah <= 0
            ) {
                throw new RuntimeException('Data transaksi tidak valid.');
            }

            $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

            if (!$date || $date->format('Y-m-d') !== $tanggal) {
                throw new RuntimeException('Tanggal transaksi tidak valid.');
            }

            $stmt = $pdo->prepare("
                SELECT nama_pelanggan
                FROM tbl_pelanggan
                WHERE id_pelanggan = ?
            ");

            $stmt->execute([$idPelanggan]);
            $pelanggan = $stmt->fetch();

            if (!$pelanggan) {
                throw new RuntimeException('Pelanggan tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                SELECT nama_produk, harga
                FROM tbl_produk
                WHERE id_produk = ?
            ");

            $stmt->execute([$idProduk]);
            $produk = $stmt->fetch();

            if (!$produk) {
                throw new RuntimeException('Produk tidak ditemukan.');
            }

            $total = $produk['harga'] * $jumlah;

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO tbl_transaksi
                (tanggal, id_pelanggan, total_belanja)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $tanggal,
                $idPelanggan,
                $total
            ]);

            $idTransaksi = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO tbl_detail_transaksi
                (id_transaksi, id_produk, nama_produk, jumlah, harga)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $idTransaksi,
                $idProduk,
                $produk['nama_produk'],
                $jumlah,
                $produk['harga']
            ]);

            log_activity(
                $pdo,
                'INSERT TRANSAKSI',
                "Transaksi #$idTransaksi oleh {$pelanggan['nama_pelanggan']}, produk {$produk['nama_produk']}, total " . rupiah($total)
            );

            $pdo->commit();

            set_flash("Transaksi #$idTransaksi berhasil disimpan.");

            kembali('transaksi');
            break;


        // =========================
        // HAPUS TRANSAKSI
        // =========================
        case 'transaksi_hapus':

            $id = (int) ($_POST['id_transaksi'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT id_transaksi
                FROM tbl_transaksi
                WHERE id_transaksi = ?
            ");

            $stmt->execute([$id]);

            if (!$stmt->fetch()) {
                throw new RuntimeException('Transaksi tidak ditemukan.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                DELETE FROM tbl_detail_transaksi
                WHERE id_transaksi = ?
            ");

            $stmt->execute([$id]);

            $stmt = $pdo->prepare("
                DELETE FROM tbl_transaksi
                WHERE id_transaksi = ?
            ");

            $stmt->execute([$id]);

            log_activity(
                $pdo,
                'DELETE TRANSAKSI',
                "Menghapus transaksi #$id beserta detailnya"
            );

            $pdo->commit();

            set_flash("Transaksi #$id berhasil dihapus.");

            kembali('transaksi');
            break;


        default:
            throw new RuntimeException('Aksi tidak dikenali.');
    }

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash($e->getMessage(), 'danger');

    $halaman = [
        'pelanggan_simpan' => 'pelanggan',
        'pelanggan_hapus' => 'pelanggan',
        'produk_simpan' => 'produk',
        'produk_hapus' => 'produk',
        'transaksi_simpan' => 'transaksi',
        'transaksi_hapus' => 'transaksi'
    ];

    kembali($halaman[$_POST['action'] ?? ''] ?? 'dashboard');
}