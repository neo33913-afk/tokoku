<?php

/**
 * Notifikasi (callback) dari Duitku.
 * Hanya dipanggil Duitku bila alamat ini bisa dijangkau dari internet (hosting / ngrok).
 * Isi notifikasi tidak dipercaya begitu saja: setelah tanda tangan cocok, status
 * dicek ulang langsung ke Duitku lewat sinkron_pembayaran().
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// auth.php memuat zona waktu Asia/Jakarta (tanpa memeriksa login)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pembayaran.php';

header('Content-Type: text/plain; charset=utf-8');

$merchantOrderId = (string) ($_POST['merchantOrderId'] ?? '');
$amount = (string) ($_POST['amount'] ?? '');
$signature = strtolower((string) ($_POST['signature'] ?? ''));

$benar = md5(DUITKU_MERCHANT_CODE . $amount . $merchantOrderId . DUITKU_API_KEY);

if ($merchantOrderId === '' || !hash_equals($benar, $signature)) {
    http_response_code(400);
    echo 'Tanda tangan tidak valid';
    exit;
}

$stmt = $pdo->prepare("
    SELECT id_transaksi
    FROM tbl_transaksi
    WHERE order_id_gateway = ? AND gateway = 'duitku'
");
$stmt->execute([$merchantOrderId]);
$id = (int) $stmt->fetchColumn();

if ($id > 0) {
    try {
        sinkron_pembayaran($pdo, $id);
    } catch (Throwable $e) {
        error_log('[Tokoku] ' . $e->getMessage());
    }
}

echo 'OK';