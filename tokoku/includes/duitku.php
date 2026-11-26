<?php

/**
 * Pembayaran QRIS lewat Duitku (API v2).
 * Wajib dipanggil SETELAH functions.php dan auth.php (zona waktu Asia/Jakarta).
 */

require_once __DIR__ . '/../config/duitku.php';

function duitku_base_url(): string
{
    return DUITKU_PRODUKSI
        ? 'https://passport.duitku.com'
        : 'https://sandbox.duitku.com';
}

function duitku_request(string $path, array $body): array
{
    foreach ([DUITKU_MERCHANT_CODE, DUITKU_API_KEY] as $nilai) {
        if ($nilai === '' || strpos($nilai, 'ISI_') === 0) {
            throw new RuntimeException('Pembayaran Duitku belum dikonfigurasi. Hubungi admin toko.');
        }
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi cURL PHP belum aktif di server.');
    }

    $ch = curl_init(duitku_base_url() . $path);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20
    ]);

    $raw = curl_exec($ch);

    if ($raw === false) {
        error_log('[Tokoku] Duitku cURL: ' . curl_error($ch));
        throw new RuntimeException('Tidak bisa menghubungi layanan pembayaran. Coba lagi sebentar lagi.');
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        error_log('[Tokoku] Duitku respons tidak valid: ' . $raw);
        throw new RuntimeException('Respons layanan pembayaran tidak valid.');
    }

    return $data;
}

/**
 * Buat transaksi QRIS di Duitku.
 * Mengembalikan ['qr' => isi QR, 'referensi' => kode referensi Duitku].
 */
function duitku_buat_qris(string $orderId, int $nominal, string $nama): array
{
    if (DUITKU_METODE === '' || strpos(DUITKU_METODE, 'ISI_') === 0) {
        throw new RuntimeException('Kode metode QRIS Duitku belum diisi di config/duitku.php.');
    }

    $namaVa = trim(mb_substr($nama, 0, 20));

    $res = duitku_request('/webapi/api/merchant/v2/inquiry', [
        'merchantCode' => DUITKU_MERCHANT_CODE,
        'paymentAmount' => $nominal,
        'paymentMethod' => DUITKU_METODE,
        'merchantOrderId' => $orderId,
        'productDetails' => 'Belanja Tokoku',
        'email' => DUITKU_EMAIL,
        'customerVaName' => $namaVa !== '' ? $namaVa : 'Pelanggan',
        'callbackUrl' => DUITKU_CALLBACK_URL,
        'returnUrl' => DUITKU_RETURN_URL,
        'signature' => md5(DUITKU_MERCHANT_CODE . $orderId . $nominal . DUITKU_API_KEY),
        'expiryPeriod' => DUITKU_MENIT_KADALUARSA
    ]);

    if ((string) ($res['statusCode'] ?? '') !== '00') {
        error_log('[Tokoku] Duitku inquiry gagal: ' . json_encode($res));
        throw new RuntimeException('Gagal membuat QRIS pembayaran Duitku. Coba lagi atau hubungi admin.');
    }

    $qr = (string) ($res['qrString'] ?? '');

    if ($qr === '') {
        error_log('[Tokoku] Duitku tidak mengirim QR (cek DUITKU_METODE, harus kode QRIS): ' . json_encode($res));
        throw new RuntimeException('Metode pembayaran Duitku ini tidak menghasilkan QR. Hubungi admin toko.');
    }

    return [
        'qr' => $qr,
        'referensi' => (string) ($res['reference'] ?? '')
    ];
}

/**
 * Bank virtual account lewat Duitku: kode bank => [kode metode Duitku, nama tampilan].
 * Kode metode ditulis dari ingatan terhadap dokumentasi Duitku; cocokkan dengan
 * daftar di cek_metode_duitku.php (hanya channel yang aktif di proyek yang bisa dipakai).
 */
function duitku_bank_va(): array
{
    return [
        'bca' => ['BC', 'BCA'],
        'bni' => ['I1', 'BNI'],
        'bri' => ['BR', 'BRI'],
        'mandiri' => ['M2', 'Mandiri'],
        'permata' => ['BT', 'Permata'],
        'cimb' => ['B1', 'CIMB Niaga']
    ];
}

/**
 * Buat virtual account di Duitku.
 * Mengembalikan ['no_va' => nomor VA, 'biller_code' => null, 'referensi' => kode referensi Duitku].
 */
function duitku_buat_va(string $orderId, int $nominal, string $bank, string $nama): array
{
    $daftar = duitku_bank_va();

    if (!isset($daftar[$bank])) {
        throw new RuntimeException('Bank virtual account tidak dikenal.');
    }

    $namaVa = trim(mb_substr($nama, 0, 20));

    $res = duitku_request('/webapi/api/merchant/v2/inquiry', [
        'merchantCode' => DUITKU_MERCHANT_CODE,
        'paymentAmount' => $nominal,
        'paymentMethod' => $daftar[$bank][0],
        'merchantOrderId' => $orderId,
        'productDetails' => 'Belanja Tokoku',
        'email' => DUITKU_EMAIL,
        'customerVaName' => $namaVa !== '' ? $namaVa : 'Pelanggan',
        'callbackUrl' => DUITKU_CALLBACK_URL,
        'returnUrl' => DUITKU_RETURN_URL,
        'signature' => md5(DUITKU_MERCHANT_CODE . $orderId . $nominal . DUITKU_API_KEY),
        'expiryPeriod' => DUITKU_MENIT_KADALUARSA
    ]);

    if ((string) ($res['statusCode'] ?? '') !== '00') {
        error_log('[Tokoku] Duitku VA gagal: ' . json_encode($res));
        throw new RuntimeException('Gagal membuat virtual account Duitku. Coba lagi atau pilih bank lain.');
    }

    $noVa = (string) ($res['vaNumber'] ?? '');

    if ($noVa === '') {
        error_log('[Tokoku] Duitku tidak mengirim nomor VA (cek kode metode ' . $daftar[$bank][0] . ' aktif di proyek): ' . json_encode($res));
        throw new RuntimeException('Bank ini belum tersedia di Duitku. Pilih bank lain.');
    }

    return [
        'no_va' => $noVa,
        'biller_code' => null,
        'referensi' => (string) ($res['reference'] ?? '')
    ];
}

/**
 * Status pembayaran di Duitku, disederhanakan: lunas | menunggu | batal.
 * Respons yang tidak dikenali dianggap error (bukan batal), supaya pesanan yang
 * mungkin sudah dibayar tidak ikut dibatalkan hanya karena salah konfigurasi.
 */
function duitku_status(string $orderId): string
{
    $res = duitku_request('/webapi/api/merchant/transactionStatus', [
        'merchantCode' => DUITKU_MERCHANT_CODE,
        'merchantOrderId' => $orderId,
        'signature' => md5(DUITKU_MERCHANT_CODE . $orderId . DUITKU_API_KEY)
    ]);

    switch ((string) ($res['statusCode'] ?? '')) {
        case '00':
            return 'lunas';

        case '01':
            return 'menunggu';

        case '02':
            return 'batal';
    }

    error_log('[Tokoku] Duitku status tidak dikenali: ' . json_encode($res));
    throw new RuntimeException('Gagal memeriksa status pembayaran Duitku.');
}