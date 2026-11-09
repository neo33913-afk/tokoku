<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf()
{
    $token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        throw new RuntimeException('Permintaan tidak valid. Silakan coba lagi.');
    }
}

function set_flash($message, $type = 'success')
{
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

function log_activity($pdo, $aktivitas, $keterangan)
{
    $stmt = $pdo->prepare("
        INSERT INTO tbl_log (aktivitas, keterangan)
        VALUES (?, ?)
    ");

    $stmt->execute([$aktivitas, $keterangan]);
}