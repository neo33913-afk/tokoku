<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/upload.php';

// Wajib login; role diperiksa ulang ke database
require_login($pdo);

function kembali($page)
{
    header("Location: index.php?page=" . urlencode($page));
    exit;
}

/**
 * Mengubah error database menjadi pesan yang ramah dibaca.
 * Pesan asli dari MySQL hanya dicatat ke error_log, tidak ditampilkan.
 */
function pesan_error_database(PDOException $e, $action)
{
    error_log('[Tokoku] ' . $e->getMessage());

    // 23000 = pelanggaran constraint (foreign key / unique key)
    if ($e->getCode() === '23000') {
        switch ($action) {
            case 'produk_hapus':
                return 'Produk tidak bisa dihapus karena sudah dipakai di transaksi.';
            case 'pelanggan_hapus':
                return 'Pelanggan tidak bisa dihapus karena sudah memiliki transaksi.';
            case 'produk_simpan':
                return 'Kode produk sudah dipakai produk lain.';
        }
    }

    return 'Terjadi kesalahan pada database. Silakan coba lagi.';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kembali('dashboard');
}

$action = $_POST['action'] ?? '';

$halaman = [
    'pelanggan_simpan' => 'pelanggan',
    'pelanggan_hapus' => 'pelanggan',
    'produk_simpan' => 'produk',
    'produk_hapus' => 'produk',
    'transaksi_simpan' => 'transaksi',
    'transaksi_hapus' => 'transaksi',
    'user_role_ubah' => 'user',
    'belanja_simpan' => 'belanja'
];

try {
    verify_csrf();

    // Setiap role hanya boleh menjalankan aksi miliknya (lihat izin_aksi di includes/auth.php)
    if (!in_array($action, izin_aksi(user_role()), true)) {
        throw new RuntimeException('Anda tidak punya akses untuk aksi ini.');
    }

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

            // ----- Gambar produk -----
            $gambarLama = null;

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    SELECT gambar
                    FROM tbl_produk
                    WHERE id_produk = ?
                ");

                $stmt->execute([$id]);
                $gambarLama = $stmt->fetchColumn() ?: null;
            }

            $gambar = $gambarLama;
            $unggahan = null;

            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Diperiksa dulu; file baru dipindahkan setelah data berhasil disimpan
                $unggahan = validasi_gambar_produk($_FILES['gambar']);
                $gambar = $unggahan['nama'];
            } elseif (!empty($_POST['hapus_gambar'])) {
                $gambar = null;
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE tbl_produk
                    SET kode_produk = ?,
                        nama_produk = ?,
                        id_kategori = ?,
                        harga = ?,
                        stok = ?,
                        gambar = ?
                    WHERE id_produk = ?
                ");

                $stmt->execute([
                    $kode, $nama, $kategori, $harga, $stok, $gambar, $id
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
                    (kode_produk, nama_produk, id_kategori, harga, stok, gambar)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $kode, $nama, $kategori, $harga, $stok, $gambar
                ]);

                log_activity(
                    $pdo,
                    'INSERT PRODUK',
                    "Menambahkan produk: $nama"
                );

                set_flash('Produk berhasil ditambahkan.');
            }

            // Pindahkan file baru; jika gagal, perubahan data ikut dibatalkan (rollback)
            if ($unggahan) {
                pindahkan_gambar_produk($unggahan);
            }

            $pdo->commit();

            // File lama dihapus hanya setelah semuanya berhasil
            if ($gambarLama && $gambarLama !== $gambar) {
                hapus_gambar_produk($gambarLama);
            }

            kembali('produk');
            break;


        // =========================
        // HAPUS PRODUK
        // =========================
        case 'produk_hapus':

            $id = (int) ($_POST['id_produk'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT nama_produk, gambar
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

            // Produk sudah terhapus dari database: hapus juga file gambarnya
            hapus_gambar_produk($produk['gambar'] ?? null);

            log_activity(
                $pdo,
                'DELETE PRODUK',
                "Menghapus produk: " . $produk['nama_produk']
            );

            set_flash('Produk berhasil dihapus.');

            kembali('produk');
            break;


        // =========================
        // TAMBAH TRANSAKSI (MULTI ITEM + STOK)
        // =========================
        case 'transaksi_simpan':
        case 'belanja_simpan':

            $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
            $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
            $daftarProduk = $_POST['id_produk'] ?? [];
            $daftarJumlah = $_POST['jumlah'] ?? [];

            // Pembelian oleh pelanggan sendiri: pembeli dan tanggal ditentukan server,
            // bukan dari kiriman form (supaya tidak bisa membeli atas nama orang lain)
            if ($action === 'belanja_simpan') {
                $idPelanggan = (int) (user_login()['id_pelanggan'] ?? 0);
                $tanggal = date('Y-m-d');

                if ($idPelanggan <= 0) {
                    throw new RuntimeException(
                        'Akun Anda belum terhubung ke data pelanggan. Hubungi admin.'
                    );
                }
            }

            if ($idPelanggan <= 0) {
                throw new RuntimeException('Pelanggan wajib dipilih.');
            }

            if (
                !is_array($daftarProduk) ||
                !is_array($daftarJumlah) ||
                count($daftarProduk) === 0 ||
                count($daftarProduk) !== count($daftarJumlah)
            ) {
                throw new RuntimeException('Tambahkan minimal satu produk.');
            }

            $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

            if (!$date || $date->format('Y-m-d') !== $tanggal) {
                throw new RuntimeException('Tanggal transaksi tidak valid.');
            }

            // Gabungkan item yang produknya sama: [id_produk => jumlah]
            $daftarProduk = array_values($daftarProduk);
            $daftarJumlah = array_values($daftarJumlah);
            $items = [];

            foreach ($daftarProduk as $i => $idProduk) {
                $idProduk = (int) $idProduk;
                $jumlah = filter_var($daftarJumlah[$i], FILTER_VALIDATE_INT);

                if ($idProduk <= 0 || $jumlah === false || $jumlah <= 0) {
                    throw new RuntimeException('Data item transaksi tidak valid.');
                }

                $items[$idProduk] = ($items[$idProduk] ?? 0) + $jumlah;
            }

            // Urutkan supaya urutan penguncian baris selalu sama
            ksort($items);

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

            $pdo->beginTransaction();

            // Kunci baris produk selama transaksi berlangsung
            $stmtProduk = $pdo->prepare("
                SELECT nama_produk, harga, stok
                FROM tbl_produk
                WHERE id_produk = ?
                FOR UPDATE
            ");

            // Pengurangan stok yang aman: hanya berhasil jika stok masih cukup
            $stmtKurangiStok = $pdo->prepare("
                UPDATE tbl_produk
                SET stok = stok - ?
                WHERE id_produk = ? AND stok >= ?
            ");

            $detail = [];
            $total = 0;

            foreach ($items as $idProduk => $jumlah) {

                $stmtProduk->execute([$idProduk]);
                $produk = $stmtProduk->fetch();

                if (!$produk) {
                    throw new RuntimeException('Ada produk yang tidak ditemukan.');
                }

                if ((int) $produk['stok'] < $jumlah) {
                    throw new RuntimeException(
                        "Stok {$produk['nama_produk']} tidak cukup "
                        . "(tersisa {$produk['stok']}, diminta $jumlah)."
                    );
                }

                $stmtKurangiStok->execute([$jumlah, $idProduk, $jumlah]);

                if ($stmtKurangiStok->rowCount() !== 1) {
                    throw new RuntimeException(
                        "Stok {$produk['nama_produk']} tidak cukup."
                    );
                }

                $detail[] = [
                    'id_produk' => $idProduk,
                    'nama_produk' => $produk['nama_produk'],
                    'jumlah' => $jumlah,
                    'harga' => $produk['harga']
                ];

                $total += $produk['harga'] * $jumlah;
            }

            $stmt = $pdo->prepare("
                INSERT INTO tbl_transaksi
                (tanggal, id_pelanggan, total_belanja)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([$tanggal, $idPelanggan, $total]);

            $idTransaksi = $pdo->lastInsertId();

            $stmtDetail = $pdo->prepare("
                INSERT INTO tbl_detail_transaksi
                (id_transaksi, id_produk, nama_produk, jumlah, harga)
                VALUES (?, ?, ?, ?, ?)
            ");

            $ringkasan = [];

            foreach ($detail as $d) {
                $stmtDetail->execute([
                    $idTransaksi,
                    $d['id_produk'],
                    $d['nama_produk'],
                    $d['jumlah'],
                    $d['harga']
                ]);

                $ringkasan[] = $d['nama_produk'] . ' x' . $d['jumlah'];
            }

            log_activity(
                $pdo,
                'INSERT TRANSAKSI',
                "Transaksi #$idTransaksi oleh {$pelanggan['nama_pelanggan']}, "
                . implode(', ', $ringkasan)
                . ", total " . rupiah($total)
                . " (oleh " . user_role() . " " . user_login()['username'] . ")"
            );

            $pdo->commit();

            set_flash("Transaksi #$idTransaksi berhasil disimpan.");

            // Kembali ke halaman transaksi; struk muncul sebagai pop up dan dialog cetak terbuka
            if ($action === 'belanja_simpan') {
                // Pelanggan: kembali ke katalog, struk muncul sebagai pop up (tanpa cetak otomatis)
                header("Location: index.php?page=belanja&struk=" . (int) $idTransaksi);
            } else {
                header("Location: index.php?page=transaksi&struk=" . (int) $idTransaksi . "&auto=1");
            }
            exit;
            break;


        // =========================
        // HAPUS TRANSAKSI (STOK DIKEMBALIKAN)
        // =========================
        case 'transaksi_hapus':

            $id = (int) ($_POST['id_transaksi'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT id_transaksi, tanggal
                FROM tbl_transaksi
                WHERE id_transaksi = ?
            ");

            $stmt->execute([$id]);
            $transaksiLama = $stmt->fetch();

            if (!$transaksiLama) {
                throw new RuntimeException('Transaksi tidak ditemukan.');
            }

            // Petugas hanya boleh membatalkan transaksi hari ini; riwayat lama hanya admin
            if (
                user_role() === 'petugas' &&
                $transaksiLama['tanggal'] !== date('Y-m-d')
            ) {
                throw new RuntimeException(
                    'Petugas hanya bisa membatalkan transaksi hari ini. Hubungi admin.'
                );
            }

            $pdo->beginTransaction();

            // Kembalikan stok dari setiap item transaksi
            $stmt = $pdo->prepare("
                SELECT id_produk, jumlah
                FROM tbl_detail_transaksi
                WHERE id_transaksi = ?
            ");

            $stmt->execute([$id]);
            $itemLama = $stmt->fetchAll();

            $stmtKembalikan = $pdo->prepare("
                UPDATE tbl_produk
                SET stok = stok + ?
                WHERE id_produk = ?
            ");

            foreach ($itemLama as $item) {
                $stmtKembalikan->execute([
                    $item['jumlah'],
                    $item['id_produk']
                ]);
            }

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
                "Menghapus transaksi #$id beserta detailnya, stok dikembalikan"
                . " (oleh " . user_role() . " " . user_login()['username'] . ")"
            );

            $pdo->commit();

            set_flash("Transaksi #$id berhasil dihapus dan stok dikembalikan.");

            kembali('transaksi');
            break;


        // =========================
        // UBAH ROLE USER (hanya admin)
        // =========================
        case 'user_role_ubah':

            $idUser = (int) ($_POST['id_user'] ?? 0);
            $roleBaru = $_POST['role'] ?? '';

            if (!in_array($roleBaru, ['admin', 'petugas', 'pelanggan'], true)) {
                throw new RuntimeException('Role tidak valid.');
            }

            // Mencegah admin mengunci dirinya sendiri; dengan ini selalu ada minimal satu admin
            if ($idUser === (int) user_login()['id_user']) {
                throw new RuntimeException('Role akun Anda sendiri tidak bisa diubah.');
            }

            $stmt = $pdo->prepare("
                SELECT username, role
                FROM tbl_user
                WHERE id_user = ?
            ");

            $stmt->execute([$idUser]);
            $target = $stmt->fetch();

            if (!$target) {
                throw new RuntimeException('User tidak ditemukan.');
            }

            if ($target['role'] === $roleBaru) {
                set_flash("Role {$target['username']} sudah $roleBaru.", 'info');
                kembali('user');
            }

            $stmt = $pdo->prepare("
                UPDATE tbl_user
                SET role = ?
                WHERE id_user = ?
            ");

            $stmt->execute([$roleBaru, $idUser]);

            log_activity(
                $pdo,
                'UPDATE ROLE',
                "Admin " . user_login()['username'] . " mengubah role {$target['username']}: "
                . "{$target['role']} menjadi $roleBaru"
            );

            set_flash("Role {$target['username']} diubah menjadi $roleBaru.");

            kembali('user');
            break;


        default:
            throw new RuntimeException('Aksi tidak dikenali.');
    }

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash(pesan_error_database($e, $action), 'danger');

    kembali($halaman[$action] ?? 'dashboard');

} catch (RuntimeException $e) {

    // Pesan validasi buatan sendiri, aman ditampilkan
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash($e->getMessage(), 'danger');

    kembali($halaman[$action] ?? 'dashboard');

} catch (Throwable $e) {

    // Error tak terduga: catat ke log, tampilkan pesan umum
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[Tokoku] ' . $e->getMessage());

    set_flash('Terjadi kesalahan. Silakan coba lagi.', 'danger');

    kembali($halaman[$action] ?? 'dashboard');
}