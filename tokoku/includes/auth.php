<?php

/**
 * Autentikasi dan role.
 * Role: admin, petugas, pelanggan.
 * Wajib dipanggil SETELAH session_start() dan functions.php.
 */

date_default_timezone_set('Asia/Jakarta');

// Semua halaman di index.php (urutan di sini = urutan menu)
function semua_halaman()
{
    return [
        'dashboard' => 'Dashboard',
        'petugas' => 'Dashboard',
        'pelanggan' => 'Pelanggan',
        'produk' => 'Produk',
        'transaksi' => 'Transaksi',
        'laporan' => 'Laporan Penjualan',
        'log' => 'Log Aktivitas',
        'belanja' => 'Belanja',
        'riwayat' => 'Riwayat Belanja',
        'user' => 'Kelola User'
    ];
}

// Halaman yang boleh dibuka tiap role. Halaman pertama = halaman awal setelah login.
function izin_halaman($role)
{
    $izin = [
        'admin' => ['dashboard', 'pelanggan', 'produk', 'transaksi', 'laporan', 'log', 'user'],
        'petugas' => ['petugas', 'transaksi'],
        'pelanggan' => ['belanja', 'riwayat']
    ];

    return $izin[$role] ?? [];
}

// Aksi di actions.php yang boleh dilakukan tiap role
function izin_aksi($role)
{
    $izin = [
        'admin' => [
            'pelanggan_simpan', 'pelanggan_hapus',
            'produk_simpan', 'produk_hapus',
            'transaksi_simpan', 'transaksi_hapus',
            'user_role_ubah'
        ],
        'petugas' => ['transaksi_simpan', 'transaksi_hapus'],
        'pelanggan' => ['belanja_simpan']
    ];

    return $izin[$role] ?? [];
}

function halaman_awal($role)
{
    $daftar = izin_halaman($role);

    return 'index.php?page=' . urlencode($daftar[0] ?? 'dashboard');
}

function user_login()
{
    return $_SESSION['user'] ?? null;
}

function user_role()
{
    return $_SESSION['user']['role'] ?? null;
}

function redirect_ke($url)
{
    header('Location: ' . $url);
    exit;
}

// Menyimpan user ke sesi setelah login berhasil
function masuk_user(array $row)
{
    // Ganti ID sesi supaya sesi lama tidak bisa dipakai ulang (session fixation)
    session_regenerate_id(true);

    unset($_SESSION['csrf_token']);

    $_SESSION['user'] = [
        'id_user' => (int) $row['id_user'],
        'username' => $row['username'],
        'nama_lengkap' => $row['nama_lengkap'],
        'role' => $row['role'],
        'id_pelanggan' => $row['id_pelanggan'] !== null ? (int) $row['id_pelanggan'] : null
    ];
}

function keluar_user()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }

    session_destroy();
}

/**
 * Pastikan user sudah login. Data user dimuat ulang dari database di setiap
 * permintaan, jadi perubahan role oleh admin langsung berlaku dan akun yang
 * dihapus langsung tidak bisa dipakai lagi.
 */
function require_login(PDO $pdo)
{
    $id = $_SESSION['user']['id_user'] ?? 0;

    if (!$id) {
        redirect_ke('login.php');
    }

    $stmt = $pdo->prepare("
        SELECT id_user, username, nama_lengkap, role, id_pelanggan
        FROM tbl_user
        WHERE id_user = ?
    ");

    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        keluar_user();
        redirect_ke('login.php');
    }

    $_SESSION['user'] = [
        'id_user' => (int) $row['id_user'],
        'username' => $row['username'],
        'nama_lengkap' => $row['nama_lengkap'],
        'role' => $row['role'],
        'id_pelanggan' => $row['id_pelanggan'] !== null ? (int) $row['id_pelanggan'] : null
    ];
}