<?php

// ---------- Filter periode (opsional) ----------
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
$adalahPetugas = user_role() === 'petugas';

if ($adalahPetugas) {
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

// Hanya transaksi yang sudah lunas
$where[] = "id_transaksi IN (SELECT id_transaksi FROM tbl_transaksi WHERE status_bayar = 'lunas')";

if ($idPetugas > 0) {
    $where[] = 'id_transaksi IN (SELECT id_transaksi FROM tbl_transaksi WHERE id_user = ?)';
    $params[] = $idPetugas;
}

$kondisi = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Daftar petugas untuk pilihan filter (hanya admin)
$daftarPetugas = $adalahPetugas ? [] : $pdo->query("
    SELECT id_user, nama_lengkap
    FROM tbl_user
    WHERE role = 'petugas'
    ORDER BY nama_lengkap
")->fetchAll();

$stmt = $pdo->prepare("
    SELECT *
    FROM view_laporan_penjualan
    $kondisi
    ORDER BY id_transaksi DESC
");
$stmt->execute($params);
$data = $stmt->fetchAll();

$adaFilter = $dari !== '' || $sampai !== '' || (!$adalahPetugas && $idPetugas > 0);

$queryCetak = http_build_query(array_filter([
    'dari' => $dari,
    'sampai' => $sampai,
    'petugas' => $adalahPetugas ? 0 : $idPetugas
]));
$linkCetak = 'laporan_cetak.php' . ($queryCetak ? '?' . $queryCetak : '');

?>

<div class="content-card">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0"><?= $adalahPetugas ? 'Laporan Penjualan Saya' : 'Laporan Detail Penjualan' ?></h5>
        <?php if ($adalahPetugas): ?>
            <div class="small text-secondary">Hanya transaksi yang kamu proses sendiri.</div>
        <?php endif; ?>
    </div>

    <!-- Filter periode dan tombol cetak -->
    <form method="GET" action="index.php" class="d-flex flex-wrap align-items-end gap-2 p-3 border-bottom">
        <input type="hidden" name="page" value="laporan">

        <div>
            <label class="form-label small mb-1" for="filter-dari">Dari tanggal</label>
            <input type="date" id="filter-dari" name="dari" class="form-control form-control-sm"
                   value="<?= e($dari) ?>">
        </div>

        <div>
            <label class="form-label small mb-1" for="filter-sampai">Sampai tanggal</label>
            <input type="date" id="filter-sampai" name="sampai" class="form-control form-control-sm"
                   value="<?= e($sampai) ?>">
        </div>

        <?php if (!$adalahPetugas): ?>
            <div>
                <label class="form-label small mb-1" for="filter-petugas">Petugas</label>
                <select id="filter-petugas" name="petugas" class="form-select form-select-sm">
                    <option value="0">Semua petugas</option>
                    <?php foreach ($daftarPetugas as $p): ?>
                        <option value="<?= e($p['id_user']) ?>" <?= $idPetugas === (int) $p['id_user'] ? 'selected' : '' ?>>
                            <?= e($p['nama_lengkap']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-funnel me-1"></i>Tampilkan
        </button>

        <?php if ($adaFilter): ?>
            <a href="index.php?page=laporan" class="btn btn-sm btn-link text-secondary">Reset</a>
        <?php endif; ?>

        <a href="<?= e($linkCetak) ?>" target="_blank" class="btn btn-sm btn-dark ms-auto">
            <i class="bi bi-printer me-1"></i>Cetak / Simpan PDF
        </a>
    </form>

    <div class="table-responsive">
        <table class="table table-hover mb-0">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                    <th>Diskon</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td>#<?= e($row['id_transaksi']) ?></td>
                        <td><?= e($row['tanggal']) ?></td>
                        <td><?= e($row['nama_pelanggan']) ?></td>
                        <td><?= e($row['nama_produk']) ?></td>
                        <td><?= e($row['nama_kategori']) ?></td>
                        <td><?= e($row['jumlah']) ?></td>
                        <td class="fw-semibold">
                            <?= rupiah($row['subtotal']) ?>
                        </td>
                        <td>
                            <?= (float) $row['diskon_persen'] > 0 ? (float) $row['diskon_persen'] . '%' : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$data): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary">
                            <?= $adaFilter ? 'Tidak ada data untuk filter ini.' : 'Data laporan belum tersedia.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>