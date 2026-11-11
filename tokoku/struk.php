<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Wajib login
require_login($pdo);

date_default_timezone_set('Asia/Jakarta');

// =====================================================
// DATA TOKO (ubah sesuai toko kamu, kosongkan [] jika tidak ingin tampil)
// =====================================================
$namaToko = 'TOKOKU';

$alamatToko = [
    'Jl. Soekarno Hatta Kertosari, Babadan, Ponorogo ',
];

$kontakToko = [
    'Telp. 0812-3456-7890',
];

// =====================================================

$id = (int) ($_GET['id'] ?? 0);
$autoPrint = ($_GET['auto'] ?? '') === '1';

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

// Pelanggan hanya boleh melihat struk miliknya sendiri
// (pesan sama dengan "tidak ditemukan" supaya ID transaksi orang lain tidak bisa ditebak)
if (
    user_role() === 'pelanggan' &&
    (int) $transaksi['id_pelanggan'] !== (int) (user_login()['id_pelanggan'] ?? 0)
) {
    http_response_code(404);
    echo 'Transaksi tidak ditemukan.';
    exit;
}

$linkKembali = user_role() === 'pelanggan'
    ? 'index.php?page=riwayat'
    : 'index.php?page=transaksi';

$stmt = $pdo->prepare("
    SELECT nama_produk, jumlah, harga
    FROM tbl_detail_transaksi
    WHERE id_transaksi = ?
    ORDER BY id_detail
");

$stmt->execute([$id]);
$items = $stmt->fetchAll();

$totalPcs = 0;

foreach ($items as $item) {
    $totalPcs += (int) $item['jumlah'];
}

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

    <title>Struk #<?= e($transaksi['id_transaksi']) ?> | <?= e($namaToko) ?></title>

    <!-- Ukuran kertas (lebar 57mm x tinggi menyesuaikan isi) diisi oleh script di bawah -->
    <style id="gaya-kertas">
        @page { margin: 0; }
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
            font-size: 7.5pt;
            line-height: 1.3;
        }

        /* ---------- Tombol (hanya di layar, tidak ikut tercetak) ---------- */
        .toolbar {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 14px;
            font-family: Arial, sans-serif;
        }

        .toolbar button,
        .toolbar a {
            padding: 8px 14px;
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

        /* ---------- Kertas struk ---------- */
        .struk {
            width: 57mm;
            margin: 0 auto 24px;
            padding: 4mm 4.5mm 6mm;
            background: #fff;
        }

        @media screen {
            .struk {
                zoom: 1.6;
                box-shadow: 0 2px 10px rgba(0, 0, 0, .18);
            }

            /* Saat mengukur tinggi kertas, zoom dimatikan sebentar */
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

        .tengah {
            text-align: center;
        }

        .nama-toko {
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .blok {
            margin-top: 3mm;
        }

        .baris {
            display: flex;
            justify-content: space-between;
            gap: 2mm;
        }

        .baris span:last-child {
            text-align: right;
            white-space: nowrap;
        }

        /* ---------- Daftar item (qty x nama, harga satuan, subtotal) ---------- */
        .item {
            margin-top: 1.2mm;
            break-inside: avoid;
        }

        .item-nama {
            word-break: break-word;
        }

        .item-harga {
            padding-left: 3ch;
        }

        .garis-kanan {
            width: 45%;
            margin: 1.5mm 0 1mm auto;
            border-top: 1px dashed #000;
        }

        /* ---------- Total ---------- */
        .total {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-top: 1mm;
        }

        .total-angka {
            font-size: 12pt;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Cetak Struk</button>
    <a href="<?= e($linkKembali) ?>">Kembali</a>
</div>

<div class="struk" id="struk">

    <!-- Kepala toko -->
    <div class="tengah">
        <div class="nama-toko"><?= e($namaToko) ?></div>

        <?php foreach ($alamatToko as $baris): ?>
            <div><?= e($baris) ?></div>
        <?php endforeach; ?>
    </div>

    <!-- Nomor dan tanggal -->
    <div class="blok">
        <div class="baris">
            <span>No. <?= e($transaksi['id_transaksi']) ?></span>
            <span><?= e(date('d/m/Y', strtotime($transaksi['tanggal']))) ?></span>
        </div>

        <div class="baris">
            <span>Pelanggan</span>
            <span><?= e($transaksi['nama_pelanggan'] ?? '-') ?></span>
        </div>
    </div>

    <!-- Daftar item -->
    <div class="blok">

        <?php foreach ($items as $item): ?>
            <div class="item">
                <div class="item-nama">
                    <?= e($item['jumlah']) ?>x <?= e($item['nama_produk']) ?>
                </div>

                <div class="baris item-harga">
                    <span>Rp. <?= angka($item['harga']) ?></span>
                    <span>Rp. <?= angka($item['harga'] * $item['jumlah']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="garis-kanan"></div>

        <div class="total">
            <span>Total :</span>
            <span>Rp</span>
            <span class="total-angka"><?= angka($transaksi['total_belanja']) ?></span>
        </div>

        <div class="baris">
            <span>Jumlah barang</span>
            <span><?= e($totalPcs) ?> pcs</span>
        </div>

    </div>

    <!-- Penutup -->
    <div class="blok tengah">
        <div>Terima kasih</div>

        <?php foreach ($kontakToko as $baris): ?>
            <div><?= e($baris) ?></div>
        <?php endforeach; ?>

        <div class="blok">Dicetak: <?= e(date('d/m/Y H:i')) ?></div>
    </div>

</div>

<script>
    // Tinggi kertas disesuaikan dengan isi struk (kertas gulungan, tinggi tidak tetap)
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
</script>

<?php if ($autoPrint): ?>
    <script>
        // Pembayaran berhasil: kosongkan keranjang dan pilihan pelanggan yang tersimpan di browser
        try {
            localStorage.removeItem('tokoku_keranjang');
            localStorage.removeItem('tokoku_pelanggan');
        } catch (e) {}

        window.addEventListener('load', function () {
            window.print();
        });
    </script>
<?php endif; ?>

</body>
</html>