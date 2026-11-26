<?php

/**
 * Login dengan kartu RFID.
 * Simpan sebagai: login_rfid.php (sejajar dengan login.php)
 *
 * Aturan keamanan:
 * - Hanya akun ber-role "pelanggan" dan "petugas" yang bisa masuk lewat kartu.
 *   Admin tetap wajib memakai username + password.
 * - Kartu harus milik member yang berstatus aktif.
 * - Maksimal 5 kali salah scan, lalu ditahan 1 menit.
 */

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// MODE UJI COBA: true = pesan penolakan menjelaskan alasan pastinya.
// Setelah login RFID berjalan, ubah menjadi false.
const RFID_DEBUG = true;

function alasan_rfid(PDO $pdo, $mentah, $uid)
{
    $info = "Kode terbaca: " . json_encode($mentah) . " (panjang " . strlen($mentah) . "). ";

    if (!preg_match('/^[A-Z0-9]{4,30}$/', $uid)) {
        return $info . 'Format kode tidak valid (harus 4-30 huruf/angka).';
    }

    $stmt = $pdo->prepare("
        SELECT m.status, u.username, u.role
        FROM member m
        LEFT JOIN tbl_user u ON u.id_pelanggan = m.id_pelanggan
        WHERE m.rfid_uid = ?
    ");

    $stmt->execute([$uid]);
    $baris = $stmt->fetchAll();

    if (!$baris) {
        return $info . 'Kode ini TIDAK ADA di kolom rfid_uid tabel member. Pasang dulu kartunya lewat menu Member > RFID.';
    }

    foreach ($baris as $b) {
        if ($b['status'] === 'aktif' && $b['username'] !== null && in_array($b['role'], ['pelanggan', 'petugas'], true)) {
            return $info . 'Data cocok, tetapi tetap ditolak. Periksa query di login_rfid.php.';
        }
    }

    $b = $baris[0];

    if ($b['status'] !== 'aktif') {
        return $info . 'Member ditemukan tetapi statusnya NONAKTIF.';
    }

    if ($b['username'] === null) {
        return $info . 'Member ditemukan, tetapi TIDAK ADA akun user yang terhubung (tbl_user.id_pelanggan kosong).';
    }

    return $info . "Akun {$b['username']} berole {$b['role']}, role ini tidak diizinkan masuk lewat RFID.";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_ke('login.php');
}

try {
    verify_csrf();

    $gagal = $_SESSION['rfid_gagal'] ?? ['n' => 0, 't' => 0];

    if (time() - $gagal['t'] >= 60) {
        $gagal = ['n' => 0, 't' => 0];
    }

    if ($gagal['n'] >= 5) {
        throw new RuntimeException('Terlalu banyak percobaan. Tunggu 1 menit lalu coba lagi.');
    }

    $mentah = (string) ($_POST['rfid_uid'] ?? '');
    $uid = strtoupper(trim($mentah));
    $row = false;

    if (preg_match('/^[A-Z0-9]{4,30}$/', $uid)) {

        $stmt = $pdo->prepare("
            SELECT u.id_user, u.username, u.nama_lengkap, u.role, u.id_pelanggan
            FROM member m
            JOIN tbl_user u ON u.id_pelanggan = m.id_pelanggan
            WHERE m.rfid_uid = ?
              AND m.status = 'aktif'
              AND u.role IN ('pelanggan', 'petugas')
            LIMIT 1
        ");

        $stmt->execute([$uid]);
        $row = $stmt->fetch();
    }

    if (!$row) {
        $gagal['n']++;
        $gagal['t'] = time();
        $_SESSION['rfid_gagal'] = $gagal;

        throw new RuntimeException(
            RFID_DEBUG
                ? '[UJI COBA] ' . alasan_rfid($pdo, $mentah, $uid)
                : 'Kartu tidak dikenali atau tidak bisa dipakai untuk masuk.'
        );
    }

    unset($_SESSION['rfid_gagal'], $_SESSION['rfid_error']);

    masuk_user($row);

    log_activity(
        $pdo,
        'LOGIN RFID',
        "User {$row['username']} ({$row['role']}) masuk dengan kartu RFID"
    );

    redirect_ke(halaman_awal($row['role']));

} catch (RuntimeException $e) {

    $_SESSION['rfid_error'] = $e->getMessage();

    redirect_ke('login.php');

} catch (Throwable $e) {

    error_log('[Tokoku] ' . $e->getMessage());

    $_SESSION['rfid_error'] = 'Terjadi kesalahan. Silakan coba lagi.';

    redirect_ke('login.php');
}