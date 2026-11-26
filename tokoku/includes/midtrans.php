<?php

/**
 * Pembayaran QRIS lewat Midtrans (Core API): buat QRIS dan cek status.
 * Pengecekan + pembaruan database ada di includes/pembayaran.php.
 */

require_once __DIR__ . '/../config/midtrans.php';

function midtrans_base_url(): string
{
    return MIDTRANS_PRODUKSI
        ? 'https://api.midtrans.com'
        : 'https://api.sandbox.midtrans.com';
}

function midtrans_request(string $method, string $path, ?array $body = null): array
{
    if (MIDTRANS_SERVER_KEY === '' || strpos(MIDTRANS_SERVER_KEY, 'ISI_') === 0) {
        throw new RuntimeException('Pembayaran QRIS belum dikonfigurasi. Hubungi admin toko.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi cURL PHP belum aktif di server.');
    }

    $ch = curl_init(midtrans_base_url() . $path);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);

    if ($raw === false) {
        error_log('[Tokoku] Midtrans cURL: ' . curl_error($ch));
        throw new RuntimeException('Tidak bisa menghubungi layanan pembayaran. Coba lagi sebentar lagi.');
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        error_log('[Tokoku] Midtrans respons tidak valid: ' . $raw);
        throw new RuntimeException('Respons layanan pembayaran tidak valid.');
    }

    return $data;
}

/**
 * Buat QRIS dinamis resmi untuk satu pesanan.
 * Mengembalikan ['qr' => isi QR atau URL gambar QR].
 */
function midtrans_buat_qris(string $orderId, int $nominal): array
{
    $res = midtrans_request('POST', '/v2/charge', [
        'payment_type' => 'qris',
        'transaction_details' => [
            'order_id' => $orderId,
            'gross_amount' => $nominal
        ],
        'qris' => ['acquirer' => 'gopay'],
        'custom_expiry' => [
            'expiry_duration' => MIDTRANS_MENIT_KADALUARSA,
            'unit' => 'minute'
        ]
    ]);

    if (!in_array((string) ($res['status_code'] ?? ''), ['200', '201'], true)) {
        error_log('[Tokoku] Midtrans charge gagal: ' . json_encode($res));
        throw new RuntimeException('Gagal membuat QRIS pembayaran. Coba lagi atau hubungi admin.');
    }

    $qr = (string) ($res['qr_string'] ?? '');
    $urlGambar = '';

    // URL gambar QR dari Midtrans: dibutuhkan simulator QRIS Sandbox
    // Pilih generate-qr-code-v2 (gambar berbingkai standar ASPI, lebih mudah dibaca simulator);
    // kalau tidak ada, pakai generate-qr-code
    $urlV1 = '';
    $urlV2 = '';

    foreach (($res['actions'] ?? []) as $aksi) {
        $nama = (string) ($aksi['name'] ?? '');

        if (empty($aksi['url'])) {
            continue;
        }

        if ($nama === 'generate-qr-code-v2') {
            $urlV2 = (string) $aksi['url'];
        } elseif ($nama === 'generate-qr-code') {
            $urlV1 = (string) $aksi['url'];
        }
    }

    $urlGambar = $urlV2 !== '' ? $urlV2 : $urlV1;

    if ($qr === '') {
        $qr = $urlGambar;
    }

    if ($qr === '') {
        error_log('[Tokoku] Midtrans tidak mengirim QR: ' . json_encode($res));
        throw new RuntimeException('QRIS pembayaran tidak tersedia. Coba lagi atau hubungi admin.');
    }

    // 'referensi' disimpan ke kolom referensi_gateway; untuk Midtrans isinya URL gambar QR
    return ['qr' => $qr, 'referensi' => $urlGambar !== '' ? $urlGambar : null];
}

/**
 * Bank virtual account yang dipakai lewat Midtrans: kode => nama tampilan.
 * Mandiri memakai e-channel (biller code + bill key), bukan satu nomor VA.
 */
function midtrans_bank_va(): array
{
    return [
        'bca' => 'BCA',
        'bni' => 'BNI',
        'bri' => 'BRI',
        'mandiri' => 'Mandiri',
        'permata' => 'Permata',
        'cimb' => 'CIMB Niaga'
    ];
}

/**
 * Buat virtual account untuk satu pesanan.
 * Mengembalikan ['no_va' => nomor VA (atau bill key Mandiri), 'biller_code' => kode perusahaan Mandiri atau null,
 *                'referensi' => id transaksi Midtrans].
 */
function midtrans_buat_va(string $orderId, int $nominal, string $bank, string $nama): array
{
    if (!array_key_exists($bank, midtrans_bank_va())) {
        throw new RuntimeException('Bank virtual account tidak dikenal.');
    }

    $body = [
        'transaction_details' => [
            'order_id' => $orderId,
            'gross_amount' => $nominal
        ],
        'customer_details' => [
            'first_name' => mb_substr($nama !== '' ? $nama : 'Pelanggan', 0, 50)
        ],
        'custom_expiry' => [
            'expiry_duration' => MIDTRANS_MENIT_KADALUARSA,
            'unit' => 'minute'
        ]
    ];

    if ($bank === 'mandiri') {
        $body['payment_type'] = 'echannel';
        $body['echannel'] = [
            'bill_info1' => 'Pembayaran',
            'bill_info2' => 'Tokoku'
        ];
    } else {
        $body['payment_type'] = 'bank_transfer';
        $body['bank_transfer'] = ['bank' => $bank];
    }

    $res = midtrans_request('POST', '/v2/charge', $body);

    if (!in_array((string) ($res['status_code'] ?? ''), ['200', '201'], true)) {
        error_log('[Tokoku] Midtrans VA gagal: ' . json_encode($res));
        throw new RuntimeException('Gagal membuat virtual account. Coba lagi atau pilih bank lain.');
    }

    $noVa = '';
    $biller = null;

    if ($bank === 'mandiri') {
        $noVa = (string) ($res['bill_key'] ?? '');
        $biller = (string) ($res['biller_code'] ?? '');
    } elseif ($bank === 'permata') {
        $noVa = (string) ($res['permata_va_number'] ?? '');
    } else {
        $noVa = (string) ($res['va_numbers'][0]['va_number'] ?? '');
    }

    if ($noVa === '') {
        error_log('[Tokoku] Midtrans tidak mengirim nomor VA: ' . json_encode($res));
        throw new RuntimeException('Nomor virtual account tidak tersedia. Coba lagi atau pilih bank lain.');
    }

    return [
        'no_va' => $noVa,
        'biller_code' => $biller !== '' ? $biller : null,
        'referensi' => (string) ($res['transaction_id'] ?? '')
    ];
}

/**
 * Status pembayaran di Midtrans, disederhanakan:
 * lunas | menunggu | batal | tidak_ada
 */
function midtrans_status(string $orderId): string
{
    $res = midtrans_request('GET', '/v2/' . rawurlencode($orderId) . '/status');

    $kode = (string) ($res['status_code'] ?? '');

    if ($kode === '404') {
        return 'tidak_ada';
    }

    if ($kode !== '200') {
        error_log('[Tokoku] Midtrans status gagal: ' . json_encode($res));
        throw new RuntimeException('Gagal memeriksa status pembayaran.');
    }

    switch ($res['transaction_status'] ?? '') {
        case 'settlement':
        case 'capture':
            return 'lunas';

        case 'expire':
        case 'cancel':
        case 'deny':
        case 'failure':
            return 'batal';

        default:
            return 'menunggu';
    }
}