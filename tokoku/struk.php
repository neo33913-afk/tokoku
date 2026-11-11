
<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Wajib login
require_login($pdo);

date_default_timezone_set('Asia/Jakarta');

// =====================================================
// DATA TOKO
// =====================================================

$namaToko = 'TOKOKU';

$alamatToko = [
    'Jl. Soekarno Hatta Kertosari',
    'Babadan, Ponorogo'
];

$kontakToko = [
    'Telp. 0812-3456-7890'
];

$sloganToko = 'Belanja Mudah, Harga Bersahabat';

// =====================================================

$id = (int) ($_GET['id'] ?? 0);
$autoPrint = ($_GET['auto'] ?? '') === '1';

// Ambil data transaksi
$stmt = $pdo->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        t.id_pelanggan,
        p.nama_pelanggan
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    WHERE t.id_transaksi = ?
");

$stmt->execute([$id]);
$transaksi = $stmt->fetch();

if (!$transaksi) {
    http_response_code(404);
    echo 'Transaksi tidak ditemukan.';
    exit;
}

// Validasi kepemilikan transaksi pelanggan
if (
    user_role() === 'pelanggan' &&
    (int) $transaksi['id_pelanggan'] !==
    (int) (user_login()['id_pelanggan'] ?? 0)
) {
    http_response_code(404);
    echo 'Transaksi tidak ditemukan.';
    exit;
}

// Link kembali
$linkKembali = user_role() === 'pelanggan'
    ? 'index.php?page=riwayat'
    : 'index.php?page=transaksi';

// Ambil detail transaksi
$stmt = $pdo->prepare("
    SELECT nama_produk, jumlah, harga
    FROM tbl_detail_transaksi
    WHERE id_transaksi = ?
    ORDER BY id_detail
");

$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Hitung jumlah barang
$totalPcs = 0;

foreach ($items as $item) {
    $totalPcs += (int) $item['jumlah'];
}

// Format angka rupiah
function angka($nilai)
{
    return number_format((float) $nilai, 0, ',', '.');
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Struk #<?= e($transaksi['id_transaksi']) ?> |
        <?= e($namaToko) ?>
    </title>

    <style id="gaya-kertas">
        @page {
            margin: 0;
        }
    </style>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e9e9e9;
            color: #000;
            font-family: "Courier New", Courier, monospace;
            font-size: 8pt;
            line-height: 1.35;
        }

        /* TOMBOL */

        .toolbar {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 14px;
            font-family: Arial, sans-serif;
        }

        .toolbar button,
        .toolbar a {
            padding: 9px 15px;
            border: 1px solid #222;
            border-radius: 6px;
            background: #fff;
            color: #222;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar button {
            background: #222;
            color: #fff;
        }

        /* KERTAS STRUK */

        .struk {
            width: 57mm;
            margin: 0 auto 24px;
            padding: 4mm 3.5mm 5mm;
            background: #fff;
        }

        @media screen {

            .struk {
                zoom: 1.6;
                box-shadow: 0 2px 10px rgba(0,0,0,.18);
            }

            body.ukur .struk {
                zoom: 1 !important;
            }

        }

        @media print {

            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .struk {
                margin: 0;
                zoom: 1;
                box-shadow: none;
            }

        }

        /* FORMAT TEKS */

        .tengah {
            text-align: center;
        }

        .nama-toko {
            font-size: 17pt;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .subjudul {
            font-size: 7pt;
            letter-spacing: 1px;
            margin-top: 1mm;
        }

        .mini {
            font-size: 7pt;
        }

        .bold {
            font-weight: bold;
        }

        .blok {
            margin-top: 3mm;
        }

        /* GARIS */

        .garis {
            border-top: 1px dashed #000;
            margin: 2.5mm 0;
        }

        .garis-tebal {
            border-top: 2px solid #000;
            margin: 2mm 0;
        }

        /* BARIS INFORMASI */

        .baris {
            display: flex;
            justify-content: space-between;
            gap: 2mm;
        }

        .baris span:last-child {
            text-align: right;
        }

        /* ITEM PRODUK */

        .item {
            margin-top: 2mm;
            break-inside: avoid;
        }

        .item-nama {
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .item-harga {
            padding-left: 3mm;
            font-size: 7.5pt;
            margin-top: .5mm;
        }

        .garis-kanan {
            width: 50%;
            margin: 2mm 0 2mm auto;
            border-top: 1px dashed #000;
        }

        /* TOTAL */

        .total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2mm;
            font-weight: bold;
        }

        .total-angka {
            font-size: 14pt;
        }

        /* INFORMASI TAMBAHAN */

        .info-box {
            border: 1px dashed #000;
            padding: 2mm;
            margin-top: 3mm;
        }

        .footer {
            margin-top: 4mm;
            font-size: 7pt;
        }

        .footer-title {
            font-size: 9pt;
            font-weight: bold;
            letter-spacing: 1px;
        }

    </style>

</head>

<body>

<!-- TOMBOL CETAK -->
<div class="toolbar">

    <button type="button" onclick="window.print()">
        Cetak Struk
    </button>

    <a href="<?= e($linkKembali) ?>">
        Kembali
    </a>

</div>


<!-- =================================================
     ISI STRUK
================================================== -->

<div class="struk" id="struk">


    <!-- HEADER TOKO -->

    <div class="tengah">

        <div class="nama-toko">
            <?= e($namaToko) ?>
        </div>

        <div class="subjudul">
            TOKO KEBUTUHAN SEHARI-HARI
        </div>

        <div class="garis-tebal"></div>

        <?php foreach ($alamatToko as $baris): ?>

            <div class="mini">
                <?= e($baris) ?>
            </div>

        <?php endforeach; ?>

        <?php foreach ($kontakToko as $baris): ?>

            <div class="mini">
                <?= e($baris) ?>
            </div>

        <?php endforeach; ?>

    </div>


    <div class="garis"></div>


    <!-- JUDUL STRUK -->

    <div class="tengah bold">

        STRUK PEMBELIAN

    </div>


    <!-- INFORMASI TRANSAKSI -->

    <div class="blok">

        <div class="baris">
            <span>No. Transaksi</span>

            <span class="bold">
                #<?= e($transaksi['id_transaksi']) ?>
            </span>
        </div>

        <div class="baris">
            <span>Tanggal</span>

            <span>
                <?= e(date('d/m/Y', strtotime($transaksi['tanggal']))) ?>
            </span>
        </div>

        <div class="baris">
            <span>Waktu Cetak</span>

            <span>
                <?= e(date('H:i:s')) ?>
            </span>
        </div>

        <div class="baris">
            <span>Kasir</span>

            <span>
                Admin
            </span>
        </div>

        <div class="baris">
            <span>Pelanggan</span>

            <span>
                <?= e($transaksi['nama_pelanggan'] ?? '-') ?>
            </span>
        </div>

    </div>


    <div class="garis"></div>


    <!-- DAFTAR PRODUK -->

    <div class="bold tengah">
        DETAIL PEMBELIAN
    </div>

    <div class="blok">

        <?php if (empty($items)): ?>

            <div class="tengah mini">
                Tidak ada detail produk.
            </div>

        <?php else: ?>

            <?php foreach ($items as $item): ?>

                <div class="item">

                    <div class="item-nama">
                        <?= e($item['nama_produk']) ?>
                    </div>

                    <div class="baris item-harga">

                        <span>
                            <?= e($item['jumlah']) ?> x
                            <?= angka($item['harga']) ?>
                        </span>

                        <span>
                            <?= angka($item['harga'] * $item['jumlah']) ?>
                        </span>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>


    <div class="garis"></div>


    <!-- RINGKASAN PEMBAYARAN -->

    <div class="baris">
        <span>Total Jenis Barang</span>

        <span>
            <?= count($items) ?> item
        </span>
    </div>

    <div class="baris">
        <span>Total Kuantitas</span>

        <span>
            <?= e($totalPcs) ?> pcs
        </span>
    </div>

    <div class="garis-tebal"></div>


    <!-- TOTAL PEMBAYARAN -->

    <div class="total">

        <span>TOTAL</span>

        <span class="total-angka">
            Rp <?= angka($transaksi['total_belanja']) ?>
        </span>

    </div>


    <!-- INFORMASI PEMBAYARAN -->

    <div class="info-box">

        <div class="tengah bold">
            PEMBAYARAN
        </div>

        <div class="baris">
            <span>Status</span>

            <span class="bold">
                LUNAS
            </span>
        </div>

        <div class="baris">
            <span>Total Bayar</span>

            <span>
                Rp <?= angka($transaksi['total_belanja']) ?>
            </span>
        </div>

    </div>


    <div class="garis"></div>


    <!-- FOOTER -->

    <div class="footer tengah">

        <div class="footer-title">
            TERIMA KASIH!
        </div>

        <div class="blok">
            Sudah berbelanja di
        </div>

        <div class="bold">
            <?= e($namaToko) ?>
        </div>

        <div class="blok">
            <?= e($sloganToko) ?>
        </div>

        <div class="garis"></div>

        <div class="bold">
            BARANG YANG SUDAH DIBELI
        </div>

        <div>
            TIDAK DAPAT DIKEMBALIKAN
        </div>

        <div class="blok">
            Simpan struk ini sebagai
        </div>

        <div>
            bukti transaksi Anda.
        </div>

        <div class="garis-tebal"></div>

        <div class="mini">
            Dicetak: <?= e(date('d/m/Y H:i:s')) ?>
        </div>

        <div class="blok bold">
            *** TOKOKU ***
        </div>

    </div>


</div>


<!-- =================================================
     SCRIPT CETAK
================================================== -->

<script>

    // Menghitung tinggi kertas otomatis
    function siapkanKertas() {

        var struk = document.getElementById('struk');

        document.body.classList.add('ukur');

        var tinggiPx = struk.getBoundingClientRect().height;

        document.body.classList.remove('ukur');

        var tinggiMm = Math.ceil(tinggiPx * 25.4 / 96) + 3;

        document.getElementById('gaya-kertas').textContent =
            '@page { size: 57mm ' + tinggiMm + 'mm; margin: 0; }';

    }


    window.addEventListener('load', function () {

        siapkanKertas();

    });


    // Cetak otomatis setelah pembayaran berhasil
    <?php if ($autoPrint): ?>

        try {

            localStorage.removeItem('tokoku_keranjang');

            localStorage.removeItem('tokoku_pelanggan');

        } catch (e) {}


        window.addEventListener('load', function () {

            window.print();

        });

    <?php endif; ?>

</script>

</body>
</html>
