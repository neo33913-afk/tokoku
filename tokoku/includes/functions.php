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
function generate_no_member(PDO $pdo): string
{
    $n = (int) $pdo->query("SELECT IFNULL(MAX(id_member), 0) + 1 FROM member")->fetchColumn();

    return 'MBR-' . str_pad($n, 6, '0', STR_PAD_LEFT);
}

// Aturan poin: tiap Rp10.000 = 1 poin
function hitung_poin($total): int
{
    return (int) floor((float) $total / 10000);
}
// Diskon untuk pelanggan yang punya kartu member aktif (persen)
const DISKON_MEMBER_PERSEN = 15;

// Mengembalikan id_member kalau pelanggan punya member aktif, selain itu null
function cari_member_aktif(PDO $pdo, int $idPelanggan): ?int
{
    $stmt = $pdo->prepare("SELECT id_member FROM member WHERE id_pelanggan = ? AND status = 'aktif'");
    $stmt->execute([$idPelanggan]);
    $id = $stmt->fetchColumn();

    return $id ? (int) $id : null;
}

function hitung_diskon($subtotal, $persen): float
{
    return round((float) $subtotal * (float) $persen / 100);
}
/**
 * Ubah QRIS statis menjadi dinamis dengan nominal tertentu.
 * Payload harus valid (berakhiran 6304XXXX).
 */
function qris_dinamis(string $statis, int $nominal): string
{
    // Hilangkan CRC lama
    $tanpaCrc = preg_replace('/6304[0-9A-F]{4}$/i', '', $statis);

    // Ubah point of initiation 11 → 12 (statis → dinamis)
    $tanpaCrc = preg_replace('/^000201010211/', '000201010212', $tanpaCrc, 1);

    // Sisipkan tag 54 (nominal) sebelum tag 58
    $nominalStr = (string) max(0, $nominal);
    $tag54 = '54' . str_pad(strlen($nominalStr), 2, '0', STR_PAD_LEFT) . $nominalStr;

    $pos58 = strpos($tanpaCrc, '5802ID');
    if ($pos58 === false) {
        throw new RuntimeException('Format QRIS tidak valid.');
    }

    $payload = substr($tanpaCrc, 0, $pos58) . $tag54 . substr($tanpaCrc, $pos58);

    // Hitung CRC16-CCITT-FALSE
    $crc = 0xFFFF;
    for ($i = 0, $len = strlen($payload); $i < $len; $i++) {
        $crc ^= ord($payload[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = ($crc << 1) ^ 0x1021;
            } else {
                $crc <<= 1;
            }
            $crc &= 0xFFFF;
        }
    }

    return $payload . '6304' . strtoupper(sprintf('%04X', $crc));
}