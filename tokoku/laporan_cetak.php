<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Wajib login dan hanya role yang boleh membuka halaman laporan
require_login($pdo);

if (!in_array('laporan', izin_halaman(user_role()), true)) {
    http_response_code(403);
    echo 'Anda tidak punya akses ke halaman ini.';
    exit;
}

// =====================================================
// DATA TOKO (sama seperti struk.php)
// =====================================================

$namaToko = 'TOKOKU';

$alamatToko = [
    'Jl. Soekarno Hatta Kertosari',
    'Babadan, Ponorogo'
];

$kontakToko = 'Telp. 0812-3456-7890';

// =====================================================
// FILTER PERIODE
// =====================================================

$ambilTanggal = function ($nilai) {
    if (!is_string($nilai)) {
        return '';
    }

    $d = DateTime::createFromFormat('Y-m-d', $nilai);

    return ($d && $d->format('Y-m-d') === $nilai) ? $nilai : '';
};

$dari = $ambilTanggal($_GET['dari'] ?? '');
$sampai = $ambilTanggal($_GET['sampai'] ?? '');

if ($dari && $sampai && $dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

// Petugas hanya melihat transaksinya sendiri; admin boleh memilih petugas (0 = semua)
if (user_role() === 'petugas') {
    $idPetugas = (int) user_login()['id_user'];
} else {
    $idPetugas = (isset($_GET['petugas']) && is_string($_GET['petugas'])) ? (int) $_GET['petugas'] : 0;
}

$where = [];
$params = [];

if ($dari) {
    $where[] = 'DATE(tanggal) >= ?';
    $params[] = $dari;
}

if ($sampai) {
    $where[] = 'DATE(tanggal) <= ?';
    $params[] = $sampai;
}

$whereTrx = $where;
$paramsTrx = $params;
$whereView = $where;
$paramsView = $params;

if ($idPetugas > 0) {
    $whereTrx[] = 'id_user = ?';
    $paramsTrx[] = $idPetugas;

    $whereView[] = 'id_transaksi IN (SELECT id_transaksi FROM tbl_transaksi WHERE id_user = ?)';
    $paramsView[] = $idPetugas;
}

// Hanya transaksi yang sudah lunas
$whereTrx[] = "status_bayar = 'lunas'";
$whereView[] = "id_transaksi IN (SELECT id_transaksi FROM tbl_transaksi WHERE status_bayar = 'lunas')";

$kondisiTrx = $whereTrx ? ' WHERE ' . implode(' AND ', $whereTrx) : '';
$kondisiView = $whereView ? ' WHERE ' . implode(' AND ', $whereView) : '';

function tglPendek($tanggal)
{
    return date('d/m/Y', strtotime($tanggal));
}

if ($dari && $sampai) {
    $labelPeriode = tglPendek($dari) . ' s/d ' . tglPendek($sampai);
    $namaFile = $dari . '_sd_' . $sampai;
} elseif ($dari) {
    $labelPeriode = 'Mulai ' . tglPendek($dari);
    $namaFile = 'mulai_' . $dari;
} elseif ($sampai) {
    $labelPeriode = 'Sampai ' . tglPendek($sampai);
    $namaFile = 'sampai_' . $sampai;
} else {
    $labelPeriode = 'Semua periode';
    $namaFile = 'semua_periode';
}

if ($idPetugas > 0) {
    $stmt = $pdo->prepare("SELECT nama_lengkap FROM tbl_user WHERE id_user = ?");
    $stmt->execute([$idPetugas]);
    $namaPetugasLaporan = $stmt->fetchColumn() ?: 'Petugas tidak dikenal';
} else {
    $namaPetugasLaporan = 'Semua petugas';
}

// =====================================================
// DATA LAPORAN
// =====================================================

$stmt = $pdo->prepare("
    SELECT id_transaksi, tanggal, nama_pelanggan, nama_produk,
           nama_kategori, jumlah, subtotal, diskon_persen
    FROM view_laporan_penjualan
    $kondisiView
    ORDER BY tanggal ASC, id_transaksi ASC
");
$stmt->execute($paramsView);
$rows = $stmt->fetchAll();

$totalPcs = 0;

foreach ($rows as $row) {
    $totalPcs += (int) $row['jumlah'];
}

// Ringkasan dihitung dari tabel transaksi (total_belanja = sesudah diskon)
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS jml_transaksi,
           COALESCE(SUM(total_belanja), 0) AS bersih,
           COALESCE(SUM(diskon), 0) AS diskon
    FROM tbl_transaksi
    $kondisiTrx
");
$stmt->execute($paramsTrx);
$ringkas = $stmt->fetch();

$bersih = (float) $ringkas['bersih'];
$diskon = (float) $ringkas['diskon'];
$kotor = $bersih + $diskon;

$stmt = $pdo->prepare("
    SELECT COALESCE(NULLIF(metode_bayar, ''), 'tunai') AS metode,
           COUNT(*) AS jml,
           COALESCE(SUM(total_belanja), 0) AS total
    FROM tbl_transaksi
    $kondisiTrx
    GROUP BY metode
    ORDER BY metode
");
$stmt->execute($paramsTrx);
$perMetode = $stmt->fetchAll();

$user = user_login();
$queryKembali = http_build_query(array_filter([
    'dari' => $dari,
    'sampai' => $sampai,
    'petugas' => user_role() === 'admin' ? $idPetugas : 0
]));
$linkKembali = 'index.php?page=laporan' . ($queryKembali ? '&' . $queryKembali : '');

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Judul dipakai browser sebagai nama file PDF bawaan -->
    <title>Laporan Penjualan <?= e($namaFile) ?></title>

    <style>

        @page {
            size: A4;
            margin: 15mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e9e9e9;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* TOMBOL (hanya di layar) */

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 14px;
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

        .petunjuk {
            width: 100%;
            text-align: center;
            font-size: 12px;
            color: #555;
        }

        /* KERTAS */

        .kertas {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 24px;
            padding: 15mm;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .18);
        }

        @media print {

            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .kertas {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

        }

        /* KOP */

        .kop {
            text-align: center;
            padding-bottom: 3mm;
            border-bottom: 2px solid #000;
        }

        .kop-nama {
            font-size: 18pt;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .kop-info {
            font-size: 9pt;
        }

        .judul {
            margin: 5mm 0 3mm;
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .meta {
            width: 100%;
            margin-bottom: 4mm;
            font-size: 9.5pt;
        }

        .meta td {
            padding: 0.5mm 0;
        }

        .meta td:first-child {
            width: 32mm;
        }

        /* RINGKASAN */

        .ringkasan {
            display: flex;
            gap: 6mm;
            margin-bottom: 5mm;
        }

        .ringkasan > div {
            flex: 1;
        }

        .judul-kecil {
            margin-bottom: 1mm;
            font-size: 9.5pt;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .tabel-kecil td {
            padding: 1mm 2mm;
            border: 1px solid #999;
            font-size: 9.5pt;
        }

        .tabel-kecil td:last-child {
            text-align: right;
            white-space: nowrap;
        }

        .tabel-kecil tr.tebal td {
            font-weight: bold;
            background: #eee;
        }

        /* TABEL DETAIL */

        .tabel-data th,
        .tabel-data td {
            padding: 1.2mm 1.5mm;
            border: 1px solid #666;
            font-size: 8.5pt;
            vertical-align: top;
        }

        .tabel-data th {
            background: #ddd;
            text-align: left;
        }

        .tabel-data thead {
            display: table-header-group;
        }

        .tabel-data tr {
            break-inside: avoid;
        }

        .kanan {
            text-align: right;
            white-space: nowrap;
        }

        .tengah {
            text-align: center;
        }

        .kosong {
            padding: 6mm !important;
            text-align: center;
            color: #555;
        }

        .catatan {
            margin-top: 4mm;
            font-size: 8.5pt;
            color: #333;
        }

    </style>

</head>

<body>

<!-- TOMBOL CETAK -->
<div class="toolbar">

    <button type="button" onclick="window.print()">
        Cetak / Simpan PDF
    </button>

    <a href="<?= e($linkKembali) ?>">
        Kembali
    </a>

    <div class="petunjuk">
        Di jendela cetak, pilih tujuan "Simpan sebagai PDF" untuk membuat file PDF.
    </div>

</div>




<div class="kertas">

    <!-- KOP TOKO -->

    <div class="kop">
        <div class="kop-nama"><?= e($namaToko) ?></div>

        <?php foreach ($alamatToko as $baris): ?>
            <div class="kop-info"><?= e($baris) ?></div>
        <?php endforeach; ?>

        <div class="kop-info"><?= e($kontakToko) ?></div>
    </div>

    <div class="judul">LAPORAN PENJUALAN</div>

    <table class="meta">
        <tr>
            <td>Periode</td>
            <td>: <?= e($labelPeriode) ?></td>
        </tr>
        <tr>
            <td>Petugas</td>
            <td>: <?= e($namaPetugasLaporan) ?></td>
        </tr>
        <tr>
            <td>Dicetak oleh</td>
            <td>: <?= e($user['nama_lengkap'] ?? '-') ?></td>
        </tr>
        <tr>
            <td>Tanggal cetak</td>
            <td>: <?= e(date('d/m/Y H:i')) ?></td>
        </tr>
    </table>


    <!-- RINGKASAN -->

    <div class="ringkasan">

        <div>
            <div class="judul-kecil">Ringkasan</div>
            <table class="tabel-kecil">
                <tr>
                    <td>Jumlah transaksi</td>
                    <td><?= e($ringkas['jml_transaksi']) ?></td>
                </tr>
                <tr>
                    <td>Barang terjual</td>
                    <td><?= e($totalPcs) ?> pcs</td>
                </tr>
                <tr>
                    <td>Penjualan kotor</td>
                    <td><?= rupiah($kotor) ?></td>
                </tr>
                <tr>
                    <td>Diskon member</td>
                    <td>- <?= rupiah($diskon) ?></td>
                </tr>
                <tr class="tebal">
                    <td>Pendapatan bersih</td>
                    <td><?= rupiah($bersih) ?></td>
                </tr>
            </table>
        </div>

        <div>
            <div class="judul-kecil">Per metode pembayaran</div>
            <table class="tabel-kecil">
                <?php foreach ($perMetode as $m): ?>
                    <tr>
                        <td><?= e(strtoupper($m['metode'])) ?> (<?= e($m['jml']) ?> transaksi)</td>
                        <td><?= rupiah($m['total']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$perMetode): ?>
                    <tr>
                        <td colspan="2" class="tengah">-</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

    </div>


    <!-- DETAIL PENJUALAN -->

    <div class="judul-kecil">Detail Penjualan</div>

    <table class="tabel-data">

        <thead>
            <tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Pelanggan</th>
                <th>Produk</th>
                <th>Kategori</th>
                <th class="tengah">Jml</th>
                <th class="kanan">Subtotal</th>
                <th class="tengah">Diskon</th>
            </tr>
        </thead>

        <tbody>

            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>#<?= e($row['id_transaksi']) ?></td>
                    <td><?= e(tglPendek($row['tanggal'])) ?></td>
                    <td><?= e($row['nama_pelanggan'] ?? '-') ?></td>
                    <td><?= e($row['nama_produk']) ?></td>
                    <td><?= e($row['nama_kategori']) ?></td>
                    <td class="tengah"><?= e($row['jumlah']) ?></td>
                    <td class="kanan"><?= rupiah($row['subtotal']) ?></td>
                    <td class="tengah">
                        <?= (float) $row['diskon_persen'] > 0 ? (float) $row['diskon_persen'] . '%' : '-' ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$rows): ?>
                <tr>
                    <td colspan="8" class="kosong">
                        Tidak ada data penjualan pada periode ini.
                    </td>
                </tr>
            <?php endif; ?>

        </tbody>

    </table>

    <div class="catatan">
        Penjualan kotor = total sebelum diskon. Pendapatan bersih = total yang dibayar pelanggan setelah diskon.
    </div>

</div>

</body>
</html>