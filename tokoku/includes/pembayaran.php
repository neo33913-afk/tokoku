<?php

/**
 * Lapisan umum pembayaran QRIS: Midtrans dan Duitku.
 * Wajib dipanggil SETELAH functions.php dan auth.php (zona waktu Asia/Jakarta).
 */

require_once __DIR__ . '/midtrans.php';
require_once __DIR__ . '/duitku.php';

// Penyedia pembayaran yang tersedia: kode => nama tampilan
function daftar_gateway(): array
{
    return [
        'midtrans' => 'Midtrans',
        'duitku' => 'Duitku'
    ];
}

function nama_gateway(?string $gateway): string
{
    return daftar_gateway()[$gateway ?: 'midtrans'] ?? 'QRIS';
}

// true = masih mode uji coba (sandbox)
function gateway_sandbox(?string $gateway): bool
{
    return ($gateway ?: 'midtrans') === 'duitku'
        ? !DUITKU_PRODUKSI
        : !MIDTRANS_PRODUKSI;
}

function menit_kadaluarsa(string $gateway): int
{
    return $gateway === 'duitku'
        ? DUITKU_MENIT_KADALUARSA
        : MIDTRANS_MENIT_KADALUARSA;
}

function buat_qris_gateway(string $gateway, string $orderId, int $nominal, string $nama): array
{
    switch ($gateway) {
        case 'midtrans':
            return midtrans_buat_qris($orderId, $nominal);

        case 'duitku':
            return duitku_buat_qris($orderId, $nominal, $nama);
    }

    throw new RuntimeException('Penyedia pembayaran tidak dikenal.');
}

// Bank virtual account yang tersedia di satu penyedia: kode bank => nama tampilan
function daftar_bank_va(string $gateway): array
{
    if ($gateway === 'duitku') {
        $hasil = [];

        foreach (duitku_bank_va() as $kode => $info) {
            $hasil[$kode] = $info[1];
        }

        return $hasil;
    }

    return midtrans_bank_va();
}

function nama_bank_va(?string $gateway, ?string $bank): string
{
    return daftar_bank_va($gateway ?: 'midtrans')[$bank ?? ''] ?? strtoupper((string) $bank);
}

function buat_va_gateway(string $gateway, string $orderId, int $nominal, string $bank, string $nama): array
{
    switch ($gateway) {
        case 'midtrans':
            return midtrans_buat_va($orderId, $nominal, $bank, $nama);

        case 'duitku':
            return duitku_buat_va($orderId, $nominal, $bank, $nama);
    }

    throw new RuntimeException('Penyedia pembayaran tidak dikenal.');
}

/**
 * Status di penyedia: lunas | menunggu | batal | tidak_ada
 */
function status_gateway(string $gateway, string $orderId): string
{
    switch ($gateway) {
        case 'midtrans':
            return midtrans_status($orderId);

        case 'duitku':
            return duitku_status($orderId);
    }

    throw new RuntimeException('Penyedia pembayaran tidak dikenal.');
}

/**
 * Cek status pesanan QRIS ke penyedia pembayarannya lalu perbarui database.
 * Lunas -> status 'lunas'. Gagal/kedaluwarsa -> status 'batal' dan stok dikembalikan.
 * Aman dipanggil berulang (hanya pesanan 'menunggu' yang diproses).
 * Mengembalikan status_bayar terbaru.
 */
function sinkron_pembayaran(PDO $pdo, int $idTransaksi): string
{
    $stmt = $pdo->prepare("
        SELECT status_bayar, metode_bayar, gateway, order_id_gateway, kadaluarsa
        FROM tbl_transaksi
        WHERE id_transaksi = ?
    ");
    $stmt->execute([$idTransaksi]);
    $trx = $stmt->fetch();

    if (!$trx) {
        return 'tidak_ada';
    }

    if ($trx['status_bayar'] !== 'menunggu' || empty($trx['order_id_gateway'])) {
        return $trx['status_bayar'];
    }

    $gateway = $trx['gateway'] ?: 'midtrans';

    try {
        $status = status_gateway($gateway, $trx['order_id_gateway']);
    } catch (RuntimeException $e) {
        // Gagal menghubungi penyedia: biarkan menunggu, dicek lagi nanti
        return 'menunggu';
    }

    if ($status === 'tidak_ada') {
        $lewat = !empty($trx['kadaluarsa']) && $trx['kadaluarsa'] < date('Y-m-d H:i:s');
        $status = $lewat ? 'batal' : 'menunggu';
    }

    if ($status === 'menunggu') {
        return 'menunggu';
    }

    $namaGateway = nama_gateway($gateway);
    $labelMetode = ($trx['metode_bayar'] ?? '') === 'va' ? 'VA' : 'QRIS';

    $pdo->beginTransaction();

    try {
        // Kunci baris supaya dua proses tidak mengubah pesanan yang sama bersamaan
        $stmt = $pdo->prepare("
            SELECT status_bayar
            FROM tbl_transaksi
            WHERE id_transaksi = ?
            FOR UPDATE
        ");
        $stmt->execute([$idTransaksi]);
        $saatIni = $stmt->fetchColumn();

        if ($saatIni === 'menunggu') {

            if ($status === 'lunas') {
                $pdo->prepare("
                    UPDATE tbl_transaksi SET status_bayar = 'lunas'
                    WHERE id_transaksi = ?
                ")->execute([$idTransaksi]);

                log_activity($pdo, 'PEMBAYARAN ' . $labelMetode, "Transaksi #$idTransaksi lunas via $labelMetode $namaGateway (terdeteksi otomatis)");

            } else {
                $stmt = $pdo->prepare("
                    SELECT id_produk, jumlah
                    FROM tbl_detail_transaksi
                    WHERE id_transaksi = ?
                ");
                $stmt->execute([$idTransaksi]);

                $stmtKembali = $pdo->prepare("
                    UPDATE tbl_produk SET stok = stok + ?
                    WHERE id_produk = ?
                ");

                foreach ($stmt->fetchAll() as $item) {
                    $stmtKembali->execute([$item['jumlah'], $item['id_produk']]);
                }

                $pdo->prepare("
                    UPDATE tbl_transaksi SET status_bayar = 'batal'
                    WHERE id_transaksi = ?
                ")->execute([$idTransaksi]);

                log_activity($pdo, 'PEMBAYARAN ' . $labelMetode, "Transaksi #$idTransaksi dibatalkan ($labelMetode $namaGateway kedaluwarsa/gagal), stok dikembalikan");
            }

            $saatIni = $status;
        }

        $pdo->commit();

        return (string) $saatIni;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Proses pesanan QRIS yang sudah lewat batas waktu supaya stok yang tertahan kembali.
 */
function bersihkan_pesanan_kadaluarsa(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        SELECT id_transaksi
        FROM tbl_transaksi
        WHERE status_bayar = 'menunggu'
          AND kadaluarsa IS NOT NULL
          AND kadaluarsa < ?
    ");
    $stmt->execute([date('Y-m-d H:i:s')]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        try {
            sinkron_pembayaran($pdo, (int) $id);
        } catch (Throwable $e) {
            error_log('[Tokoku] ' . $e->getMessage());
        }
    }
}