<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pembayaran.php';

// Wajib login
require_login($pdo);

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT id_transaksi, id_pelanggan, total_belanja, metode_bayar,
           status_bayar, gateway, referensi_gateway, qr_data, kadaluarsa,
           bank_va, no_va, biller_code
    FROM tbl_transaksi
    WHERE id_transaksi = ?
");
$stmt->execute([$id]);
$trx = $stmt->fetch();

// Pelanggan hanya boleh membuka pesanannya sendiri
if (
    !$trx ||
    (
        user_role() === 'pelanggan' &&
        (int) $trx['id_pelanggan'] !== (int) (user_login()['id_pelanggan'] ?? 0)
    )
) {
    http_response_code(404);
    echo 'Pesanan tidak ditemukan.';
    exit;
}

// Dipanggil berkala oleh halaman ini untuk mengecek apakah sudah dibayar
if (($_GET['cek'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    try {
        $status = sinkron_pembayaran($pdo, $id);
    } catch (Throwable $e) {
        error_log('[Tokoku] ' . $e->getMessage());
        $status = 'menunggu';
    }

    echo json_encode(['status' => $status]);
    exit;
}

// Cek awal ke penyedia pembayaran
try {
    $statusAwal = sinkron_pembayaran($pdo, $id);
} catch (Throwable $e) {
    error_log('[Tokoku] ' . $e->getMessage());
    $statusAwal = $trx['status_bayar'];
}

$sisaDetik = !empty($trx['kadaluarsa'])
    ? max(0, strtotime($trx['kadaluarsa']) - time())
    : 0;

$namaGateway = nama_gateway($trx['gateway']);
$modeUji = gateway_sandbox($trx['gateway']);

$qrData = (string) ($trx['qr_data'] ?? '');
$qrAdalahUrl = strpos($qrData, 'http') === 0;

$adalahQris = ($trx['metode_bayar'] ?? '') === 'qris' && $qrData !== '';
$adalahVa = ($trx['metode_bayar'] ?? '') === 'va' && !empty($trx['no_va']);
$siapBayar = $adalahQris || $adalahVa;
$namaBank = nama_bank_va($trx['gateway'], $trx['bank_va'] ?? '');
$judulMetode = $adalahVa ? 'Virtual Account' : 'QRIS';
$kembali = user_role() === 'pelanggan' ? 'index.php?page=riwayat' : 'index.php?page=transaksi';

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pembayaran <?= e($judulMetode) ?> #<?= e($trx['id_transaksi']) ?> | Tokoku</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4" style="max-width: 440px;">

    <div class="card shadow-sm" style="border-radius: 18px;">
        <div class="card-body p-4 text-center">

            <div class="text-secondary small">Pesanan #<?= e($trx['id_transaksi']) ?></div>
            <h4 class="fw-bold mb-1">Pembayaran <?= e($judulMetode) ?></h4>
            <div class="small text-secondary mb-1">via <?= e($namaGateway) ?></div>
            <div class="display-6 fw-bold mb-3"><?= rupiah($trx['total_belanja']) ?></div>

            <!-- MENUNGGU -->
            <div id="st-menunggu" class="<?= ($statusAwal === 'menunggu' && $siapBayar) ? '' : 'd-none' ?>">

                <?php if ($adalahQris): ?>
                <!-- QR digambar di browser (assets/js/qrcode.js), tidak bergantung layanan luar -->
                <div id="qr-box" class="border rounded bg-white p-2 mb-3 d-inline-block" style="min-width: 120px; min-height: 120px;"></div>

                <div class="small text-secondary mb-2">
                    <?php if ($modeUji): ?>
                        <span class="badge text-bg-warning mb-1">Mode uji coba</span><br>
                        QR ini hanya bisa dibayar lewat simulator <?= e($namaGateway) ?> Sandbox,
                        bukan aplikasi pembayaran asli.
                    <?php else: ?>
                        Scan dengan GoPay, DANA, OVO, ShopeePay, atau m-banking.<br>
                        Nominal sudah terisi otomatis.
                    <?php endif; ?>
                </div>

                <?php if ($modeUji && !empty($trx['referensi_gateway'])): ?>
                    <div class="border rounded p-2 mb-2 bg-light">
                        <div class="small text-secondary">
                            <?= $trx['gateway'] === 'midtrans' ? 'URL gambar QR' : 'Kode referensi' ?>
                            (untuk simulator <?= e($namaGateway) ?>)
                        </div>
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <code class="fs-6" id="kode-ref" style="word-break: break-all; user-select: all;"><?= e($trx['referensi_gateway']) ?></code>
                            <button type="button" class="btn btn-sm btn-outline-dark salin" data-target="kode-ref">Salin</button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php endif; ?>

                <?php if ($adalahVa): ?>
                    <div class="border rounded p-3 mb-2 bg-white text-start">
                        <div class="small text-secondary">Bank</div>
                        <div class="fw-bold mb-2"><?= e($namaBank) ?></div>

                        <?php if (!empty($trx['biller_code'])): ?>
                            <div class="small text-secondary">Kode perusahaan (biller code)</div>
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <code class="fs-5" id="kode-biller" style="user-select: all;"><?= e($trx['biller_code']) ?></code>
                                <button type="button" class="btn btn-sm btn-outline-dark salin" data-target="kode-biller">Salin</button>
                            </div>
                            <div class="small text-secondary">Kode bayar (bill key)</div>
                        <?php else: ?>
                            <div class="small text-secondary">Nomor virtual account</div>
                        <?php endif; ?>

                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <code class="fs-5" id="no-va" style="word-break: break-all; user-select: all;"><?= e($trx['no_va']) ?></code>
                            <button type="button" class="btn btn-sm btn-outline-dark salin" data-target="no-va">Salin</button>
                        </div>
                    </div>

                    <div class="small text-secondary mb-2">
                        <?php if ($modeUji): ?>
                            <span class="badge text-bg-warning mb-1">Mode uji coba</span><br>
                            Nomor ini hanya bisa dibayar lewat simulator <?= e($namaGateway) ?> Sandbox.
                        <?php else: ?>
                            Transfer ke nomor di atas lewat ATM, m-banking, atau internet banking <?= e($namaBank) ?>.<br>
                            Nominal harus sesuai total pesanan.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                    <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                    <span>Menunggu pembayaran...</span>
                </div>

                <div class="small text-secondary">
                    Berlaku <strong id="hitung">--:--</strong>
                </div>
            </div>

            <!-- LUNAS -->
            <div id="st-lunas" class="<?= $statusAwal === 'lunas' ? '' : 'd-none' ?>">
                <div class="display-3 text-success">&#10004;</div>
                <h5 class="fw-bold text-success">Pembayaran berhasil</h5>
                <p class="text-secondary">Pesanan Anda sudah lunas. Terima kasih!</p>
                <a href="struk.php?id=<?= e($trx['id_transaksi']) ?>" target="_blank" class="btn btn-dark w-100 mb-2">
                    Lihat Struk
                </a>
            </div>

            <!-- BATAL -->
            <div id="st-batal" class="<?= ($statusAwal === 'batal' || ($statusAwal === 'menunggu' && !$siapBayar)) ? '' : 'd-none' ?>">
                <div class="display-3 text-secondary">&#10006;</div>
                <h5 class="fw-bold">Pesanan dibatalkan</h5>
                <p class="text-secondary">
                    Waktu pembayaran habis atau pembayaran gagal. Stok sudah dikembalikan,
                    silakan belanja lagi.
                </p>
                <a href="index.php?page=belanja" class="btn btn-dark w-100 mb-2">Belanja Lagi</a>
            </div>

            <a href="<?= e($kembali) ?>" class="btn btn-link text-secondary">Kembali ke riwayat</a>

        </div>
    </div>

</div>

<script src="assets/js/qrcode.js"></script>
<script>
(function () {

    var ID = <?= (int) $id ?>;
    var status = <?= json_encode($statusAwal) ?>;
    var sisa = <?= (int) $sisaDetik ?>;
    var qrData = <?= json_encode($qrData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var qrAdalahUrl = <?= $qrAdalahUrl ? 'true' : 'false' ?>;
    var kunciKeranjang = 'tokoku_belanja_<?= e(user_login()['id_user']) ?>';

    var elMenunggu = document.getElementById('st-menunggu');
    var elLunas = document.getElementById('st-lunas');
    var elBatal = document.getElementById('st-batal');
    var elHitung = document.getElementById('hitung');
    var selesai = false;

    // Pesanan baru dibuat: kosongkan keranjang belanja di browser
    <?php if (($_GET['baru'] ?? '') === '1'): ?>
    try { localStorage.removeItem(kunciKeranjang); } catch (e) {}
    <?php endif; ?>

    // Gambar QR pembayaran
    (function () {
        var kotak = document.getElementById('qr-box');

        if (!kotak || !qrData) {
            return;
        }

        try {
            if (qrAdalahUrl) {
                var img = document.createElement('img');
                img.src = qrData;
                img.alt = 'QRIS';
                img.style.maxWidth = '100%';
                kotak.appendChild(img);
            } else {
                gambarQR(kotak, qrData, 280);
            }
        } catch (e) {
            kotak.textContent = 'QR gagal ditampilkan. Muat ulang halaman ini.';
        }
    })();

    function tampil(st) {
        elMenunggu.classList.toggle('d-none', st !== 'menunggu');
        elLunas.classList.toggle('d-none', st !== 'lunas');
        elBatal.classList.toggle('d-none', st !== 'batal');

        if (st === 'lunas' || st === 'batal') {
            selesai = true;
        }
    }

    function cek() {
        if (selesai) {
            return;
        }

        fetch('bayar_qris.php?id=' + ID + '&cek=1', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.status && d.status !== status) {
                    status = d.status;
                    tampil(status);
                }
            })
            .catch(function () { /* coba lagi pada putaran berikutnya */ });
    }

    function hitungMundur() {
        if (selesai) {
            return;
        }

        if (sisa > 0) {
            sisa--;
        }

        var m = Math.floor(sisa / 60);
        var d = sisa % 60;
        elHitung.textContent = (m < 10 ? '0' : '') + m + ':' + (d < 10 ? '0' : '') + d;
    }

    // Salin teks (kode referensi, URL QR, nomor VA) ke clipboard
    function salinTeks(tombol, teks) {
        function berhasil() {
            tombol.textContent = 'Tersalin';
            setTimeout(function () { tombol.textContent = 'Salin'; }, 1500);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(teks).then(berhasil);
            return;
        }

        var area = document.createElement('textarea');
        area.value = teks;
        document.body.appendChild(area);
        area.select();

        try {
            document.execCommand('copy');
            berhasil();
        } catch (e) {}

        document.body.removeChild(area);
    }

    Array.prototype.forEach.call(document.querySelectorAll('.salin'), function (tombol) {
        tombol.addEventListener('click', function () {
            var el = document.getElementById(tombol.getAttribute('data-target'));

            if (el) {
                salinTeks(tombol, el.textContent.trim());
            }
        });
    });

    tampil(status);
    hitungMundur();

    setInterval(hitungMundur, 1000);
    setInterval(cek, 3000);

})();
</script>

</body>
</html>